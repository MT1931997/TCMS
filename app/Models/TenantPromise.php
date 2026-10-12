<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'user_id', 'promise_date', 'note', 'status'])]
class TenantPromise extends Model
{
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'promise_date' => 'date',
        ];
    }

    /**
     * The tenant info this promise belongs to.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(TenantInfo::class, 'tenant_id');
    }

    /**
     * The user who recorded this promise.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
