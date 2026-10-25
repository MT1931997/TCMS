<?php

namespace App\Policies;

use App\Models\MaintenanceRequest;
use App\Models\User;

class MaintenanceRequestPolicy
{
    /**
     * Determine whether the user can view the maintenance request attachment.
     */
    public function viewAttachment(?User $user, MaintenanceRequest $maintenanceRequest): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->isGeneralManager() || $user->isMaintenanceSupervisor() || $user->isRealEstateSupervisor()) {
            return true;
        }

        if ($user->isOwner() && $maintenanceRequest->owner_id === $user->id) {
            return true;
        }

        if ($user->isTenant() && (
            $maintenanceRequest->tenant_id === $user->tenantInfo?->id
            || ($user->tenantInfo?->national_id && $maintenanceRequest->national_id === $user->tenantInfo->national_id)
        )) {
            return true;
        }

        return false;
    }
}
