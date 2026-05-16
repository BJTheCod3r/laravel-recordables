<?php

declare(strict_types=1);

namespace BJTheCod3r\Recordables\Enums;

enum ChangeType: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Synced = 'synced';
    case Imported = 'imported';
    case Manual = 'manual';
}
