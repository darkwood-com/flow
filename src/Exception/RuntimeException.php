<?php

declare(strict_types=1);

namespace Flow\Exception;

use RuntimeException as NativeRuntimeException;

class RuntimeException extends NativeRuntimeException implements ExceptionInterface
{
}
