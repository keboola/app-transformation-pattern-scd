<?php

declare(strict_types=1);

namespace Keboola\TransformationPatternScd;

use Keboola\TransformationPatternScd\Exception\ApplicationException;
use Keboola\TransformationPatternScd\Parameters\Parameters;

class QuoteHelper
{
    public const TYPE_SNOWFLAKE = 'snowflake';

    private string $type;

    public function __construct(string $backend)
    {
        switch ($backend) {
            case Parameters::BACKEND_SNOWFLAKE:
                $this->type = self::TYPE_SNOWFLAKE;
                break;
            default:
                throw new ApplicationException(sprintf('Unexpected backend ""%s.', $backend));
        }
    }

    public function quoteIdentifier(string $str): string
    {
        switch ($this->type) {
            case self::TYPE_SNOWFLAKE:
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

            default:
                throw new ApplicationException('Unexpected quoting type.');
        }
    }
}
