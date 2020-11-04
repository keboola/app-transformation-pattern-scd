<?php

declare(strict_types=1);

namespace Keboola\TransformationPatternScd\Patterns;

use Keboola\Component\UserException;
use Keboola\TransformationPatternScd\Config;
use Keboola\TransformationPatternScd\Configuration\GenerateDefinition;
use Keboola\TransformationPatternScd\QuoteHelper;

class PatternFactory
{
    private Config $config;

    private QuoteHelper $quoteHelper;

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->quoteHelper = new QuoteHelper($config);
    }

    public function create(): Pattern
    {
        switch ($this->config->getScdType()) {
            case GenerateDefinition::SCD_TYPE_2:
                return new Scd2Pattern($this->config, $this->quoteHelper);
            case GenerateDefinition::SCD_TYPE_4:
                return new Scd4Pattern($this->config, $this->quoteHelper);
            default:
                throw new UserException(
                    sprintf('Unknown scd type "%s"', $this->config->getScdType())
                );
        }
    }
}
