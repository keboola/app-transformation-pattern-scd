<?php

declare(strict_types=1);

namespace Keboola\TransformationPatternScd\PlatformTests;

use FilesystemIterator;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\RequestException;
use Keboola\Csv\CsvFile;
use Keboola\StorageApi\Client;
use Keboola\StorageApi\ClientException;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Process\Process;

class PlatformTest extends TestCase
{
    private const SNAPSHOT_PRINT_SEPARATOR = '-------------------------';

    private Client $storageApiClient;
    private string $configsPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configsPath = __DIR__ . '/configs';
    }


    public function testScd2DtypesNew(): void
    {
        $result = $this->runPlatformTest('scd2-dtypes-new', 'dtypes-new', '01k6awndxqbrgzgsmj9w8ss1yh');

        $actualColumnNames = array_map(fn($col) => $col['name'], $result['definition']['columns']);
        $this->assertEquals(
            ['end_date', 'holidays', 'id', 'is_actual', 'is_deleted', 'job', 'name', 'snapshot_pk', 'start_date'],
            $actualColumnNames
        );

        $actualTypes = array_map(fn($col) => $col['definition']['type'], $result['definition']['columns']);
        $this->assertEquals(
            [
                'TIMESTAMP_NTZ',
                'VARCHAR',
                'VARCHAR',
                'NUMBER',
                'NUMBER',
                'VARCHAR',
                'VARCHAR',
                'VARCHAR',
                'TIMESTAMP_NTZ',
            ],
            $actualTypes
        );
    }

    public function testScd4DtypesNew(): void
    {
        putenv('KBC_PROJECT_FEATURE_GATES=feature1;new-native-types');
        $result = $this->runPlatformTest('scd4-dtypes-new', 'dtypes-new', '01k6awndxqbrgzgsmj9w8ss1yh');

        $actualColumnNames = array_map(fn($col) => $col['name'], $result['definition']['columns']);
        $this->assertEquals(
            ['holidays', 'id', 'is_actual', 'is_deleted', 'job', 'name', 'snapshot_date', 'snapshot_pk'],
            $actualColumnNames
        );

        $actualTypes = array_map(fn($col) => $col['definition']['type'], $result['definition']['columns']);
        $this->assertEquals(
            [
                'VARCHAR',
                'VARCHAR',
                'VARCHAR',
                'VARCHAR',
                'VARCHAR',
                'VARCHAR',
                'TIMESTAMP_NTZ',
                'VARCHAR',
            ],
            $actualTypes
        );
    }

    public function testScd2DtypesOld(): void
    {
        $result = $this->runPlatformTest('scd2-dtypes-old', 'dtypes-old', '01k6cxttjwscvabn7xny4a2ebs');

        $actualColumnNames = array_map(fn($col) => $col['name'], $result['definition']['columns']);
        $this->assertEquals(
            ['end_date', 'holidays', 'id', 'is_actual', 'is_deleted', 'job', 'name', 'snapshot_pk', 'start_date'],
            $actualColumnNames
        );

        $actualTypes = array_map(fn($col) => $col['definition']['type'], $result['definition']['columns']);
        $this->assertEquals(
            [
                'TIMESTAMP_NTZ',
                'VARCHAR',
                'VARCHAR',
                'NUMBER',
                'NUMBER',
                'VARCHAR',
                'VARCHAR',
                'VARCHAR',
                'TIMESTAMP_NTZ',
            ],
            $actualTypes
        );
    }

    public function testScd4DtypesOld(): void
    {
        $result = $this->runPlatformTest('scd4-dtypes-old', 'dtypes-old', '01k6cxttjwscvabn7xny4a2ebs');

        $actualColumnNames = array_map(fn($col) => $col['name'], $result['definition']['columns']);
        $this->assertEquals(
            ['holidays', 'id', 'is_actual', 'is_deleted', 'job', 'name', 'snapshot_date', 'snapshot_pk'],
            $actualColumnNames
        );

        $actualTypes = array_map(fn($col) => $col['definition']['type'], $result['definition']['columns']);
        $this->assertEquals(
            ['VARCHAR', 'VARCHAR', 'NUMBER', 'NUMBER', 'VARCHAR', 'VARCHAR', 'TIMESTAMP_NTZ', 'VARCHAR'],
            $actualTypes
        );
    }

    public function testScd2NoDtypes(): void
    {
        $result = $this->runPlatformTest('scd2-no-dtypes', 'no-dtypes', '01k6cv0xqd1z6vtmw6wjx9hdsh');

        $this->assertEquals(
            ['snapshot_pk', 'id', 'name', 'job', 'holidays', 'start_date', 'end_date', 'is_actual', 'is_deleted'],
            $result['columns']
        );
    }

    public function testScd4NoDtypes(): void
    {
        $result = $this->runPlatformTest('scd4-no-dtypes', 'no-dtypes', '01k6cv0xqd1z6vtmw6wjx9hdsh');

        $this->assertEquals(
            ['snapshot_pk', 'id', 'name', 'job', 'holidays', 'snapshot_date', 'is_actual', 'is_deleted'],
            $result['columns']
        );
    }

    private function runPlatformTest(string $configType, string $projectType, ?string $snowflakeConfigId = null): array
    {
        $credentials = $this->getProjectCredentials($projectType);
        $this->storageApiClient = new Client([
            'url' => $credentials['url'],
            'token' => $credentials['token'],
        ]);
        $this->setEnvironmentCredentials($credentials);

        $config = $this->loadConfig($configType);
        // If no config id provided, create a temporary Snowflake transformation configuration
        $createdConfigId = null;
        if ($snowflakeConfigId === null || $snowflakeConfigId === '') {
            $createdConfigId = $this->createSnowflakeConfiguration("SCD platform: $configType ($projectType)");
            $snowflakeConfigId = $createdConfigId;
        }
        $inputTableId = $this->extractInputTableId($config);
        $this->setupInputBucket($inputTableId);

        $snowflakeConfig = $this->getSnowflakeConfiguration($snowflakeConfigId);
        $this->deleteSnapshotTablesFromConfig($snowflakeConfig);

        $generatedConfiguration = $this->runGeneratorComponent($configType, $config);
        $snapshotTableId = $this->extractSnapshotTableId($generatedConfiguration);

        $this->loadCsvToTable($inputTableId, 'historized1.csv');
        $this->updateSnowflakeConfiguration(
            $snowflakeConfigId,
            $generatedConfiguration,
            $snowflakeConfig['name'] ?? null
        );
        $this->runSnowflakeTransformation($snowflakeConfigId);

        $this->loadCsvToTable($inputTableId, 'historized2.csv');
        $this->runSnowflakeTransformation($snowflakeConfigId);

        $actualSnapshot = $this->fetchSnapshotTableData($snapshotTableId);
        $this->printSnapshotData($configType, $projectType, $actualSnapshot);

        $tableDetail = $this->storageApiClient->getTable($snapshotTableId);

        return $tableDetail;
    }

    /**
     * @return array{url: string, token: string}
     */
    private function getProjectCredentials(string $projectType): array
    {
        $suffix = strtoupper(str_replace('-', '_', $projectType));

        $url = getenv('KBC_URL');
        $token = getenv('KBC_TOKEN_' . $suffix);

        if (!$token) {
            $this->fail(sprintf(
                'Missing KBC credentials. Set KBC_URL and KBC_TOKEN_%1$s',
                $suffix
            ));
        }

        return [
            'url' => (string) $url,
            'token' => (string) $token,
        ];
    }

    private function setEnvironmentCredentials(array $credentials): void
    {
        putenv('KBC_URL=' . $credentials['url']);
        putenv('KBC_TOKEN=' . $credentials['token']);
    }

    private function loadConfig(string $configType): array
    {
        $configPath = sprintf('%s/%s/config.json', $this->configsPath, $configType);
        if (!file_exists($configPath)) {
            $this->fail(sprintf('Config file not found: %s', $configPath));
        }

        $content = file_get_contents($configPath);
        if ($content === false) {
            $this->fail(sprintf('Unable to read config file: %s', $configPath));
        }

        $decoded = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->fail(sprintf('Invalid JSON in config file %s: %s', $configPath, json_last_error_msg()));
        }

        return $decoded;
    }

    private function createSnowflakeConfiguration(string $name): string
    {
        try {
            $response = $this->storageApiClient->apiPostJson(
                sprintf('components/%s/configs', 'keboola.snowflake-transformation'),
                [
                    'name' => $name,
                    'configuration' => [
                        'parameters' => [],
                        'storage' => [
                            'input' => ['tables' => []],
                            'output' => ['tables' => []],
                        ],
                    ],
                ]
            );
        } catch (ClientException $e) {
            $this->fail(sprintf('Failed to create snowflake configuration: %s', $e->getMessage()));
        }

        if (!isset($response['id'])) {
            $this->fail('Create config response missing id');
        }

        return (string) $response['id'];
    }


    private function extractInputTableId(array $config): string
    {
        $tables = $config['storage']['input']['tables'] ?? [];
        if (!$tables || !isset($tables[0]['source'])) {
            $this->fail('Input table source missing in configuration.');
        }

        return (string) $tables[0]['source'];
    }

    private function setupInputBucket(string $tableId): void
    {
        [$bucketId] = $this->splitTableId($tableId);

        try {
            if (!$this->storageApiClient->bucketExists($bucketId)) {
                [$stage, $bucketName] = explode('.', $bucketId, 2);
                $bucketName = (string) preg_replace('~^c-~', '', $bucketName);
                $this->storageApiClient->createBucket($bucketName, $stage);
            }
        } catch (ClientException $e) {
            $this->fail(sprintf('Unable to ensure bucket %s exists: %s', $bucketId, $e->getMessage()));
        }
    }

    private function getSnowflakeConfiguration(string $configId): array
    {
        try {
            return $this->storageApiClient->apiGet(sprintf(
                'components/%s/configs/%s',
                'keboola.snowflake-transformation',
                $configId
            ));
        } catch (ClientException $e) {
            $this->fail(sprintf(
                'Failed to load snowflake transformation config "%s": %s',
                $configId,
                $e->getMessage()
            ));
        }
    }

    private function deleteSnapshotTablesFromConfig(array $config): void
    {
        $tables = $config['configuration']['storage']['output']['tables'] ?? [];
        foreach ($tables as $table) {
            if (!is_array($table)) {
                continue;
            }

            $tableId = $table['destination'] ?? $table['source'] ?? null;
            if (!$tableId) {
                continue;
            }

            try {
                $this->storageApiClient->dropTable($tableId);
            } catch (ClientException $e) {
                if ($e->getCode() !== 404) {
                    $this->fail(sprintf('Unable to drop snapshot table "%s": %s', $tableId, $e->getMessage()));
                }
            }
        }
    }

    private function runGeneratorComponent(string $configType, array $config): array
    {
        $dataDir = $this->createTempDatadir($configType, $config);
        $originalDatadir = getenv('KBC_DATADIR');
        putenv('KBC_DATADIR=' . $dataDir);

        try {
            $processEnv = $this->buildProcessEnv($dataDir);
            $process = Process::fromShellCommandline('php src/run.php', $this->getProjectRoot(), $processEnv);
            $process->setTimeout(300);
            $process->run();

            if (!$process->isSuccessful()) {
                $this->fail(sprintf(
                    'SCD generator failed for %s: %s%s',
                    $configType,
                    $process->getErrorOutput(),
                    $process->getOutput()
                ));
            }

            $output = trim($process->getOutput());
            if ($output === '') {
                $this->fail(sprintf('Generator returned empty output for %s', $configType));
            }

            $decoded = json_decode($output, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->fail(sprintf('Invalid generator output for %s: %s', $configType, json_last_error_msg()));
            }

            $result = $decoded['result'] ?? null;
            if (!is_array($result)) {
                $this->fail(sprintf('Generator output missing "result" key for %s', $configType));
            }

            return $result;
        } finally {
            if ($originalDatadir === false) {
                putenv('KBC_DATADIR');
            } else {
                putenv('KBC_DATADIR=' . $originalDatadir);
            }
            $this->cleanupDirectory($dataDir);
        }
    }

    /**
     * @return array<string, string>
     */
    private function buildProcessEnv(string $dataDir): array
    {
        return array_merge($_ENV, [
            'KBC_DATADIR' => $dataDir,
            'KBC_URL' => (string) getenv('KBC_URL'),
            'KBC_TOKEN' => (string) getenv('KBC_TOKEN'),
        ]);
    }

    private function extractSnapshotTableId(array $configuration): string
    {
        $tables = $configuration['storage']['output']['tables'] ?? [];
        if (!$tables) {
            $this->fail('Generated configuration does not contain output mapping.');
        }

        $table = reset($tables);
        if (!is_array($table) || empty($table['destination'])) {
            $this->fail('Generated configuration output mapping is missing "destination".');
        }

        return (string) $table['destination'];
    }

    private function loadCsvToTable(string $tableId, string $csvFileName): void
    {
        $csvPath = __DIR__ . '/data/' . $csvFileName;
        if (!file_exists($csvPath)) {
            $this->fail(sprintf('CSV file not found: %s', $csvPath));
        }

        [$bucketId, $tableName] = $this->splitTableId($tableId);
        $csvFile = new CsvFile($csvPath);

        try {
            if ($this->storageApiClient->tableExists($tableId)) {
                $this->storageApiClient->writeTableAsync($tableId, $csvFile, ['truncate' => true]);
            } else {
                $this->storageApiClient->createTableAsync($bucketId, $tableName, $csvFile);
            }
        } catch (ClientException $e) {
            $this->fail(sprintf(
                'Failed to load CSV "%s" into table "%s": %s',
                $csvFileName,
                $tableId,
                $e->getMessage()
            ));
        }
    }

    private function updateSnowflakeConfiguration(string $configId, array $configuration, ?string $configName): void
    {
        $payload = [
            'configuration' => $configuration,
            'changeDescription' => 'Updated by platform test',
        ];

        if ($configName) {
            $payload['name'] = $configName;
        }

        try {
            $this->storageApiClient->apiPutJson(sprintf(
                'components/%s/configs/%s',
                'keboola.snowflake-transformation',
                $configId
            ), $payload);
        } catch (ClientException $e) {
            $this->fail(sprintf('Failed to update snowflake config "%s": %s', $configId, $e->getMessage()));
        }
    }

    private function runSnowflakeTransformation(string $configId): void
    {
        // Start the job directly on the Queue API as requested
        $payload = [
            'component' => 'keboola.snowflake-transformation',
            'mode' => 'run',
            'config' => $configId,
        ];

        $response = $this->postQueueJob($payload);
        $runId = isset($response['runId']) ? (string) $response['runId'] : '';
        if ($runId === '') {
            $this->fail('Queue API response missing runId.');
        }

        $job = $this->waitForJob($runId);
        $status = $job['status'] ?? 'unknown';
        if ($status !== 'success') {
            $this->fail(sprintf(
                'Snowflake job %d for config "%s" finished with status "%s"',
                $job['runId'] ?? 0,
                $configId,
                $status
            ));
        }
    }

    /**
     * Post a job to the Queue API.
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     * @throws \Exception when the API request fails
     */
    private function postQueueJob(array $data): array
    {
        $client = $this->createQueueApiClient();

        try {
            $response = $client->post('jobs', [
                'headers' => $this->queueRequestHeaders(),
                'json' => $data,
            ]);

            $decoded = json_decode((string) $response->getBody(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->fail('Invalid JSON: ' . json_last_error_msg());
            }

            if ($response->getStatusCode() >= 300) {
                $message = $decoded['error'] ?? (string) $response->getBody();
                $this->fail("Queue API error ({$response->getStatusCode()}): $message");
            }

            return is_array($decoded) ? $decoded : [];
        } catch (RequestException $e) {
            $message = $e->getMessage();
            if ($e->hasResponse() && $e->getResponse() !== null) {
                $message .= ' Response: ' . $e->getResponse()->getBody();
            }
            $this->fail('Queue API request failed: ' . $message);
        }
    }

    /**
     * Get job status from Queue API using /jobs/{jobId} endpoint.
     * @return array<string, mixed>
     */
    private function getQueueJob(string $jobId): array
    {
        $client = $this->createQueueApiClient();

        try {
            $response = $client->get("jobs/{$jobId}", [
                'headers' => $this->queueRequestHeaders(),
            ]);

            $decoded = json_decode((string) $response->getBody(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException('Invalid JSON response: ' . json_last_error_msg());
            }

            if ($response->getStatusCode() >= 300) {
                $message = $decoded['error'] ?? (string) $response->getBody();
                throw new \RuntimeException("Queue API error ({$response->getStatusCode()}): $message");
            }

            return is_array($decoded) ? $decoded : [];
        } catch (RequestException $e) {
            $message = $e->getMessage();
            if ($e->hasResponse() && $e->getResponse() !== null) {
                $message .= ' Response: ' . $e->getResponse()->getBody();
            }
            throw new \RuntimeException('Queue API request failed: ' . $message);
        }
    }

    private function createQueueApiClient(): GuzzleClient
    {
        return new GuzzleClient([
            'base_uri' => 'https://queue.keboola.com/',
            'timeout' => 120,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function queueRequestHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
            'X-StorageApi-Token' => (string) getenv('KBC_TOKEN'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function waitForJob(string $jobId, int $timeout = 600): array
    {
        $start = time();

        do {
            try {
                $job = $this->getQueueJob($jobId);
            } catch (\RuntimeException $e) {
                $this->fail(sprintf('Failed to fetch job %s status: %s', $jobId, $e->getMessage()));
            }

            $status = $job['status'] ?? '';
            if (in_array($status, ['success', 'error', 'cancelled'], true)) {
                return $job;
            }

            sleep(5);
        } while ((time() - $start) < $timeout);

        $this->fail(sprintf('Job %s did not finish within %d seconds', $jobId, $timeout));
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function fetchSnapshotTableData(string $tableId): array
    {
        try {
            $preview = $this->storageApiClient->getTableDataPreview(
                $tableId,
                ['limit' => 100]
            );
        } catch (ClientException $e) {
            $this->fail(sprintf('Unable to preview snapshot table "%s": %s', $tableId, $e->getMessage()));
        }

        return $this->parseCsvString($preview);
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function parseCsvString(string $csvContent): array
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'snapshot-');
        if ($tempPath === false) {
            $this->fail('Unable to create temporary file for snapshot parsing');
        }

        file_put_contents($tempPath, $csvContent);
        $csv = new CsvFile($tempPath);

        $rows = [];
        $header = null;
        foreach ($csv as $row) {
            if ($header === null) {
                $header = $row;
                continue;
            }

            $rows[] = $this->combineRow($header, $row);
        }

        unlink($tempPath);
        return $rows;
    }

    private function printSnapshotData(string $configType, string $projectType, array $rows): void
    {

        $columns = array_keys($rows[0]);
        $lines = [
            self::SNAPSHOT_PRINT_SEPARATOR,
            sprintf('Snapshot for %s (%s)', $configType, $projectType),
            implode(',', $columns),
        ];

        foreach ($rows as $row) {
            $lines[] = implode(',', array_map(static function (string $column) use ($row): string {
                return $row[$column] ?? '';
            }, $columns));
        }

        $lines[] = self::SNAPSHOT_PRINT_SEPARATOR;
        fwrite(STDOUT, implode(PHP_EOL, $lines) . PHP_EOL);
    }

    /**
     * @param array<int, string> $header
     * @param array<int, string> $row
     * @return array<string, string>
     */
    private function combineRow(array $header, array $row): array
    {
        $combined = [];
        foreach ($header as $index => $column) {
            $combined[(string) $column] = isset($row[$index]) ? (string) $row[$index] : '';
        }

        return $combined;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitTableId(string $tableId): array
    {
        $position = strrpos($tableId, '.');
        if ($position === false) {
            $this->fail(sprintf('Invalid table id format: %s', $tableId));
        }

        return [
            substr($tableId, 0, $position),
            substr($tableId, $position + 1),
        ];
    }

    private function createTempDatadir(string $configType, array $config): string
    {
        $dataDir = sys_get_temp_dir() . '/scd-platform-' . $configType . '-' . uniqid('', true);
        if (!mkdir($dataDir, 0777, true) && !is_dir($dataDir)) {
            $this->fail(sprintf('Failed to create temporary data directory: %s', $dataDir));
        }

        $structure = ['in', 'in/tables', 'in/files', 'out', 'out/tables', 'out/files'];
        foreach ($structure as $subDir) {
            $path = $dataDir . '/' . $subDir;
            if (!mkdir($path, 0777, true) && !is_dir($path)) {
                $this->fail(sprintf('Failed to create temporary directory: %s', $path));
            }
        }

        $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            $this->fail('Unable to encode configuration for generator.');
        }

        if (file_put_contents($dataDir . '/config.json', $json) === false) {
            $this->fail('Unable to write temporary configuration file.');
        }

        return $dataDir;
    }

    private function cleanupDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isDir()) {
                rmdir($fileInfo->getPathname());
            } else {
                unlink($fileInfo->getPathname());
            }
        }

        rmdir($path);
    }

    private function getProjectRoot(): string
    {
        return dirname(__DIR__, 2);
    }
}
