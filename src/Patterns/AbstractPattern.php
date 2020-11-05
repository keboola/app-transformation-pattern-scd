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
    // Available after render method calling
    protected QuoteHelper $quoteHelper;

    // Available after render method calling
    protected Parameters $parameters;

    abstract protected function getTemplatePath(): string;

    abstract protected function getTemplateVariables(): array;

    public function render(Parameters $parameters): string
    {
        $this->parameters = $parameters;
        $this->quoteHelper = new QuoteHelper($parameters->getBackend());

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
            new TwigFilter('quoteIdentifier', fn(string $str) => $this->quoteHelper->quoteIdentifier($str)),
            new TwigFilter('quoteValue', fn(string $str) => $this->quoteHelper->quoteValue($str)),
            new TwigFilter('noIndent', fn(string $str) => $this->noIndent($str)),
        ];
    }


    protected function columnsToLower(array $columns): array
    {
        return array_map(fn(string $column) => mb_strtolower($column), $columns);
    }

    protected function noIndent(string $str): string
    {
        return implode(
            "\n",
            array_map(fn(string $line) => trim($line), explode("\n", $str))
        );
    }
}
