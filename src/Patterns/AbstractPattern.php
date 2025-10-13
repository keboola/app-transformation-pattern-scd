<?php

declare(strict_types=1);

namespace Keboola\TransformationPatternScd\Patterns;

use Twig;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Keboola\TransformationPatternScd\Parameters\Parameters;
use Keboola\TransformationPatternScd\Exception\ApplicationException;
use Keboola\TransformationPatternScd\QuoteHelper;

abstract class AbstractPattern extends AbstractExtension implements Pattern
{
    protected string $templateName;
    private ?QuoteHelper $quoteHelper = null;
    private ?Parameters $parameters = null;

    abstract protected function getSnapshotSpecialColumns(): array;

    protected function getTemplatePath(): string
    {
        $backend = $this->getParameters()->getBackend();
        switch ($backend) {
            case Parameters::BACKEND_SNOWFLAKE:
                return $this->templateName;
            default:
                throw new ApplicationException(sprintf('Unexpected backend "%s".', $backend));
        }
    }

    abstract protected function getTemplateVariables(): array;

    public function setParameters(Parameters $parameters): void
    {
        // Set parameters before render
        $this->parameters = $parameters;
        $this->quoteHelper = new QuoteHelper($parameters->getBackend());
    }

    public function render(): string
    {
        $loader = new Twig\Loader\FilesystemLoader(__DIR__ . '/templates');
        $twig = new Twig\Environment($loader, ['strict_variables' => true, 'autoescape' => false]);
        $twig->addExtension($this);

        try {
            $template = $twig->load($this->getTemplatePath());
            return $template->render($this->getTemplateVariables());
        } catch (Twig\Error\Error $e) {
            throw new ApplicationException(
                $e->getMessage() . " File: {$e->getFile()}, line: {$e->getLine()}.",
                $e->getCode(),
                $e
            );
        }
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('quoteIdentifier', fn(string $str) => $this->getQuoteHelper()->quoteIdentifier($str)),
            new TwigFilter('quoteValue', fn(string $str) => $this->getQuoteHelper()->quoteValue($str)),
            new TwigFilter('noIndent', fn(string $str) => PatternHelper::noIndent($str)),
        ];
    }

    public function getSnapshotTableHeader(): array
    {
        return array_merge(
            [$this->getSnapshotPrimaryKey()],
            $this->getSnapshotAllColumnsExceptPk()
        );
    }

    public function getSnapshotTypedColumns(): array
    {
        return PatternHelper::mergeColumnsWithDefinition(
            $this->getColumnsWithDefinition([$this->getSnapshotPrimaryKey()]),
            $this->getSnapshotAllColumnsExceptPk(),
        );
    }

    protected function getQuoteHelper(): QuoteHelper
    {
        if (!$this->quoteHelper) {
            throw new ApplicationException('Please, call "setParameters" before calling "getQuoteHelper".');
        }

        return $this->quoteHelper;
    }

    public function getParameters(): Parameters
    {
        if (!$this->parameters) {
            throw new ApplicationException('Please, call "setParameters" before calling "getParameters".');
        }

        return $this->parameters;
    }

    protected function getSnapshotAllColumnsExceptPk(): array
    {
        return PatternHelper::mergeColumnsWithDefinition(
            PatternHelper::transformColumnsCase(
                $this->getInputColumns(),
                $this->getParameters()->getUppercaseColumns()
            ),
            $this->getSnapshotSpecialColumns()
        );
    }

    protected function getInputColumns(): array
    {
        return $this->getColumnsWithDefinition(
            array_merge($this->getParameters()->getPrimaryKey(), $this->getParameters()->getMonitoredParameters()),
        );
    }


    protected function getColumnsWithDefinition(array $columns): array
    {
        $tableDefinition = $this->getParameters()->getInputTableDefinition();

        $result = [];
        foreach ($columns as $column) {
            $columnDefinition = array_filter($tableDefinition['columns'] ?? [], fn($col) => $col['name'] === $column);
            $result[] = [
                'name' => $column,
                'definition' => $columnDefinition ?
                    array_values($columnDefinition)[0]['definition'] :
                    ['type' => 'VARCHAR'],
            ];
        }

        return $result;
    }


    /**
     * Returns the column definition for the deleted flag.
     */
    protected function getDeletedFlagColumnDefinition(): array
    {
        $length = max(
            mb_strlen(str_replace("'", '', (string) $this->getParameters()->getDeletedFlagValue()[0])),
            mb_strlen(str_replace("'", '', (string) $this->getParameters()->getDeletedFlagValue()[1])),
        );
        $isNumeric = in_array('0', $this->getParameters()->getDeletedFlagValue(), true)
            && in_array('1', $this->getParameters()->getDeletedFlagValue(), true);

        return [
            'type' => $isNumeric ? 'NUMERIC' : 'VARCHAR',
            'length' => $isNumeric ? 1 : $length,
        ];
    }
}
