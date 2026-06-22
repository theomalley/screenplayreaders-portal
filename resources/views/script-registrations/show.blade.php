<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('script-registrations.index') }}" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Registration {{ $registration->registration_id }}
            </h2>
            @php
                $varBadge = match($registration->variation_id) {
                    \App\Models\ScriptRegistration::VAR_FREE_90  => 'bg-gray-100 text-gray-700',
                    \App\Models\ScriptRegistration::VAR_5YR      => 'bg-blue-100 text-blue-800',
                    \App\Models\ScriptRegistration::VAR_10YR     => 'bg-indigo-100 text-indigo-800',
                    \App\Models\ScriptRegistration::VAR_LIFETIME => 'bg-purple-100 text-purple-800',
                    default => 'bg-gray-100 text-gray-600',
                };
            @endphp
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $varBadge }}">
                {{ $registration->variation_label }}
            </span>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if(session('success'))
                <div class="px-4 py-3 rounded bg-green-50 border border-green-200 text-green-800 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            @if($errors->any())
                <div class="px-4 py-3 rounded bg-red-50 border border-red-200 text-red-800 text-sm">
                    {{ $errors->first() }}
                </div>
            @endif

            {{-- Registration Details --}}
            <div class="bg-white shadow-sm sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h3 class="text-base font-semibold text-gray-900">Registration Details</h3>
                </div>
                <div class="px-6 py-4">
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-3 text-sm">
                        <div>
                            <dt class="text-gray-500">Registration ID</dt>
                            <dd class="font-mono font-medium text-gray-900">{{ $registration->registration_id }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Status</dt>
                            <dd>
                                @php
                                    $statusBadge = match($registration->status) {
                                        'completed' => 'bg-green-100 text-green-800',
                                        'pending'   => 'bg-amber-100 text-amber-800',
                                        'failed'    => 'bg-red-100 text-red-800',
                                        default     => 'bg-gray-100 text-gray-600',
                                    };
                                @endphp
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $statusBadge }}">
                                    {{ ucfirst($registration->status) }}
                                </span>
                                @if($registration->status === 'failed' && $registration->error_message)
                                    <p class="mt-1 text-xs text-red-600">{{ $registration->error_message }}</p>
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Script Title</dt>
                            <dd class="font-medium text-gray-900">{{ $registration->script_title }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Type of Work</dt>
                            <dd class="text-gray-900">{{ $registration->type_of_work }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Page Count</dt>
                            <dd class="text-gray-900">{{ $registration->page_count ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Auth Code</dt>
                            <dd class="font-mono text-xs text-gray-700">{{ $registration->authcode }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Registered</dt>
                            <dd class="text-gray-900">{{ $registration->registered_at->format('F j, Y g:i A') }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Expires</dt>
                            <dd class="text-gray-900">
                                @if($registration->expires_at)
                                    {{ $registration->expires_at->format('F j, Y') }}
                                    @if($registration->isExpired())
                                        <span class="text-red-500 font-medium">(expired)</span>
                                    @endif
                                @else
                                    <span class="text-purple-600 font-medium">Never (Unlimited)</span>
                                @endif
                            </dd>
                        </div>
                        @if($registration->uploaded_file_name)
                            <div class="sm:col-span-2">
                                <dt class="text-gray-500">Uploaded File</dt>
                                <dd class="text-gray-900">{{ $registration->uploaded_file_name }}</dd>
                            </div>
                        @endif
                        @if($registration->unique_id && $registration->unique_id !== 'None provided')
                            <div>
                                <dt class="text-gray-500">Unique ID</dt>
                                <dd class="text-gray-900">{{ $registration->unique_id }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>
            </div>

            {{-- Author & Contact --}}
            <div class="bg-white shadow-sm sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h3 class="text-base font-semibold text-gray-900">Author & Contact</h3>
                </div>
                <div class="px-6 py-4">
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-3 text-sm">
                        <div>
                            <dt class="text-gray-500">Author</dt>
                            <dd class="text-gray-900">{{ $registration->author_first }} {{ $registration->author_last }}</dd>
                        </div>
                        @if($registration->additional_authors && $registration->additional_authors !== 'None provided')
                            <div>
                                <dt class="text-gray-500">Additional Authors</dt>
                                <dd class="text-gray-900">{{ $registration->additional_authors }}</dd>
                            </div>
                        @endif
                        <div>
                            <dt class="text-gray-500">Email</dt>
                            <dd class="text-gray-900">{{ $registration->email }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Phone</dt>
                            <dd class="text-gray-900">{{ $registration->phone }}</dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-gray-500">Address</dt>
                            <dd class="text-gray-900">
                                {{ $registration->street_address }}<br>
                                {{ $registration->city }}, {{ $registration->state_or_province }} {{ $registration->postal_or_zip }}<br>
                                {{ $registration->country }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>

            {{-- WooCommerce Order --}}
            <div class="bg-white shadow-sm sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h3 class="text-base font-semibold text-gray-900">WooCommerce Order</h3>
                </div>
                <div class="px-6 py-4">
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-3 text-sm">
                        <div>
                            <dt class="text-gray-500">Order ID</dt>
                            <dd class="text-gray-900">{{ $registration->woo_order_id }}</dd>
                        </div>
                        @if($registration->woo_order_number)
                            <div>
                                <dt class="text-gray-500">Order Number</dt>
                                <dd class="text-gray-900">{{ $registration->woo_order_number }}</dd>
                            </div>
                        @endif
                        <div>
                            <dt class="text-gray-500">Customer Name</dt>
                            <dd class="text-gray-900">{{ $registration->customer_name }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Customer Email</dt>
                            <dd class="text-gray-900">{{ $registration->customer_email }}</dd>
                        </div>
                        @if($registration->unlimited_token_parent_id)
                            <div class="sm:col-span-2">
                                <dt class="text-gray-500">Parent Registration</dt>
                                <dd>
                                    <a href="{{ route('script-registrations.show', $registration->parent) }}"
                                       class="text-indigo-600 hover:text-indigo-800 text-sm font-medium">
                                        {{ $registration->parent->registration_id }} — {{ $registration->parent->script_title }}
                                    </a>
                                </dd>
                            </div>
                        @endif
                    </dl>
                </div>
            </div>

            {{-- Certificate Actions --}}
            <div class="bg-white shadow-sm sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h3 class="text-base font-semibold text-gray-900">Certificate</h3>
                </div>
                <div class="px-6 py-4 flex flex-wrap gap-3">
                    @if($registration->drive_certificate_pdf_id)
                        <a href="{{ route('script-registrations.download', $registration) }}"
                           class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700 transition">
                            Download Certificate PDF
                        </a>
                    @endif
                    <form method="POST" action="{{ route('script-registrations.regenerate', $registration) }}">
                        @csrf
                        <button type="submit"
                            class="inline-flex items-center px-4 py-2 bg-gray-600 text-white text-sm font-medium rounded-md hover:bg-gray-700 transition">
                            Regenerate Certificate
                        </button>
                    </form>
                </div>
            </div>

            {{-- Unlimited Token --}}
            @if($registration->isUnlimited() && $registration->unlimited_token)
                <div class="bg-white shadow-sm sm:rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-100">
                        <h3 class="text-base font-semibold text-gray-900">Unlimited Registration Token</h3>
                    </div>
                    <div class="px-6 py-4 space-y-4">
                        <div x-data="{ copied: false }">
                            <label class="block text-sm text-gray-500 mb-1">Personal Registration URL</label>
                            <div class="flex items-center gap-2">
                                <input type="text" readonly
                                    value="{{ $registration->publicRegistrationUrl() }}"
                                    class="flex-1 text-sm font-mono bg-gray-50 border-gray-300 rounded-md text-gray-700">
                                <button type="button"
                                    @click="navigator.clipboard.writeText(@js($registration->publicRegistrationUrl())).then(() => { copied = true; setTimeout(() => copied = false, 2000) })"
                                    class="inline-flex items-center px-3 py-2 bg-indigo-600 text-white text-sm rounded-md hover:bg-indigo-700 transition">
                                    <span x-show="!copied">Copy</span>
                                    <span x-show="copied" x-cloak class="text-white">Copied!</span>
                                </button>
                            </div>
                        </div>
                        <form method="POST" action="{{ route('script-registrations.regenerate-token', $registration) }}">
                            @csrf
                            <button type="submit"
                                class="inline-flex items-center px-4 py-2 bg-amber-600 text-white text-sm font-medium rounded-md hover:bg-amber-700 transition"
                                onclick="return confirm('This will invalidate the current URL. Are you sure?')">
                                Regenerate Token
                            </button>
                        </form>
                    </div>
                </div>
            @endif

            {{-- Child Registrations (for unlimited) --}}
            @if($registration->isUnlimited() && $registration->children->isNotEmpty())
                <div class="bg-white shadow-sm sm:rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-100">
                        <h3 class="text-base font-semibold text-gray-900">
                            Registered Scripts ({{ $registration->children->count() }})
                        </h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Reg ID</th>
                                    <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Title</th>
                                    <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Author</th>
                                    <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Registered</th>
                                    <th class="px-4 py-2 text-center text-xs font-semibold text-gray-500 uppercase">Status</th>
                                    <th class="px-4 py-2 w-16"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($registration->children as $child)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-2.5 font-mono text-xs">{{ $child->registration_id }}</td>
                                        <td class="px-4 py-2.5 text-gray-900">{{ $child->script_title }}</td>
                                        <td class="px-4 py-2.5 text-gray-700">{{ $child->author_first }} {{ $child->author_last }}</td>
                                        <td class="px-4 py-2.5 text-xs text-gray-500">{{ $child->registered_at->format('M j, Y') }}</td>
                                        <td class="px-4 py-2.5 text-center">
                                            @php
                                                $childBadge = match($child->status) {
                                                    'completed' => 'bg-green-100 text-green-800',
                                                    'pending'   => 'bg-amber-100 text-amber-800',
                                                    'failed'    => 'bg-red-100 text-red-800',
                                                    default     => 'bg-gray-100 text-gray-600',
                                                };
                                            @endphp
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $childBadge }}">
                                                {{ ucfirst($child->status) }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-2.5 text-right">
                                            <a href="{{ route('script-registrations.show', $child) }}"
                                               class="text-indigo-600 hover:text-indigo-800 text-xs font-medium">View</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
