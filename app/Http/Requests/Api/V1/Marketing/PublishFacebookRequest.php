<?php

namespace App\Http\Requests\Api\V1\Marketing;

use Illuminate\Foundation\Http\FormRequest;

class PublishFacebookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'message' => 'required_without:image_url|nullable|string',
            'link' => 'nullable|url',
            'image_url' => 'nullable|url',
            'page_id' => 'nullable|string',
            'access_token' => 'nullable|string',
        ];
    }
}
