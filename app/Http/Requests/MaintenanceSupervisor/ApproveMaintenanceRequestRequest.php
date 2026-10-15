<?php

namespace App\Http\Requests\MaintenanceSupervisor;

use App\Enums\UserType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApproveMaintenanceRequestRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && ($user->isMaintenanceSupervisor() || $user->isGeneralManager());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
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
            'property_unit' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Get custom error messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'owner_id.required' => 'حقل المالك إلزامي لاعتماد طلب الصيانة.',
            'owner_id.exists' => 'المالك المختار غير موجود أو ليس حساب مالك صالح.',
            'property_unit.max' => 'اسم العقار / الوحدة يجب ألا يتجاوز 255 حرفاً.',
        ];
    }
}
