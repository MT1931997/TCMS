<?php

namespace App\Http\Requests\Collector;

use Illuminate\Foundation\Http\FormRequest;

class StoreNoticeRequest extends FormRequest
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
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'tenant_id' => ['required', 'integer', 'exists:tenant_info,id'],
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }

    /**
     * Custom validation error messages in Arabic.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tenant_id.required' => 'يرجى اختيار المستأجر.',
            'tenant_id.exists' => 'المستأجر المحدد غير موجود في النظام.',
            'reason.required' => 'سبب / تفاصيل الطلب مطلوبة.',
            'reason.max' => 'تفاصيل الطلب يجب ألا تتجاوز 2000 حرف.',
        ];
    }
}
