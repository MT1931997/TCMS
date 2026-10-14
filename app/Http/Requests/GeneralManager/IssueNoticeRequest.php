<?php

namespace App\Http\Requests\GeneralManager;

use Illuminate\Foundation\Http\FormRequest;

class IssueNoticeRequest extends FormRequest
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
            'notice_content' => ['required', 'string'],
            'notice_file' => ['nullable', 'file', 'max:10240'],
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
            'notice_content.required' => 'يرجى كتابة نص الإشعار الذي سيُسلَّم للمستأجر.',
            'notice_file.max' => 'حجم ملف الإشعار لا يجب أن يتجاوز 10 ميجابايت.',
        ];
    }
}
