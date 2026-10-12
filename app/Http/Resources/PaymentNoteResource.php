<?php

namespace App\Http\Resources;

use App\Models\PaymentNote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PaymentNote
 */
class PaymentNoteResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payment_id' => $this->payment_id,
            'content' => $this->content,
            'author_name' => $this->user?->name ?? 'مستخدم',
            'author_role' => $this->user?->type?->label() ?? 'مستخدم',
            'created_at' => $this->created_at?->translatedFormat('d F Y'),
            'created_at_time' => $this->created_at?->translatedFormat('h:i A'),
            'created_at_human' => $this->created_at?->diffForHumans(),
        ];
    }
}
