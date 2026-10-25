<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    /**
     * Determine whether the user can view the payment.
     */
    public function view(User $user, Payment $payment): bool
    {
        if ($user->isGeneralManager() || $user->isSupervisorType()) {
            return true;
        }

        if ($payment->collector_id === $user->id) {
            return true;
        }

        return (bool) ($payment->tenant && $payment->tenant->owner_id === $user->id);
    }
}
