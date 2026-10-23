<?php

namespace App\Enums;

enum PaymentCycle: string
{
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case SemiAnnual = 'semi_annual';
    case Annual = 'annual';

    /**
     * Get the Arabic label for the payment cycle.
     */
    public function label(): string
    {
        return match ($this) {
            self::Monthly => 'شهري',
            self::Quarterly => 'ربع سنوي',
            self::SemiAnnual => 'نصف سنوي',
            self::Annual => 'سنوي',
        };
    }

    /**
     * Number of periods / installments per year.
     */
    public function periodsPerYear(): int
    {
        return match ($this) {
            self::Monthly => 12,
            self::Quarterly => 4,
            self::SemiAnnual => 2,
            self::Annual => 1,
        };
    }

    /**
     * Calculate suggested installment amount from total annual/contract amount.
     */
    public function calculateInstallment(float $totalAmount): float
    {
        $periods = $this->periodsPerYear();

        return $periods > 0 ? round($totalAmount / $periods, 2) : $totalAmount;
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
