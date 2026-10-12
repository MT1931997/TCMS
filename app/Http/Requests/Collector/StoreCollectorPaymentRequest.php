<?php

namespace App\Http\Requests\Collector;

use App\Enums\PaymentMethod;
use App\Models\TenantInfo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCollectorPaymentRequest extends FormRequest
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
        $hasRouteTenant = $this->route('tenant') !== null;

        $rules = [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'period' => ['nullable', 'string', 'max:20'],
            'payment_date' => ['nullable', 'date'],
            'payment_method' => ['required', 'string', Rule::enum(PaymentMethod::class)],
            'notes' => ['nullable', 'string'],
        ];

        if (! $hasRouteTenant) {
            $rules['tenant_id'] = ['required', 'integer', 'exists:tenant_info,id'];
        }

        $paymentMethod = $this->input('payment_method');

        if ($paymentMethod === PaymentMethod::Cash->value) {
            $rules['receipt_number'] = ['required', 'string', 'max:100'];
            $rules['receipt_attachment'] = ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'];
        } else {
            $rules['receipt_number'] = ['nullable', 'string', 'max:100', 'required_without:receipt_attachment'];
            $rules['receipt_attachment'] = ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120', 'required_without:receipt_number'];
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
            'tenant_id.required' => 'يجب اختيار المستأجر أو العقار.',
            'tenant_id.exists' => 'المستأجر المحدد غير موجود.',
            'amount.required' => 'مبلغ السداد مطلوب.',
            'amount.numeric' => 'مبلغ السداد يجب أن يكون رقماً صالحاً.',
            'amount.min' => 'مبلغ السداد يجب أن يكون أكبر من صفر.',
            'payment_method.required' => 'طريقة السداد مطلوبة.',
            'payment_method.in' => 'طريقة السداد المحددة غير صالحة. الخيارات المتاحة: كاش بالمكتب، سداد بالمنصة، تحويل بنكي.',
            'receipt_number.required' => 'رقم الإيصال إلزامي عند اختيار كاش بالمكتب.',
            'receipt_number.required_without' => 'رقم الإيصال مطلوب في حال عدم إرفاق صورة أو ملف للإيصال.',
            'receipt_attachment.required_without' => 'صورة أو ملف الإيصال مطلوب في حال عدم إدخال رقم الإيصال.',
            'receipt_attachment.file' => 'يجب أن يكون المرفق ملفاً صالحاً.',
            'receipt_attachment.mimes' => 'صيغة الملف غير مدعومة. الصيغ المسموحة هي: JPG, PNG, PDF.',
            'receipt_attachment.max' => 'حجم الملف يجب ألا يتجاوز 5 ميجابايت.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $tenantId = $this->input('tenant_id');

            if ($tenantId && ! $this->route('tenant')) {
                $tenant = TenantInfo::find($tenantId);
                $user = $this->user();

                if ($tenant && $user && $tenant->collector_id !== $user->id && ! $user->isGeneralManager()) {
                    $v->errors()->add('tenant_id', 'لا يمكنك تسجيل سداد لمستأجر غير مسند إليك.');
                }
            }
        });
    }
}
