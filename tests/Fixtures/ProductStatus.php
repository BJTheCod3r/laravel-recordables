<?php

declare(strict_types=1);

namespace BJTheCod3r\Recordables\Tests\Fixtures;

enum ProductStatus: string
{
    case Draft = 'draft';
    case Live = 'live';
}
