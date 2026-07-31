<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">Notification Preview</h2>
        <p class="mt-1 text-sm text-gray-600">Get a copy of the same new-assignment email readers receive &mdash; useful for testing deliverability and previewing the template.</p>
    </header>

    <form method="post" action="{{ route('profile.notifications') }}" class="mt-6 space-y-5">
        @csrf
        @method('patch')

        <div x-data="{ emailOn: {{ auth()->user()->editorProfile?->email_notifications ? 'true' : 'false' }} }">
            <div class="flex items-start gap-3">
                <input id="email_notifications" name="email_notifications" type="checkbox" value="1"
                       x-model="emailOn"
                       class="mt-0.5 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                <div>
                    <label for="email_notifications" class="text-sm font-medium text-gray-700">Receive new-assignment emails</label>
                    <p class="text-xs text-gray-500">Get an email whenever a new assignment becomes available, just like readers do.</p>
                </div>
            </div>

            <div x-show="emailOn" x-cloak class="mt-3 ml-7 space-y-2"
                 x-data="{
                     any:      {{ auth()->user()->editorProfile?->email_notify_any ? 'true' : 'false' }},
                     rush:     {{ auth()->user()->editorProfile?->email_notify_rush ? 'true' : 'false' }},
                     requests: {{ auth()->user()->editorProfile?->email_notify_requests ? 'true' : 'false' }}
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
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" name="email_notify_requests" value="1"
                           {{ auth()->user()->editorProfile?->email_notify_requests ? 'checked' : '' }}
                           class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                    Reader requests
                </label>
                <p class="text-xs text-gray-400">You'll get the exact email a reader would get for each of these, addressed to you.</p>
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
