<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-medium uppercase tracking-wide text-gray-500">{{ __('Appointments') }}</p>
            <h2 class="mt-1 text-xl font-semibold leading-tight text-gray-800">{{ __('Request a photo or video session') }}</h2>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('appointments.store') }}" class="space-y-6 bg-white p-6 shadow-sm sm:rounded-lg sm:p-8">
                @csrf

                <div>
                    <x-input-label for="shoot_type" :value="__('Session type')" />
                    <select id="shoot_type" name="shoot_type" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="photoshoot" @selected(old('shoot_type') === 'photoshoot')>{{ __('Photo shoot') }}</option>
                        <option value="videography" @selected(old('shoot_type') === 'videography')>{{ __('Film / videography') }}</option>
                    </select>
                    <x-input-error :messages="$errors->get('shoot_type')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="photographer_id" :value="__('Choose your photographer / videographer')" />
                    <select id="photographer_id" name="photographer_id" required @disabled($photographers->isEmpty()) class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100">
                        <option value="">{{ __('Select a staff member') }}</option>
                        @foreach ($photographers as $photographer)
                            <option value="{{ $photographer->id }}" @selected((string) old('photographer_id') === (string) $photographer->id)>
                                {{ $photographer->name }}
                            </option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('photographer_id')" class="mt-2" />
                    @if ($photographers->isEmpty())
                        <p class="mt-2 text-sm text-gray-600">{{ __('No photographers or videographers are available yet.') }}</p>
                    @endif
                </div>

                <div>
                    <x-input-label for="appointment_date" :value="__('Appointment date')" />
                    <x-text-input id="appointment_date" name="appointment_date" type="date" :value="old('appointment_date')" :min="now()->toDateString()" required class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('appointment_date')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="notes" :value="__('Notes (optional)')" />
                    <textarea id="notes" name="notes" rows="4" maxlength="1000" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('notes') }}</textarea>
                    <x-input-error :messages="$errors->get('notes')" class="mt-2" />
                </div>

                <p class="text-sm text-gray-600">{{ __('Your request will be sent to the selected staff member. No payment is collected now.') }}</p>

                <div class="flex items-center justify-between gap-4">
                    <a href="{{ route('appointments.index') }}" class="text-sm text-gray-600 underline hover:text-gray-900">{{ __('Back to appointments') }}</a>
                    <x-primary-button :disabled="$photographers->isEmpty()">{{ __('Send appointment request') }}</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>