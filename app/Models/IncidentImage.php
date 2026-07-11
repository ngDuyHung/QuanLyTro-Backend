<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Incident\IncidentImageType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncidentImage extends Model
{
    protected $fillable = [
        'incident_id',
        'image_path',
        'type',
    ];

    protected $casts = [
        'type' => IncidentImageType::class,
    ];

    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }
}
