<?php

declare(strict_types=1);

namespace BJTheCod3r\Recordables\Exceptions;

class MissingRecordableDefinitionException extends RecordablesException
{
    public string $recordableClass;

    public static function for(string $class): self
    {
        $exception = new self(sprintf(
            '%s must define a $recordable property or override toRecording().',
            $class,
        ));

        $exception->recordableClass = $class;

        return $exception;
    }
}
