<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['payment_id', 'user_id', 'content'])]
class PaymentNote extends Model
{
    use HasFactory;

    /**
     * The payment this note belongs to.
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * The user (owner/supervisor) who wrote this note.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
