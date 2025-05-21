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
    private ?QuoteHelper $quoteHelper = null;

    private ?Parameters $parameters = null;

    abstract protected function getTemplatePath(): string;

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
            new TwigFilter('noIndent', fn(string $str) => $this->noIndent($str)),
        ];
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

    protected function columnsToLower(array $columns): array
    {
        return array_map(fn(string $column) => mb_strtolower($column), $columns);
    }

    protected function columnsToUpper(array $columns): array
    {
        return array_map(fn(string $column) => mb_strtoupper($column), $columns);
    }

    protected function noIndent(string $str): string
    {
        return implode(
            "\n",
            array_map(fn(string $line) => trim($line), explode("\n", $str))
        );
    }
}
