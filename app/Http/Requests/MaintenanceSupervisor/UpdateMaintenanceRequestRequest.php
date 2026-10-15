<?php

namespace App\Http\Requests\MaintenanceSupervisor;

use App\Enums\MaintenanceRequestOwnerStatus;
use App\Enums\MaintenanceRequestSupervisorStatus;
use App\Enums\UserType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMaintenanceRequestRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && ($user->isMaintenanceSupervisor() || $user->isGeneralManager());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tenant_name' => ['sometimes', 'required', 'string', 'max:255'],
            'national_id' => ['sometimes', 'required', 'string', 'max:50'],
            'birth_date' => ['sometimes', 'required', 'date'],
            'phone' => ['sometimes', 'required', 'string', 'max:50'],
            'property_unit' => ['sometimes', 'required', 'string', 'max:255'],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'required', 'string', 'max:5000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'address' => ['nullable', 'string', 'max:500'],
            'owner_id' => [
                'nullable',
                Rule::exists('users', 'id')->where(function ($query) {
                    $query->where('type', UserType::Owner->value);
                }),
            ],
            'supervisor_status' => ['nullable', Rule::in(MaintenanceRequestSupervisorStatus::values())],
            'owner_status' => ['nullable', Rule::in(MaintenanceRequestOwnerStatus::values())],
            'status' => ['nullable', Rule::in(MaintenanceRequestSupervisorStatus::values())],
            'notes' => ['nullable', 'string', 'max:2000'],
            'rejection_reason' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
