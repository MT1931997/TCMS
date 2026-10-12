<?php

namespace App\Http\Requests\Collector;

use Illuminate\Foundation\Http\FormRequest;

class SubmitDeliveryProofRequest extends FormRequest
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
            'delivery_proof' => ['required_without:image', 'nullable', 'file', 'image', 'max:10240'],
            'image' => ['required_without:delivery_proof', 'nullable', 'file', 'image', 'max:10240'],
            'delivery_date' => ['nullable', 'date'],
            'delivery_notes' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:1000'],
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
            'delivery_proof.required_without' => 'يرجى إرفاق صورة إثبات التسليم.',
            'image.required_without' => 'يرجى إرفاق صورة إثبات التسليم.',
            'delivery_proof.image' => 'يجب أن يكون الملف المرفق صورة صالحة.',
            'image.image' => 'يجب أن يكون الملف المرفق صورة صالحة.',
            'delivery_proof.max' => 'حجم الصورة لا يجب أن يتجاوز 10 ميجابايت.',
            'image.max' => 'حجم الصورة لا يجب أن يتجاوز 10 ميجابايت.',
            'delivery_date.date' => 'تاريخ التسليم غير صحيح.',
        ];
    }
}
