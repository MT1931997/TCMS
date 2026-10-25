<?php

namespace App\Services;

use App\Enums\ClaimGenerationMode;
use App\Enums\DueStatus;
use App\Enums\PaymentCycle;
use App\Models\Contract;
use App\Models\Due;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class DueScheduleService
{
    /**
     * Generate or regenerate scheduled dues for a given contract.
     *
     * @return Collection<int, Due>
     */
    public function generateForContract(Contract $contract): Collection
    {
        $mode = $contract->claim_generation_mode instanceof ClaimGenerationMode
            ? $contract->claim_generation_mode
            : ClaimGenerationMode::tryFrom((string) $contract->claim_generation_mode);

        if ($mode === ClaimGenerationMode::None) {
            $contract->dues()->delete();

            return collect();
        }

        // Delete existing uncollected dues before rebuilding
        $contract->dues()->where('paid_amount', '<=', 0)->delete();

        $cycle = $contract->payment_cycle instanceof PaymentCycle
            ? $contract->payment_cycle
            : PaymentCycle::tryFrom((string) $contract->payment_cycle) ?? PaymentCycle::Monthly;

        $monthsInterval = match ($cycle) {
            PaymentCycle::Quarterly => 3,
            PaymentCycle::SemiAnnual => 6,
            PaymentCycle::Annual => 12,
            default => 1,
        };

        $installment = (float) $contract->installment_amount;
        $totalAmount = (float) $contract->total_amount;
        $startDate = Carbon::parse($contract->start_date);
        $endDate = Carbon::parse($contract->end_date);

        $dues = collect();
        $currentDue = $startDate->copy();
        $accumulated = 0;

        while ($currentDue->lte($endDate) && $accumulated < $totalAmount) {
            $dueAmount = min($installment, $totalAmount - $accumulated);
            if ($dueAmount <= 0) {
                break;
            }

            // Check if a paid/partial due already exists for this due_date
            $existing = $contract->dues()
                ->where('due_date', $currentDue->format('Y-m-d'))
                ->first();

            if (! $existing) {
                $status = $currentDue->isPast() ? DueStatus::Pending : DueStatus::Pending;

                $due = Due::create([
                    'contract_id' => $contract->id,
                    'tenant_id' => $contract->tenant_id,
                    'unit_id' => $contract->unit_id,
                    'due_date' => $currentDue->format('Y-m-d'),
                    'amount' => $dueAmount,
                    'paid_amount' => 0,
                    'period' => $currentDue->format('Y-m'),
                    'status' => $status,
                ]);

                $dues->push($due);
            } else {
                $dues->push($existing);
            }

            $accumulated += $dueAmount;
            $currentDue->addMonths($monthsInterval);
        }

        // Allocate any existing completed/approved payments to these dues
        $this->syncPaymentsToDues($contract);

        return $dues;
    }

    /**
     * Synchronize and allocate payments of this contract to its dues.
     */
    public function syncPaymentsToDues(Contract $contract): void
    {
        $payments = $contract->tenant?->payments()
            ->whereIn('status', ['completed', 'approved'])
            ->whereBetween('payment_date', [
                $contract->start_date->format('Y-m-d'),
                $contract->end_date->format('Y-m-d'),
            ])
            ->orderBy('payment_date')
            ->get() ?? collect();

        // Reset paid amounts on dues
        $dues = $contract->dues()->orderBy('due_date')->get();
        foreach ($dues as $due) {
            $due->update([
                'paid_amount' => 0,
                'status' => DueStatus::Pending,
            ]);
        }

        foreach ($payments as $payment) {
            $this->allocatePayment($payment, $contract);
        }
    }

    /**
     * Allocate a specific payment against contract dues.
     */
    public function allocatePayment(Payment $payment, ?Contract $contract = null): void
    {
        $contract = $contract ?? $payment->tenant?->activeContract;
        if (! $contract) {
            return;
        }

        $remainingToAllocate = (float) $payment->amount;

        // 1. Try matching by period first if available
        if ($payment->period) {
            $matchingDue = $contract->dues()
                ->where('period', $payment->period)
                ->where('status', '!=', DueStatus::Paid->value)
                ->first();

            if ($matchingDue) {
                $remainingToAllocate = $matchingDue->recordPayment($remainingToAllocate);
                $payment->update(['due_id' => $matchingDue->id]);
            }
        }

        // 2. Allocate any remaining amount to oldest pending/partial dues
        if ($remainingToAllocate > 0) {
            $pendingDues = $contract->dues()
                ->whereIn('status', [DueStatus::Pending->value, DueStatus::Partial->value])
                ->orderBy('due_date')
                ->get();

            foreach ($pendingDues as $due) {
                if ($remainingToAllocate <= 0) {
                    break;
                }

                $remainingToAllocate = $due->recordPayment($remainingToAllocate);
                if (! $payment->due_id) {
                    $payment->update(['due_id' => $due->id]);
                }
            }
        }
    }
}
