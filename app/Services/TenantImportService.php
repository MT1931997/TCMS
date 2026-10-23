<?php

namespace App\Services;

use App\Enums\UserType;
use App\Models\Property;
use App\Models\TenantInfo;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TenantImportService
{
    /**
     * Import tenants from an uploaded Excel or CSV file.
     *
     * @return array{
     *     total: int,
     *     imported: int,
     *     updated: int,
     *     failed: int,
     *     errors: array<int, array{row: int, error: string}>
     * }
     */
    public function import(UploadedFile $file, User $supervisor): array
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $worksheet = $spreadsheet->getActiveSheet();
        $highestRow = $worksheet->getHighestRow();

        $imported = 0;
        $updated = 0;
        $failed = 0;
        $errors = [];

        // Preload collectors and owners to minimize database queries
        $collectors = User::where('type', UserType::Collector)->get();
        $owners = User::where('type', UserType::Owner)->get();
        $defaultCollector = $collectors->first() ?? $supervisor;

        // Row 1 is headers; data starts from row 2
        for ($row = 2; $row <= $highestRow; $row++) {
            $contractNumber = trim((string) $worksheet->getCell("A{$row}")->getValue());
            $tenantNumber = trim((string) $worksheet->getCell("B{$row}")->getValue());
            $name = trim((string) $worksheet->getCell("C{$row}")->getValue());
            $phone = trim((string) $worksheet->getCell("D{$row}")->getValue());
            $propertyUnit = trim((string) $worksheet->getCell("E{$row}")->getValue());
            $dueDateCell = $worksheet->getCell("F{$row}");
            $collectorVal = trim((string) $worksheet->getCell("G{$row}")->getValue());
            $ownerVal = trim((string) $worksheet->getCell("H{$row}")->getValue());
            $monthlyTarget = (float) $worksheet->getCell("I{$row}")->getValue();
            $notes = trim((string) $worksheet->getCell("J{$row}")->getValue());

            // Skip completely empty rows
            if (empty($tenantNumber) && empty($name) && empty($phone) && empty($contractNumber)) {
                continue;
            }

            // Basic validation for essential columns
            if (empty($name)) {
                $failed++;
                $errors[] = ['row' => $row, 'error' => 'اسم المستأجر مفقود في السطر '.$row];

                continue;
            }

            if (empty($tenantNumber)) {
                $failed++;
                $errors[] = ['row' => $row, 'error' => 'رقم المستأجر مفقود في السطر '.$row];

                continue;
            }

            if (empty($phone)) {
                $failed++;
                $errors[] = ['row' => $row, 'error' => 'رقم الجوال مفقود في السطر '.$row];

                continue;
            }

            // Parse Due Date
            $dueDate = $this->parseExcelDate($dueDateCell);

            // Match Collector by name, username, or id
            $collector = null;
            if (! empty($collectorVal)) {
                $collector = $collectors->first(function ($c) use ($collectorVal) {
                    return $c->name === $collectorVal
                        || $c->username === $collectorVal
                        || (string) $c->id === $collectorVal;
                });
            }
            $collectorId = $collector?->id ?? $defaultCollector->id;

            // Match Owner by name, username, or id
            $owner = null;
            if (! empty($ownerVal)) {
                $owner = $owners->first(function ($o) use ($ownerVal) {
                    return $o->name === $ownerVal
                        || $o->username === $ownerVal
                        || (string) $o->id === $ownerVal;
                });
            }
            $ownerId = $owner?->id;

            $contractNumberVal = $contractNumber !== '' ? $contractNumber : null;

            try {
                DB::transaction(function () use (
                    $tenantNumber,
                    $contractNumberVal,
                    $name,
                    $phone,
                    $propertyUnit,
                    $dueDate,
                    $monthlyTarget,
                    $collectorId,
                    $ownerId,
                    $supervisor,
                    $notes,
                    &$imported,
                    &$updated
                ) {
                    $matchedPropertyId = null;
                    if (! empty($propertyUnit)) {
                        $parts = explode('-', $propertyUnit);
                        $propertyName = trim($parts[0] ?: $propertyUnit);
                        if (! empty($propertyName)) {
                            if ($ownerId) {
                                $prop = Property::firstOrCreate(
                                    ['name' => $propertyName, 'owner_id' => $ownerId],
                                    ['created_by' => $supervisor->id]
                                );
                                $matchedPropertyId = $prop->id;
                            } else {
                                $prop = Property::where('name', $propertyName)->first();
                                $matchedPropertyId = $prop?->id;
                            }
                        }
                    }

                    $existingTenantInfo = TenantInfo::where('tenant_number', $tenantNumber)->first();

                    if ($existingTenantInfo) {
                        // Update existing User
                        $existingTenantInfo->user->update([
                            'name' => $name,
                            'phone' => $phone,
                        ]);

                        // Update existing TenantInfo
                        $existingTenantInfo->update([
                            'contract_number' => $contractNumberVal ?? $existingTenantInfo->contract_number,
                            'property_unit' => $propertyUnit ?: $existingTenantInfo->property_unit,
                            'property_id' => $matchedPropertyId ?? $existingTenantInfo->property_id,
                            'due_date' => $dueDate,
                            'monthly_target' => $monthlyTarget ?: $existingTenantInfo->monthly_target,
                            'collector_id' => $collectorId,
                            'owner_id' => $ownerId ?? $existingTenantInfo->owner_id,
                            'notes' => $notes ?: $existingTenantInfo->notes,
                        ]);

                        $updated++;
                    } else {
                        // Create new User
                        $user = User::create([
                            'name' => $name,
                            'username' => 'tenant_'.$tenantNumber,
                            'phone' => $phone,
                            'type' => UserType::Tenant,
                            'password' => Hash::make('password123'),
                            'is_active' => true,
                        ]);

                        // Create new TenantInfo
                        TenantInfo::create([
                            'user_id' => $user->id,
                            'tenant_number' => $tenantNumber,
                            'contract_number' => $contractNumberVal,
                            'property_unit' => $propertyUnit ?: 'غير محدد',
                            'property_id' => $matchedPropertyId,
                            'due_date' => $dueDate,
                            'monthly_target' => $monthlyTarget,
                            'collector_id' => $collectorId,
                            'owner_id' => $ownerId,
                            'supervisor_id' => $supervisor->id,
                            'notes' => $notes ?: null,
                            'status' => 'regular',
                        ]);

                        $imported++;
                    }
                });
            } catch (\Throwable $e) {
                $failed++;
                $errors[] = ['row' => $row, 'error' => "خطأ في السطر {$row}: ".$e->getMessage()];
            }
        }

        return [
            'total' => $imported + $updated + $failed,
            'imported' => $imported,
            'updated' => $updated,
            'failed' => $failed,
            'errors' => $errors,
        ];
    }

    /**
     * Generate and download a sample Excel template for tenants import.
     */
    public function downloadTemplate(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setRightToLeft(true);

        // Header columns matching the screenshot
        $headers = [
            'A1' => 'رقم العقد',
            'B1' => 'رقم المستأجر',
            'C1' => 'اسم المستأجر',
            'D1' => 'رقم الجوال',
            'E1' => 'العقار والوحدة',
            'F1' => 'تاريخ الاستحقاق',
            'G1' => 'المحصل',
            'H1' => 'المالك',
            'I1' => 'الهدف الشهري',
            'J1' => 'ملاحظات',
        ];

        foreach ($headers as $cell => $value) {
            $sheet->setCellValue($cell, $value);
            $sheet->getStyle($cell)->getFont()->setBold(true);
        }

        // Example data matching the screenshot
        $sheet->setCellValue('A2', '5454');
        $sheet->setCellValue('B2', '121212');
        $sheet->setCellValue('C2', 'محمد عبدالله');
        $sheet->setCellValue('D2', '0500000000');
        $sheet->setCellValue('E2', 'برج الأمل - وحدة 12');
        $sheet->setCellValue('F2', '2026-10-30');
        $sheet->setCellValue('G2', 'المحصل الأول');
        $sheet->setCellValue('H2', 'المالك الأول');
        $sheet->setCellValue('I2', 2000);
        $sheet->setCellValue('J2', 'ملاحظات تجريبية');

        // Auto-fit column widths
        foreach (range('A', 'J') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $fileName = 'قالب_استيراد_المستأجرين.xlsx';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Helper to safely parse dates from Excel cell.
     */
    private function parseExcelDate($cell): string
    {
        $val = $cell->getValue();

        if (empty($val)) {
            return now()->addDays(15)->format('Y-m-d');
        }

        if (is_numeric($val) && ExcelDate::isDateTime($cell)) {
            return ExcelDate::excelToDateTimeObject((float) $val)->format('Y-m-d');
        }

        try {
            return Carbon::parse($val)->format('Y-m-d');
        } catch (\Throwable) {
            return now()->addDays(15)->format('Y-m-d');
        }
    }
}
