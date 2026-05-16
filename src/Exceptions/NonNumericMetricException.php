<?php

declare(strict_types=1);

namespace BJTheCod3r\Recordables\Exceptions;

class NonNumericMetricException extends RecordablesException
{
    public string $metric;

    public string $recordableClass;

    public string $actualType;

    public static function for(string $metric, string $class, mixed $value): self
    {
        $type = get_debug_type($value);

        $exception = new self(sprintf(
            'Metric "%s" on %s is %s; analytics helpers require int or float.',
            $metric,
            $class,
            $type,
        ));

        $exception->metric = $metric;
        $exception->recordableClass = $class;
        $exception->actualType = $type;

        return $exception;
    }
}
