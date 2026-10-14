<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class TenantImportApiTest extends TestCase
{
    use RefreshDatabase;

    private User $supervisor;

    private User $collector;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->supervisor = User::factory()->generalManager()->create();
        $this->collector = User::factory()->collector()->create(['name' => 'المحصل الأول']);
        $this->owner = User::factory()->owner()->create(['name' => 'المالك الأول']);
    }

    public function test_supervisor_can_download_excel_template(): void
    {
        $response = $this->actingAs($this->supervisor, 'sanctum')
            ->get(route('api.general-manager.tenants.import.template'));

        $response->assertOk();
        $this->assertStringContainsString('spreadsheetml', (string) $response->headers->get('content-type'));
    }

    public function test_supervisor_can_import_tenants_from_excel_file(): void
    {
        // 1. Create a dummy Excel file matching the template columns
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        // Headers
        $sheet->setCellValue('A1', 'رقم العقد');
        $sheet->setCellValue('B1', 'رقم المستأجر');
        $sheet->setCellValue('C1', 'اسم المستأجر');
        $sheet->setCellValue('D1', 'رقم الجوال');
        $sheet->setCellValue('E1', 'العقار والوحدة');
        $sheet->setCellValue('F1', 'تاريخ الاستحقاق');
        $sheet->setCellValue('G1', 'المحصل');
        $sheet->setCellValue('H1', 'المالك');
        $sheet->setCellValue('I1', 'الهدف الشهري');
        $sheet->setCellValue('J1', 'ملاحظات');

        // Row 2 (with contract number)
        $sheet->setCellValue('A2', 'CNT-101');
        $sheet->setCellValue('B2', 'T-9001');
        $sheet->setCellValue('C2', 'فيصل القحطاني');
        $sheet->setCellValue('D2', '0599999991');
        $sheet->setCellValue('E2', 'برج التحلية - شقة 101');
        $sheet->setCellValue('F2', '2026-10-25');
        $sheet->setCellValue('G2', 'المحصل الأول');
        $sheet->setCellValue('H2', 'المالك الأول');
        $sheet->setCellValue('I2', 2500);
        $sheet->setCellValue('J2', 'مستأجر ممتاز');

        // Row 3 (without contract number - optional)
        $sheet->setCellValue('A3', '');
        $sheet->setCellValue('B3', 'T-9002');
        $sheet->setCellValue('C3', 'ياسر الحربي');
        $sheet->setCellValue('D3', '0599999992');
        $sheet->setCellValue('E3', 'برج التحلية - شقة 102');
        $sheet->setCellValue('F3', '2026-10-30');
        $sheet->setCellValue('G3', 'المحصل الأول');
        $sheet->setCellValue('H3', 'المالك الأول');
        $sheet->setCellValue('I3', 3000);
        $sheet->setCellValue('J3', '');

        $tempPath = tempnam(sys_get_temp_dir(), 'test_tenants_').'.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempPath);

        $uploadedFile = new UploadedFile(
            $tempPath,
            'tenants_test.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $response = $this->actingAs($this->supervisor, 'sanctum')
            ->postJson(route('api.general-manager.tenants.import'), [
                'file' => $uploadedFile,
            ]);

        @unlink($tempPath);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.imported', 2)
            ->assertJsonPath('data.updated', 0)
            ->assertJsonPath('data.failed', 0);

        // Verify users created
        $this->assertDatabaseHas('users', [
            'name' => 'فيصل القحطاني',
            'phone' => '0599999991',
        ]);

        $this->assertDatabaseHas('users', [
            'name' => 'ياسر الحربي',
            'phone' => '0599999992',
        ]);

        // Verify tenant_info created
        $this->assertDatabaseHas('tenant_info', [
            'tenant_number' => 'T-9001',
            'contract_number' => 'CNT-101',
            'property_unit' => 'برج التحلية - شقة 101',
            'monthly_target' => 2500,
        ]);

        $this->assertDatabaseHas('tenant_info', [
            'tenant_number' => 'T-9002',
            'contract_number' => null,
            'monthly_target' => 3000,
        ]);
    }

    public function test_supervisor_can_update_existing_tenants_from_excel_file(): void
    {
        // First import to create the tenant
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setCellValue('A1', 'رقم العقد');
        $sheet->setCellValue('B1', 'رقم المستأجر');
        $sheet->setCellValue('C1', 'اسم المستأجر');
        $sheet->setCellValue('D1', 'رقم الجوال');
        $sheet->setCellValue('E1', 'العقار والوحدة');
        $sheet->setCellValue('F1', 'تاريخ الاستحقاق');
        $sheet->setCellValue('G1', 'المحصل');
        $sheet->setCellValue('H1', 'المالك');
        $sheet->setCellValue('I1', 'الهدف الشهري');
        $sheet->setCellValue('J1', 'ملاحظات');

        $sheet->setCellValue('A2', '');
        $sheet->setCellValue('B2', 'T-9001');
        $sheet->setCellValue('C2', 'فيصل القحطاني');
        $sheet->setCellValue('D2', '0599999991');
        $sheet->setCellValue('E2', 'برج التحلية - شقة 101');
        $sheet->setCellValue('F2', '2026-10-25');
        $sheet->setCellValue('G2', 'المحصل الأول');
        $sheet->setCellValue('H2', 'المالك الأول');
        $sheet->setCellValue('I2', 2500);
        $sheet->setCellValue('J2', '');

        $tempPath = tempnam(sys_get_temp_dir(), 'test_tenants_').'.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempPath);

        $uploadedFile = new UploadedFile($tempPath, 'tenants_test.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
        $this->actingAs($this->supervisor, 'sanctum')->postJson(route('api.general-manager.tenants.import'), ['file' => $uploadedFile]);
        @unlink($tempPath);

        // Second import updating the same tenant with contract number and updated target
        $spreadsheet2 = new Spreadsheet;
        $sheet2 = $spreadsheet2->getActiveSheet();
        $sheet2->setCellValue('A1', 'رقم العقد');
        $sheet2->setCellValue('B1', 'رقم المستأجر');
        $sheet2->setCellValue('C1', 'اسم المستأجر');
        $sheet2->setCellValue('D1', 'رقم الجوال');
        $sheet2->setCellValue('E1', 'العقار والوحدة');
        $sheet2->setCellValue('F1', 'تاريخ الاستحقاق');
        $sheet2->setCellValue('G1', 'المحصل');
        $sheet2->setCellValue('H1', 'المالك');
        $sheet2->setCellValue('I1', 'الهدف الشهري');
        $sheet2->setCellValue('J1', 'ملاحظات');

        $sheet2->setCellValue('A2', 'CNT-NEW-555');
        $sheet2->setCellValue('B2', 'T-9001');
        $sheet2->setCellValue('C2', 'فيصل القحطاني المعدل');
        $sheet2->setCellValue('D2', '0599999991');
        $sheet2->setCellValue('E2', 'برج التحلية - شقة 202');
        $sheet2->setCellValue('F2', '2026-11-01');
        $sheet2->setCellValue('G2', 'المحصل الأول');
        $sheet2->setCellValue('H2', 'المالك الأول');
        $sheet2->setCellValue('I2', 3500);
        $sheet2->setCellValue('J2', 'تم التحديث');

        $tempPath2 = tempnam(sys_get_temp_dir(), 'test_tenants_update_').'.xlsx';
        $writer2 = new Xlsx($spreadsheet2);
        $writer2->save($tempPath2);

        $uploadedFile2 = new UploadedFile($tempPath2, 'tenants_update.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
        $response = $this->actingAs($this->supervisor, 'sanctum')
            ->postJson(route('api.general-manager.tenants.import'), ['file' => $uploadedFile2]);
        @unlink($tempPath2);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.imported', 0)
            ->assertJsonPath('data.updated', 1)
            ->assertJsonPath('data.failed', 0);

        $this->assertDatabaseHas('users', [
            'name' => 'فيصل القحطاني المعدل',
            'phone' => '0599999991',
        ]);

        $this->assertDatabaseHas('tenant_info', [
            'tenant_number' => 'T-9001',
            'contract_number' => 'CNT-NEW-555',
            'property_unit' => 'برج التحلية - شقة 202',
            'monthly_target' => 3500,
        ]);
    }

    public function test_import_fails_without_file(): void
    {
        $response = $this->actingAs($this->supervisor, 'sanctum')
            ->postJson(route('api.general-manager.tenants.import'), []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }
}
