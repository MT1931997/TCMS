<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'كاش بالمكتب';
    case Platform = 'سداد بالمنصة';
    case BankTransfer = 'تحويل بنكي';

    /**
     * Get the Arabic label for the payment method.
     */
    public function label(): string
    {
        return match ($this) {
            self::Cash => 'كاش بالمكتب',
            self::Platform => 'سداد بالمنصة',
            self::BankTransfer => 'تحويل بنكي',
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
     * Get all methods as array of value and label.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $method): array => [
                'value' => $method->value,
                'label' => $method->label(),
            ],
            self::cases()
        );
    }
}
