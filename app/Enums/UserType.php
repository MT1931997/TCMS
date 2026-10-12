<?php

namespace App\Enums;

enum UserType: string
{
    case GeneralManager = 'general_manager';
    case Owner = 'owner';
    case Supervisor = 'supervisor';
    case Collector = 'collector';
    case MaintenanceSupervisor = 'maintenance_supervisor';
    case Tenant = 'tenant';

    /**
     * Get the Arabic label for the user type.
     */
    public function label(): string
    {
        return match ($this) {
            self::GeneralManager => 'مشرف عام',
            self::Owner => 'مالك',
            self::Supervisor => 'مشرف',
            self::Collector => 'محصل',
            self::MaintenanceSupervisor => 'مشرف صيانه',
            self::Tenant => 'مستأجر',
        };
    }

    /**
     * Get all types as array of value and label.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $type): array => [
                'value' => $type->value,
                'label' => $type->label(),
            ],
            self::cases()
        );
    }
}
