<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SearchActionRequest extends FormRequest
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
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        $extensions = [];
        $mimeTypes = (array)config('mime-type');
        foreach ($mimeTypes as $category => $subArray) {
            $extensions = array_merge($extensions, array_keys((array)$subArray));
        }
        return [
            'fileStatus' => [
                'nullable',
                'string',
                Rule::in(['all', 'transcribed', 'not_transcribed']),
            ],
            'fileType' => [
                'nullable',
                'string',
                Rule::in(['all', 'image', 'voice', 'video', 'book', 'office'])
            ],
            'fileExtension' => ['nullable', 'array'],
            'fileExtension.*' => [
                'string',
                Rule::in(['all', ...$extensions])
            ],
            'searchableText' => ['nullable', 'string'],
            'departments' => ['nullable', 'array'],
            'departments.*' => ['nullable', 'integer'],
            'fileName' => ['nullable', 'string'],
            'adminType' => ['nullable', Rule::in([
                'all', 'owner', 'other', 'identifier'
            ])],
            'adminIdentifier' => ['nullable', 'string'],
            'fromDate' => ['nullable', 'regex:/[0-9]{4}-[0-9]{2}-[0-9]{2}/'],
            'toDate' => ['nullable', 'regex:/[0-9]{4}-[0-9]{2}-[0-9]{2}/']
        ];
    }
}
