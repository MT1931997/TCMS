<?php

namespace Database\Seeders;

use App\Enums\MaintenanceRequestOwnerStatus;
use App\Enums\MaintenanceRequestSupervisorStatus;
use App\Enums\MaintenanceWorkStatus;
use App\Enums\UserType;
use App\Models\MaintenanceRequest;
use App\Models\MaintenanceWork;
use App\Models\Property;
use App\Models\TenantInfo;
use App\Models\TenantNote;
use App\Models\TenantPromise;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $defaultPassword = Hash::make('password123');

        // 1. حسابات رئيسية ثابتة ومحددة لتسهيل تسجيل الدخول
        $fixedUsers = [
            [
                'name' => 'المشرف العام',
                'username' => 'admin',
                'phone' => '0500000001',
                'email' => 'admin@tcms.sa',
                'type' => UserType::GeneralManager,
                'is_active' => true,
                'password' => $defaultPassword,
            ],
            [
                'name' => 'مشرف العقارات',
                'username' => 'realestate_supervisor',
                'phone' => '0500000003',
                'email' => 'realestate@tcms.sa',
                'type' => UserType::RealEstateSupervisor,
                'is_active' => true,
                'password' => $defaultPassword,
            ],
            [
                'name' => 'مشرف التحصيل والمتابعة',
                'username' => 'collection_supervisor',
                'phone' => '0500000006',
                'email' => 'collection_mgr@tcms.sa',
                'type' => UserType::CollectionSupervisor,
                'is_active' => true,
                'password' => $defaultPassword,
            ],
            [
                'name' => 'مشرف الصيانة',
                'username' => 'maint1',
                'phone' => '0500000005',
                'email' => 'maintenance@tcms.sa',
                'type' => UserType::MaintenanceSupervisor,
                'is_active' => true,
                'password' => $defaultPassword,
            ],
            [
                'name' => 'المالك الأول',
                'username' => 'owner',
                'phone' => '0500000002',
                'email' => 'owner@tcms.sa',
                'type' => UserType::Owner,
                'is_active' => true,
                'password' => $defaultPassword,
            ],
            [
                'name' => 'المحصل الأول',
                'username' => 'collector1',
                'phone' => '0500000004',
                'email' => 'collector@tcms.sa',
                'type' => UserType::Collector,
                'is_active' => true,
                'password' => $defaultPassword,
            ],
        ];

        foreach ($fixedUsers as $user) {
            User::updateOrCreate(
                ['username' => $user['username']],
                $user
            );
        }

        // توليد مستخدمين إضافيين
        User::factory()->count(2)->realEstateSupervisor()->create(['password' => $defaultPassword]);
        User::factory()->count(2)->collectionSupervisor()->create(['password' => $defaultPassword]);
        User::factory()->count(4)->collector()->create(['password' => $defaultPassword]);
        User::factory()->count(2)->owner()->create(['password' => $defaultPassword]);

        $generalManager = User::where('username', 'admin')->first();
        $collector = User::where('username', 'collector1')->first();
        $owner = User::where('username', 'owner')->first();
        $maintenanceSupervisor = User::where('username', 'maint1')->first();

        // 1.5 إنشاء العقارات (الأملاك) المطابقة لشاشة "إدارة الأملاك والعقود"
        $propTest = Property::updateOrCreate(
            ['name' => 'تيست'],
            [
                'name' => 'تيست',
                'owner_id' => $owner->id,
                'location' => 'الرياض - حي النرجس',
                'created_by' => $generalManager->id,
            ]
        );

        $propMmm = Property::updateOrCreate(
            ['name' => 'mmm'],
            [
                'name' => 'mmm',
                'owner_id' => $owner->id,
                'location' => 'جدة - حي الروضة',
                'created_by' => $generalManager->id,
            ]
        );

        // 2. إنشاء المستأجر المطابق لشاشة الفرونت إند تماماً (محمد أحمد #1212)
        $tenantUser = User::updateOrCreate(
            ['username' => 'tenant1212'],
            [
                'name' => 'محمد احمد',
                'username' => 'tenant1212',
                'phone' => '01143693590',
                'type' => UserType::Tenant,
                'password' => $defaultPassword,
                'is_active' => true,
            ]
        );

        $demoTenant = TenantInfo::updateOrCreate(
            ['tenant_number' => '1212'],
            [
                'user_id' => $tenantUser->id,
                'tenant_number' => '1212',
                'property_unit' => 'برج الامل',
                'property_id' => $propTest->id,
                'due_date' => '2026-10-30',
                'monthly_target' => 1000.00,
                'collector_id' => $collector->id,
                'owner_id' => $owner->id,
                'supervisor_id' => $generalManager->id,
                'national_id' => '124578963654',
                'birth_date' => '1997-03-19',
                'notes' => 'مستأجر منتظم بالدفع الشهري',
                'status' => 'regular',
            ]
        );

        // إضافة ملاحظات تجريبية للمستأجر
        TenantNote::create([
            'tenant_id' => $demoTenant->id,
            'user_id' => $generalManager->id,
            'content' => 'تم التواصل مع المستأجر بخصوص موعد الاستحقاق القادم.',
        ]);

        // إضافة موعد/وعد تجريبي
        TenantPromise::create([
            'tenant_id' => $demoTenant->id,
            'user_id' => $generalManager->id,
            'promise_date' => '2026-10-25',
            'note' => 'وعد بسداد القسط قبل نهاية الشهر عبر التحويل البنكي',
            'status' => 'pending',
        ]);

        // 3. توليد 8 مستأجرين إضافيين (حساب في users + تفاصيل في tenant_info)
        for ($i = 1; $i <= 8; $i++) {
            $u = User::factory()->tenant()->create([
                'password' => $defaultPassword,
            ]);

            $ti = TenantInfo::create([
                'user_id' => $u->id,
                'tenant_number' => 'T-'.(1000 + $i),
                'property_unit' => 'برج الأمل - وحدة '.$i,
                'due_date' => now()->addDays($i * 3)->format('Y-m-d'),
                'monthly_target' => 1000 + ($i * 200),
                'collector_id' => $collector->id,
                'supervisor_id' => $generalManager->id,
                'owner_id' => $owner->id,
                'status' => 'regular',
            ]);

            TenantNote::create([
                'tenant_id' => $ti->id,
                'user_id' => $generalManager->id,
                'content' => 'ملاحظة أولية عند تسجيل المستأجر.',
            ]);
        }

        // 4. توليد طلبات صيانة تجريبية مطابقة للواجهة المرفقة
        // أ. طلب صيانة بانتظار مراجعة المشرف العام
        MaintenanceRequest::updateOrCreate(
            ['ticket_number' => 'MNT-202610-0001'],
            [
                'ticket_number' => 'MNT-202610-0001',
                'tenant_name' => 'محمد احمد',
                'national_id' => '1211212121',
                'birth_date' => '1997-03-19',
                'phone' => '01143693590',
                'property_unit' => 'تيست',
                'title' => 'تسريب',
                'description' => 'تستستست',
                'latitude' => 24.73350000,
                'longitude' => 46.68450000,
                'address' => 'طريق الملك عبدالعزيز الفرعي، المرسلات، الرياض',
                'supervisor_status' => MaintenanceRequestSupervisorStatus::PendingReview,
                'owner_status' => null,
                'tenant_id' => $demoTenant->id,
                'owner_id' => null,
            ]
        );

        // ب. طلب صيانة معتمد من المشرف وبانتظار موافقة المالك الأول (كما بشاشة أعمال الصيانة)
        MaintenanceRequest::updateOrCreate(
            ['ticket_number' => 'MNT-202610-0002'],
            [
                'ticket_number' => 'MNT-202610-0002',
                'tenant_name' => 'محمد احمد',
                'national_id' => '1211212121',
                'birth_date' => '1997-03-19',
                'phone' => '01143693590',
                'property_unit' => 'تيست',
                'title' => 'تسريب',
                'description' => 'تستستست',
                'latitude' => 24.73350000,
                'longitude' => 46.68450000,
                'address' => 'طريق الملك عبدالعزيز الفرعي، المرسلات، الرياض',
                'supervisor_status' => MaintenanceRequestSupervisorStatus::Approved,
                'owner_status' => MaintenanceRequestOwnerStatus::Pending,
                'tenant_id' => $demoTenant->id,
                'owner_id' => $owner->id,
                'reviewed_by' => $generalManager->id,
                'reviewed_at' => '2026-10-14 08:00:00',
                'notes' => 'تم اعتماد المشرف وإحالة الطلب للمالك',
            ]
        );

        // ج. طلب صيانة تم اتخاذ قرار بشأنه في سجل قرارات المالك (مرفوضة)
        MaintenanceRequest::updateOrCreate(
            ['ticket_number' => 'MNT-202610-0003'],
            [
                'ticket_number' => 'MNT-202610-0003',
                'tenant_name' => 'محمد احمد',
                'national_id' => '1211212121',
                'birth_date' => '1997-03-19',
                'phone' => '01143693590',
                'property_unit' => 'تيست',
                'title' => 'تسريب قديم',
                'description' => 'مشكلة في مواسير المياه',
                'latitude' => 24.73350000,
                'longitude' => 46.68450000,
                'address' => 'طريق الملك عبدالعزيز الفرعي، المرسلات، الرياض',
                'supervisor_status' => MaintenanceRequestSupervisorStatus::Approved,
                'owner_status' => MaintenanceRequestOwnerStatus::Rejected,
                'tenant_id' => $demoTenant->id,
                'owner_id' => $owner->id,
                'reviewed_by' => $generalManager->id,
                'reviewed_at' => '2026-10-14 07:00:00',
                'owner_decision_at' => '2026-10-14 08:30:00',
                'owner_rejection_reason' => 'تم رفض الصيانة لكونها مسؤولية المستأجر',
            ]
        );

        // 6. أعمال صيانة تجريبية ("أعمال الصيانة" - إرسال تقرير صيانة جديد)
        // أ. تقرير صيانة مرسل بانتظار قرار المالك ("بانتظار قراركم")
        MaintenanceWork::updateOrCreate(
            ['work_order_number' => 'WRK-202610-0001'],
            [
                'work_order_number' => 'WRK-202610-0001',
                'owner_id' => $owner->id,
                'created_by' => $generalManager->id,
                'property_unit' => 'برج الأمل - وحدة 12',
                'title' => 'إصلاح تسريب المكيف في الصالة',
                'description' => 'تم فحص وحدة التكييف واستبدال العازل وصيانة مجرى الصرف واختبار التبريد بنجاح.',
                'attachments' => [],
                'status' => MaintenanceWorkStatus::Pending,
            ]
        );

        // ب. تقرير صيانة تم اعتماده وتقييمه من قبل المالك ("مقبولة" مع 5 نجوم)
        MaintenanceWork::updateOrCreate(
            ['work_order_number' => 'WRK-202610-0002'],
            [
                'work_order_number' => 'WRK-202610-0002',
                'owner_id' => $owner->id,
                'created_by' => $maintenanceSupervisor->id,
                'property_unit' => 'برج الأمل - وحدة 12',
                'title' => 'صيانة سباكة وتغيير خلاطات المطبخ',
                'description' => 'تم تركيب خلاطات إيطالية جديدة وتغيير الليات واختبار ضغط المياه بدون أي تسريبات.',
                'attachments' => [],
                'status' => MaintenanceWorkStatus::Approved,
                'owner_decision_at' => '2026-10-14 10:30:00',
                'owner_notes' => 'تمت المعاينة والعمل ممتاز ومقبول.',
                'rating' => 5,
                'rating_comment' => 'عمل احترافي وسريع، شكراً لكم.',
                'rated_at' => '2026-10-14 11:00:00',
            ]
        );

        // ج. تقرير صيانة تم رفضه من قبل المالك ("مرفوضة")
        MaintenanceWork::updateOrCreate(
            ['work_order_number' => 'WRK-202610-0003'],
            [
                'work_order_number' => 'WRK-202610-0003',
                'owner_id' => $owner->id,
                'created_by' => $generalManager->id,
                'property_unit' => 'برج الأمل - وحدة 15',
                'title' => 'دهان الجدران الداخلية',
                'description' => 'تم دهان جدران الصالة والممر بدهان زيتي نصف لمعة.',
                'attachments' => [],
                'status' => MaintenanceWorkStatus::Rejected,
                'owner_decision_at' => '2026-10-14 12:00:00',
                'owner_rejection_reason' => 'اللون المختار غير مطابق للمتفق عليه مسبقاً، يرجى إعادة المعاينة.',
                'owner_notes' => 'يرجى مراجعة المقاول لإعادة الدهان باللون المطلوب.',
            ]
        );
    }
}
