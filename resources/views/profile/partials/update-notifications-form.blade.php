<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">{{ __('Notifications') }}</h2>
        <p class="mt-1 text-sm text-gray-600">{{ __('Choose how you want to be notified when new assignments are available.') }}</p>
    </header>

    <form method="post" action="{{ route('profile.notifications') }}" class="mt-6 space-y-5">
        @csrf
        @method('patch')

        {{-- SMS --}}
        <div x-data="{ smsOn: {{ auth()->user()->readerProfile?->sms_notifications ? 'true' : 'false' }} }">
            <div class="flex items-start gap-3">
                <input id="sms_notifications" name="sms_notifications" type="checkbox" value="1"
                       x-model="smsOn"
                       class="mt-0.5 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                <div>
                    <label for="sms_notifications" class="text-sm font-medium text-gray-700">Receive SMS notifications</label>
                    <p class="text-xs text-gray-500">Get a text message when new assignments are available.</p>
                </div>
            </div>

            <div x-show="smsOn" x-cloak class="mt-3 ml-7 space-y-2"
                 x-data="{
                     any:      {{ auth()->user()->readerProfile?->sms_notify_any ? 'true' : 'false' }},
                     rush:     {{ auth()->user()->readerProfile?->sms_notify_rush ? 'true' : 'false' }},
                     requests: {{ auth()->user()->readerProfile?->sms_notify_requests ? 'true' : 'false' }}
                 }">
                <p class="text-xs font-medium text-gray-600 mb-1">Notify me for:</p>
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" name="sms_notify_any" value="1"
                           x-model="any"
                           class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                    Any new assignment
                </label>
                <label class="flex items-center gap-2 text-sm"
                       :class="any ? 'text-gray-400 cursor-not-allowed' : 'text-gray-700'">
                    <input type="checkbox" name="sms_notify_rush" value="1"
                           :checked="any || rush"
                           :disabled="any"
                           @change="rush = $event.target.checked"
                           class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed" />
                    Rush assignments
                </label>
                <label class="flex items-center gap-2 text-sm"
                       :class="any ? 'text-gray-400 cursor-not-allowed' : 'text-gray-700'">
                    <input type="checkbox" name="sms_notify_requests" value="1"
                           :checked="any || requests"
                           :disabled="any"
                           @change="requests = $event.target.checked"
                           class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed" />
                    Reader requests (when I'm specifically requested)
                </label>
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" name="sms_notify_followup" value="1"
                           {{ auth()->user()->readerProfile?->sms_notify_followup ? 'checked' : '' }}
                           class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                    Followup questions from customers
                </label>
            </div>
        </div>

        {{-- Email --}}
        <div x-data="{ emailOn: {{ auth()->user()->readerProfile?->email_notifications ? 'true' : 'false' }} }">
            <div class="flex items-start gap-3">
                <input id="email_notifications" name="email_notifications" type="checkbox" value="1"
                       x-model="emailOn"
                       class="mt-0.5 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                <div>
                    <label for="email_notifications" class="text-sm font-medium text-gray-700">Receive email notifications</label>
                    <p class="text-xs text-gray-500">Get an email when new assignments are available.</p>
                </div>
            </div>

            <div x-show="emailOn" x-cloak class="mt-3 ml-7 space-y-2"
                 x-data="{
                     any:      {{ auth()->user()->readerProfile?->email_notify_any ? 'true' : 'false' }},
                     rush:     {{ auth()->user()->readerProfile?->email_notify_rush ? 'true' : 'false' }},
                     requests: {{ auth()->user()->readerProfile?->email_notify_requests ? 'true' : 'false' }}
                 }">
                <p class="text-xs font-medium text-gray-600 mb-1">Notify me for:</p>
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" name="email_notify_any" value="1"
                           x-model="any"
                           class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                    Any new assignment
                </label>
                <label class="flex items-center gap-2 text-sm"
                       :class="any ? 'text-gray-400 cursor-not-allowed' : 'text-gray-700'">
                    <input type="checkbox" name="email_notify_rush" value="1"
                           :checked="any || rush"
                           :disabled="any"
                           @change="rush = $event.target.checked"
                           class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed" />
                    Rush assignments
                </label>
                <label class="flex items-center gap-2 text-sm"
                       :class="any ? 'text-gray-400 cursor-not-allowed' : 'text-gray-700'">
                    <input type="checkbox" name="email_notify_requests" value="1"
                           :checked="any || requests"
                           :disabled="any"
                           @change="requests = $event.target.checked"
                           class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed" />
                    Reader requests (when I'm specifically requested)
                </label>
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" name="email_notify_followup" value="1"
                           {{ auth()->user()->readerProfile?->email_notify_followup ? 'checked' : '' }}
                           class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                    Followup questions from customers
                </label>
            </div>
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Save') }}</x-primary-button>

            @if (session('status') === 'notifications-updated')
                <p x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 2000)" class="text-sm text-gray-600">{{ __('Saved.') }}</p>
            @endif
        </div>
    </form>
</section>
