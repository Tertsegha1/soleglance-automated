<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'min:3', 'max:30', 'regex:/^[A-Za-z0-9_]+$/', Rule::unique(User::class)->ignore($this->user()->id)],
            'bio' => ['nullable', 'string', 'max:500'],
            'country' => ['nullable', 'string', 'size:2', 'alpha'],
            'city' => ['nullable', 'string', 'max:100'],
            'date_of_birth' => ['nullable', 'date', 'before:today', 'after:1900-01-01'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=4000,max_height=4000'],
            'remove_avatar' => ['nullable', 'boolean'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
        ];
    }
}
