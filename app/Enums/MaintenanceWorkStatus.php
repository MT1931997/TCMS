<?php

namespace App\Enums;

enum MaintenanceWorkStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    /**
     * Get the Arabic label for the owner's decision status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'بانتظار قراركم',
            self::Approved => 'مقبولة',
            self::Rejected => 'مرفوضة',
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
