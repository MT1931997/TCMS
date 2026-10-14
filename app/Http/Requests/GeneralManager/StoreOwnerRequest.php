<?php

namespace App\Http\Requests\GeneralManager;

use Illuminate\Foundation\Http\FormRequest;

class StoreOwnerRequest extends FormRequest
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
            'national_id' => ['required', 'string', 'max:50', 'unique:users,national_id'],
            'phone' => ['nullable', 'string', 'max:20', 'unique:users,phone'],
            'username' => ['nullable', 'string', 'max:100', 'unique:users,username'],
            'password' => ['required', 'string', 'min:4'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
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
            'name.required' => 'اسم المالك مطلوب.',
            'name.max' => 'اسم المالك يجب ألا يتجاوز 255 حرفاً.',
            'national_id.required' => 'رقم الهوية مطلوب (يُستخدم للدخول).',
            'national_id.unique' => 'رقم الهوية مسجل مسبقاً لمالك آخر.',
            'phone.unique' => 'رقم الجوال مسجل مسبقاً لمستخدم آخر.',
            'password.required' => 'كلمة المرور مطلوبة.',
            'password.min' => 'يجب أن تتكون كلمة المرور من 4 أحرف على الأقل.',
            'username.unique' => 'اسم المستخدم مسجل مسبقاً.',
            'email.email' => 'البريد الإلكتروني غير صالح.',
            'email.unique' => 'البريد الإلكتروني مسجل مسبقاً.',
        ];
    }
}
