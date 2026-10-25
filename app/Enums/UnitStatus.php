<?php

namespace App\Enums;

enum UnitStatus: string
{
    case Vacant = 'vacant';
    case Occupied = 'occupied';
    case Maintenance = 'maintenance';

    /**
     * Get Arabic label for the unit status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Vacant => 'شاغرة',
            self::Occupied => 'مؤجرة',
            self::Maintenance => 'تحت الصيانة',
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
