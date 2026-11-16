<?php

namespace App\Models;

use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['client_id', 'photographer_id', 'shoot_type', 'appointment_date', 'status', 'notes'])]
class Appointment extends Model
{
    /** @use HasFactory<AppointmentFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Appointment $appointment): void {
            $appointment->captureClientSnapshot();
            $appointment->capturePhotographerSnapshot();
        });

        static::updating(function (Appointment $appointment): void {
            if ($appointment->isDirty('client_id')) {
                $appointment->captureClientSnapshot();
            }

            if ($appointment->isDirty('photographer_id')) {
                $appointment->capturePhotographerSnapshot();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'appointment_date' => 'date',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function photographer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'photographer_id');
    }

    private function captureClientSnapshot(): void
    {
        $client = $this->client()->firstOrFail();
        $this->client_name = $client->name;
        $this->client_email = $client->email;
    }

    private function capturePhotographerSnapshot(): void
    {
        $photographer = $this->photographer()->firstOrFail();
        $this->photographer_name = $photographer->name;
        $this->photographer_email = $photographer->email;
    }
}
