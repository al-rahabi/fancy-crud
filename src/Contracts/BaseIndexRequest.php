<?php

declare(strict_types=1);

namespace FancyCrud\Contracts;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates every index query parameter, so the repository never reads the raw
 * request: `per_page`, `unlimited`, `search`, `fields`, `order_by` and the value
 * of each filter the resource declares (rules from `Filter::rules()`, collected
 * through the controller's repository).
 *
 * Extend it and add rules, then point the controller's `$indexRequest` at the subclass.
 */
class BaseIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [...$this->baseRules(), ...$this->resourceRules()];
    }

    /**
     * @return array<string, mixed>
     */
    protected function baseRules(): array
    {
        return [
            'per_page' => ['nullable', 'integer', 'min:1'],
            'unlimited' => ['nullable', 'boolean'],
            'search' => ['nullable', 'string', 'max:255'],
            'fields' => ['nullable'],
            'fields.*' => ['string'],
            'order_by' => ['nullable', 'array'],
            'order_by.*.name' => ['required', 'string'],
            'order_by.*.direction' => ['nullable', 'in:asc,desc'],
        ];
    }

    /**
     * Rules the resource itself contributes (its filters).
     *
     * @return array<string, mixed>
     */
    protected function resourceRules(): array
    {
        $controller = $this->route()?->getController();

        return $controller instanceof CrudController ? $controller->indexRules() : [];
    }
}
