<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TrackMaintenanceRequestRequest extends FormRequest
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
            'national_id' => ['required_without_all:phone,ticket_number', 'nullable', 'string', 'max:50'],
            'phone' => ['required_without_all:national_id,ticket_number', 'nullable', 'string', 'max:50'],
            'ticket_number' => ['nullable', 'string', 'max:50'],
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
            'national_id.required_without_all' => 'يرجى إدخال رقم الهوية أو رقم الجوال أو رقم الطلب لتتبع حالة طلباتك.',
            'phone.required_without_all' => 'يرجى إدخال رقم الهوية أو رقم الجوال أو رقم الطلب لتتبع حالة طلباتك.',
        ];
    }
}
