<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Notification History</h2>
            @if($notifications->isNotEmpty())
                <form method="POST" action="{{ route('notification-history.destroyAll') }}"
                      onsubmit="return confirm('Clear all notification history? This cannot be undone.')">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                            class="inline-flex items-center px-3 py-1.5 bg-gray-100 border border-gray-200 rounded text-xs font-medium text-gray-600 hover:bg-gray-200 transition">
                        Clear All
                    </button>
                </form>
            @endif
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-4">

            @if(session('success'))
                <div class="px-4 py-3 rounded bg-green-50 border border-green-200 text-green-800 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg divide-y divide-gray-100">
                @forelse($notifications as $notification)
                    <div class="px-4 py-3 flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-gray-800">
                                @if($notification->url)
                                    <a href="{{ $notification->url }}" class="hover:underline">{{ $notification->title }}</a>
                                @else
                                    {{ $notification->title }}
                                @endif
                            </p>
                            @if($notification->body)
                                <p class="text-sm text-gray-600 whitespace-pre-line mt-0.5">{{ $notification->body }}</p>
                            @endif
                            <p class="text-xs text-gray-400 mt-1">{{ $notification->created_at->format('M j, Y g:i A') }}</p>
                        </div>
                        <form method="POST" action="{{ route('notification-history.destroy', $notification) }}" class="shrink-0">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-xs text-gray-400 hover:text-red-500 transition">Clear</button>
                        </form>
                    </div>
                @empty
                    <div class="px-4 py-8 text-center text-sm text-gray-400">No notifications yet.</div>
                @endforelse
            </div>

        </div>
    </div>
</x-app-layout>
