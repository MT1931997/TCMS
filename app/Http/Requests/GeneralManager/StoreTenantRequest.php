<?php

namespace App\Http\Requests\GeneralManager;

use Illuminate\Foundation\Http\FormRequest;

class StoreTenantRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:255'],
            'tenant_number' => ['required', 'string', 'max:100', 'unique:tenant_info,tenant_number'],
            'contract_number' => ['nullable', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:20'],
            'property_unit' => ['required', 'string', 'max:255'],
            'due_date' => ['required', 'date'],
            'monthly_target' => ['required', 'numeric', 'min:0'],
            'collector_id' => ['required', 'integer', 'exists:users,id'],
            'owner_id' => ['nullable', 'integer', 'exists:users,id'],
            'national_id' => ['nullable', 'string', 'max:50'],
            'birth_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
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
            'tenant_number.unique' => 'رقم المستأجر مسجل مسبقاً، يرجى استخدام رقم آخر.',
            'phone.required' => 'رقم الجوال مطلوب.',
            'property_unit.required' => 'العقار والوحدة مطلوب.',
            'due_date.required' => 'تاريخ الاستحقاق مطلوب.',
            'monthly_target.required' => 'الهدف الشهري مطلوب.',
            'monthly_target.numeric' => 'الهدف الشهري يجب أن يكون رقماً صحيحاً.',
            'collector_id.required' => 'يرجى تحديد المحصل المسؤول.',
            'collector_id.exists' => 'المحصل المحدد غير موجود في النظام.',
            'owner_id.exists' => 'المالك المحدد غير موجود في النظام.',
        ];
    }
}
