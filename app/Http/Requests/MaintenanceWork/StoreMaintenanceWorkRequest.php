<?php

namespace App\Http\Requests\MaintenanceWork;

use App\Models\MaintenanceRequest;
use App\Models\Property;
use App\Models\TenantInfo;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreMaintenanceWorkRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare inputs for validation.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->filled('owner_id')) {
            if ($this->filled('property_id')) {
                $property = Property::find($this->input('property_id'));
                if ($property?->owner_id) {
                    $this->merge([
                        'owner_id' => $property->owner_id,
                        'property_unit' => $this->input('property_unit') ?? $property->name,
                    ]);
                }
            } elseif ($this->filled('tenant_id')) {
                $tenant = TenantInfo::find($this->input('tenant_id'));
                if ($tenant?->owner_id) {
                    $this->merge([
                        'owner_id' => $tenant->owner_id,
                        'property_unit' => $this->input('property_unit') ?? $tenant->property_unit,
                    ]);
                }
            } elseif ($this->filled('maintenance_request_id')) {
                $mr = MaintenanceRequest::find($this->input('maintenance_request_id'));
                if ($mr?->owner_id) {
                    $this->merge([
                        'owner_id' => $mr->owner_id,
                        'property_unit' => $this->input('property_unit') ?? $mr->property_unit,
                    ]);
                }
            }
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'owner_id' => ['required', 'integer', 'exists:users,id'],
            'property_id' => ['nullable', 'integer', 'exists:properties,id'],
            'tenant_id' => ['nullable', 'integer', 'exists:tenant_info,id'],
            'property_unit' => ['required', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:15360', 'mimes:jpeg,jpg,png,webp,gif,mp4,mov,avi,webm'],
            'maintenance_request_id' => ['nullable', 'integer', 'exists:maintenance_requests,id'],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'owner_id' => 'المالك',
            'property_unit' => 'العقار / الوحدة',
            'title' => 'عنوان العمل',
            'description' => 'الوصف',
            'attachments' => 'المرفقات',
            'attachments.*' => 'ملف المرفق',
            'maintenance_request_id' => 'طلب صيانة المستأجر المرتبط',
        ];
    }
}
