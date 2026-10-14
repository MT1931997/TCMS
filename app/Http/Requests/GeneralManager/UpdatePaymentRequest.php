<?php

namespace App\Http\Requests\GeneralManager;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePaymentRequest extends FormRequest
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
            'amount' => ['sometimes', 'numeric', 'min:0.01'],
            'period' => ['sometimes', 'string', 'max:20'],
            'payment_date' => ['nullable', 'date'],
            'payment_method' => ['sometimes', 'string', Rule::enum(PaymentMethod::class)],
            'receipt_number' => ['nullable', 'string', 'max:100'],
            'status' => ['sometimes', 'string', Rule::enum(PaymentStatus::class)],
            'notes' => ['nullable', 'string'],
            'rejection_reason' => ['nullable', 'string', 'max:1000'],
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
            'amount.numeric' => 'مبلغ السداد يجب أن يكون رقماً صالحاً.',
            'amount.min' => 'مبلغ السداد يجب أن يكون أكبر من صفر.',
            'payment_method.in' => 'طريقة السداد غير صالحة. الخيارات المتاحة: كاش بالمكتب، سداد بالمنصة، تحويل بنكي.',
            'status.in' => 'حالة السداد المحددة غير صالحة.',
        ];
    }
}
