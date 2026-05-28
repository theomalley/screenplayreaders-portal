<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-4">
            <a href="{{ route('assignments.index') }}" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                WD Coverage — #{{ $assignment->order_number }}
                @if($assignment->rush)
                    <span class="ml-2 text-sm font-bold text-amber-600 uppercase tracking-wide">Rush</span>
                @endif
            </h2>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            {{-- Read-only assignment info --}}
            @php
                $wdTypeLabels = [
                    'coverage'          => 'Coverage',
                    'development_notes' => 'Development Notes',
                ];
                $typeDisplay  = $wdTypeLabels[$assignment->assignment_type] ?? ucfirst(str_replace('_', ' ', $assignment->assignment_type ?? '—'));
                $writerDisplay = $existing?->writer_name ?? $assignment->writer_name;
            @endphp
            <div class="bg-indigo-50 border border-indigo-200 rounded-lg p-4 grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm">
                <div><span class="text-indigo-500 font-medium block">Script</span>{{ $assignment->script_title }}</div>
                <div><span class="text-indigo-500 font-medium block">Writer</span>{{ $writerDisplay }}</div>
                <div><span class="text-indigo-500 font-medium block">Pages</span>{{ $assignment->page_count }}</div>
                <div><span class="text-indigo-500 font-medium block">Rate</span>${{ number_format($assignment->pay_rate, 2) }}</div>
                <div><span class="text-indigo-500 font-medium block">Type</span>{{ $typeDisplay }}</div>
                <div><span class="text-indigo-500 font-medium block">Request?</span>{{ $assignment->requested_reader_id ? 'Yes' : 'No' }}</div>
                <div><span class="text-indigo-500 font-medium block">Reader</span>{{ auth()->user()->readerProfile?->initials ?? '—' }}</div>
            </div>

            @if ($errors->any())
                <div class="bg-red-50 border border-red-200 rounded-lg p-4 text-sm text-red-700">
                    <strong>Please correct the following:</strong>
                    <ul class="mt-1 list-disc list-inside space-y-0.5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- DEV ONLY: remove before launch --}}
            <button type="button" onclick="wdAutofill()"
                class="w-full py-2 text-xs font-bold text-white bg-orange-500 hover:bg-orange-600 rounded-lg">
                DEV: Autofill test data
            </button>

            <form method="POST" action="{{ route('coverage.store', $assignment) }}"
                  x-data="wdCoverage()" x-cloak
                  @submit="submitting = true">
                @csrf

                {{-- ── Section 1: Assignment Metadata ──────────────────────────────────── --}}
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-5">
                    <h3 class="font-semibold text-gray-700 text-base border-b border-gray-100 pb-2">Assignment Details</h3>

                    <input type="hidden" name="wd_assignment_type" value="{{ old('wd_assignment_type', $existing?->wd_assignment_type ?? $assignment->assignment_type) }}" />

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="genre" value="Genre" />
                            <x-text-input id="genre" name="genre" type="text" class="mt-1 block w-full"
                                value="{{ old('genre', $existing?->genre) }}" required />
                            <x-input-error :messages="$errors->get('genre')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="time_period" value="Time Period" />
                            <x-text-input id="time_period" name="time_period" type="text" class="mt-1 block w-full"
                                value="{{ old('time_period', $existing?->time_period) }}" required />
                            <x-input-error :messages="$errors->get('time_period')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="locations" value="Location(s)" />
                            <x-text-input id="locations" name="locations" type="text" class="mt-1 block w-full"
                                value="{{ old('locations', $existing?->locations) }}" required />
                            <x-input-error :messages="$errors->get('locations')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="estimated_budget" value="Estimated Budget" />
                            <x-text-input id="estimated_budget" name="estimated_budget" type="text" class="mt-1 block w-full"
                                value="{{ old('estimated_budget', $existing?->estimated_budget) }}"
                                placeholder="low / medium / high" required />
                            <x-input-error :messages="$errors->get('estimated_budget')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="wd_form" value="Form of Material" />
                            <x-text-input id="wd_form" name="wd_form" type="text" class="mt-1 block w-full"
                                value="{{ old('wd_form', $existing?->wd_form) }}"
                                placeholder="Screenplay / Treatment / Pilot / Short / other" required />
                            <x-input-error :messages="$errors->get('wd_form')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="wd_mpaa_rating" value="MPAA Rating (imagined)" />
                            <x-text-input id="wd_mpaa_rating" name="wd_mpaa_rating" type="text" class="mt-1 block w-full"
                                value="{{ old('wd_mpaa_rating', $existing?->wd_mpaa_rating) }}"
                                placeholder="G / PG / PG-13 / R / NC-17" required />
                            <x-input-error :messages="$errors->get('wd_mpaa_rating')" class="mt-1" />
                        </div>
                    </div>

                    <input type="hidden" name="wd_request" value="{{ $assignment->requested_reader_id ? 1 : 0 }}" />
                </div>

                {{-- ── Section 2: Content ───────────────────────────────────────────────── --}}
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-5">
                    <h3 class="font-semibold text-gray-700 text-base border-b border-gray-100 pb-2">Coverage Content</h3>

                    {{-- Logline --}}
                    <div>
                        <x-input-label for="wd_logline" value="Logline" />
                        <textarea id="wd_logline" name="wd_logline" rows="3"
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">{{ old('wd_logline', $existing?->wd_logline) }}</textarea>
                        <x-input-error :messages="$errors->get('wd_logline')" class="mt-1" />
                    </div>

                    {{-- Synopsis (Coverage type only) --}}
                    <div x-show="assignmentType === 'coverage'">
                        <div class="flex items-baseline justify-between">
                            <x-input-label for="wd_synopsis" value="Synopsis" />
                            <span class="text-xs" :class="wordCount(synopsis) >= 450 ? 'text-green-600' : 'text-gray-400'"
                                x-text="wordCount(synopsis) + ' words (min 450)'"></span>
                        </div>
                        <textarea id="wd_synopsis" name="wd_synopsis" rows="10"
                            x-model="synopsis"
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">{{ old('wd_synopsis', $existing?->wd_synopsis) }}</textarea>
                        <x-input-error :messages="$errors->get('wd_synopsis')" class="mt-1" />
                    </div>
                </div>

                {{-- ── Section 3: Notes (7 sections) ───────────────────────────────────── --}}
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-6">
                    <div class="flex items-baseline justify-between border-b border-gray-100 pb-2">
                        <h3 class="font-semibold text-gray-700 text-base">Notes</h3>
                        <span class="text-xs" :class="totalNoteWords() >= notesMinWords() ? 'text-green-600' : 'text-gray-400'"
                            x-text="totalNoteWords() + ' total words (min ' + notesMinWords() + ')'"></span>
                    </div>

                    @php
                    $wdSections = [
                        ['key' => 'concept',    'label' => 'Concept'],
                        ['key' => 'plot',       'label' => 'Plot / Structure'],
                        ['key' => 'pacing',     'label' => 'Pacing'],
                        ['key' => 'format',     'label' => 'Format'],
                        ['key' => 'characters', 'label' => 'Characters'],
                        ['key' => 'dialogue',   'label' => 'Dialogue'],
                        ['key' => 'overall',    'label' => 'Overall'],
                    ];
                    @endphp

                    @foreach ($wdSections as $section)
                        <div class="space-y-2">
                            <div class="flex items-center gap-3">
                                <x-input-label value="{{ $section['label'] }}" class="min-w-28" />
                                <select name="wd_score_{{ $section['key'] }}"
                                    class="border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                                    @foreach (['Poor', 'Fair', 'Good', 'Excellent'] as $score)
                                        <option value="{{ $score }}"
                                            {{ old('wd_score_' . $section['key'], $existing?->{'wd_score_' . $section['key']}) === $score ? 'selected' : '' }}>
                                            {{ $score }}
                                        </option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('wd_score_' . $section['key'])" />
                            </div>
                            <textarea name="wd_notes_{{ $section['key'] }}" rows="5"
                                x-model="notes.{{ $section['key'] }}"
                                class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                                placeholder="{{ $section['label'] }} notes…">{{ old('wd_notes_' . $section['key'], $existing?->{'wd_notes_' . $section['key']}) }}</textarea>
                            <x-input-error :messages="$errors->get('wd_notes_' . $section['key'])" class="mt-1" />
                        </div>
                    @endforeach
                </div>

                {{-- ── Section 4: Final Fields ──────────────────────────────────────────── --}}
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-5">
                    <h3 class="font-semibold text-gray-700 text-base border-b border-gray-100 pb-2">Final Assessment</h3>

                    <div>
                        <x-input-label for="wd_script_recommendations" value="Script Recommendations" />
                        <p class="text-xs text-gray-400 mt-0.5">Titles of scripts or films this shares a vibe with</p>
                        <x-text-input id="wd_script_recommendations" name="wd_script_recommendations" type="text"
                            class="mt-1 block w-full"
                            value="{{ old('wd_script_recommendations', $existing?->wd_script_recommendations) }}" required />
                        <x-input-error :messages="$errors->get('wd_script_recommendations')" class="mt-1" />
                    </div>

                    <div class="grid grid-cols-2 gap-5">
                        <div>
                            <x-input-label for="wd_recommend_writer" value="Recommend Writer?" />
                            <select id="wd_recommend_writer" name="wd_recommend_writer"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                                @foreach (['Pass', 'Consider', 'Recommend'] as $opt)
                                    <option value="{{ $opt }}" {{ old('wd_recommend_writer', $existing?->wd_recommend_writer) === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('wd_recommend_writer')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="wd_recommend_material" value="Recommend Material?" />
                            <select id="wd_recommend_material" name="wd_recommend_material"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                                @foreach (['Pass', 'Consider', 'Recommend'] as $opt)
                                    <option value="{{ $opt }}" {{ old('wd_recommend_material', $existing?->wd_recommend_material) === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('wd_recommend_material')" class="mt-1" />
                        </div>
                    </div>

                    {{-- Quality check --}}
                    <div class="border-t border-gray-100 pt-4">
                        <label class="flex items-start gap-3 cursor-pointer">
                            <input id="quality_checked" name="quality_checked" type="checkbox" value="1"
                                x-model="qualityChecked"
                                {{ old('quality_checked') ? 'checked' : '' }}
                                class="mt-0.5 rounded border-gray-300 text-green-600 shadow-sm focus:ring-green-500" />
                            <span class="text-sm text-gray-700 font-medium">
                                I have reviewed this coverage and confirm it is complete, accurate, and ready for QC.
                            </span>
                        </label>
                        <x-input-error :messages="$errors->get('quality_checked')" class="mt-1" />
                    </div>

                    {{-- Submit --}}
                    <div class="flex items-center justify-between pt-2">
                        <a href="{{ route('assignments.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
                        <div class="flex items-center gap-3">
                            <span x-show="draftSaved" x-cloak class="text-sm text-green-600 font-medium">Saved!</span>
                            <span x-show="draftError" x-cloak class="text-sm text-red-500">Error saving.</span>
                            <button type="button"
                                :disabled="draftSaving || submitting"
                                @click="saveDraft()"
                                class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 transition-colors inline-flex items-center gap-2 disabled:opacity-50">
                                <svg x-show="draftSaving" class="animate-spin h-4 w-4 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span x-text="draftSaving ? 'Saving…' : 'Save for Later'"></span>
                            </button>
                            <button type="submit"
                                :disabled="!qualityChecked || submitting"
                                :class="(qualityChecked && !submitting) ? 'bg-indigo-600 hover:bg-indigo-700' : 'bg-gray-300 cursor-not-allowed'"
                                class="px-4 py-2 text-sm font-semibold text-white rounded-md transition-colors inline-flex items-center gap-2">
                                <svg x-show="submitting" class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span x-text="submitting ? 'Submitting…' : 'Submit Coverage'"></span>
                            </button>
                        </div>
                    </div>
                </div>

            </form>
        </div>
    </div>

    <script>
    // DEV ONLY — remove before launch
    function wdAutofill() {
        const short  = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.';
        const medium = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur.';

        const fill = (el, val) => { if (el) { el.value = val; el.dispatchEvent(new Event('input', { bubbles: true })); } };

        fill(document.getElementById('genre'),                    'Drama');
        fill(document.getElementById('time_period'),              'Contemporary');
        fill(document.getElementById('locations'),                'Los Angeles');
        fill(document.getElementById('estimated_budget'),         'medium');
        fill(document.getElementById('wd_form'),                  'Screenplay');
        fill(document.getElementById('wd_mpaa_rating'),           'R');
        fill(document.getElementById('wd_logline'),               'A struggling writer discovers a mysterious manuscript that begins rewriting itself.');
        fill(document.getElementById('wd_synopsis'),              medium);
        fill(document.getElementById('wd_script_recommendations'),'Chinatown, The Big Lebowski');

        ['concept','plot','pacing','format','characters','dialogue','overall'].forEach(section => {
            fill(document.querySelector(`textarea[name="wd_notes_${section}"]`), medium);
            document.querySelector(`select[name="wd_score_${section}"]`).value = 'Good';
        });

        document.querySelector('select[name="wd_recommend_writer"]').value   = 'Consider';
        document.querySelector('select[name="wd_recommend_material"]').value = 'Consider';

        const qc = document.getElementById('quality_checked');
        qc.checked = true;
        qc.dispatchEvent(new Event('input', { bubbles: true }));
    }

    function wdCoverage() {
        return {
            assignmentType: '{{ old('wd_assignment_type', $existing?->wd_assignment_type ?? $assignment->assignment_type ?? 'coverage') }}',
            qualityChecked: {{ old('quality_checked') ? 'true' : 'false' }},
            submitting: false,
            draftSaving: false,
            draftSaved: false,
            draftError: false,
            draftUrl: @js(route('coverage.draft', $assignment)),
            synopsis: @js(old('wd_synopsis', $existing?->wd_synopsis ?? '')),
            notes: {
                concept:    @js(old('wd_notes_concept',    $existing?->wd_notes_concept    ?? '')),
                plot:       @js(old('wd_notes_plot',       $existing?->wd_notes_plot       ?? '')),
                pacing:     @js(old('wd_notes_pacing',     $existing?->wd_notes_pacing     ?? '')),
                format:     @js(old('wd_notes_format',     $existing?->wd_notes_format     ?? '')),
                characters: @js(old('wd_notes_characters', $existing?->wd_notes_characters ?? '')),
                dialogue:   @js(old('wd_notes_dialogue',   $existing?->wd_notes_dialogue   ?? '')),
                overall:    @js(old('wd_notes_overall',    $existing?->wd_notes_overall    ?? '')),
            },

            wordCount(text) {
                if (!text || !text.trim()) return 0;
                return text.trim().split(/\s+/).length;
            },

            totalNoteWords() {
                return Object.values(this.notes).reduce((sum, t) => sum + this.wordCount(t), 0);
            },

            notesMinWords() {
                if (this.assignmentType === 'development_notes') return 3700;
                return 1200;
            },

            async saveDraft() {
                this.draftSaving = true;
                this.draftSaved = false;
                this.draftError = false;
                try {
                    const fd = new FormData(this.$el);
                    const r = await fetch(this.draftUrl, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                            'Accept': 'application/json',
                        },
                        body: fd,
                    });
                    if (r.ok) {
                        this.draftSaved = true;
                        setTimeout(() => { this.draftSaved = false; }, 3000);
                    } else {
                        this.draftError = true;
                    }
                } catch {
                    this.draftError = true;
                } finally {
                    this.draftSaving = false;
                }
            },
        };
    }
    </script>
</x-app-layout>
