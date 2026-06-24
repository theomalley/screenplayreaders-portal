<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Settings</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">

            @include('settings._nav')

            <div class="space-y-6">

            @if(session('success'))
                <div class="bg-green-50 border border-green-200 rounded-lg px-4 py-3 text-sm text-green-700">
                    {{ session('success') }}
                </div>
            @endif

            @if($isAdmin)

            {{-- Email Notification Texts --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-sm font-semibold text-gray-800 mb-1">Email Notification Text</h3>
                <p class="text-xs text-gray-500 mb-4">Text sent to readers when a new assignment is available. These map directly to your MailerSend template variables.</p>

                <form method="POST" action="{{ route('settings.email-notifications') }}" class="space-y-5">
                    @csrf
                    @method('PATCH')

                    <fieldset>
                        <legend class="text-xs font-semibold text-gray-600 uppercase tracking-wide mb-2">Subject lines <span class="normal-case font-normal text-gray-400">(@{{ subject }} in template)</span></legend>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            @foreach ([
                                'email_notif_subject_new'          => 'Standard new assignment',
                                'email_notif_subject_rush'         => 'Rush assignment',
                                'email_notif_subject_request'      => 'Reader request',
                                'email_notif_subject_rush_request' => 'Rush reader request',
                            ] as $key => $label)
                            <div>
                                <x-input-label :for="$key" :value="$label" class="mb-1" />
                                <x-text-input :id="$key" :name="$key" type="text" class="w-full text-sm"
                                    :value="old($key, $emailNotifTexts[$key])" required maxlength="500" />
                            </div>
                            @endforeach
                        </div>
                    </fieldset>

                    <fieldset>
                        <legend class="text-xs font-semibold text-gray-600 uppercase tracking-wide mb-2">Headers <span class="normal-case font-normal text-gray-400">(@{{ header }} in template)</span></legend>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            @foreach ([
                                'email_notif_header_new'          => 'Standard new assignment',
                                'email_notif_header_rush'         => 'Rush assignment',
                                'email_notif_header_request'      => 'Reader request',
                                'email_notif_header_rush_request' => 'Rush reader request',
                            ] as $key => $label)
                            <div>
                                <x-input-label :for="$key" :value="$label" class="mb-1" />
                                <x-text-input :id="$key" :name="$key" type="text" class="w-full text-sm"
                                    :value="old($key, $emailNotifTexts[$key])" required maxlength="500" />
                            </div>
                            @endforeach
                        </div>
                    </fieldset>

                    <fieldset>
                        <legend class="text-xs font-semibold text-gray-600 uppercase tracking-wide mb-2">Body messages <span class="normal-case font-normal text-gray-400">(@{{ body_message }} in template)</span></legend>
                        <div class="space-y-3">
                            @foreach ([
                                'email_notif_body_new'     => 'Standard / rush (open pool)',
                                'email_notif_body_request' => 'Reader request',
                            ] as $key => $label)
                            <div>
                                <x-input-label :for="$key" :value="$label" class="mb-1" />
                                <textarea :id="$key" id="{{ $key }}" name="{{ $key }}" rows="2" maxlength="500"
                                    class="w-full text-sm border border-gray-300 rounded px-2.5 py-1.5 resize-none focus:outline-none focus:ring-1 focus:ring-indigo-400"
                                >{{ old($key, $emailNotifTexts[$key]) }}</textarea>
                            </div>
                            @endforeach
                        </div>
                    </fieldset>

                    <div class="flex justify-end">
                        <x-primary-button>Save notification text</x-primary-button>
                    </div>
                </form>
            </div>

            {{-- Completion Draft Email --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 class="text-sm font-semibold text-gray-800 mb-1">Completion Draft Email</h3>
                <p class="text-xs text-gray-500 mb-4">
                    HTML body for the HelpScout draft created when all readers on an order are approved. Accepts raw HTML.
                    Use <code class="text-xs bg-gray-100 px-1 rounded">{%customer.firstName,fallback=...%}</code> for HelpScout
                    merge fields, <code class="text-xs bg-gray-100 px-1 rounded">@{{script_title}}</code> for the
                    assignment's script title, <code class="text-xs bg-gray-100 px-1 rounded">@{{followup_url}}</code> for the
                    customer's followup-questions link, and <code class="text-xs bg-gray-100 px-1 rounded">@{{woodiscountcode}}</code>
                    for the auto-generated discount code (configured in Post-Coverage Discount Coupon on the Orders & Payments tab).
                </p>

                <form method="POST" action="{{ route('settings.completion-draft') }}" class="space-y-4">
                    @csrf
                    @method('PATCH')

                    <textarea name="completion_draft_body" rows="14"
                              class="block w-full border-gray-300 rounded-md shadow-sm text-sm font-mono focus:border-indigo-500 focus:ring-indigo-500">{{ old('completion_draft_body', $completionDraftBody) }}</textarea>
                    <x-input-error :messages="$errors->get('completion_draft_body')" class="mt-1" />

                    <details class="mt-2">
                        <summary class="text-xs text-gray-400 cursor-pointer select-none hover:text-gray-600">Preview</summary>
                        <div class="mt-2 p-4 bg-gray-50 border border-gray-200 rounded-md text-sm text-gray-700 prose prose-sm max-w-none">
                            {!! $completionDraftBody !!}
                        </div>
                    </details>

                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Test HelpScout conversation ID</label>
                        <input type="text" name="test_helpscout_conversation_id"
                               value="{{ old('test_helpscout_conversation_id', $testHelpscoutConvId) }}"
                               class="block w-full max-w-xs border-gray-300 rounded-md shadow-sm text-sm font-mono focus:border-indigo-500 focus:ring-indigo-500">
                        <x-input-error :messages="$errors->get('test_helpscout_conversation_id')" class="mt-1" />
                        <p class="mt-1 text-xs text-gray-400">
                            Sandbox conversation used by "Send Test Draft" below — never a real customer ticket.
                        </p>
                    </div>

                    <div class="flex items-center justify-end gap-3">
                        <div x-data="{ loading: false, error: '' }" class="relative">
                            <button type="button"
                                    @click="
                                        loading = true; error = '';
                                        fetch('{{ route('settings.completion-draft.test') }}', {
                                            method: 'POST',
                                            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' }
                                        })
                                        .then(r => r.json())
                                        .then(d => { loading = false; if (d.url) window.open(d.url, '_blank'); else error = d.error ?? 'Unknown error'; })
                                        .catch(e => { loading = false; error = e.message; })
                                    "
                                    :disabled="loading"
                                    :class="loading ? 'opacity-50 cursor-not-allowed' : 'hover:bg-gray-300'"
                                    class="inline-flex items-center px-4 py-2 bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-gray-800 uppercase tracking-widest transition ease-in-out duration-150">
                                <span x-text="loading ? 'Sending…' : 'Send Test Draft'"></span>
                            </button>
                            <p x-show="error" x-cloak x-text="error"
                               class="absolute right-0 top-full mt-1 text-xs text-red-600 bg-white border border-red-200 rounded px-2 py-1 whitespace-nowrap shadow-sm z-10"></p>
                        </div>
                        <x-primary-button>Save</x-primary-button>
                    </div>
                    <p class="text-xs text-gray-400 -mt-2">
                        "Send Test Draft" creates a draft using the saved template (with a placeholder PDF attachment) on a HelpScout
                        sandbox conversation — it does not contact a real customer and does not create a real discount coupon
                        (@{{woodiscountcode}} is replaced with a placeholder).
                    </p>
                </form>
            </div>

            {{-- Followup Questions Form --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 class="text-sm font-semibold text-gray-800 mb-1">Followup Questions Form</h3>
                <p class="text-xs text-gray-500 mb-4">
                    Optional HTML injected into the public followup form sent to customers. Accepts raw HTML. Leave blank for none.
                </p>

                <form method="POST" action="{{ route('settings.followup-html') }}" class="space-y-4">
                    @csrf
                    @method('PATCH')

                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Page heading</label>
                        <input type="text" name="followup_heading"
                               value="{{ old('followup_heading', $followupHeading) }}"
                               placeholder="Followup Questions"
                               class="block w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <p class="mt-1 text-xs text-gray-400">Defaults to "Followup Questions" if left blank.</p>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">HTML before the form</label>
                        <textarea name="followup_before_html" rows="5"
                                  class="block w-full border-gray-300 rounded-md shadow-sm text-sm font-mono focus:border-indigo-500 focus:ring-indigo-500"
                                  placeholder="<p>Your HTML here...</p>">{{ old('followup_before_html', $followupBeforeHtml) }}</textarea>
                        @if($followupBeforeHtml)
                            <details class="mt-2">
                                <summary class="text-xs text-gray-400 cursor-pointer select-none hover:text-gray-600">Preview</summary>
                                <div class="mt-2 p-4 bg-gray-50 border border-gray-200 rounded-md text-sm text-gray-700 prose prose-sm max-w-none">
                                    {!! $followupBeforeHtml !!}
                                </div>
                            </details>
                        @endif
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">HTML after the form</label>
                        <textarea name="followup_after_html" rows="5"
                                  class="block w-full border-gray-300 rounded-md shadow-sm text-sm font-mono focus:border-indigo-500 focus:ring-indigo-500"
                                  placeholder="<p>Your HTML here...</p>">{{ old('followup_after_html', $followupAfterHtml) }}</textarea>
                        @if($followupAfterHtml)
                            <details class="mt-2">
                                <summary class="text-xs text-gray-400 cursor-pointer select-none hover:text-gray-600">Preview</summary>
                                <div class="mt-2 p-4 bg-gray-50 border border-gray-200 rounded-md text-sm text-gray-700 prose prose-sm max-w-none">
                                    {!! $followupAfterHtml !!}
                                </div>
                            </details>
                        @endif
                    </div>

                    <div class="flex justify-end">
                        <x-primary-button>Save</x-primary-button>
                    </div>
                </form>
            </div>

            {{-- Notification History Retention --}}
            @if($notificationHistoryRetentionDays !== null)
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 class="text-sm font-semibold text-gray-800 mb-1">Notification History Retention</h3>
                <p class="text-xs text-gray-500 mb-4">
                    Notifications older than this are deleted from everyone's Notification History.
                    Set to 0 to keep notifications forever.
                </p>

                <form method="POST" action="{{ route('settings.notification-history-retention') }}" class="space-y-4">
                    @csrf
                    @method('PATCH')

                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                        <div>
                            <x-input-label value="Retention (days)" />
                            <input type="number" name="notification_history_retention_days" min="0" max="3650"
                                value="{{ old('notification_history_retention_days', $notificationHistoryRetentionDays) }}"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" />
                            <p class="mt-0.5 text-xs text-gray-400">0 = never expire</p>
                        </div>
                    </div>

                    <div class="flex justify-end border-t border-gray-100 pt-4">
                        <x-primary-button>Save retention period</x-primary-button>
                    </div>
                </form>
            </div>
            @endif

            @endif
            </div>
        </div>
    </div>
</x-app-layout>
