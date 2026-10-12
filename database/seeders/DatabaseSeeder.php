<?php

namespace Database\Seeders;

use App\Enums\UserType;
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
                'name' => 'المشرف الثاني (مشرف نظام)',
                'username' => 'admin',
                'phone' => '0500000001',
                'email' => 'admin@tcms.sa',
                'type' => UserType::GeneralManager,
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
                'name' => 'أحمد العتيبي (مشرف)',
                'username' => 'supervisor1',
                'phone' => '0500000003',
                'email' => 'supervisor@tcms.sa',
                'type' => UserType::Supervisor,
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
            [
                'name' => 'عمر مهندس الصيانة (مشرف صيانة)',
                'username' => 'maint1',
                'phone' => '0500000005',
                'email' => 'maintenance@tcms.sa',
                'type' => UserType::MaintenanceSupervisor,
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
        User::factory()->count(3)->supervisor()->create(['password' => $defaultPassword]);
        User::factory()->count(4)->collector()->create(['password' => $defaultPassword]);
        User::factory()->count(2)->owner()->create(['password' => $defaultPassword]);

        $supervisor = User::where('username', 'supervisor1')->first();
        $collector = User::where('username', 'collector1')->first();
        $owner = User::where('username', 'owner')->first();

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
                'due_date' => '2026-10-30',
                'monthly_target' => 1000.00,
                'collector_id' => $collector->id,
                'owner_id' => $owner->id,
                'supervisor_id' => $supervisor->id,
                'national_id' => '124578963654',
                'birth_date' => '1997-03-19',
                'notes' => 'مستأجر منتظم بالدفع الشهري',
                'status' => 'regular',
            ]
        );

        // إضافة ملاحظات تجريبية للمستأجر
        TenantNote::create([
            'tenant_id' => $demoTenant->id,
            'user_id' => $supervisor->id,
            'content' => 'تم التواصل مع المستأجر بخصوص موعد الاستحقاق القادم.',
        ]);

        // إضافة موعد/وعد تجريبي
        TenantPromise::create([
            'tenant_id' => $demoTenant->id,
            'user_id' => $supervisor->id,
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
                'supervisor_id' => $supervisor->id,
                'owner_id' => $owner->id,
                'status' => 'regular',
            ]);

            TenantNote::create([
                'tenant_id' => $ti->id,
                'user_id' => $supervisor->id,
                'content' => 'ملاحظة أولية عند تسجيل المستأجر.',
            ]);
        }
    }
}
