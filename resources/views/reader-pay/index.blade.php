<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Reader Pay</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            @if(session('success'))
                <div class="bg-green-50 border border-green-200 text-green-700 rounded-lg px-4 py-3 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            {{-- Unpaid section --}}
            @if($byReader->isEmpty())
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 px-6 py-12 text-center text-gray-400 text-sm">
                    No unpaid coverages. All readers are paid up.
                </div>
            @else
                @foreach($byReader as $readerData)
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 bg-amber-50">
                        <div>
                            <span class="font-semibold text-gray-800">{{ $readerData['reader_name'] }}</span>
                            @if($readerData['paypal_email'])
                                <span class="ml-2 text-sm text-gray-500">· PayPal: <span class="font-mono text-xs">{{ $readerData['paypal_email'] }}</span></span>
                            @endif
                            <span class="ml-3 text-sm font-semibold text-amber-700">
                                {{ $readerData['assignments']->count() }} coverage{{ $readerData['assignments']->count() === 1 ? '' : 's' }}
                                &nbsp;·&nbsp; ${{ number_format($readerData['total_owed'], 2) }} owed
                            </span>
                        </div>
                        <form method="POST" action="{{ route('reader-pay.mark-paid', $readerData['reader_id']) }}"
                            onsubmit="return confirm('Mark all {{ $readerData['assignments']->count() }} coverages for {{ $readerData['reader_name'] }} as paid (${{ number_format($readerData['total_owed'], 2) }})?')">
                            @csrf
                            <button type="submit"
                                class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-white bg-green-600 hover:bg-green-700 rounded-md transition-colors">
                                Mark All Paid
                            </button>
                        </form>
                    </div>
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead class="bg-gray-50 text-xs font-medium text-gray-500 uppercase tracking-wide">
                            <tr>
                                <th class="px-4 py-2 text-left">Order</th>
                                <th class="px-4 py-2 text-left">Title</th>
                                <th class="px-4 py-2 text-left">Type</th>
                                <th class="px-4 py-2 text-left">Completed</th>
                                <th class="px-4 py-2 text-right">Pay Rate</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach($readerData['assignments'] as $assignment)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2 font-mono text-xs text-gray-600">{{ $assignment->order_number }}</td>
                                <td class="px-4 py-2 text-gray-800">{{ $assignment->script_title }}</td>
                                <td class="px-4 py-2 text-gray-500 text-xs uppercase">{{ $assignment->assignment_type }}</td>
                                <td class="px-4 py-2 text-gray-500 text-xs">{{ $assignment->completed_at?->format('M j, Y') }}</td>
                                <td class="px-4 py-2 text-right font-medium text-gray-700">${{ number_format($assignment->pay_rate, 2) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endforeach
            @endif

            {{-- Recent paid --}}
            @if($recentPaid->isNotEmpty())
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h3 class="text-sm font-semibold text-gray-600 uppercase tracking-wide">Recently Paid (last 50)</h3>
                </div>
                <table class="min-w-full divide-y divide-gray-100 text-sm">
                    <thead class="bg-gray-50 text-xs font-medium text-gray-500 uppercase tracking-wide">
                        <tr>
                            <th class="px-4 py-2 text-left">Paid On</th>
                            <th class="px-4 py-2 text-left">Reader</th>
                            <th class="px-4 py-2 text-left">Order</th>
                            <th class="px-4 py-2 text-left">Title</th>
                            <th class="px-4 py-2 text-right">Pay Rate</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($recentPaid as $assignment)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-2 text-gray-500 text-xs">{{ $assignment->reader_paid_at?->format('M j, Y') }}</td>
                            <td class="px-4 py-2 text-gray-700">
                                {{ $assignment->assignedReader?->readerProfile?->displayName() ?? $assignment->assignedReader?->name }}
                            </td>
                            <td class="px-4 py-2 font-mono text-xs text-gray-600">{{ $assignment->order_number }}</td>
                            <td class="px-4 py-2 text-gray-700">{{ $assignment->script_title }}</td>
                            <td class="px-4 py-2 text-right text-gray-600">${{ number_format($assignment->pay_rate, 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif

        </div>
    </div>
</x-app-layout>
