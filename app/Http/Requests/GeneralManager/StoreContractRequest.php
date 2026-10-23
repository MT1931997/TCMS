<?php

namespace App\Http\Requests\GeneralManager;

use App\Enums\ClaimGenerationMode;
use App\Enums\PaymentCycle;
use App\Enums\UserType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContractRequest extends FormRequest
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
        $isNewTenant = $this->boolean('is_new_tenant');

        $rules = [
            'is_new_tenant' => ['boolean'],
            'unit_number' => ['required', 'string', 'max:100'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'total_amount' => ['required', 'numeric', 'min:0'],
            'payment_cycle' => ['required', Rule::enum(PaymentCycle::class)],
            'installment_amount' => ['nullable', 'numeric', 'min:0'],
            'claim_generation_mode' => ['required', Rule::enum(ClaimGenerationMode::class)],
            'notes' => ['nullable', 'string'],
        ];

        if ($isNewTenant) {
            $rules['name'] = ['required', 'string', 'max:255'];
            $rules['phone'] = ['required', 'string', 'max:20'];
            $rules['national_id'] = ['nullable', 'string', 'max:50'];
            $rules['birth_date'] = ['nullable', 'date'];
            $rules['collector_id'] = [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(fn ($q) => $q->where('type', UserType::Collector->value)),
            ];
        } else {
            $rules['tenant_id'] = ['required', 'integer', 'exists:tenant_info,id'];
        }

        return $rules;
    }

    /**
     * Custom validation messages in Arabic.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'unit_number.required' => 'رقم الوحدة مطلوب.',
            'start_date.required' => 'تاريخ بداية العقد مطلوب.',
            'start_date.date' => 'تاريخ بداية العقد غير صالح.',
            'end_date.required' => 'تاريخ انتهاء العقد مطلوب.',
            'end_date.date' => 'تاريخ انتهاء العقد غير صالح.',
            'end_date.after_or_equal' => 'تاريخ انتهاء العقد يجب أن يكون بعد أو مساوياً لتاريخ البداية.',
            'total_amount.required' => 'قيمة العقد الإجمالية مطلوبة.',
            'total_amount.numeric' => 'قيمة العقد يجب أن تكون رقماً.',
            'payment_cycle.required' => 'دورة السداد مطلوبة.',
            'claim_generation_mode.required' => 'يرجى تحديد خيار إنشاء مطالبات الاستحقاق.',
            'tenant_id.required' => 'يرجى اختيار المستأجر من القائمة.',
            'tenant_id.exists' => 'المستأجر المحدد غير موجود.',
            'name.required' => 'اسم المستأجر الجديد مطلوب.',
            'phone.required' => 'رقم جوال المستأجر مطلوب.',
            'collector_id.required' => 'يرجى اختيار المحصل المسؤول.',
            'collector_id.exists' => 'المحصل المحدد غير موجود أو غير صالح.',
        ];
    }
}
