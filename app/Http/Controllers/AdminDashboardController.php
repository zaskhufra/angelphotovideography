<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.dashboard', [
            'totalUsers' => User::count(),
            'clientCount' => User::where('role', 'client')->count(),
            'photographerCount' => User::where('role', 'photographer')->count(),
            'adminCount' => User::where('role', 'admin')->count(),
            'recentUsers' => User::query()
                ->latest()
                ->limit(8)
                ->get(['name', 'email', 'role', 'created_at']),
            'weather' => $this->weather(),
        ]);
    }

    /**
     * @return array{location: string, temperature: float, condition: string, humidity: int, wind_speed: float}|null
     */
    private function weather(): ?array
    {
        $apiKey = config('services.openweather.api_key');
        $location = config('services.openweather.location');

        if (! is_string($apiKey) || $apiKey === '' || ! is_string($location) || trim($location) === '') {
            Log::warning('Weather dashboard is missing API configuration.');

            return null;
        }

        try {
            $response = Http::acceptJson()
                ->connectTimeout(3)
                ->timeout(5)
                ->get(config('services.openweather.endpoint'), [
                    'q' => $location,
                    'appid' => $apiKey,
                    'units' => config('services.openweather.units'),
                ]);
        } catch (ConnectionException) {
            Log::warning('Weather API connection failed or timed out.');

            return null;
        }

        if (! $response->successful()) {
            Log::warning('Weather API returned an unsuccessful response.', [
                'status' => $response->status(),
            ]);

            return null;
        }

        $payload = $response->json();
        $weather = is_array($payload) ? ($payload['weather'][0] ?? null) : null;
        $main = is_array($payload) ? ($payload['main'] ?? null) : null;
        $wind = is_array($payload) ? ($payload['wind'] ?? null) : null;

        if (! is_array($weather) || ! is_array($main) || ! is_array($wind)) {
            Log::warning('Weather API returned an invalid weather payload.');

            return null;
        }

        $name = $payload['name'] ?? null;
        $temperature = $main['temp'] ?? null;
        $condition = $weather['description'] ?? null;
        $humidity = $main['humidity'] ?? null;
        $windSpeed = $wind['speed'] ?? null;

        if (! is_string($name) || trim($name) === ''
            || ! is_numeric($temperature)
            || ! is_string($condition) || trim($condition) === ''
            || ! is_numeric($humidity) || $humidity < 0 || $humidity > 100
            || ! is_numeric($windSpeed) || $windSpeed < 0) {
            Log::warning('Weather API returned incomplete or invalid weather values.');

            return null;
        }

        return [
            'location' => trim($name),
            'temperature' => (float) $temperature,
            'condition' => trim($condition),
            'humidity' => (int) $humidity,
            'wind_speed' => (float) $windSpeed,
        ];
    }
}
