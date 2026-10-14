<?php

namespace App\Http\Requests\GeneralManager;

use App\Enums\UserType;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSupervisorRequest extends FormRequest
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
        $supervisorValues = array_map(fn (UserType $t) => $t->value, UserType::supervisorTypes());
        $user = $this->route('supervisor') ?? $this->route('user');
        $userId = $user instanceof User ? $user->id : $user;

        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in($supervisorValues)],
            'phone' => ['required', 'string', 'max:20', Rule::unique('users', 'phone')->ignore($userId)],
            'username' => ['nullable', 'string', 'max:100', Rule::unique('users', 'username')->ignore($userId)],
            'password' => ['nullable', 'string', 'min:4'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
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
            'name.required' => 'اسم المشرف مطلوب.',
            'name.max' => 'اسم المشرف يجب ألا يتجاوز 255 حرفاً.',
            'type.required' => 'يرجى اختيار نوع المشرف.',
            'type.in' => 'نوع المشرف المحدد غير صالح.',
            'phone.required' => 'رقم الجوال مطلوب.',
            'phone.unique' => 'رقم الجوال مسجل مسبقاً لمستخدم آخر.',
            'phone.max' => 'رقم الجوال يجب ألا يتجاوز 20 حرفاً.',
            'username.unique' => 'اسم المستخدم مسجل مسبقاً.',
            'password.min' => 'يجب أن تتكون كلمة المرور الجديدة من 4 أحرف على الأقل.',
            'email.email' => 'البريد الإلكتروني المدخل غير صالح.',
            'email.unique' => 'البريد الإلكتروني مسجل مسبقاً.',
        ];
    }
}
