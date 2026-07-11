<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Incident\IncidentCategory;
use App\Enums\Incident\IncidentPayer;
use App\Enums\Incident\IncidentPriority;
use App\Enums\Incident\IncidentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Incident extends Model
{
    protected $fillable = [
        'property_id',
        'room_id',
        'reported_by_tenant_id',
        'title',
        'description',
        'category',
        'priority',
        'status',
        'repair_cost',
        'payer',
        'financial_transaction_id',
        'invoice_id',
        'resolved_at',
        'created_by',
    ];

    protected $casts = [
        'category' => IncidentCategory::class,
        'priority' => IncidentPriority::class,
        'status' => IncidentStatus::class,
        'payer' => IncidentPayer::class,
        'repair_cost' => 'integer',
        'resolved_at' => 'datetime',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function reportedByTenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'reported_by_tenant_id');
    }

    public function financialTransaction(): BelongsTo
    {
        return $this->belongsTo(FinancialTransaction::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function images(): HasMany
    {
        return $this->hasMany(IncidentImage::class);
    }
}
