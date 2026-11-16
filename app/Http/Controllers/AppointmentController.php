<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAppointmentRequest;
use App\Http\Requests\UpdateAppointmentStatusRequest;
use App\Models\Appointment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AppointmentController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $appointments = Appointment::query()->with(['client', 'photographer']);

        if ($user->role === 'client') {
            $appointments->where('client_id', $user->id);
        } elseif ($user->role === 'photographer') {
            $appointments->where('photographer_id', $user->id);
        } elseif ($user->role !== 'admin') {
            abort(403);
        }

        return view('appointments.index', [
            'appointments' => $appointments
                ->orderBy('appointment_date')
                ->latest('id')
                ->get(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('book-appointments');

        return view('appointments.create', [
            'photographers' => User::query()
                ->where('role', 'photographer')
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function store(StoreAppointmentRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $dateAlreadyBooked = Appointment::query()
            ->where('photographer_id', $validated['photographer_id'])
            ->whereDate('appointment_date', $validated['appointment_date'])
            ->where('status', '!=', 'cancelled')
            ->exists();

        if ($dateAlreadyBooked) {
            throw ValidationException::withMessages([
                'appointment_date' => __('This photographer is already booked on that date.'),
            ]);
        }

        Appointment::create([
            'client_id' => $request->user()->id,
            'photographer_id' => $validated['photographer_id'],
            'shoot_type' => $validated['shoot_type'],
            'appointment_date' => $validated['appointment_date'],
            'status' => 'pending',
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()
            ->route('appointments.index')
            ->with('status', __('Appointment request sent. No payment is required now.'));
    }

    public function cancel(Request $request, Appointment $appointment): RedirectResponse
    {
        Gate::authorize('cancel-appointment', $appointment);

        if (! in_array($appointment->status, ['pending', 'confirmed'], true)) {
            throw ValidationException::withMessages([
                'appointment' => __('This appointment can no longer be cancelled.'),
            ]);
        }

        $appointment->update(['status' => 'cancelled']);

        return redirect()
            ->route('appointments.index')
            ->with('status', __('Appointment cancelled.'));
    }

    public function updateStatus(UpdateAppointmentStatusRequest $request, Appointment $appointment): RedirectResponse
    {
        $newStatus = $request->validated('status');
        $allowedTransitions = match ($appointment->status) {
            'pending' => ['confirmed', 'cancelled'],
            'confirmed' => ['cancelled', 'completed'],
            default => [],
        };

        if (! in_array($newStatus, $allowedTransitions, true)) {
            throw ValidationException::withMessages([
                'status' => __('This status change is not allowed.'),
            ]);
        }

        $appointment->update(['status' => $newStatus]);

        return redirect()
            ->route('appointments.index')
            ->with('status', __('Appointment status updated.'));
    }
}
