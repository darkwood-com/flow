<?php

declare(strict_types=1);

namespace Flow\Exception;

use Flow\ExceptionInterface;
use LogicException as NativeLogicException;

class LogicException extends NativeLogicException implements ExceptionInterface
{
}
