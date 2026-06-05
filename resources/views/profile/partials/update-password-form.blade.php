<section x-data="{
    current: '',
    password: '',
    confirmation: '',
    get hasLength()    { return this.password.length >= 12 },
    get hasMixed()     { return /[A-Z]/.test(this.password) && /[a-z]/.test(this.password) },
    get hasNumber()    { return /[0-9]/.test(this.password) },
    get hasSymbol()    { return /[^A-Za-z0-9]/.test(this.password) },
    get hasMatch()     { return this.password.length > 0 && this.password === this.confirmation },
    get allMet()       { return this.hasLength && this.hasMixed && this.hasNumber && this.hasSymbol && this.hasMatch },
}">
    <h2 class="text-lg font-medium text-gray-900">{{ __('Update Password') }}</h2>
    <p class="mt-1 text-sm text-gray-600 mb-6">{{ __('Use a strong password you don\'t use anywhere else.') }}</p>

    @if (session('status') === 'password-updated')
        <div class="mb-6 flex items-center gap-3 px-4 py-3 bg-green-50 border border-green-300 rounded-lg">
            <svg class="w-5 h-5 text-green-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            <p class="text-sm font-semibold text-green-800">Password updated successfully. You're good to go.</p>
        </div>
    @endif

    <form method="post" action="{{ route('password.update') }}" class="space-y-5">
        @csrf
        @method('put')

        <div>
            <x-input-label for="update_password_current_password" :value="__('Current Password')" />
            <x-text-input id="update_password_current_password" name="current_password" type="password"
                          class="mt-1 block w-full" autocomplete="current-password"
                          x-model="current" />
            <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="update_password_password" :value="__('New Password')" />
            <x-text-input id="update_password_password" name="password" type="password"
                          class="mt-1 block w-full" autocomplete="new-password"
                          x-model="password" />
            <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-2" />

            {{-- Live requirements checklist --}}
            <ul class="mt-3 space-y-1">
                <li class="flex items-center gap-2 text-xs" :class="hasLength ? 'text-green-700' : 'text-gray-500'">
                    <svg x-show="hasLength"  class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                    <svg x-show="!hasLength" class="w-3.5 h-3.5 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" stroke-width="2"/></svg>
                    At least 12 characters
                </li>
                <li class="flex items-center gap-2 text-xs" :class="hasMixed ? 'text-green-700' : 'text-gray-500'">
                    <svg x-show="hasMixed"  class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                    <svg x-show="!hasMixed" class="w-3.5 h-3.5 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" stroke-width="2"/></svg>
                    Upper and lowercase letters
                </li>
                <li class="flex items-center gap-2 text-xs" :class="hasNumber ? 'text-green-700' : 'text-gray-500'">
                    <svg x-show="hasNumber"  class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                    <svg x-show="!hasNumber" class="w-3.5 h-3.5 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" stroke-width="2"/></svg>
                    At least one number
                </li>
                <li class="flex items-center gap-2 text-xs" :class="hasSymbol ? 'text-green-700' : 'text-gray-500'">
                    <svg x-show="hasSymbol"  class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                    <svg x-show="!hasSymbol" class="w-3.5 h-3.5 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" stroke-width="2"/></svg>
                    At least one symbol (e.g. !@#$)
                </li>
                <li class="flex items-center gap-2 text-xs" :class="hasMatch ? 'text-green-700' : 'text-gray-500'">
                    <svg x-show="hasMatch"  class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                    <svg x-show="!hasMatch" class="w-3.5 h-3.5 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" stroke-width="2"/></svg>
                    Passwords match
                </li>
            </ul>
        </div>

        <div>
            <x-input-label for="update_password_password_confirmation" :value="__('Confirm New Password')" />
            <x-text-input id="update_password_password_confirmation" name="password_confirmation" type="password"
                          class="mt-1 block w-full" autocomplete="new-password"
                          x-model="confirmation" />
            <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-2" />
        </div>

        <div>
            <x-primary-button x-bind:disabled="!allMet" x-bind:class="!allMet ? 'opacity-40 cursor-not-allowed' : ''">
                {{ __('Save Password') }}
            </x-primary-button>
        </div>
    </form>
</section>
