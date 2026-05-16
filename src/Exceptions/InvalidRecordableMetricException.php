<?php

declare(strict_types=1);

namespace BJTheCod3r\Recordables\Exceptions;

class InvalidRecordableMetricException extends RecordablesException
{
    public string $metric;

    public string $recordableClass;

    public static function for(string $metric, string $class): self
    {
        $exception = new self(sprintf(
            'Metric "%s" not found on any recording for %s.',
            $metric,
            $class,
        ));

        $exception->metric = $metric;
        $exception->recordableClass = $class;

        return $exception;
    }
}
