<?php

namespace App\Http\Requests\Truv;

use Illuminate\Foundation\Http\FormRequest;

class ExchangePublicTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'public_token' => ['required', 'string', 'max:255'],
        ];
    }
}
