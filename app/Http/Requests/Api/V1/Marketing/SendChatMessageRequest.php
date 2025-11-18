<?php

namespace App\Http\Requests\Api\V1\Marketing;

use Illuminate\Foundation\Http\FormRequest;

class SendChatMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'platform' => 'required|in:whatsapp,messenger,instagram',
            'to' => 'required|string',
            'message' => 'required|string',
            'phone_number_id' => 'nullable|string',
            'recipient_id' => 'nullable|string',
            'access_token' => 'nullable|string',
        ];
    }
}
