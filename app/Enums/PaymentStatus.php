<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Completed = 'completed';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    /**
     * Get the Arabic label for the payment status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'قيد المراجعة',
            self::Approved, self::Completed => 'معتمد',
            self::Rejected, self::Cancelled => 'مرفوض',
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
