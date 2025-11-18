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
            // Accept uploaded image or video files from frontend microservice
            'image' => 'nullable|file|image|max:10240', // 10MB max
            'video' => 'nullable|file|mimetypes:video/mp4,video/quicktime|max:512000', // 500MB max
            'page_id' => 'nullable|string',
            'access_token' => 'nullable|string',
        ];
    }
}
