<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Followup History — Order #{{ $orderNumber }}
                </h2>
                @if ($representative)
                    <p class="text-sm text-gray-500 mt-0.5">
                        {{ $representative->script_title }}
                        @if ($representative->writer_name)
                            &middot; {{ $representative->writer_name }}
                        @endif
                    </p>
                @endif
            </div>
            <a href="{{ route('archive.index') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Archive</a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">

            @if (session('success'))
                <div class="bg-green-50 border border-green-200 text-green-700 text-sm rounded px-4 py-2">
                    {{ session('success') }}
                </div>
            @endif
            @if (session('error'))
                <div class="bg-red-50 border border-red-200 text-red-700 text-sm rounded px-4 py-2">
                    {{ session('error') }}
                </div>
            @endif

            @foreach ($tokens as $round => $token)
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">

                    {{-- Round header --}}
                    <div class="px-5 py-3 bg-gray-50 border-b border-gray-200 flex items-center justify-between gap-4">
                        <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">
                            Round {{ $round + 1 }}
                        </span>
                        <div class="flex items-center gap-4">
                            <span class="text-xs text-gray-400">
                                Token generated {{ $token->created_at->setTimezone($appTimezone)->format('M j, Y g:ia') }}
                                &middot; expires {{ $token->expires_at->setTimezone($appTimezone)->format('M j, Y') }}
                            </span>
                            @if(auth()->user()->isAdmin())
                            <form method="POST" action="{{ route('followupTokens.destroy', $token) }}"
                                  onsubmit="return confirm('Delete this entire round including all questions and responses?')">
                                @csrf @method('DELETE')
                                <button type="submit"
                                        class="text-xs text-red-400 hover:text-red-600 transition">
                                    Delete round
                                </button>
                            </form>
                            @endif
                        </div>
                    </div>

                    @forelse ($token->followupQuestions->sortBy('created_at') as $fq)
                        @php
                            $assignment = $fq->assignment;
                            $reader     = $assignment?->assignedReader;
                            $initials   = $reader?->readerProfile?->initials
                                ?? ($reader ? strtoupper(substr($reader->name, 0, 2)) : '??');
                            $typeLabel  = match($assignment?->assignment_type) {
                                'script_coverage'   => 'Script Coverage',
                                'notes_only'        => 'Notes-Only',
                                'deep_dive'         => 'Deep-Dive Dev Notes',
                                'short'             => 'Short Script Coverage',
                                default             => ucwords(str_replace('_', ' ', $assignment?->assignment_type ?? '')),
                            };
                            $statusColor = match($fq->status) {
                                'pending'    => 'bg-gray-100 text-gray-600',
                                'unanswered' => 'bg-amber-100 text-amber-700',
                                'answered'   => 'bg-green-100 text-green-700',
                                'complete'   => 'bg-blue-100 text-blue-700',
                                default      => 'bg-gray-100 text-gray-500',
                            };
                        @endphp

                        <div class="px-5 py-4 border-b border-gray-100 last:border-b-0">

                            {{-- Slot header --}}
                            <div class="flex items-center gap-3 mb-4">
                                <span class="text-sm font-semibold text-gray-800">
                                    Reader <span class="font-mono text-indigo-600">{{ $initials }}</span>
                                </span>
                                <span class="text-xs text-gray-400">{{ $typeLabel }}</span>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $statusColor }}">
                                    {{ ucfirst($fq->status) }}
                                </span>
                            </div>

                            <div class="space-y-4">

                                {{-- Customer questions --}}
                                <div>
                                    <div class="flex items-baseline gap-2 mb-1">
                                        <p class="text-xs font-semibold text-gray-600">Customer's questions</p>
                                        <span class="text-[10px] text-gray-400">
                                            submitted {{ $fq->created_at->setTimezone($appTimezone)->format('M j, Y g:ia') }}
                                        </span>
                                    </div>
                                    <div class="text-sm text-gray-800 whitespace-pre-wrap bg-gray-50 border border-gray-200 rounded px-3 py-2">{{ $fq->customer_questions ?? '—' }}</div>
                                    @if ($fq->edited_questions && $fq->edited_questions !== $fq->customer_questions)
                                        <div class="mt-2">
                                            <p class="text-[10px] text-gray-400 mb-1">Edited version shown to reader</p>
                                            <div class="text-sm text-gray-700 whitespace-pre-wrap bg-amber-50 border border-amber-200 rounded px-3 py-2">{{ $fq->edited_questions }}</div>
                                        </div>
                                    @endif
                                </div>

                                {{-- Sent to reader --}}
                                @if ($fq->unanswered_at)
                                    <div class="flex items-center gap-2 text-xs text-amber-700">
                                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                                        </svg>
                                        Sent to reader {{ $fq->unanswered_at->setTimezone($appTimezone)->format('M j, Y g:ia') }}
                                    </div>
                                @endif

                                {{-- Reader response --}}
                                @if ($fq->reader_response)
                                    <div>
                                        <div class="flex items-baseline gap-2 mb-1">
                                            <p class="text-xs font-semibold text-gray-600">Reader's response</p>
                                            <span class="text-[10px] text-gray-400">
                                                received {{ $fq->updated_at->setTimezone($appTimezone)->format('M j, Y g:ia') }}
                                            </span>
                                        </div>
                                        <div class="text-sm text-gray-800 whitespace-pre-wrap bg-gray-50 border border-gray-200 rounded px-3 py-2">{{ $fq->reader_response }}</div>
                                        @if ($fq->edited_response && $fq->edited_response !== $fq->reader_response)
                                            <div class="mt-2">
                                                <p class="text-[10px] text-gray-400 mb-1">Edited version sent to customer</p>
                                                <div class="text-sm text-gray-700 whitespace-pre-wrap bg-green-50 border border-green-200 rounded px-3 py-2">{{ $fq->edited_response }}</div>
                                            </div>
                                        @endif
                                        <form method="POST" action="{{ route('followups.regenerate-draft', $fq) }}" class="mt-2">
                                            @csrf
                                            <button type="submit" class="px-3 py-1 text-xs bg-indigo-600 text-white rounded hover:bg-indigo-700">
                                                Regenerate HelpScout Draft
                                            </button>
                                        </form>
                                    </div>
                                @endif

                            </div>
                        </div>

                    @empty
                        <div class="px-5 py-4 text-sm text-gray-400 italic">No questions submitted for this round.</div>
                    @endforelse

                </div>
            @endforeach

        </div>
    </div>
</x-app-layout>
