<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Readers</h2>
            <a href="{{ route('readers.create') }}"
               class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 transition ease-in-out duration-150">
                + Add Reader
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8">

            @if (session('success'))
                <div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-800 rounded-md text-sm">
                    {{ session('success') }}
                </div>
            @endif

            @if (session('error'))
                <div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-800 rounded-md text-sm">
                    {{ session('error') }}
                </div>
            @endif

            @if ($readers->isEmpty())
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-12 text-center text-gray-500">
                    No readers yet. <a href="{{ route('readers.create') }}" class="text-indigo-600 hover:underline">Add one.</a>
                </div>
            @else
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reader</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Active</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Completed</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Total</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Capacity</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">PayPal</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100">
                            @foreach ($readers as $reader)
                                @php
                                    $profile  = $reader->readerProfile;
                                    $initials = $profile?->initials ?? strtoupper(substr($reader->name, 0, 2));
                                    $max      = $profile?->max_concurrent_assignments ?? 0;
                                    $active   = $reader->active_count;
                                    $atCap    = $max > 0 && $active >= $max;
                                @endphp
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-3">
                                            <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-gray-200 text-gray-700 text-xs font-mono font-semibold shrink-0">
                                                {{ $initials }}
                                            </span>
                                            <div>
                                                <div class="font-medium text-gray-900">
                                                    {{ $profile?->displayName() ?? $reader->name }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-gray-500">{{ $reader->email }}</td>
                                    <td class="px-4 py-3 tabular-nums">
                                        <span class="{{ $atCap ? 'text-amber-700 font-semibold' : 'text-gray-700' }}">
                                            {{ $active }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-gray-700 tabular-nums">{{ $reader->completed_count }}</td>
                                    <td class="px-4 py-3 text-gray-500 tabular-nums">{{ $reader->total_count }}</td>
                                    <td class="px-4 py-3 text-gray-500 tabular-nums">
                                        {{ $active }}/{{ $max ?: '—' }}
                                        @if ($atCap)
                                            <span class="ml-1 text-[10px] font-bold text-amber-600 uppercase">Full</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-gray-500 text-xs">
                                        {{ $profile?->paypal_email ?? '—' }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <a href="{{ route('readers.edit', $reader) }}"
                                               class="inline-flex items-center px-2.5 py-1 bg-white border border-gray-300 rounded text-xs font-medium text-gray-700 hover:bg-gray-50 transition">
                                                Edit
                                            </a>
                                            <form method="POST" action="{{ route('readers.destroy', $reader) }}"
                                                  onsubmit="return confirm('Delete {{ addslashes($profile?->displayName() ?? $reader->name) }}? This cannot be undone.')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                        class="inline-flex items-center px-2.5 py-1 bg-white border border-red-300 rounded text-xs font-medium text-red-600 hover:bg-red-50 transition">
                                                    Delete
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
