<?php

namespace App\Enums;

enum ContractStatus: string
{
    case Active = 'active';
    case Renewed = 'renewed';
    case Closed = 'closed';

    /**
     * Get the Arabic label for the contract status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Active => 'ساري',
            self::Renewed => 'مُجدّد',
            self::Closed => 'مُغلق',
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
