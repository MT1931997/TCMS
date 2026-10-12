<?php

namespace App\Enums;

enum NoticeRequestStatus: string
{
    case PendingIssuance = 'pending_issuance';
    case Issued = 'issued';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Rejected = 'rejected';

    /**
     * Get the Arabic label for the notice request status.
     */
    public function label(): string
    {
        return match ($this) {
            self::PendingIssuance => 'بانتظار الإصدار',
            self::Issued => 'صادر - بانتظار التسليم',
            self::PendingApproval => 'بانتظار اعتماد التسليم',
            self::Approved => 'معتمد',
            self::Rejected => 'مرفوض',
        };
    }

    /**
     * Get the default descriptive details text for this status.
     */
    public function defaultDetails(): string
    {
        return match ($this) {
            self::PendingIssuance => 'بانتظار قيام المشرف بإصدار نص الإشعار',
            self::Issued => 'تم إصدار نص الإشعار، يرجى تسليمه وإرفاق صورة إثبات التسليم',
            self::PendingApproval => 'تم إرفاق إثبات التسليم، بانتظار اعتماد المشرف',
            self::Approved => 'تم تسليم الإشعار واعتماده بنجاح من قِبل المشرف',
            self::Rejected => 'تم رفض طلب الإشعار من قِبل المشرف',
        };
    }

    /**
     * Get all raw values.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get all statuses as array of value and label.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $status): array => [
                'value' => $status->value,
                'label' => $status->label(),
            ],
            self::cases()
        );
    }
}
