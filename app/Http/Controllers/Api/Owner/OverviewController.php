<?php

namespace App\Http\Controllers\Api\Owner;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\TenantInfo;
use App\Support\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OverviewController extends Controller
{
    /**
     * Display the owner overview dashboard data ("نظرة عامة").
     */
    public function index(Request $request): JsonResponse
    {
        $owner = $request->user();
        $period = $request->input('period', now()->format('Y-m'));

        try {
            $currentDate = Carbon::createFromFormat('Y-m', $period)->startOfMonth();
        } catch (\Throwable) {
            $period = now()->format('Y-m');
            $currentDate = now()->startOfMonth();
        }

        $periodFormatted = $currentDate->translatedFormat('F Y');

        // Retrieve all tenants owned by this owner
        $tenants = TenantInfo::with(['collector'])
            ->where('owner_id', $owner->id)
            ->get();

        // 1. KPI Cards
        $totalUnitsCount = $tenants->count();
        $targetThisMonth = (float) $tenants->sum('monthly_target');

        $collectedThisMonth = (float) Payment::whereHas('tenant', function ($q) use ($owner) {
            $q->where('owner_id', $owner->id);
        })
            ->where('period', $period)
            ->whereIn('status', [PaymentStatus::Approved->value, PaymentStatus::Completed->value])
            ->sum('amount');

        $collectionPercentage = $targetThisMonth > 0 ? round(($collectedThisMonth / $targetThisMonth) * 100, 1) : 0;

        $totalCollectedAllTime = (float) Payment::whereHas('tenant', function ($q) use ($owner) {
            $q->where('owner_id', $owner->id);
        })
            ->whereIn('status', [PaymentStatus::Approved->value, PaymentStatus::Completed->value])
            ->sum('amount');

        // 2. Monthly Target Progress Bar
        $monthlyProgress = [
            'target' => $targetThisMonth,
            'collected' => $collectedThisMonth,
            'remaining' => max(0, $targetThisMonth - $collectedThisMonth),
            'percentage' => $collectionPercentage,
            'label' => number_format($collectedThisMonth).' ر.س / '.number_format($targetThisMonth).' ر.س',
        ];

        // 3. Assigned Collectors Table: "المحصل المسؤول عن وحداتك"
        $assignedCollectors = $tenants->groupBy('collector_id')->map(function ($collectorTenants) use ($period) {
            $collector = $collectorTenants->first()?->collector;
            $unitsCount = $collectorTenants->count();
            $targetAmount = (float) $collectorTenants->sum('monthly_target');

            $tenantIds = $collectorTenants->pluck('id');
            $collectedAmount = (float) Payment::whereIn('tenant_id', $tenantIds)
                ->where('period', $period)
                ->whereIn('status', [PaymentStatus::Approved->value, PaymentStatus::Completed->value])
                ->sum('amount');

            $pct = $targetAmount > 0 ? round(($collectedAmount / $targetAmount) * 100, 1) : 0;

            return [
                'id' => $collector?->id,
                'name' => $collector?->name ?? 'غير محدد',
                'units_count' => $unitsCount,
                'target_amount' => $targetAmount,
                'collected_amount' => $collectedAmount,
                'percentage' => $pct,
            ];
        })->values();

        // 4. Chart: "تحصيل وحداتي – آخر 6 أشهر"
        $chartLast6Months = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthDate = $currentDate->copy()->subMonths($i);
            $monthPeriod = $monthDate->format('Y-m');
            $monthLabel = $monthDate->translatedFormat('F Y');

            $amount = (float) Payment::whereHas('tenant', function ($q) use ($owner) {
                $q->where('owner_id', $owner->id);
            })
                ->where('period', $monthPeriod)
                ->whereIn('status', [PaymentStatus::Approved->value, PaymentStatus::Completed->value])
                ->sum('amount');

            $chartLast6Months[] = [
                'period' => $monthPeriod,
                'month_label' => $monthLabel,
                'collected_amount' => $amount,
            ];
        }

        return ApiResponse::success([
            'period' => $period,
            'period_formatted' => $periodFormatted,
            'kpis' => [
                'collected_this_month' => $collectedThisMonth,
                'target_this_month' => $targetThisMonth,
                'collection_percentage' => $collectionPercentage,
                'total_collected_all_time' => $totalCollectedAllTime,
                'total_units_count' => $totalUnitsCount,
            ],
            'monthly_progress' => $monthlyProgress,
            'assigned_collectors' => $assignedCollectors,
            'chart_last_6_months' => $chartLast6Months,
        ], 'تم جلب بيانات النظرة العامة بنجاح.');
    }
}
