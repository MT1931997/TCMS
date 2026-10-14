<?php

namespace App\Http\Requests\Auth;

use App\Enums\UserType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LoginRequest extends FormRequest
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
        $validTypes = array_merge(
            array_map(fn (UserType $t): string => $t->value, UserType::cases()),
            ['supervisor', 'supervisors']
        );

        return [
            'login' => ['required_without:user_id', 'nullable', 'string'],
            'user_id' => ['required_without:login', 'nullable', 'integer', 'exists:users,id'],
            'password' => ['required', 'string'],
            'type' => ['nullable', 'string', Rule::in($validTypes)],
            'device_name' => ['nullable', 'string', 'max:255'],
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
            'login.required_without' => 'يرجى إدخال اسم المستخدم، البريد، أو رقم الجوال.',
            'user_id.required_without' => 'يرجى اختيار الاسم أو إدخال بيانات الدخول.',
            'user_id.exists' => 'المستخدم المحدد غير موجود.',
            'password.required' => 'يرجى إدخال كلمة المرور.',
            'type.enum' => 'نوع المستخدم المحدد غير صالح.',
        ];
    }
}
