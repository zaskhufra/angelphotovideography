<?php

namespace App\Http\Requests\Api;

use App\Models\Appointment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UpdateAppointmentRequest extends FormRequest
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

        if (array_is_list($records)) {
            $appointment = $this->route('appointment');
            $appointmentId = $appointment instanceof Appointment
                ? (string) $appointment->getKey()
                : (string) $appointment;
            $record = collect($records)->first(
                fn (mixed $item): bool => is_array($item)
                    && isset($item['id'])
                    && (string) $item['id'] === $appointmentId,
            );

            if ($record === null && count($records) === 1 && is_array($records[0])) {
                $record = $records[0];
            }

            if (! is_array($record)) {
                throw ValidationException::withMessages([
                    'data' => [__('The data list must contain the appointment ID from the URL.')],
                ]);
            }
        } else {
            $record = $records;
        }

        $normalized = [
            'client_id' => $record['client_id'] ?? data_get($record, 'client.id'),
            'photographer_id' => $record['photographer_id'] ?? data_get($record, 'photographer.id'),
            'shoot_type' => $record['shoot_type'] ?? null,
            'appointment_date' => $record['appointment_date'] ?? null,
        ];

        foreach ($normalized as $field => $value) {
            if (! $this->exists($field) && $value !== null) {
                $this->merge([$field => $value]);
            }
        }

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
        $required = $this->isMethod('put') ? 'required' : 'sometimes|required';

        return [
            'client_id' => [
                $required,
                'integer',
                Rule::exists('users', 'id')->where(fn (Builder $query) => $query->where('role', 'client')),
            ],
            'photographer_id' => [
                $required,
                'integer',
                Rule::exists('users', 'id')->where(fn (Builder $query) => $query->where('role', 'photographer')),
            ],
            'shoot_type' => [$required, 'string', 'max:32'],
            'appointment_date' => [$required, 'date_format:Y-m-d'],
            'status' => [$required, 'string', Rule::in(['pending', 'confirmed', 'cancelled', 'completed'])],
            'notes' => [$this->isMethod('put') ? 'present' : 'sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
