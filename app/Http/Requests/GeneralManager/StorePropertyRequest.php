<?php

namespace App\Http\Requests\GeneralManager;

use App\Enums\UserType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePropertyRequest extends FormRequest
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
            'owner_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(function ($query) {
                    $query->where('type', UserType::Owner->value);
                }),
            ],
            'name' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
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
            'owner_id.required' => 'يرجى اختيار المالك.',
            'owner_id.integer' => 'معرف المالك يجب أن يكون رقماً صحيحاً.',
            'owner_id.exists' => 'المالك المحدد غير موجود أو غير صالح.',
            'name.required' => 'اسم العقار مطلوب.',
            'name.string' => 'اسم العقار يجب أن يكون نصاً.',
            'name.max' => 'اسم العقار يجب ألا يتجاوز 255 حرفاً.',
            'location.string' => 'الموقع / الحي يجب أن يكون نصاً.',
            'location.max' => 'الموقع / الحي يجب ألا يتجاوز 255 حرفاً.',
        ];
    }
}
