<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('budget-orders.index') }}" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Budget {{ $order->woo_order_id }}
            </h2>
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $order->topsheet_only ? 'bg-gray-100 text-gray-700' : 'bg-blue-100 text-blue-800' }}">
                {{ $order->topsheet_only ? 'Topsheet Only' : 'Full Budget' }}
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

            {{-- Order Details --}}
            <div class="bg-white shadow-sm sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h3 class="text-base font-semibold text-gray-900">Order Details</h3>
                </div>
                <div class="px-6 py-4">
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-3 text-sm">
                        <div>
                            <dt class="text-gray-500">Order ID</dt>
                            <dd class="font-mono font-medium text-gray-900">{{ $order->woo_order_id }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Status</dt>
                            <dd>
                                @php
                                    $statusBadge = match($order->status) {
                                        'completed'  => 'bg-green-100 text-green-800',
                                        'processing' => 'bg-blue-100 text-blue-800',
                                        'pending'    => 'bg-amber-100 text-amber-800',
                                        'failed'     => 'bg-red-100 text-red-800',
                                        default      => 'bg-gray-100 text-gray-600',
                                    };
                                @endphp
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $statusBadge }}">
                                    {{ ucfirst($order->status) }}
                                </span>
                                @if($order->status === 'failed' && $order->error_message)
                                    <p class="mt-1 text-xs text-red-600">{{ $order->error_message }}</p>
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Customer Name</dt>
                            <dd class="text-gray-900">{{ $order->customer_name }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Customer Email</dt>
                            <dd class="text-gray-900">{{ $order->customer_email }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Budget Amount</dt>
                            <dd class="font-mono font-medium text-gray-900">${{ number_format($order->budget_amount, 0) }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Budget Class</dt>
                            <dd class="text-gray-900">{{ $order->budget_class }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">State</dt>
                            <dd class="text-gray-900">{{ $order->state }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Type</dt>
                            <dd class="text-gray-900">{{ $order->topsheet_only ? 'PDF Topsheet Only' : 'Editable Excel + PDF' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Created</dt>
                            <dd class="text-gray-900">{{ $order->created_at->format('F j, Y g:i A') }}</dd>
                        </div>
                        @if($order->completed_at)
                            <div>
                                <dt class="text-gray-500">Completed</dt>
                                <dd class="text-gray-900">{{ $order->completed_at->format('F j, Y g:i A') }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>
            </div>

            {{-- Header Data --}}
            @if($order->header_data)
                <div class="bg-white shadow-sm sm:rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-100">
                        <h3 class="text-base font-semibold text-gray-900">Project Header</h3>
                    </div>
                    <div class="px-6 py-4">
                        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-3 text-sm">
                            @if($order->header_data['title'] ?? null)
                                <div>
                                    <dt class="text-gray-500">Project Title</dt>
                                    <dd class="text-gray-900">{{ $order->header_data['title'] }}</dd>
                                </div>
                            @endif
                            @if(($order->header_data['name_first'] ?? null) || ($order->header_data['name_last'] ?? null))
                                <div>
                                    <dt class="text-gray-500">Name</dt>
                                    <dd class="text-gray-900">{{ $order->header_data['name_first'] ?? '' }} {{ $order->header_data['name_last'] ?? '' }}</dd>
                                </div>
                            @endif
                            @if($order->header_data['date'] ?? null)
                                <div>
                                    <dt class="text-gray-500">Date</dt>
                                    <dd class="text-gray-900">{{ $order->header_data['date'] }}</dd>
                                </div>
                            @endif
                        </dl>
                    </div>
                </div>
            @endif

            {{-- Guilds & Production --}}
            <div class="bg-white shadow-sm sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h3 class="text-base font-semibold text-gray-900">Production Details</h3>
                </div>
                <div class="px-6 py-4">
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-3 text-sm">
                        <div>
                            <dt class="text-gray-500">Guilds</dt>
                            <dd class="text-gray-900">
                                @php
                                    $guilds = collect([
                                        'SAG' => $order->guild_sag,
                                        'WGA' => $order->guild_wga,
                                        'DGA' => $order->guild_dga,
                                        'IATSE' => $order->guild_iatse,
                                        'Teamsters' => $order->guild_teamsters,
                                    ])->filter()->keys();
                                @endphp
                                @if($guilds->isNotEmpty())
                                    {{ $guilds->implode(', ') }}
                                @else
                                    <span class="text-gray-400">None</span>
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Cast Size</dt>
                            <dd class="text-gray-900">{{ $order->cast_size }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Weeks Prep</dt>
                            <dd class="text-gray-900">{{ $order->weeks_prep }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Weeks Shoot</dt>
                            <dd class="text-gray-900">{{ $order->weeks_shoot }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Weeks Wrap</dt>
                            <dd class="text-gray-900">{{ $order->weeks_wrap }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Weeks Post</dt>
                            <dd class="text-gray-900">{{ $order->weeks_post }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            {{-- Form Input Data --}}
            @if($order->form_input_data)
                <div class="bg-white shadow-sm sm:rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-100">
                        <h3 class="text-base font-semibold text-gray-900">Form Input Data</h3>
                    </div>
                    <div class="px-6 py-4">
                        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-2 text-sm">
                            @foreach($order->form_input_data as $key => $value)
                                @if($value !== '' && $value !== null)
                                    <div>
                                        <dt class="text-gray-500 font-mono text-xs">{{ $key }}</dt>
                                        <dd class="text-gray-900">{{ is_array($value) ? json_encode($value) : $value }}</dd>
                                    </div>
                                @endif
                            @endforeach
                        </dl>
                    </div>
                </div>
            @endif

            {{-- Download & Regenerate --}}
            <div class="bg-white shadow-sm sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h3 class="text-base font-semibold text-gray-900">Files</h3>
                </div>
                <div class="px-6 py-4 flex flex-wrap gap-3">
                    @if($order->drive_pdf_id)
                        <a href="{{ route('budget-orders.download-pdf', $order) }}"
                           class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700 transition">
                            Download PDF
                        </a>
                    @endif
                    @if($order->drive_xlsx_id && !$order->topsheet_only)
                        <a href="{{ route('budget-orders.download-xlsx', $order) }}"
                           class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700 transition">
                            Download Excel
                        </a>
                    @endif
                    <form method="POST" action="{{ route('budget-orders.regenerate', $order) }}">
                        @csrf
                        <button type="submit"
                            class="inline-flex items-center px-4 py-2 bg-gray-600 text-white text-sm font-medium rounded-md hover:bg-gray-700 transition">
                            Regenerate Files
                        </button>
                    </form>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
