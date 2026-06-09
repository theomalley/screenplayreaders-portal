<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <a href="{{ route('assignments.index') }}" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </a>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    SR Coverage — #{{ $assignment->order_number }}
                    @if($assignment->rush)
                        <span class="ml-2 text-sm font-bold text-amber-600 uppercase tracking-wide">Rush</span>
                    @endif
                </h2>
            </div>
            @can('submitCoverage', $assignment)
                <a href="{{ route('assignments.show', $assignment) }}"
                   class="text-sm text-indigo-600 hover:text-indigo-800 font-medium whitespace-nowrap">
                    &larr; View Script
                </a>
            @endcan
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            {{-- Read-only assignment info --}}
            @php
                $typeLabels = [
                    'script_coverage' => 'Script Coverage',
                    'notes_only'      => 'Notes-Only Coverage',
                    'short'           => 'Short Coverage',
                    'deep_dive'       => 'Deep-Dive Development Notes',
                    'budget'          => 'Budget Script Coverage',
                    'book'            => 'Book Coverage',
                ];
                $typeDisplay = $typeLabels[$assignment->assignment_type] ?? ucfirst(str_replace('_', ' ', $assignment->assignment_type ?? '—'));
                $writerDisplay = $existing?->writer_name ?? $assignment->writer_name;
            @endphp
            <div class="bg-indigo-50 border border-indigo-200 rounded-lg p-4 grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm">
                <div><span class="text-indigo-500 font-medium block">Script</span>{{ $assignment->script_title }}</div>
                <div><span class="text-indigo-500 font-medium block">Writer</span>{{ $writerDisplay }}</div>
                <div><span class="text-indigo-500 font-medium block">Pages</span>{{ $assignment->page_count }}</div>
                <div><span class="text-indigo-500 font-medium block">Rate</span>${{ $assignment->assignedReader?->isAdmin() ? '0.00' : number_format($assignment->pay_rate, 2) }}</div>
                <div><span class="text-indigo-500 font-medium block">Type</span>{{ $typeDisplay }}</div>
                <div><span class="text-indigo-500 font-medium block">Request?</span>{{ $assignment->requested_reader_id ? 'Yes' : 'No' }}</div>
                <div><span class="text-indigo-500 font-medium block">Reader</span>{{ $assignment->assignedReader?->readerProfile?->initials ?? $assignment->assignedReader?->editorProfile?->initials ?? '—' }}</div>
            </div>

            @if ($readingNotes->isNotEmpty())
            <div x-data="{ open: true }" class="bg-indigo-50 border border-indigo-200 rounded-lg overflow-hidden">
                <button type="button" @click="open = !open"
                        class="w-full flex items-center justify-between px-4 py-3 text-sm font-semibold text-indigo-800 hover:bg-indigo-100 transition-colors">
                    <span>📝 Reading Notes ({{ $readingNotes->count() }})</span>
                    <svg class="w-4 h-4 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="open" x-cloak class="px-4 pb-4 space-y-2">
                    @foreach ($readingNotes as $rn)
                        <div class="bg-white border border-indigo-100 rounded px-3 py-2">
                            <p class="text-sm text-gray-800 whitespace-pre-wrap leading-snug">{{ $rn->body }}</p>
                            <p class="text-[10px] text-gray-400 mt-1">{{ $rn->created_at->format('M j, g:ia') }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
            @endif

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

            @if($showAutofill)
            <button type="button" onclick="srAutofill()"
                class="w-full py-2 text-xs font-bold text-white bg-orange-500 hover:bg-orange-600 rounded-lg">
                TEST: Populate form with test data.
            </button>
            @endif

            <form method="POST" action="{{ route('coverage.store', $assignment) }}"
                  x-data="srCoverage()" x-cloak
                  @submit="submitting = true">
                @csrf
                <input type="hidden" name="sr_assignment_type" value="{{ $existing?->sr_assignment_type ?? $assignment->assignment_type }}" />
                <input type="hidden" name="writer_name" value="{{ $writerDisplay }}" />
                <input type="hidden" name="page_count" value="{{ $assignment->page_count }}" />
                <input type="hidden" name="sr_reader_request" value="{{ $assignment->requested_reader_id ? 1 : 0 }}" />

                {{-- ── Section 1: Assignment Metadata ──────────────────────────────────── --}}
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-5">
                    <h3 class="font-semibold text-gray-700 text-base border-b border-gray-100 pb-2">Assignment Details</h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
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
                    </div>

                    {{-- Custom Oversized Fee (page_count > 160, not book) --}}
                    <div x-show="pageCount > 160 && type !== 'book'">
                        <x-input-label for="sr_custom_oversized_fee" value="Custom Oversized Fee ($)" />
                        <x-text-input id="sr_custom_oversized_fee" name="sr_custom_oversized_fee" type="number"
                            class="mt-1 block w-48" min="0" step="0.01"
                            value="{{ old('sr_custom_oversized_fee', $existing?->sr_custom_oversized_fee) }}" />
                        <x-input-error :messages="$errors->get('sr_custom_oversized_fee')" class="mt-1" />
                    </div>

                </div>

                {{-- ── Section 2: Content ───────────────────────────────────────────────── --}}
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-5">
                    <h3 class="font-semibold text-gray-700 text-base border-b border-gray-100 pb-2">Coverage Content</h3>

                    {{-- Logline (script_coverage, short, deep_dive, book) --}}
                    <div x-show="['script_coverage','short','deep_dive','book'].includes(type)">
                        <div class="flex items-baseline justify-between">
                            <x-input-label for="sr_logline" value="Logline" />
                            <span class="text-xs"
                                :class="loglineMinWords() > 0
                                    ? (wordCount(logline) >= loglineMinWords() ? 'text-green-600' : 'text-gray-400')
                                    : 'text-gray-400'"
                                x-text="loglineMinWords() > 0
                                    ? wordCount(logline) + ' words (min ' + loglineMinWords() + ')'
                                    : wordCount(logline) + ' / 50 words'"></span>
                        </div>
                        <textarea id="sr_logline" name="sr_logline" rows="3"
                            x-model="logline"
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                            placeholder="Max 50 words…">{{ old('sr_logline', $existing?->sr_logline) }}</textarea>
                        <p x-show="loglineMinWords() === 0 && wordCount(logline) > 50" class="mt-1 text-xs text-red-500">Over 50 words — please trim.</p>
                        <x-input-error :messages="$errors->get('sr_logline')" class="mt-1" />
                    </div>

                    {{-- Synopsis (script_coverage, book only) --}}
                    <div x-show="['script_coverage','book'].includes(type)">
                        <div class="flex items-baseline justify-between">
                            <x-input-label for="sr_synopsis" value="Synopsis" />
                            <span class="text-xs"
                                :class="wordCount(synopsis) >= synopsisMinWords() ? 'text-green-600' : 'text-gray-400'"
                                x-text="wordCount(synopsis) + ' words' + (synopsisMinWords() > 0 ? ' (min ' + synopsisMinWords() + ')' : '')"></span>
                        </div>
                        <textarea id="sr_synopsis" name="sr_synopsis" rows="10"
                            x-model="synopsis"
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">{{ old('sr_synopsis', $existing?->sr_synopsis) }}</textarea>
                        <x-input-error :messages="$errors->get('sr_synopsis')" class="mt-1" />
                    </div>

                    {{-- Notes (always shown) --}}
                    <div>
                        <div class="flex items-baseline justify-between">
                            <x-input-label for="sr_notes" value="Notes" />
                            <span class="text-xs" :class="wordCount(notes) >= notesMinWords() ? 'text-green-600' : 'text-gray-400'"
                                x-text="wordCount(notes) + ' words' + (notesMinWords() > 0 ? ' (min ' + notesMinWords() + ')' : '')"></span>
                        </div>
                        <textarea id="sr_notes" name="sr_notes" rows="14"
                            x-model="notes"
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">{{ old('sr_notes', $existing?->sr_notes) }}</textarea>
                        <x-input-error :messages="$errors->get('sr_notes')" class="mt-1" />
                    </div>
                </div>

                {{-- ── Section 3: Scoresheet ────────────────────────────────────────────── --}}
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-5">
                    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-gray-100 pb-2">
                        <h3 class="font-semibold text-gray-700 text-base flex items-baseline gap-3">
                            Scoresheet <span class="text-gray-400 font-normal text-sm">(50–100)</span>
                            <span class="text-base font-bold tabular-nums"
                                :style="`color: ${scoreColor(averageScore())}`"
                                x-text="'Avg: ' + averageScore()"></span>
                        </h3>
                        <div>
                            <div class="flex items-center gap-2">
                                <x-input-label for="randomAnchor" value="Randomize around:" class="whitespace-nowrap text-xs" />
                                <x-text-input id="randomAnchor" type="number" class="w-16 text-sm"
                                    x-model.number="randomAnchor" min="50" max="100" placeholder="75" />
                                <button type="button"
                                    @click="randomizeScores()"
                                    class="text-xs px-2.5 py-1.5 rounded-md bg-gray-100 hover:bg-gray-200 text-gray-600 font-medium whitespace-nowrap">
                                    Randomize
                                </button>
                            </div>
                            <p class="text-xs text-gray-400 mt-1">Starting point only — dial in from there.</p>
                        </div>
                    </div>

                    @php
                    $scoreItems = [
                        ['key' => 'concept',                'label' => 'Concept is strong and/or material has a buzzworthy hook'],
                        ['key' => 'opening_pages',          'label' => 'Opening pages/chapters are compelling'],
                        ['key' => 'theme',                  'label' => 'Theme is well-executed/interweaved well'],
                        ['key' => 'story_logic',            'label' => 'Story/plot/story logic is clear and easy to follow'],
                        ['key' => 'story_element',          'label' => 'Every story element feels essential'],
                        ['key' => 'setting',                'label' => 'Setting/world is easy to understand/follow'],
                        ['key' => 'story_bogged',           'label' => 'Story is not bogged down by exposition'],
                        ['key' => 'scenes_impact',          'label' => 'Scenes and moments cause or impact later scenes and moments'],
                        ['key' => 'stakes',                 'label' => 'Stakes are clear/conflict is strong and/or compelling'],
                        ['key' => 'tension',                'label' => 'Tension builds/escalates throughout'],
                        ['key' => 'characters_interesting', 'label' => 'Characters are interesting/entertaining/fun to follow'],
                        ['key' => 'characters_choices',     'label' => 'Characters\' choices and actions drive the story forward'],
                        ['key' => 'characters_motivations', 'label' => 'Characters\' motivations/wants/obstacles are clearly defined'],
                        ['key' => 'characters_different',   'label' => 'It\'s easy to tell who\'s who — Characters are different from one another'],
                        ['key' => 'antagonistic',           'label' => 'Antagonistic forces are difficult for protagonist/s to overcome'],
                        ['key' => 'dialogue',               'label' => 'Dialogue is strong/colorful/entertaining/impactful'],
                        ['key' => 'action_text',            'label' => 'Action/description text is visual/concise/vivid'],
                        ['key' => 'climax',                 'label' => 'Climax/resolution is entertaining/satisfying'],
                        ['key' => 'work_feels',             'label' => 'Work feels as if it\'s as strong/funny/dramatic/entertaining as it can be'],
                        ['key' => 'target_audience',        'label' => 'Target audience/demographic is clear'],
                        ['key' => 'content',                'label' => 'Content/subject matter is likely to be strategically appealing to buyers'],
                        ['key' => 'format',                 'label' => 'Format/spelling/presentation isn\'t distracting'],
                    ];
                    @endphp

                    <div class="space-y-4">
                        @foreach ($scoreItems as $i => $item)
                            <div class="grid grid-cols-[1fr_auto_auto] gap-2 sm:gap-3 items-center">
                                <label for="score_{{ $item['key'] }}" class="text-sm text-gray-700">
                                    <span class="text-gray-400 mr-1">{{ $i + 1 }}.</span>{{ $item['label'] }}
                                </label>
                                <input type="range"
                                    id="score_{{ $item['key'] }}"
                                    name="sr_score_{{ $item['key'] }}"
                                    min="50" max="100" step="1"
                                    x-model.number="scores.{{ $item['key'] }}"
                                    :style="`accent-color: ${scoreColor(scores.{{ $item['key'] }})}`"
                                    class="w-24 sm:w-40 h-2 cursor-pointer" />
                                <span class="text-sm font-bold w-8 text-right tabular-nums"
                                    :style="`color: ${scoreColor(scores.{{ $item['key'] }})}`"
                                    x-text="scores.{{ $item['key'] }}"></span>
                                {{-- Hidden input carries submitted value --}}
                                <input type="hidden" name="sr_score_{{ $item['key'] }}"
                                    x-bind:value="scores.{{ $item['key'] }}" />
                            </div>
                            @if($errors->has('sr_score_' . $item['key']))
                                <x-input-error :messages="$errors->get('sr_score_' . $item['key'])" />
                            @endif
                        @endforeach
                    </div>
                </div>

                {{-- ── Section 4: Final Fields ──────────────────────────────────────────── --}}
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-5">
                    <h3 class="font-semibold text-gray-700 text-base border-b border-gray-100 pb-2">Final Assessment</h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        {{-- Bechdel --}}
                        <div>
                            <x-input-label for="sr_bechdel" value="Bechdel Test" />
                            <select id="sr_bechdel" name="sr_bechdel"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                                @foreach (['Not applicable', 'Yes', 'No'] as $opt)
                                    <option value="{{ $opt }}" {{ old('sr_bechdel', $existing?->sr_bechdel) === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('sr_bechdel')" class="mt-1" />
                        </div>

                        {{-- Diversity --}}
                        <div>
                            <x-input-label for="sr_diversity" value="Diversity" />
                            <select id="sr_diversity" name="sr_diversity"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                                @foreach (['Not applicable', 'Diverse', 'Moderately Diverse', 'Could use more Diversity'] as $opt)
                                    <option value="{{ $opt }}" {{ old('sr_diversity', $existing?->sr_diversity) === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('sr_diversity')" class="mt-1" />
                        </div>
                    </div>

                    {{-- Recommendation --}}
                    <div>
                        <x-input-label value="Recommendation" />
                        <div class="mt-2 flex flex-wrap gap-x-5 gap-y-2">
                            @foreach (['Pass', 'Consider', 'Consider with Reservations', 'Recommend'] as $opt)
                                <label class="flex items-center gap-1.5 text-sm font-medium text-gray-700 cursor-pointer">
                                    <input type="radio" name="sr_recommendation" value="{{ $opt }}"
                                        class="text-indigo-600 border-gray-300 focus:ring-indigo-500"
                                        {{ old('sr_recommendation', $existing?->sr_recommendation) === $opt ? 'checked' : '' }} />
                                    {{ $opt }}
                                </label>
                            @endforeach
                        </div>
                        <x-input-error :messages="$errors->get('sr_recommendation')" class="mt-1" />
                    </div>

                    {{-- Quality check --}}
                    <div class="border-t border-gray-100 pt-4">
                        <label class="flex items-start gap-3 cursor-pointer">
                            <input id="quality_checked" name="quality_checked" type="checkbox" value="1"
                                x-model="qualityChecked"
                                {{ old('quality_checked') ? 'checked' : '' }}
                                class="mt-0.5 rounded border-gray-300 text-green-600 shadow-sm focus:ring-green-500" />
                            <span class="text-sm text-gray-700 font-medium">
                                I've provided helpful, actionable feedback, have adhered to Screenplay Readers quality standards listed in the Reader Manual, and have reviewed my work for errors.
                            </span>
                        </label>
                        <x-input-error :messages="$errors->get('quality_checked')" class="mt-1" />
                    </div>

                    {{-- Note to editor (optional) --}}
                    <div class="border-t border-gray-100 pt-4">
                        <x-input-label for="note_to_team" value="Note to editor (optional)" />
                        <textarea id="note_to_team" name="note_to_team" rows="2" maxlength="1000"
                                  class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500 resize-y"
                                  placeholder="Anything the editor should know about this coverage…">{{ old('note_to_team') }}</textarea>
                        <p class="mt-1 text-xs text-gray-400">Visible to admins and editors only — not sent to the customer.</p>
                    </div>

                    {{-- Submit --}}
                    <div class="flex items-center justify-between pt-2">
                        <a href="{{ route('assignments.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
                        <div class="flex items-center gap-3">
                            <span x-show="draftSaved" x-cloak class="text-sm text-green-600 font-medium">Saved!</span>
                            <span x-show="draftError" x-cloak class="text-sm text-red-500">Error saving.</span>
                            <span x-show="!wordCountsMet()" x-cloak class="text-sm text-amber-600">Word count minimums not yet met.</span>
                            <button type="button"
                                :disabled="draftSaving || submitting"
                                @click="saveDraft($el)"
                                class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 transition-colors inline-flex items-center gap-2 disabled:opacity-50">
                                <svg x-show="draftSaving" class="animate-spin h-4 w-4 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span x-text="draftSaving ? 'Saving…' : 'Save for Later'"></span>
                            </button>
                            <button type="submit"
                                :disabled="!qualityChecked || submitting || !wordCountsMet()"
                                :class="(qualityChecked && !submitting && wordCountsMet()) ? 'bg-indigo-600 hover:bg-indigo-700' : 'bg-gray-300 cursor-not-allowed'"
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
    function srAutofill() {
        const fill = (id, val) => {
            const el = document.getElementById(id);
            if (!el) return;
            el.value = val;
            el.dispatchEvent(new Event('input', { bubbles: true }));
        };

        // Build text long enough to meet minWords by repeating a realistic sentence block.
        const block = 'The story follows a determined protagonist navigating unexpected obstacles while uncovering deeper truths about themselves and the world around them. Character motivations are clearly established and the narrative maintains strong tension throughout. The dialogue feels authentic and each scene advances both plot and character development in meaningful ways.';
        const countWords = str => str.trim().split(/\s+/).length;
        const makeText = (minWords) => {
            let t = block;
            while (countWords(t) < minWords) t += ' ' + block;
            return t;
        };

        // Determine which notes minimum applies for this assignment type
        const assignType = document.querySelector('input[name="sr_assignment_type"]')?.value ?? 'script_coverage';
        const notesMinsMap = {
            script_coverage: srWcSettings.wc_sr_notes_script_coverage,
            deep_dive:       srWcSettings.wc_sr_notes_deep_dive,
            book:            srWcSettings.wc_sr_notes_book,
            short:           srWcSettings.wc_sr_notes_short,
            budget:          srWcSettings.wc_sr_notes_budget,
            notes_only:      srWcSettings.wc_sr_notes_notes_only,
        };
        const notesMin    = notesMinsMap[assignType] || 0;
        const synopsisMin = srWcSettings.wc_sr_synopsis || 0;
        const loglineMin  = srWcSettings.wc_sr_logline  || 0;

        fill('genre',            'Drama');
        fill('time_period',      'Contemporary');
        fill('locations',        'Los Angeles, New York');
        fill('estimated_budget', 'medium');

        // Logline: meet min if set, otherwise stay under the 50-word soft cap
        fill('sr_logline',  loglineMin > 0
            ? makeText(loglineMin)
            : 'A determined writer uncovers a manuscript that rewrites itself each night, forcing a reckoning with identity and truth before the final chapter erases him completely.');

        fill('sr_synopsis', makeText(Math.max(synopsisMin, 50)));
        fill('sr_notes',    makeText(Math.max(notesMin,    50)));

        document.querySelector('select[name="sr_bechdel"]').value  = 'Yes';
        document.querySelector('select[name="sr_diversity"]').value = 'Diverse';
        document.querySelector('input[name="sr_recommendation"][value="Consider"]').checked = true;

    }

    const srWcSettings = @js($wordCounts);
    const srWcExempt   = @js($wcExempt);

    function srCoverage() {
        return {
            type: '{{ old('sr_assignment_type', $existing?->sr_assignment_type ?? $assignment->assignment_type ?? '') }}',
            pageCount: {{ old('page_count', $assignment->page_count) }},
            qualityChecked: {{ old('quality_checked') ? 'true' : 'false' }},
            submitting: false,
            draftSaving: false,
            draftSaved: false,
            draftError: false,
            draftUrl: @js(route('coverage.draft', $assignment)),
            logline: @js(old('sr_logline', $existing?->sr_logline ?? '')),
            synopsis: @js(old('sr_synopsis', $existing?->sr_synopsis ?? '')),
            notes: @js(old('sr_notes', $existing?->sr_notes ?? '')),
            randomAnchor: 75,

            scores: {
                concept:                {{ old('sr_score_concept',                $existing?->sr_score_concept                ?? 75) }},
                opening_pages:          {{ old('sr_score_opening_pages',          $existing?->sr_score_opening_pages          ?? 75) }},
                theme:                  {{ old('sr_score_theme',                  $existing?->sr_score_theme                  ?? 75) }},
                story_logic:            {{ old('sr_score_story_logic',            $existing?->sr_score_story_logic            ?? 75) }},
                story_element:          {{ old('sr_score_story_element',          $existing?->sr_score_story_element          ?? 75) }},
                setting:                {{ old('sr_score_setting',                $existing?->sr_score_setting                ?? 75) }},
                story_bogged:           {{ old('sr_score_story_bogged',           $existing?->sr_score_story_bogged           ?? 75) }},
                scenes_impact:          {{ old('sr_score_scenes_impact',          $existing?->sr_score_scenes_impact          ?? 75) }},
                stakes:                 {{ old('sr_score_stakes',                 $existing?->sr_score_stakes                 ?? 75) }},
                tension:                {{ old('sr_score_tension',                $existing?->sr_score_tension                ?? 75) }},
                characters_interesting: {{ old('sr_score_characters_interesting', $existing?->sr_score_characters_interesting ?? 75) }},
                characters_choices:     {{ old('sr_score_characters_choices',     $existing?->sr_score_characters_choices     ?? 75) }},
                characters_motivations: {{ old('sr_score_characters_motivations', $existing?->sr_score_characters_motivations ?? 75) }},
                characters_different:   {{ old('sr_score_characters_different',   $existing?->sr_score_characters_different   ?? 75) }},
                antagonistic:           {{ old('sr_score_antagonistic',           $existing?->sr_score_antagonistic           ?? 75) }},
                dialogue:               {{ old('sr_score_dialogue',               $existing?->sr_score_dialogue               ?? 75) }},
                action_text:            {{ old('sr_score_action_text',            $existing?->sr_score_action_text            ?? 75) }},
                climax:                 {{ old('sr_score_climax',                 $existing?->sr_score_climax                 ?? 75) }},
                work_feels:             {{ old('sr_score_work_feels',             $existing?->sr_score_work_feels             ?? 75) }},
                target_audience:        {{ old('sr_score_target_audience',        $existing?->sr_score_target_audience        ?? 75) }},
                content:                {{ old('sr_score_content',                $existing?->sr_score_content                ?? 75) }},
                format:                 {{ old('sr_score_format',                 $existing?->sr_score_format                 ?? 75) }},
            },

            averageScore() {
                const vals = Object.values(this.scores);
                return Math.round(vals.reduce((sum, v) => sum + v, 0) / vals.length);
            },

            randomizeScores() {
                const anchor = parseInt(this.randomAnchor);
                if (!anchor || anchor < 50 || anchor > 100) return;
                Object.keys(this.scores).forEach(k => {
                    const delta = Math.floor(Math.random() * 11) - 5;
                    this.scores[k] = Math.min(100, Math.max(50, anchor + delta));
                });
            },

            scoreColor(val) {
                const hue = ((val - 50) / 50) * 120;
                return `hsl(${hue}, 100%, 38%)`;
            },

            wordCount(text) {
                if (!text || !text.trim()) return 0;
                return text.trim().split(/\s+/).length;
            },

            loglineMinWords() {
                return srWcSettings.wc_sr_logline || 0;
            },

            synopsisMinWords() {
                return srWcSettings.wc_sr_synopsis || 0;
            },

            notesMinWords() {
                const map = {
                    script_coverage: srWcSettings.wc_sr_notes_script_coverage,
                    deep_dive:       srWcSettings.wc_sr_notes_deep_dive,
                    book:            srWcSettings.wc_sr_notes_book,
                    short:           srWcSettings.wc_sr_notes_short,
                    budget:          srWcSettings.wc_sr_notes_budget,
                    notes_only:      srWcSettings.wc_sr_notes_notes_only,
                };
                return map[this.type] ?? 0;
            },

            wordCountsMet() {
                if (!srWcSettings.wc_enabled || srWcExempt) return true;
                const showLogline  = ['script_coverage', 'short', 'deep_dive', 'book'].includes(this.type);
                const showSynopsis = ['script_coverage', 'book'].includes(this.type);
                if (showLogline  && this.loglineMinWords()  > 0 && this.wordCount(this.logline)  < this.loglineMinWords())  return false;
                if (showSynopsis && this.synopsisMinWords() > 0 && this.wordCount(this.synopsis) < this.synopsisMinWords()) return false;
                if (this.notesMinWords() > 0 && this.wordCount(this.notes) < this.notesMinWords()) return false;
                return true;
            },

            async saveDraft(btn) {
                this.draftSaving = true;
                this.draftSaved = false;
                this.draftError = false;
                try {
                    const fd = new FormData(btn.closest('form'));
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
