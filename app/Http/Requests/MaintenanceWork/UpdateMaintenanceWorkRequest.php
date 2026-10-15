<?php

namespace App\Http\Requests\MaintenanceWork;

use App\Enums\MaintenanceWorkStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMaintenanceWorkRequest extends FormRequest
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
            'owner_id' => ['sometimes', 'integer', 'exists:users,id'],
            'property_unit' => ['sometimes', 'string', 'max:255'],
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string'],
            'status' => ['nullable', 'string', Rule::in(MaintenanceWorkStatus::values())],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:15360', 'mimes:jpeg,jpg,png,webp,gif,mp4,mov,avi,webm'],
            'deleted_attachments' => ['nullable', 'array'],
            'deleted_attachments.*' => ['string'],
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
            'status' => 'الحالة',
            'attachments' => 'المرفقات',
            'maintenance_request_id' => 'طلب صيانة المستأجر المرتبط',
        ];
    }
}
