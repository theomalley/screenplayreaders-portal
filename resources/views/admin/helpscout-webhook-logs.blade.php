<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">HelpScout Webhook Logs</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <p class="text-sm text-gray-500">
                Every delivery to <code class="text-xs bg-gray-100 rounded px-1 py-0.5">/api/helpscout-webhook</code>
                is logged here, including ones that fail signature verification. Use
                <code class="text-xs bg-gray-100 rounded px-1 py-0.5">php artisan helpscout:simulate-webhook {conversation_id}</code>
                to send a test delivery.
            </p>

            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-xs font-medium text-gray-500 uppercase tracking-wide">
                            <tr>
                                <th class="px-4 py-3 text-left">Received</th>
                                <th class="px-4 py-3 text-left">Event</th>
                                <th class="px-4 py-3 text-left">Conversation ID</th>
                                <th class="px-4 py-3 text-center">Signature</th>
                                <th class="px-4 py-3 text-left">Payload</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($logs as $log)
                                <tr class="align-top">
                                    <td class="px-4 py-3 whitespace-nowrap text-gray-600">{{ $log->created_at->format('M j, Y g:i:s A') }}</td>
                                    <td class="px-4 py-3 text-gray-700">{{ $log->event ?? '—' }}</td>
                                    <td class="px-4 py-3 text-gray-700 font-mono">{{ $log->helpscout_conversation_id ?? '—' }}</td>
                                    <td class="px-4 py-3 text-center">
                                        @if($log->signature_valid)
                                            <span class="text-green-600">✓</span>
                                        @else
                                            <span class="text-red-600">✗</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <details>
                                            <summary class="cursor-pointer text-indigo-600 hover:text-indigo-800 text-xs">View payload</summary>
                                            <pre class="mt-2 text-xs bg-gray-50 border border-gray-200 rounded-md p-3 overflow-x-auto">{{ json_encode($log->payload, JSON_PRETTY_PRINT) }}</pre>
                                        </details>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-12 text-center text-gray-400">No webhook deliveries logged yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{ $logs->links() }}

        </div>
    </div>
</x-app-layout>
