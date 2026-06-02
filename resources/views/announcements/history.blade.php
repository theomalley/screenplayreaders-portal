<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Announcements</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">

            @if($announcements->isEmpty())
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 px-6 py-12 text-center text-gray-400 text-sm">
                    No announcements have been posted yet.
                </div>
            @else
                <div class="space-y-3">
                    @foreach($announcements as $ann)
                    @php
                        $annExpired  = $ann->isExpired();
                        $read        = $ann->reads->first();
                        $wasRead     = $read?->read_at !== null;
                        $wasDismissed = $read?->dismissed_at !== null;
                    @endphp
                    <div class="bg-white rounded-lg shadow-sm border {{ $annExpired ? 'border-gray-100' : 'border-amber-100' }} px-5 py-4
                                {{ $annExpired ? 'opacity-70' : '' }}">
                        <div class="flex items-start justify-between gap-4">
                            <p class="text-sm text-gray-800 leading-relaxed flex-1">{{ $ann->body }}</p>
                            @if(!$annExpired && !$wasDismissed)
                                <span class="shrink-0 text-[10px] font-semibold uppercase tracking-wide text-amber-600 bg-amber-50 border border-amber-200 rounded px-1.5 py-0.5">Active</span>
                            @elseif($annExpired)
                                <span class="shrink-0 text-[10px] font-semibold uppercase tracking-wide text-gray-400 bg-gray-50 border border-gray-200 rounded px-1.5 py-0.5">Expired</span>
                            @endif
                        </div>

                        <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-400">
                            <span>Posted by <span class="text-gray-600">{{ $ann->createdBy?->name ?? 'Staff' }}</span></span>
                            <span>&middot;</span>
                            <span>{{ $ann->created_at->setTimezone($appTimezone)->format('M j, Y \a\t g:i A') }}</span>

                            @if($ann->expires_at)
                                <span>&middot;</span>
                                <span class="{{ $annExpired ? 'text-red-400' : 'text-amber-500' }}">
                                    {{ $annExpired ? 'Expired' : 'Expires' }}
                                    {{ $ann->expires_at->setTimezone($appTimezone)->format('M j, Y \a\t g:i A') }}
                                </span>
                            @endif

                            @if($wasRead)
                                <span>&middot;</span>
                                <span class="text-green-500">&#10003; Read {{ $read->read_at->setTimezone($appTimezone)->format('M j') }}</span>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>

                <div class="mt-6">
                    {{ $announcements->links() }}
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
