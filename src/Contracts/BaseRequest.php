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

    /**
     * `updateRules()` with the bare `required` rule removed (array rules) or
     * turned into `nullable` (string rules). Only the exact `required` token is
     * touched, so `required_if:…` and rule objects / closures pass through as-is.
     */
    public function patchRules(): array
    {
        $patchRules = [];
        foreach ($this->updateRules() as $key => $rule) {
            if (is_array($rule)) {
                $rule = array_values(array_filter($rule, fn (mixed $item): bool => $item !== 'required'));
            } elseif (is_string($rule)) {
                $rule = implode('|', array_map(
                    fn (string $item): string => $item === 'required' ? 'nullable' : $item,
                    explode('|', $rule)
                ));
            }
            $patchRules[$key] = $rule;
        }

        return $patchRules;
    }
}
