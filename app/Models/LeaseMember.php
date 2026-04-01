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
        'full_name',
        'id_card_number',
        'birth_year',
        'relationship',
        'phone',
        'move_in_date',
        'move_out_date',
        'note',
    ];

    protected $casts = [
        'relationship'  => MemberRelationship::class,
        'birth_year'    => 'integer',
        'move_in_date'  => 'date',
        'move_out_date' => 'date',
    ];

    // ===== Relationships =====

    /**
     * Thành viên thuộc hợp đồng thuê nào.
     */
    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }
}
