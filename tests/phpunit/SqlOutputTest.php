<?php

declare(strict_types=1);

namespace Keboola\TransformationPatternScd\Tests;

use PHPUnit\Framework\Assert;
use Twig;
use Generator;
use RuntimeException;
use Keboola\Component\JsonHelper;
use Keboola\TransformationPatternScd\BlocksGenerator;
use Keboola\TransformationPatternScd\Parameters\Parameters;
use Keboola\TransformationPatternScd\Patterns\PatternFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;

class SqlOutputTest extends TestCase
{
    /**
     * @dataProvider getInputs
     */
    public function testSqlOutput(array $parameters, string $sqlFile): void
    {
        $parametersObject = $this->createParameters($parameters);
        $sql = $this->render($parameters['scd_type'], $parametersObject);
        Assert::assertSame(trim((string) file_get_contents($sqlFile)), trim($sql));

        //if (!file_exists($sqlFile)) {
            // File doesn't exists -> new test -> save results
            //file_put_contents($sqlFile, $sql . "\n");
        //}
    }

    protected function createParameters(array $parameters): Parameters
    {
        return new Parameters(
            $parameters['backend'],
            $parameters['primary_key'],
            $parameters['monitored_parameters'],
            $parameters['timezone'],
            $parameters['deleted_flag'],
            $parameters['use_datetime'],
            $parameters['keep_del_active'],
            $parameters['start_date_name'],
            $parameters['end_date_name'],
            $parameters['actual_name'],
            $parameters['is_deleted_name'],
            $parameters['deleted_flag_value'],
            $parameters['end_date_value'],
            $parameters['current_timestamp_minus_one'],
            $parameters['uppercase_columns'],
        );
    }

    protected function render(string $scdType, Parameters $parameters): string
    {
        $patternFactory = new PatternFactory($scdType);
        $pattern = $patternFactory->create();
        $pattern->setParameters($parameters);
        $blocksGenerator = new BlocksGenerator();

        try {
            $result = $blocksGenerator->generate($pattern->render());
        } catch (Twig\Error\Error $e) {
            // Add file and line to message
            throw new RuntimeException($e->getMessage() . "File: {$e->getFile()}, Line: {$e->getLine()}");
        }

        $sql = '';
        foreach ($result['blocks'] as $block) {
            foreach ($block['codes'] as $code) {
                $sql .= implode("\n\n", $code['script']);
            }
        }

        return $sql;
    }

    public function getInputs(): Generator
    {
        $finder = new Finder();
        $files = $finder->files()->in(__DIR__ . '/sql-parameters');
        foreach ($files as $parametersFile) {
            $sqlFile =
                __DIR__ . '/sql-outputs/' .
                preg_replace('~.json$~', '.sql', $parametersFile->getFilename());
            $parameters = JsonHelper::decode((string) file_get_contents($parametersFile->getPathname()));
            yield $parametersFile->getPathname() => [$parameters, $sqlFile];
        }
    }
}
