<x-guest-layout>
    <div class="mb-6">
        <p class="text-sm font-medium uppercase tracking-wide text-gray-500">{{ __('Google account connected') }}</p>
        <h1 class="mt-1 text-xl font-semibold text-gray-900">{{ __('Finish creating your account') }}</h1>
        <p class="mt-2 text-sm text-gray-600">{{ __('Your Google name and email are verified and cannot be changed here.') }}</p>
    </div>

    <form method="POST" action="{{ route('google.register.store') }}" class="space-y-4">
        @csrf

        <div>
            <x-input-label for="google_name" :value="__('Google name')" />
            <x-text-input id="google_name" type="text" :value="$googleName" readonly aria-readonly="true" class="mt-1 block w-full bg-gray-100 text-gray-600" />
        </div>

        <div>
            <x-input-label for="google_email" :value="__('Google email')" />
            <x-text-input id="google_email" type="email" :value="$googleEmail" readonly aria-readonly="true" class="mt-1 block w-full bg-gray-100 text-gray-600" />
        </div>

        <div>
            <x-input-label for="role" :value="__('I am registering as')" />
            <select id="role" name="role" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="client" @selected(old('role', 'client') === 'client')>{{ __('Client') }}</option>
                <option value="photographer" @selected(old('role') === 'photographer')>{{ __('Photographer / Videographer') }}</option>
            </select>
            <x-input-error :messages="$errors->get('role')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password" :value="__('Create a password')" />
            <x-text-input id="password" name="password" type="password" required autocomplete="new-password" class="mt-1 block w-full" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password_confirmation" :value="__('Confirm password')" />
            <x-text-input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="mt-1 block w-full" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <p class="text-xs text-gray-500">{{ __('Your Google name and email are saved from Google. Only your role and password are submitted on this form.') }}</p>

        <div class="flex items-center justify-between gap-4 pt-2">
            <a href="{{ route('login') }}" class="text-sm text-gray-600 underline hover:text-gray-900">{{ __('Cancel') }}</a>
            <x-primary-button>{{ __('Create account') }}</x-primary-button>
        </div>
    </form>
</x-guest-layout>