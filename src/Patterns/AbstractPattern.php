<?php

declare(strict_types=1);

namespace Keboola\TransformationPatternScd\Patterns;

use Keboola\TransformationPatternScd\Config;
use Keboola\TransformationPatternScd\QuoteHelper;
use Twig\Extension\AbstractExtension;
use Twig\Extension\ExtensionInterface;
use Twig\TwigFilter;

abstract class AbstractPattern extends AbstractExtension implements Pattern
{
    protected Config $config;

    protected QuoteHelper $quoteHelper;

    public function __construct(Config $config, QuoteHelper $quoteHelper)
    {
        $this->config = $config;
        $this->quoteHelper = $quoteHelper;
    }

    public function getTwigExtension(): ?ExtensionInterface
    {
        // Custom filters, ... can be specified, see AbstractExtension
        return $this;
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('quoteIdentifier', fn(string $str) => $this->quoteHelper->quoteIdentifier($str)),
            new TwigFilter('quoteValue', fn(string $str) => $this->quoteHelper->quoteValue($str)),
        ];
    }
}
