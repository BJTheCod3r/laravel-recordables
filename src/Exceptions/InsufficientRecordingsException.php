<?php

declare(strict_types=1);

namespace BJTheCod3r\Recordables\Exceptions;

class InsufficientRecordingsException extends RecordablesException
{
    public string $metric;

    public int $required;

    public int $actual;

    public static function for(string $metric, int $required, int $actual): self
    {
        $exception = new self(sprintf(
            'Metric "%s" requires at least %d recording(s); %d found.',
            $metric,
            $required,
            $actual,
        ));

        $exception->metric = $metric;
        $exception->required = $required;
        $exception->actual = $actual;

        return $exception;
    }
}
