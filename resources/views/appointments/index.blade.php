<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-sm font-medium uppercase tracking-wide text-gray-500">{{ __('Bookings') }}</p>
                <h2 class="mt-1 text-xl font-semibold leading-tight text-gray-800">{{ __('Appointments') }}</h2>
            </div>
            @can('book-appointments')
                <a href="{{ route('appointments.create') }}" class="inline-flex items-center rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
                    {{ __('Book an appointment') }}
                </a>
            @endcan
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <x-auth-session-status :status="session('status')" />

            @if ($errors->has('appointment'))
                <p role="alert" class="text-sm text-red-600">{{ $errors->first('appointment') }}</p>
            @endif

            @forelse ($appointments as $appointment)
                <article class="border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                    <div class="flex flex-col justify-between gap-5 md:flex-row md:items-start">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-3">
                                <h3 class="text-lg font-semibold text-gray-900">
                                    {{ $appointment->shoot_type === 'photoshoot' ? __('Photo shoot') : __('Film / videography') }}
                                </h3>
                                <span @class([
                                    'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold capitalize',
                                    'bg-amber-100 text-amber-800' => $appointment->status === 'pending',
                                    'bg-emerald-100 text-emerald-800' => $appointment->status === 'confirmed',
                                    'bg-rose-100 text-rose-800' => $appointment->status === 'cancelled',
                                    'bg-gray-100 text-gray-700' => $appointment->status === 'completed',
                                ])>{{ $appointment->status }}</span>
                            </div>
                            <dl class="mt-4 grid gap-x-8 gap-y-3 text-sm sm:grid-cols-2 lg:grid-cols-3">
                                @if (auth()->user()->role !== 'client')
                                    <div>
                                        <dt class="text-gray-500">{{ __('Client') }}</dt>
                                        <dd class="mt-1 font-medium text-gray-900">{{ $appointment->client->name }}</dd>
                                    </div>
                                @endif
                                @if (auth()->user()->role !== 'photographer')
                                    <div>
                                        <dt class="text-gray-500">{{ __('Photographer / videographer') }}</dt>
                                        <dd class="mt-1 font-medium text-gray-900">{{ $appointment->photographer->name }}</dd>
                                    </div>
                                @endif
                                <div>
                                    <dt class="text-gray-500">{{ __('Date') }}</dt>
                                    <dd class="mt-1 font-medium text-gray-900">{{ $appointment->appointment_date->format('F j, Y') }}</dd>
                                </div>
                            </dl>
                            @if ($appointment->notes)
                                <p class="mt-4 max-w-3xl whitespace-pre-line text-sm text-gray-600">{{ $appointment->notes }}</p>
                            @endif
                        </div>

                        <div class="flex shrink-0 flex-wrap gap-2">
                            @if (auth()->id() === $appointment->client_id && in_array($appointment->status, ['pending', 'confirmed'], true))
                                <form method="POST" action="{{ route('appointments.cancel', $appointment) }}" onsubmit="return confirm('{{ __('Cancel this appointment?') }}')">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="rounded-md border border-rose-300 px-3 py-2 text-sm font-medium text-rose-700 hover:bg-rose-50">{{ __('Cancel') }}</button>
                                </form>
                            @endif

                            @can('manage-appointment', $appointment)
                                @if ($appointment->status === 'pending')
                                    <form method="POST" action="{{ route('appointments.status.update', $appointment) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="status" value="confirmed">
                                        <button type="submit" class="rounded-md bg-emerald-700 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-800">{{ __('Confirm') }}</button>
                                    </form>
                                @endif
                                @if (in_array($appointment->status, ['pending', 'confirmed'], true))
                                    <form method="POST" action="{{ route('appointments.status.update', $appointment) }}" onsubmit="return confirm('{{ __('Cancel this appointment?') }}')">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="status" value="cancelled">
                                        <button type="submit" class="rounded-md border border-rose-300 px-3 py-2 text-sm font-medium text-rose-700 hover:bg-rose-50">{{ __('Cancel') }}</button>
                                    </form>
                                @endif
                                @if ($appointment->status === 'confirmed')
                                    <form method="POST" action="{{ route('appointments.status.update', $appointment) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="status" value="completed">
                                        <button type="submit" class="rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('Mark complete') }}</button>
                                    </form>
                                @endif
                            @endcan
                        </div>
                    </div>
                </article>
            @empty
                <div class="border border-dashed border-gray-300 bg-white px-6 py-12 text-center">
                    <h3 class="text-base font-semibold text-gray-900">{{ __('No appointments yet') }}</h3>
                    <p class="mt-2 text-sm text-gray-600">
                        @can('book-appointments')
                            {{ __('Choose a photographer or videographer and request a date for your session.') }}
                        @else
                            {{ __('Appointments assigned to you will appear here.') }}
                        @endcan
                    </p>
                    @can('book-appointments')
                        <a href="{{ route('appointments.create') }}" class="mt-5 inline-flex rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700">{{ __('Book an appointment') }}</a>
                    @endcan
                </div>
            @endforelse
        </div>
    </div>
</x-app-layout>