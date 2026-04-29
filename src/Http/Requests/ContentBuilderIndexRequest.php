<?php

namespace Reno\ContentBuilder\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ContentBuilderIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'resource_id' => ['required', 'integer', 'min:1'],
            'resource_field_id' => ['required', 'integer', 'min:1'],
        ];
    }
}
