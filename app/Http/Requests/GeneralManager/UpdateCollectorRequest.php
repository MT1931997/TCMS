<?php

namespace App\Http\Requests\GeneralManager;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCollectorRequest extends FormRequest
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
        $collector = $this->route('collector') ?? $this->route('user');
        $collectorId = $collector instanceof User ? $collector->id : $collector;

        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20', Rule::unique('users', 'phone')->ignore($collectorId)],
            'username' => ['nullable', 'string', 'max:100', Rule::unique('users', 'username')->ignore($collectorId)],
            'password' => ['nullable', 'string', 'min:4'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($collectorId)],
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
            'name.required' => 'اسم المحصل مطلوب.',
            'name.max' => 'اسم المحصل يجب ألا يتجاوز 255 حرفاً.',
            'phone.unique' => 'رقم الجوال مسجل مسبقاً لمستخدم آخر.',
            'password.min' => 'يجب أن تتكون كلمة المرور الجديدة من 4 أحرف على الأقل.',
            'username.unique' => 'اسم المستخدم مسجل مسبقاً.',
            'email.email' => 'البريد الإلكتروني غير صالح.',
            'email.unique' => 'البريد الإلكتروني مسجل مسبقاً.',
        ];
    }
}
