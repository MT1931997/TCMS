<?php

namespace App\Http\Requests\MaintenanceWork;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreMaintenanceWorkRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'owner_id' => ['required', 'integer', 'exists:users,id'],
            'property_unit' => ['required', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:15360', 'mimes:jpeg,jpg,png,webp,gif,mp4,mov,avi,webm'],
            'maintenance_request_id' => ['nullable', 'integer', 'exists:maintenance_requests,id'],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'owner_id' => 'المالك',
            'property_unit' => 'العقار / الوحدة',
            'title' => 'عنوان العمل',
            'description' => 'الوصف',
            'attachments' => 'المرفقات',
            'attachments.*' => 'ملف المرفق',
            'maintenance_request_id' => 'طلب صيانة المستأجر المرتبط',
        ];
    }
}
