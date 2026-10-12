<?php

namespace App\Http\Requests\Supervisor;

use Illuminate\Foundation\Http\FormRequest;

class StorePromiseRequest extends FormRequest
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
            'promise_date' => ['required', 'date'],
            'note' => ['required', 'string', 'max:1000'],
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
            'promise_date.required' => 'تاريخ الموعد / الوعد مطلوب.',
            'promise_date.date' => 'صيغة تاريخ الموعد غير صالحة.',
            'note.required' => 'ملاحظة الوعد مطلوبة.',
        ];
    }
}
