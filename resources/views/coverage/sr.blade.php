<x-app-layout>
    <x-slot name="header">
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
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            {{-- Read-only assignment info --}}
            <div class="bg-indigo-50 border border-indigo-200 rounded-lg p-4 grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm">
                <div><span class="text-indigo-500 font-medium block">Script</span>{{ $assignment->script_title }}</div>
                <div><span class="text-indigo-500 font-medium block">Author</span>{{ $assignment->authorDisplay() }}</div>
                <div><span class="text-indigo-500 font-medium block">Pages</span>{{ $assignment->page_count }}</div>
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

            <form method="POST" action="{{ route('coverage.store', $assignment) }}"
                  x-data="srCoverage()" x-cloak>
                @csrf

                {{-- ── Section 1: Assignment Metadata ──────────────────────────────────── --}}
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-5">
                    <h3 class="font-semibold text-gray-700 text-base border-b border-gray-100 pb-2">Assignment Details</h3>

                    {{-- Assignment Type --}}
                    <div>
                        <x-input-label value="Assignment Type" />
                        <div class="mt-2 flex flex-wrap gap-x-5 gap-y-2">
                            @foreach ([
                                'script_coverage' => 'Script Coverage',
                                'notes_only'      => 'Notes Only',
                                'short'           => 'Short Coverage',
                                'deep_dive'       => 'Deep-Dive Dev Notes',
                                'budget'          => 'Budget Coverage',
                                'book'            => 'Book Coverage',
                            ] as $val => $label)
                                <label class="flex items-center gap-1.5 text-sm font-medium text-gray-700 cursor-pointer">
                                    <input type="radio" name="sr_assignment_type" value="{{ $val }}"
                                        x-model="type"
                                        class="text-indigo-600 border-gray-300 focus:ring-indigo-500"
                                        {{ old('sr_assignment_type', $existing?->sr_assignment_type ?? $assignment->assignment_type) === $val ? 'checked' : '' }} />
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>
                        <x-input-error :messages="$errors->get('sr_assignment_type')" class="mt-1" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        {{-- Writer's Name --}}
                        <div class="col-span-2 sm:col-span-1">
                            <x-input-label for="writer_name" value="Writer's Name" />
                            <x-text-input id="writer_name" name="writer_name" type="text" class="mt-1 block w-full"
                                value="{{ old('writer_name', $existing?->writer_name ?? $assignment->authorDisplay()) }}" required />
                            <x-input-error :messages="$errors->get('writer_name')" class="mt-1" />
                        </div>

                        {{-- Page Count (hidden for book) --}}
                        <div x-show="type !== 'book'">
                            <x-input-label for="page_count" value="Page Count" />
                            <x-text-input id="page_count" name="page_count" type="number"
                                class="mt-1 block w-full"
                                x-model.number="pageCount"
                                value="{{ old('page_count', $existing?->assignment?->page_count ?? $assignment->page_count) }}"
                                min="1" max="9999" />
                            <x-input-error :messages="$errors->get('page_count')" class="mt-1" />
                        </div>
                    </div>

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
                    </div>

                    {{-- Number of Readers (hidden for deep_dive, short, book, budget) --}}
                    <div x-show="!['deep_dive','short','book','budget'].includes(type)">
                        <x-input-label value="Number of Readers" />
                        <div class="mt-2 flex flex-wrap gap-x-5 gap-y-2">
                            @foreach (['1 Reader', '2 Readers', '3 Readers', 'other'] as $opt)
                                <label class="flex items-center gap-1.5 text-sm font-medium text-gray-700 cursor-pointer">
                                    <input type="radio" name="sr_number_of_readers" value="{{ $opt }}"
                                        class="text-indigo-600 border-gray-300 focus:ring-indigo-500"
                                        {{ old('sr_number_of_readers', $existing?->sr_number_of_readers) === $opt ? 'checked' : '' }} />
                                    {{ $opt }}
                                </label>
                            @endforeach
                        </div>
                        <x-input-error :messages="$errors->get('sr_number_of_readers')" class="mt-1" />
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                        {{-- Reader Request (hidden for deep_dive) --}}
                        <div x-show="type !== 'deep_dive'">
                            <x-input-label value="Reader Request?" />
                            <div class="mt-2 flex gap-4">
                                @foreach ([0 => 'No', 1 => 'Yes'] as $val => $label)
                                    <label class="flex items-center gap-1.5 text-sm font-medium text-gray-700 cursor-pointer">
                                        <input type="radio" name="sr_reader_request" value="{{ $val }}"
                                            class="text-indigo-600 border-gray-300 focus:ring-indigo-500"
                                            {{ old('sr_reader_request', $existing?->sr_reader_request) == $val ? 'checked' : '' }} />
                                        {{ $label }}
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        {{-- Proofreading (hidden for book, short) --}}
                        <div x-show="!['book','short'].includes(type)">
                            <x-input-label value="Proofreading?" />
                            <div class="mt-2 flex gap-4">
                                @foreach ([0 => 'No', 1 => 'Yes'] as $val => $label)
                                    <label class="flex items-center gap-1.5 text-sm font-medium text-gray-700 cursor-pointer">
                                        <input type="radio" name="sr_proofreading" value="{{ $val }}"
                                            class="text-indigo-600 border-gray-300 focus:ring-indigo-500"
                                            {{ old('sr_proofreading', $existing?->sr_proofreading) == $val ? 'checked' : '' }} />
                                        {{ $label }}
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        {{-- NET15 --}}
                        <div>
                            <x-input-label value="NET15?" />
                            <div class="mt-2 flex gap-4">
                                @foreach ([0 => 'No', 1 => 'Yes'] as $val => $label)
                                    <label class="flex items-center gap-1.5 text-sm font-medium text-gray-700 cursor-pointer">
                                        <input type="radio" name="sr_net15" value="{{ $val }}"
                                            class="text-indigo-600 border-gray-300 focus:ring-indigo-500"
                                            {{ old('sr_net15', $existing?->sr_net15) == $val ? 'checked' : '' }} />
                                        {{ $label }}
                                    </label>
                                @endforeach
                            </div>
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

                    {{-- Book Pay Rate (book only) --}}
                    <div x-show="type === 'book'">
                        <x-input-label for="sr_book_pay_rate" value="Book Pay Rate ($)" />
                        <x-text-input id="sr_book_pay_rate" name="sr_book_pay_rate" type="number"
                            class="mt-1 block w-48" min="0" step="0.01"
                            value="{{ old('sr_book_pay_rate', $existing?->sr_book_pay_rate) }}" />
                        <x-input-error :messages="$errors->get('sr_book_pay_rate')" class="mt-1" />
                    </div>
                </div>

                {{-- ── Section 2: Content ───────────────────────────────────────────────── --}}
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-5">
                    <h3 class="font-semibold text-gray-700 text-base border-b border-gray-100 pb-2">Coverage Content</h3>

                    {{-- Logline (script_coverage, short, deep_dive, book) --}}
                    <div x-show="['script_coverage','short','deep_dive','book'].includes(type)">
                        <div class="flex items-baseline justify-between">
                            <x-input-label for="sr_logline" value="Logline" />
                            <span class="text-xs text-gray-400" x-text="wordCount(logline) + ' / 50 words'"></span>
                        </div>
                        <textarea id="sr_logline" name="sr_logline" rows="3"
                            x-model="logline"
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                            placeholder="Max 50 words…">{{ old('sr_logline', $existing?->sr_logline) }}</textarea>
                        <p x-show="wordCount(logline) > 50" class="mt-1 text-xs text-red-500">Over 50 words — please trim.</p>
                        <x-input-error :messages="$errors->get('sr_logline')" class="mt-1" />
                    </div>

                    {{-- Synopsis (script_coverage, book only) --}}
                    <div x-show="['script_coverage','book'].includes(type)">
                        <div class="flex items-baseline justify-between">
                            <x-input-label for="sr_synopsis" value="Synopsis" />
                            <span class="text-xs" :class="wordCount(synopsis) >= 600 ? 'text-green-600' : 'text-gray-400'"
                                x-text="wordCount(synopsis) + ' words (min 600)'"></span>
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
                    <div class="flex items-center justify-between border-b border-gray-100 pb-2">
                        <h3 class="font-semibold text-gray-700 text-base">Scoresheet <span class="text-gray-400 font-normal text-sm">(50–100)</span></h3>
                        <div class="flex items-center gap-2">
                            <x-input-label for="randomAnchor" value="Randomize around:" class="whitespace-nowrap" />
                            <x-text-input id="randomAnchor" type="number" class="w-20 text-sm"
                                x-model.number="randomAnchor" min="50" max="100" placeholder="75" />
                            <button type="button"
                                @click="randomizeScores()"
                                class="text-xs px-3 py-1.5 rounded-md bg-gray-100 hover:bg-gray-200 text-gray-600 font-medium">
                                Randomize
                            </button>
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
                            <div class="grid grid-cols-[1fr_auto_auto] gap-3 items-center">
                                <label for="score_{{ $item['key'] }}" class="text-sm text-gray-700">
                                    <span class="text-gray-400 mr-1">{{ $i + 1 }}.</span>{{ $item['label'] }}
                                </label>
                                <input type="range"
                                    id="score_{{ $item['key'] }}"
                                    name="sr_score_{{ $item['key'] }}"
                                    min="50" max="100" step="1"
                                    x-model.number="scores.{{ $item['key'] }}"
                                    :style="`accent-color: ${scoreColor(scores.{{ $item['key'] }})}`"
                                    class="w-40 h-2 cursor-pointer" />
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
                                I have reviewed this coverage and confirm it is complete, accurate, and ready for QC.
                            </span>
                        </label>
                        <x-input-error :messages="$errors->get('quality_checked')" class="mt-1" />
                    </div>

                    {{-- Submit --}}
                    <div class="flex items-center justify-end gap-3 pt-2">
                        <a href="{{ route('assignments.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
                        <button type="submit"
                            :disabled="!qualityChecked"
                            :class="qualityChecked ? 'bg-indigo-600 hover:bg-indigo-700' : 'bg-gray-300 cursor-not-allowed'"
                            class="px-4 py-2 text-sm font-semibold text-white rounded-md transition-colors">
                            Submit Coverage
                        </button>
                    </div>
                </div>

            </form>
        </div>
    </div>

    <script>
    function srCoverage() {
        return {
            type: '{{ old('sr_assignment_type', $existing?->sr_assignment_type ?? $assignment->assignment_type ?? '') }}',
            pageCount: {{ old('page_count', $assignment->page_count) }},
            qualityChecked: {{ old('quality_checked') ? 'true' : 'false' }},
            logline: '',
            synopsis: '',
            notes: '',
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

            notesMinWords() {
                const map = {
                    script_coverage: 1200,
                    deep_dive: 4100,
                    book: 4100,
                    short: 600,
                    budget: 150,
                    notes_only: 0,
                };
                return map[this.type] || 0;
            },
        };
    }
    </script>
</x-app-layout>
