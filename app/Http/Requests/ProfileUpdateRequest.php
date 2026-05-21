<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        $rules = [
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
        ];

        if (!$this->user()->isReader()) {
            $rules['name'] = ['required', 'string', 'max:255'];
        } else {
            $rules['phone']              = ['nullable', 'string', 'max:30'];
            $rules['sms_notifications']  = ['nullable', 'boolean'];
        }

        return $rules;
    }
}
