<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'user_id', 'content'])]
class TenantNote extends Model
{
    use HasFactory;

    /**
     * The tenant info this note belongs to.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(TenantInfo::class, 'tenant_id');
    }

    /**
     * The user (supervisor/collector) who wrote this note.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
