<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendMessageRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'message' => 'required|string|max:2000',
            'conversation_id' => 'nullable|string|uuid',
            'model' => 'nullable|string|in:gpt-3.5-turbo,gpt-4,gpt-4-turbo-preview',
            'temperature' => 'nullable|numeric|between:0,2',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'message.required' => 'A message is required to send to OpenAI.',
            'message.max' => 'Message cannot exceed 2000 characters.',
            'conversation_id.uuid' => 'Conversation ID must be a valid UUID.',
            'model.in' => 'Invalid model specified.',
            'temperature.between' => 'Temperature must be between 0 and 2.',
        ];
    }
}
