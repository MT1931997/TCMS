<?php

namespace App\Http\Controllers\Api\Owner;

use App\Enums\TenantStatus;
use App\Http\Controllers\Controller;
use App\Models\TenantInfo;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PropertyController extends Controller
{
    /**
     * Display owner's properties, units, and tenant collections ("إدارة أملاكي").
     */
    public function index(Request $request): JsonResponse
    {
        $owner = $request->user();
        $period = $request->input('period', now()->format('Y-m'));

        $tenants = TenantInfo::with(['user', 'collector'])
            ->where('owner_id', $owner->id)
            ->get();

        // 1. Group by property name if units are named like "برج الامل - شقة 1" or distinct property_unit
        $properties = $tenants->groupBy(function ($tenant) {
            // Extract building/property name before hyphen if format is "برج X - شقة Y"
            $parts = explode('-', $tenant->property_unit);

            return trim($parts[0] ?: $tenant->property_unit);
        })->map(function ($units, $propertyName) use ($period) {
            $totalTarget = (float) $units->sum('monthly_target');
            $totalPaid = (float) $units->sum(fn ($tenant) => $tenant->paidForPeriod($period));

            return [
                'name' => $propertyName,
                'units_count' => $units->count(),
                'total_monthly_target' => $totalTarget,
                'total_collected' => $totalPaid,
                'collection_percentage' => $totalTarget > 0 ? round(($totalPaid / $totalTarget) * 100, 1) : 0,
            ];
        })->values();

        // 2. Tenants table: "مستأجرو وحداتي – المستهدف والمحصل لشهر X"
        $tenantsData = $tenants->map(function ($tenant) use ($period) {
            $target = (float) $tenant->monthly_target;
            $paid = (float) $tenant->paidForPeriod($period);
            $remaining = max(0, $target - $paid);

            // Determine status label matching the design
            if ($target > 0 && $paid >= $target) {
                $status = TenantStatus::Paid->value;
                $statusLabel = 'مسدد';
            } elseif ($paid > 0 && $paid < $target) {
                $status = TenantStatus::Regular->value;
                $statusLabel = 'مسدد جزئي';
            } elseif ($tenant->due_date && $tenant->due_date->isPast()) {
                $status = TenantStatus::Late->value;
                $statusLabel = 'متأخر';
            } else {
                $status = TenantStatus::Regular->value;
                $statusLabel = 'لم يسدد';
            }

            return [
                'id' => $tenant->id,
                'property_unit' => $tenant->property_unit,
                'tenant_name' => $tenant->user?->name ?? '',
                'tenant_phone' => $tenant->user?->phone ?? '',
                'collector_name' => $tenant->collector?->name ?? 'غير محدد',
                'monthly_target' => $target,
                'paid_amount' => $paid,
                'remaining_amount' => $remaining,
                'status' => $status,
                'status_label' => $statusLabel,
            ];
        });

        // 3. Overall Statistics
        $totalUnits = $tenants->count();
        $totalTarget = (float) $tenants->sum('monthly_target');
        $totalCollected = (float) $tenants->sum(fn ($t) => $t->paidForPeriod($period));
        $collectionPercentage = $totalTarget > 0 ? round(($totalCollected / $totalTarget) * 100, 1) : 0;

        return ApiResponse::success([
            'period' => $period,
            'period_formatted' => now()->createFromFormat('Y-m', $period)->translatedFormat('F Y'),
            'has_properties' => $properties->isNotEmpty(),
            'properties' => $properties,
            'tenants' => $tenantsData,
            'statistics' => [
                'total_units' => $totalUnits,
                'total_target' => $totalTarget,
                'total_collected' => $totalCollected,
                'collection_percentage' => $collectionPercentage,
            ],
        ], 'تم جلب بيانات أملاكك ومستأجريها بنجاح.');
    }
}
