<?php

namespace App\Enums;

enum UserType: string
{
    case GeneralManager = 'general_manager';
    case RealEstateSupervisor = 'real_estate_supervisor';
    case CollectionSupervisor = 'collection_supervisor';
    case MaintenanceSupervisor = 'maintenance_supervisor';
    case Owner = 'owner';
    case Collector = 'collector';
    case Tenant = 'tenant';

    /**
     * Get the Arabic label for the user type.
     */
    public function label(): string
    {
        return match ($this) {
            self::GeneralManager => 'مشرف عام',
            self::RealEstateSupervisor => 'مشرف عقارات',
            self::CollectionSupervisor => 'مشرف تحصيل ومتابعة',
            self::MaintenanceSupervisor => 'مشرف صيانه',
            self::Owner => 'مالك',
            self::Collector => 'محصل',
            self::Tenant => 'مستأجر',
        };
    }

    /**
     * Get the description/permissions summary for the user type.
     */
    public function description(): string
    {
        return match ($this) {
            self::GeneralManager => 'جميع الصلاحيات',
            self::RealEstateSupervisor => 'العقود وتجديدها وتحديث بيانات المستأجرين، إضافة لكل صلاحيات مشرف التحصيل والمتابعة',
            self::CollectionSupervisor => 'استيراد المستأجرين وتعديلهم وحذفهم، اعتماد السدادات، متابعة التحصيل، وإدارة الشقق الشاغرة',
            self::MaintenanceSupervisor => 'أدوات الصيانة فقط والتحكم بها وإرسالها للمالك',
            self::Owner => 'متابعة الوحدات والعقارات الخاصة والاطلاع على السدادات والملاحظات',
            self::Collector => 'تحصيل المستحقات وإرفاق السدادات ومتابعة المستأجرين وطلبات الإشعارات',
            self::Tenant => 'بوابة المستأجر وعرض المستحقات وطلب الصيانة',
        };
    }

    /**
     * Return all supervisor types.
     *
     * @return array<int, self>
     */
    public static function supervisorTypes(): array
    {
        return [
            self::GeneralManager,
            self::RealEstateSupervisor,
            self::CollectionSupervisor,
            self::MaintenanceSupervisor,
        ];
    }

    /**
     * Determine if this user type is a supervisor type.
     */
    public function isSupervisorType(): bool
    {
        return in_array($this, self::supervisorTypes(), true);
    }

    /**
     * Get supervisor types options with labels and descriptions.
     *
     * @return array<int, array{value: string, label: string, description: string}>
     */
    public static function supervisorOptions(): array
    {
        return array_map(
            fn (self $type): array => [
                'value' => $type->value,
                'label' => $type->label(),
                'description' => $type->description(),
            ],
            self::supervisorTypes()
        );
    }

    /**
     * Get all types as array of value, label, and description.
     *
     * @return array<int, array{value: string, label: string, description: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $type): array => [
                'value' => $type->value,
                'label' => $type->label(),
                'description' => $type->description(),
            ],
            self::cases()
        );
    }
}
