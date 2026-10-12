<?php

namespace App\Http\Resources;

use App\Models\TenantNote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TenantNote
 */
class TenantNoteResource extends JsonResource
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
            'content' => $this->content,
            'author_name' => $this->user?->name ?? 'المشرف',
            'author_role' => $this->user?->type?->label() ?? 'مشرف',
            'created_at' => $this->created_at?->translatedFormat('d F Y'),
            'created_at_time' => $this->created_at?->translatedFormat('h:i A'),
        ];
    }
}
