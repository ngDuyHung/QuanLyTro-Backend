<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MemberRelationship;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaseMember extends Model
{
    use HasFactory;

    protected $table = 'lease_members';

    protected $fillable = [
        'lease_id',
        'tenant_id',
        'relationship',
        'move_in_date',
        'move_out_date',
        'note',
    ];

    protected $casts = [
        'relationship'  => MemberRelationship::class,
        'move_in_date'  => 'date',
        'move_out_date' => 'date',
    ];

    // ===== Relationships =====

    /**
     * Bản ghi này thuộc về hợp đồng nào.
     */
    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    /**
     * Bản ghi này đang đại diện cho Cư dân nào.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
