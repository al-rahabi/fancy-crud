<?php

declare(strict_types=1);

namespace FancyCrud\Contracts;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validates a bulk payload: the `action`, exactly one selection (`ids`, `filters`
 * or `all`) and the `extra` data using the chosen action's own rules. Filter
 * values are validated with the resource's `Filter::rules()`.
 */
class BaseBulkRequest extends FormRequest
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
            'action' => ['required', 'string'],
            'ids' => ['nullable', 'array', 'min:1'],
            'ids.*' => ['required'],
            'filters' => ['nullable', 'array'],
            'all' => ['nullable', 'boolean'],
            'extra' => ['nullable', 'array'],
        ];
    }

    /**
     * Action name check, filter-value rules and the chosen action's `extra.*` rules.
     *
     * @return array<string, mixed>
     */
    protected function resourceRules(): array
    {
        $controller = $this->route()?->getController();

        return $controller instanceof CrudController ? $controller->bulkRules($this->input('action')) : [];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $this->validateSelection($validator, $validator->validated());
        });
    }

    /**
     * Exactly one of ids / filters / all; an empty or unknown-only `filters`
     * object is rejected rather than read as "everything".
     *
     * @param  array<string, mixed>  $data
     */
    protected function validateSelection(Validator $validator, array $data): void
    {
        $filters = array_filter($data['filters'] ?? [], fn (mixed $value): bool => ! is_null($value));
        $chosen = array_filter([
            'ids' => ! empty($data['ids']),
            'filters' => $filters !== [],
            'all' => filter_var($data['all'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ]);

        if (! empty($this->input('filters')) && $filters === [] && $chosen === []) {
            $validator->errors()->add('filters', __('fancy-crud::messages.bulk_filters_invalid'));
        } elseif (count($chosen) !== 1) {
            $validator->errors()->add('selection', __('fancy-crud::messages.bulk_selection_invalid'));
        }
    }
}
