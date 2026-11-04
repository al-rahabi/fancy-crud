<?php

namespace FancyCrud\Contracts;

use Illuminate\Foundation\Http\FormRequest;

class BaseRequest extends FormRequest
{
    public function rules(): array
    {
        return match ($this->method()) {
            'POST' => $this->storeRules(),
            'PUT' => $this->updateRules(),
            'PATCH' => $this->patchRules(),
            default => $this->commonRules()
        };
    }

    public function commonRules(): array
    {
        return [];
    }

    public function storeRules(): array
    {
        return $this->commonRules();
    }

    public function updateRules(): array
    {
        return $this->commonRules();
    }

    public function patchRules(): array
    {
        $patchRules = [];
        foreach ($this->updateRules() as $key => $rule) {
            if (is_array($rule) && in_array('required', $rule)) {
                $rule = array_diff($rule, ['required']);
            } elseif (is_string($rule) && str_contains($rule, 'required')) {
                $rule = str_replace('required', 'nullable', $rule);
            }
            $patchRules[$key] = $rule;
        }

        return $patchRules;
    }
}
