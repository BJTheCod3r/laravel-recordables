<?php

declare(strict_types=1);

namespace BJTheCod3r\Recordables\Contracts;

use Illuminate\Database\Eloquent\Relations\MorphMany;

interface Recordable
{
    /**
     * The attributes to capture in a recording snapshot.
     *
     * @return array<string, mixed>
     */
    public function toRecording(): array;

    /**
     * The recordings associated with this model.
     */
    public function recordings(): MorphMany;
}
