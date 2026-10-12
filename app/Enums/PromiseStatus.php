<?php

namespace App\Enums;

enum PromiseStatus: string
{
    case Pending = 'pending';
    case Fulfilled = 'fulfilled';
    case Broken = 'broken';

    /**
     * Get the Arabic label for the promise status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'معلق / بانتظار السداد',
            self::Fulfilled => 'تم الوفاء بالوعد',
            self::Broken => 'مخلف / لم يسدد',
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
