<?php

declare(strict_types=1);

namespace Keboola\TransformationPatternScd;

use Twig;
use Keboola\TransformationPatternScd\Exception\ApplicationException;
use Keboola\TransformationPatternScd\Patterns\Pattern;
use Keboola\TransformationPatternScd\Mapping\MappingManager;
use Keboola\TransformationPatternScd\Patterns\PatternFactory;
use Psr\Log\LoggerInterface;

class Application
{
    public const SNOWFLAKE_TRANS_COMPONENT = 'keboola.snowflake-transformation';
    public const SYNAPSE_TRANS_COMPONENT = 'keboola.synapse-transformation';

    private string $dataDir;

    private Config $config;

    private LoggerInterface $logger;

    private PatternFactory $patternFactory;

    private Pattern $pattern;

    private MappingManager $mappingManager;

    private StorageGenerator $storageGenerator;

    private ParametersGenerator $parametersGenerator;

    private ApiFacade $apiFacade;

    public function __construct(string $dataDir, Config $config, LoggerInterface $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
        $this->dataDir = $dataDir;
        $this->patternFactory = new PatternFactory($this->config);
        $this->pattern = $this->patternFactory->create();
        $this->mappingManager = new MappingManager($this->config, $this->pattern);
        $this->storageGenerator = new StorageGenerator($this->mappingManager);
        $this->parametersGenerator = new ParametersGenerator();
        $this->apiFacade = new ApiFacade($this->config, $this->pattern, $this->mappingManager, $dataDir);
    }

    public function generateConfig(): array
    {
        $this->apiFacade->createSnapshotTable();

        return [
            'storage' => $this->storageGenerator->generate(),
            'parameters' => $this->parametersGenerator->generate($this->renderSqlCode()),
        ];
    }

    private function renderSqlCode(): string
    {
        $loader = new Twig\Loader\FilesystemLoader(__DIR__ . '/Patterns/templates');
        $twig = new Twig\Environment($loader, ['strict_variables' => true, 'autoescape' => false]);

        $extension = $this->pattern->getTwigExtension();
        if ($extension) {
            $twig->addExtension($extension);
        }

        try {
            $template = $twig->load($this->pattern->getTemplatePath());
            $sql = $template->render($this->pattern->getTemplateVariables());
        } catch (Twig\Error\Error $e) {
            throw new ApplicationException(
                $e->getMessage() . " File: {$e->getFile()}, line: {$e->getLine()}.",
                $e->getCode(),
                $e
            );
        }

        return $sql;
    }
}
