<?php

namespace App\Http\Requests\GeneralManager;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOwnerRequest extends FormRequest
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
        $owner = $this->route('owner') ?? $this->route('user');
        $ownerId = $owner instanceof User ? $owner->id : $owner;

        return [
            'name' => ['required', 'string', 'max:255'],
            'national_id' => ['required', 'string', 'max:50', Rule::unique('users', 'national_id')->ignore($ownerId)],
            'phone' => ['nullable', 'string', 'max:20', Rule::unique('users', 'phone')->ignore($ownerId)],
            'username' => ['nullable', 'string', 'max:100', Rule::unique('users', 'username')->ignore($ownerId)],
            'password' => ['nullable', 'string', 'min:4'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($ownerId)],
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
            'national_id.required' => 'رقم الهوية مطلوب.',
            'national_id.unique' => 'رقم الهوية مسجل مسبقاً لمالك آخر.',
            'phone.unique' => 'رقم الجوال مسجل مسبقاً لمستخدم آخر.',
            'password.min' => 'يجب أن تتكون كلمة المرور الجديدة من 4 أحرف على الأقل.',
            'username.unique' => 'اسم المستخدم مسجل مسبقاً.',
            'email.email' => 'البريد الإلكتروني غير صالح.',
            'email.unique' => 'البريد الإلكتروني مسجل مسبقاً.',
        ];
    }
}
