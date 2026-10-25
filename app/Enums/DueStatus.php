<?php

namespace App\Enums;

enum DueStatus: string
{
    case Pending = 'pending';
    case Partial = 'partial';
    case Paid = 'paid';
    case Overdue = 'overdue';

    /**
     * Get Arabic label for the due status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'مستحق',
            self::Partial => 'مسدد جزئياً',
            self::Paid => 'مسدد بالكامل',
            self::Overdue => 'متأخر',
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
