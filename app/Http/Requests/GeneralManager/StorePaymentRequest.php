<?php

namespace App\Http\Requests\GeneralManager;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
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
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_date' => ['required', 'date'],
            'payment_method' => ['required', 'string', Rule::enum(PaymentMethod::class)],
            'receipt_number' => ['nullable', 'string', 'max:100'],
            'period' => ['nullable', 'string', 'max:20'],
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
            'amount.required' => 'مبلغ السداد مطلوب.',
            'amount.numeric' => 'مبلغ السداد يجب أن يكون رقماً صالحاً.',
            'payment_date.required' => 'تاريخ السداد مطلوب.',
            'payment_method.required' => 'طريقة السداد مطلوبة.',
        ];
    }
}
