<?php

namespace App\Http\Requests\Api\V1\Marketing;

use Illuminate\Foundation\Http\FormRequest;

class PublishInstagramRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'image_url' => 'required_without:image|url',
            'image' => 'nullable|file|image|max:10240', // 10MB
            'caption' => 'nullable|string',
            'ig_user_id' => 'nullable|string',
            'access_token' => 'nullable|string',
        ];
    }
}
