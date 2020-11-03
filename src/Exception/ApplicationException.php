<?php

declare(strict_types=1);

namespace Keboola\TransformationPatternScd\Exception;

use \Exception;
use \Keboola\CommonExceptions\ApplicationExceptionInterface;

class ApplicationException extends Exception implements ApplicationExceptionInterface
{

}
