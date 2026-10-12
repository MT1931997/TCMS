<?php

namespace App\Enums;

enum TenantStatus: string
{
    case Regular = 'regular';
    case Late = 'late';
    case Paid = 'paid';

    /**
     * Get the Arabic label for the tenant collection status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Regular => 'منتظم',
            self::Late => 'متأخر',
            self::Paid => 'مسدد',
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
