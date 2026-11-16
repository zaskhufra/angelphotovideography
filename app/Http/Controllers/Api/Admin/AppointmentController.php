<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreAppointmentRequest;
use App\Http\Requests\Api\UpdateAppointmentRequest;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AppointmentController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return AppointmentResource::collection(
            Appointment::query()
                ->with(['client:id,name,email', 'photographer:id,name,email'])
                ->latest()
                ->paginate(25),
        );
    }

    public function store(StoreAppointmentRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $appointment = Appointment::query()->create($validated);
        $appointment->load(['client:id,name,email', 'photographer:id,name,email']);

        return (new AppointmentResource($appointment))
            ->additional([
                'message' => 'APPOINTMENT '.$appointment->id.' HAVE BEEN CREATED',
            ])
            ->response()
            ->setStatusCode(201);
    }

    public function show(Appointment $appointment): AppointmentResource
    {
        return new AppointmentResource(
            $appointment->load(['client:id,name,email', 'photographer:id,name,email']),
        );
    }

    public function update(UpdateAppointmentRequest $request, Appointment $appointment): JsonResponse
    {
        $validated = $request->validated();
        $appointment->update($validated);
        $appointment->load(['client:id,name,email', 'photographer:id,name,email']);

        return (new AppointmentResource($appointment))
            ->additional([
                'message' => 'APPOINTMENT '.$appointment->id.' HAVE BEEN UPDATED',
            ])
            ->response();
    }

    public function destroy(Appointment $appointment): JsonResponse
    {
        $appointment->delete();

        return response()->json([
            'message' => __('Appointment have been deleted'),
        ]);
    }
}
