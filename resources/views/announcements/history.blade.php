<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Announcements</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-5">

            @if(session('success'))
                <div class="px-4 py-3 rounded bg-green-50 border border-green-200 text-green-800 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            {{-- Post new announcement — admins and editors only --}}
            @if(auth()->user()->canManageAssignments())
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
                <h3 class="text-sm font-semibold text-gray-700 mb-4">Post Announcement</h3>
                <form method="POST" action="{{ route('announcements.store') }}" class="space-y-3">
                    @csrf
                    <div>
                        <textarea id="announcement_body" name="body" rows="2"
                                  class="block w-full text-sm rounded-md border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                  placeholder="e.g. SR will be closed Monday for a holiday. No new assignments will be sent."
                                  maxlength="2000" required></textarea>
                        <x-input-error :messages="$errors->get('body')" class="mt-1" />
                    </div>
                    <div class="flex items-end gap-4">
                        <div>
                            <x-input-label for="announcement_expires_at" value="Expires at (optional)" />
                            <input type="datetime-local" id="announcement_expires_at" name="expires_at"
                                   class="mt-1 block w-52 text-sm rounded-md border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500" />
                            <p class="mt-0.5 text-xs text-gray-400">Banner auto-hides after this time. Leave blank to never expire.</p>
                        </div>
                        <x-primary-button class="mb-0.5">Post</x-primary-button>
                    </div>
                </form>
            </div>
            @endif

            {{-- Announcement list --}}
            @if($announcements->isEmpty())
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 px-6 py-12 text-center text-gray-400 text-sm">
                    No announcements have been posted yet.
                </div>
            @else
                <div class="space-y-3">
                    @foreach($announcements as $ann)
                    @php
                        $annExpired   = $ann->isExpired();
                        $read         = $ann->reads->first();
                        $wasRead      = $read?->read_at !== null;
                        $wasDismissed = $read?->dismissed_at !== null;
                    @endphp
                    <div class="bg-white rounded-lg shadow-sm border {{ $annExpired ? 'border-gray-100' : 'border-amber-100' }} px-5 py-4
                                {{ $annExpired ? 'opacity-70' : '' }}">
                        <div class="flex items-start justify-between gap-4">
                            <p class="text-sm text-gray-800 leading-relaxed flex-1">{{ $ann->body }}</p>
                            <div class="flex items-center gap-2 shrink-0">
                                @if(!$annExpired && !$wasDismissed)
                                    <span class="text-[10px] font-semibold uppercase tracking-wide text-amber-600 bg-amber-50 border border-amber-200 rounded px-1.5 py-0.5">Active</span>
                                @elseif($annExpired)
                                    <span class="text-[10px] font-semibold uppercase tracking-wide text-gray-400 bg-gray-50 border border-gray-200 rounded px-1.5 py-0.5">Expired</span>
                                @endif
                                @if(auth()->user()->canManageAssignments())
                                    <form method="POST" action="{{ route('announcements.destroy', $ann) }}"
                                          onsubmit="return confirm('Delete this announcement for all users?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-red-400 hover:text-red-600 text-xs underline whitespace-nowrap">Delete</button>
                                    </form>
                                @endif
                            </div>
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

                <div class="mt-2">
                    {{ $announcements->links() }}
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
