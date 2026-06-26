<?php

declare(strict_types=1);

namespace App\Http\Requests\SepayConfig;

use Illuminate\Foundation\Http\FormRequest;

class SaveSepayConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sepay_api_token' => ['nullable', 'string', 'max:255'],
            'sepay_webhook_secret' => ['nullable', 'string', 'max:255'],
            'sepay_auto_confirm' => ['nullable', 'boolean'],
            'sepay_match_pattern' => ['nullable', 'string', 'max:15'],
        ];
    }

    public function mappedData(): array
    {
        $data = [];
        if ($this->has('sepay_api_token')) $data['sepay_api_token'] = $this->input('sepay_api_token');
        if ($this->has('sepay_webhook_secret')) $data['sepay_webhook_secret'] = $this->input('sepay_webhook_secret');
        if ($this->has('sepay_auto_confirm')) $data['sepay_auto_confirm'] = $this->boolean('sepay_auto_confirm') ? 'true' : 'false';
        if ($this->has('sepay_match_pattern')) $data['sepay_match_pattern'] = strtoupper($this->input('sepay_match_pattern'));
        return $data;
    }
}