<?php

namespace App\Http\Requests\GeneralManager;

use App\Enums\TenantStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTenantRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $tenantId = $this->route('tenant')?->id ?? $this->route('tenant');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'tenant_number' => ['sometimes', 'required', 'string', 'max:100', Rule::unique('tenant_info', 'tenant_number')->ignore($tenantId)],
            'contract_number' => ['nullable', 'string', 'max:100'],
            'phone' => ['sometimes', 'required', 'string', 'max:20'],
            'property_unit' => ['sometimes', 'required', 'string', 'max:255'],
            'due_date' => ['sometimes', 'required', 'date'],
            'monthly_target' => ['sometimes', 'required', 'numeric', 'min:0'],
            'collector_id' => ['sometimes', 'required', 'integer', 'exists:users,id'],
            'owner_id' => ['nullable', 'integer', 'exists:users,id'],
            'national_id' => ['nullable', 'string', 'max:50'],
            'birth_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'status' => ['nullable', 'string', Rule::enum(TenantStatus::class)],
        ];
    }

    /**
     * Custom validation messages in Arabic.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'اسم المستأجر مطلوب.',
            'tenant_number.required' => 'رقم المستأجر مطلوب.',
            'tenant_number.unique' => 'رقم المستأجر مسجل لمستأجر آخر.',
            'phone.required' => 'رقم الجوال مطلوب.',
            'property_unit.required' => 'العقار والوحدة مطلوب.',
            'due_date.required' => 'تاريخ الاستحقاق مطلوب.',
            'monthly_target.required' => 'الهدف الشهري مطلوب.',
            'collector_id.required' => 'يرجى تحديد المحصل المسؤول.',
            'collector_id.exists' => 'المحصل المحدد غير موجود في النظام.',
            'owner_id.exists' => 'المالك المحدد غير موجود في النظام.',
        ];
    }
}
