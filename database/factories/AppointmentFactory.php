<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => User::factory(),
            'photographer_id' => User::factory()->state(['role' => 'photographer']),
            'shoot_type' => fake()->randomElement(['photoshoot', 'videography']),
            'appointment_date' => now()->addDay()->toDateString(),
            'status' => 'pending',
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
