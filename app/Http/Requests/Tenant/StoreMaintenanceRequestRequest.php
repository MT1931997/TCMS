<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreMaintenanceRequestRequest extends FormRequest
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
            'tenant_name' => ['required', 'string', 'max:255'],
            'national_id' => ['required', 'string', 'max:50'],
            'birth_date' => ['required', 'date'],
            'phone' => ['required', 'string', 'max:50'],
            'property_unit' => ['required', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'address' => ['nullable', 'string', 'max:500'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:15360', 'mimes:jpeg,jpg,png,webp,gif,mp4,mov,avi,webm,pdf'],
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
            'tenant_name.required' => 'اسم المستأجر مطلوب.',
            'national_id.required' => 'رقم الهوية / الإقامة مطلوب.',
            'birth_date.required' => 'تاريخ ميلاد المستأجر مطلوب.',
            'birth_date.date' => 'صيغة تاريخ الميلاد غير صحيحة.',
            'phone.required' => 'رقم جوال المستأجر مطلوب.',
            'property_unit.required' => 'العقار ورقم الوحدة مطلوب.',
            'title.required' => 'عنوان الطلب مطلوب.',
            'description.required' => 'وصف المشكلة مطلوب.',
            'attachments.*.max' => 'الحد الأقصى لحجم كل ملف مرفق هو 15 ميجابايت.',
            'attachments.*.mimes' => 'نوع الملف المرفق غير مدعوم، يرجى رفع صور أو فيديو أو مستند PDF.',
        ];
    }
}
