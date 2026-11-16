<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    protected function prepareForValidation(): void
    {
        $records = $this->input('data');

        if (! is_array($records)) {
            return;
        }

        $record = array_is_list($records) ? ($records[0] ?? null) : $records;

        if (! is_array($record)) {
            return;
        }

        $this->merge([
            'client_id' => $this->input('client_id', $record['client_id'] ?? data_get($record, 'client.id')),
            'photographer_id' => $this->input('photographer_id', $record['photographer_id'] ?? data_get($record, 'photographer.id')),
            'shoot_type' => $this->input('shoot_type', $record['shoot_type'] ?? null),
            'appointment_date' => $this->input('appointment_date', $record['appointment_date'] ?? null),
        ]);

        foreach (['status', 'notes'] as $field) {
            if (! $this->exists($field) && array_key_exists($field, $record)) {
                $this->merge([$field => $record[$field]]);
            }
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'client_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(fn (Builder $query) => $query->where('role', 'client')),
            ],
            'photographer_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(fn (Builder $query) => $query->where('role', 'photographer')),
            ],
            'shoot_type' => ['required', 'string', 'max:32'],
            'appointment_date' => ['required', 'date_format:Y-m-d'],
            'status' => ['sometimes', 'required', 'string', Rule::in(['pending', 'confirmed', 'cancelled', 'completed'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
