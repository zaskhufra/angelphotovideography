<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-medium uppercase tracking-wide text-gray-500">{{ __('Administration') }}</p>
            <h2 class="mt-1 text-xl font-semibold leading-tight text-gray-800">{{ __('Admin Dashboard') }}</h2>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-8 px-4 sm:px-6 lg:px-8">
            <section aria-label="{{ __('Account totals') }}" class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <div class="border-l-4 border-gray-800 bg-white px-5 py-4 shadow-sm">
                    <p class="text-sm text-gray-600">{{ __('All accounts') }}</p>
                    <p class="mt-2 text-3xl font-semibold text-gray-900">{{ $totalUsers }}</p>
                </div>
                <div class="border-l-4 border-emerald-600 bg-white px-5 py-4 shadow-sm">
                    <p class="text-sm text-gray-600">{{ __('Clients') }}</p>
                    <p class="mt-2 text-3xl font-semibold text-gray-900">{{ $clientCount }}</p>
                </div>
                <div class="border-l-4 border-amber-500 bg-white px-5 py-4 shadow-sm">
                    <p class="text-sm text-gray-600">{{ __('Photographers') }}</p>
                    <p class="mt-2 text-3xl font-semibold text-gray-900">{{ $photographerCount }}</p>
                </div>
                <div class="border-l-4 border-rose-600 bg-white px-5 py-4 shadow-sm">
                    <p class="text-sm text-gray-600">{{ __('Administrators') }}</p>
                    <p class="mt-2 text-3xl font-semibold text-gray-900">{{ $adminCount }}</p>
                </div>
            </section>

            <section aria-labelledby="weather-heading" class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <div class="border-b border-gray-200 px-6 py-4">
                    <h3 id="weather-heading" class="text-base font-semibold text-gray-900">{{ __('Current weather') }}</h3>
                </div>

                @if ($weather)
                    <div class="space-y-6 px-6 py-5">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                            <div>
                                <p class="text-sm text-gray-500">{{ __('Location') }}</p>
                                <p class="text-xl font-semibold text-gray-900">{{ $weather['location'] }}</p>
                            </div>
                            <p class="text-4xl font-semibold text-sky-700">
                                {{ number_format($weather['temperature'], 1) }}°C
                            </p>
                        </div>

                        <dl class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div class="rounded-md bg-gray-50 px-4 py-3">
                                <dt class="text-sm text-gray-500">{{ __('Condition') }}</dt>
                                <dd class="mt-1 font-medium capitalize text-gray-900">{{ $weather['condition'] }}</dd>
                            </div>
                            <div class="rounded-md bg-gray-50 px-4 py-3">
                                <dt class="text-sm text-gray-500">{{ __('Humidity') }}</dt>
                                <dd class="mt-1 font-medium text-gray-900">{{ $weather['humidity'] }}%</dd>
                            </div>
                            <div class="rounded-md bg-gray-50 px-4 py-3">
                                <dt class="text-sm text-gray-500">{{ __('Wind speed') }}</dt>
                                <dd class="mt-1 font-medium text-gray-900">{{ number_format($weather['wind_speed'], 1) }} m/s</dd>
                            </div>
                        </dl>
                    </div>
                @else
                    <p role="status" class="px-6 py-5 text-sm text-gray-600">
                        {{ __('Weather information is currently unavailable. Please check the configured location and API key.') }}
                    </p>
                @endif
            </section>

            <section aria-labelledby="recent-accounts-heading" class="bg-white shadow-sm sm:rounded-lg">
                <div class="border-b border-gray-200 px-6 py-4">
                    <h3 id="recent-accounts-heading" class="text-base font-semibold text-gray-900">{{ __('Recent accounts') }}</h3>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Name') }}</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Email') }}</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Role') }}</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Joined') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @forelse ($recentUsers as $user)
                                <tr>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900">{{ $user->name }}</td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600">{{ $user->email }}</td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm capitalize text-gray-600">{{ $user->role }}</td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600">{{ $user->created_at->format('M j, Y') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-6 py-8 text-center text-sm text-gray-500">{{ __('No accounts found.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>