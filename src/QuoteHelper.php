<?php

declare(strict_types=1);

namespace Keboola\TransformationPatternScd;

use Keboola\Component\UserException;
use Keboola\TransformationPatternScd\Exception\ApplicationException;

class QuoteHelper
{
    public const TYPE_SNOWFLAKE = 'snowflake';
    public const TYPE_SYNAPSE = 'synapse';

    private string $type;

    public function __construct(Config $config)
    {
        switch ($config->getComponentId()) {
            case Application::SNOWFLAKE_TRANS_COMPONENT:
                $this->type = self::TYPE_SNOWFLAKE;
                break;
            case Application::SYNAPSE_TRANS_COMPONENT:
                $this->type = self::TYPE_SYNAPSE;
                break;
            default:
                throw new UserException(sprintf(
                    'The SCD code pattern is not compatible with component "%s".',
                    $config->getComponentId()
                ));
        }
    }

    public function quoteIdentifier(string $str): string
    {
        switch ($this->type) {
            case self::TYPE_SNOWFLAKE:
                return sprintf('"%s"', $str);

            case self::TYPE_SYNAPSE:
                return sprintf('"%s"', $str);

            default:
                throw new ApplicationException('Unexpected quoting type.');
        }
    }

    public function quoteValue(string $str): string
    {
        switch ($this->type) {
            case self::TYPE_SNOWFLAKE:
                return sprintf("'%s'", $str);

            case self::TYPE_SYNAPSE:
                return sprintf("'%s'", $str);

            default:
                throw new ApplicationException('Unexpected quoting type.');
        }
    }
}
