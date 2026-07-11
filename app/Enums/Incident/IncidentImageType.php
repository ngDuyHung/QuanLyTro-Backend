<?php

declare(strict_types=1);

namespace App\Enums\Incident;

enum IncidentImageType: string
{
    case BeforeRepair = 'before_repair';
    case AfterRepair = 'after_repair';
}