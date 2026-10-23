<?php

namespace App\Enums;

enum ClaimGenerationMode: string
{
    case CurrentMonthOnly = 'current_month_only';
    case FromStartDate = 'from_start_date';
    case None = 'none';

    /**
     * Get the Arabic label for the claim generation mode.
     */
    public function label(): string
    {
        return match ($this) {
            self::CurrentMonthOnly => 'من الشهر الحالي فقط',
            self::FromStartDate => 'من تاريخ بداية العقد (تظهر المتأخرات)',
            self::None => 'بدون إنشاء مطالبات',
        };
    }

    /**
     * Return all cases as array with value and label.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case): array => [
                'value' => $case->value,
                'label' => $case->label(),
            ],
            self::cases()
        );
    }
}
