<?php

declare(strict_types=1);

namespace BJTheCod3r\Recordables\Exceptions;

use Throwable;

class RecordingFailedException extends RecordablesException
{
    public static function wrap(Throwable $previous, string $message = 'Failed to persist recording.'): self
    {
        return new self($message, 0, $previous);
    }
}
