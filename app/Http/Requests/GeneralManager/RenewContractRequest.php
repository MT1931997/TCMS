<?php

namespace App\Http\Requests\GeneralManager;

use App\Enums\ClaimGenerationMode;
use App\Enums\PaymentCycle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RenewContractRequest extends FormRequest
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
            'unit_number' => ['nullable', 'string', 'max:100'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'total_amount' => ['required', 'numeric', 'min:0'],
            'payment_cycle' => ['required', Rule::enum(PaymentCycle::class)],
            'installment_amount' => ['nullable', 'numeric', 'min:0'],
            'claim_generation_mode' => ['required', Rule::enum(ClaimGenerationMode::class)],
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
            'start_date.required' => 'تاريخ بداية العقد الجديد مطلوب.',
            'end_date.required' => 'تاريخ انتهاء العقد الجديد مطلوب.',
            'end_date.after_or_equal' => 'تاريخ انتهاء العقد يجب أن يكون بعد أو مساوياً لتاريخ البداية.',
            'total_amount.required' => 'قيمة العقد الإجمالية مطلوبة.',
            'payment_cycle.required' => 'دورة السداد مطلوبة.',
            'claim_generation_mode.required' => 'يرجى تحديد خيار إنشاء مطالبات الاستحقاق.',
        ];
    }
}
