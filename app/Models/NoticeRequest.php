<?php

namespace App\Models;

use App\Enums\NoticeRequestStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'collector_id',
    'supervisor_id',
    'reason',
    'status',
    'notice_content',
    'notice_file_path',
    'issued_at',
    'delivery_proof_path',
    'delivery_date',
    'delivery_notes',
    'rejection_reason',
])]
class NoticeRequest extends Model
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
            'status' => NoticeRequestStatus::class,
            'issued_at' => 'datetime',
            'delivery_date' => 'date',
        ];
    }

    /**
     * The tenant linked to this notice request.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(TenantInfo::class, 'tenant_id');
    }

    /**
     * The collector who requested the notice.
     */
    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collector_id');
    }

    /**
     * The supervisor who handled or issued the notice.
     */
    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    /**
     * Get dynamic details text matching the workflow state.
     */
    public function getDetailsSummary(): string
    {
        return match ($this->status) {
            NoticeRequestStatus::PendingIssuance => 'بانتظار قيام المشرف بإصدار نص الإشعار',
            NoticeRequestStatus::Issued => $this->notice_content ? 'نص الإشعار: '.$this->notice_content : 'تم إصدار نص الإشعار، يرجى تسليمه وإرفاق صورة إثبات التسليم',
            NoticeRequestStatus::PendingApproval => $this->delivery_notes ?: 'تم إرفاق إثبات التسليم، بانتظار اعتماد المشرف',
            NoticeRequestStatus::Approved => 'تم تسليم الإشعار واعتماده بنجاح',
            NoticeRequestStatus::Rejected => $this->rejection_reason ? 'تم الرفض: '.$this->rejection_reason : 'تم رفض الطلب',
        };
    }
}
