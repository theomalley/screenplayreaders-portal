<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coverage — {{ $assignment->script_title }}</title>
    @vite(['resources/css/app.css'])
    <style>
        @media print {
            html, body { display: none !important; }
        }
    </style>
    <script>
        window.print = function () {};
        document.addEventListener('keydown', function (e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
                e.preventDefault();
            }
        });
    </script>
</head>
<body class="bg-white text-gray-800 text-sm font-sans p-6 max-w-3xl mx-auto">

    @php
        $s = $submission;
        $typeLabels = [
            'script_coverage'   => 'Script Coverage',
            'notes_only'        => 'Notes-Only Coverage',
            'short'             => 'Short Coverage',
            'deep_dive'         => 'Deep-Dive Development Notes',
            'budget'            => 'Budget Script Coverage',
            'book'              => 'Book Coverage',
            'coverage'          => 'Coverage',
            'development_notes' => 'Development Notes',
        ];
        $typeDisplay = $typeLabels[$assignment->assignment_type] ?? ucfirst(str_replace('_', ' ', $assignment->assignment_type ?? '—'));
        $vendor = $assignment->vendor;

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
            ['key' => 'characters_choices',     'label' => "Characters' choices and actions drive the story forward"],
            ['key' => 'characters_motivations', 'label' => "Characters' motivations/wants/obstacles are clearly defined"],
            ['key' => 'characters_different',   'label' => "It's easy to tell who's who — Characters are different from one another"],
            ['key' => 'antagonistic',           'label' => 'Antagonistic forces are difficult for protagonist/s to overcome'],
            ['key' => 'dialogue',               'label' => 'Dialogue is strong/colorful/entertaining/impactful'],
            ['key' => 'action_text',            'label' => 'Action/description text is visual/concise/vivid'],
            ['key' => 'climax',                 'label' => 'Climax/resolution is entertaining/satisfying'],
            ['key' => 'work_feels',             'label' => "Work feels as if it's as strong/funny/dramatic/entertaining as it can be"],
            ['key' => 'target_audience',        'label' => 'Target audience/demographic is clear'],
            ['key' => 'content',                'label' => 'Content/subject matter is likely to be strategically appealing to buyers'],
            ['key' => 'format',                 'label' => "Format/spelling/presentation isn't distracting"],
        ];

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

    {{-- Header --}}
    <div class="border-b border-gray-200 pb-4 mb-6">
        <h1 class="text-lg font-bold text-gray-900">{{ $assignment->script_title }}</h1>
        <div class="mt-2 flex flex-wrap gap-x-5 gap-y-1 text-xs text-gray-500">
            <span>Writer: <span class="font-medium text-gray-700">{{ $s->writer_name ?? $assignment->writer_name }}</span></span>
            <span>Type: <span class="font-medium text-gray-700">{{ $typeDisplay }}</span></span>
            <span>Pages: <span class="font-medium text-gray-700">{{ $assignment->page_count }}</span></span>
            <span>Order: <span class="font-medium text-gray-700 font-mono">{{ $assignment->order_number }}</span></span>
            @if($s->genre)
                <span>Genre: <span class="font-medium text-gray-700">{{ $s->genre }}</span></span>
            @endif
            @if($s->time_period)
                <span>Period: <span class="font-medium text-gray-700">{{ $s->time_period }}</span></span>
            @endif
            @if($s->locations)
                <span>Locations: <span class="font-medium text-gray-700">{{ $s->locations }}</span></span>
            @endif
            @if($s->estimated_budget)
                <span>Budget: <span class="font-medium text-gray-700">{{ $s->estimated_budget }}</span></span>
            @endif
        </div>

        {{-- Timing --}}
        @php
            $tz = 'America/Los_Angeles';
            $acceptedStr  = $assignment->accepted_at  ? $assignment->accepted_at->setTimezone($tz)->format('M j, Y g:ia')  : null;
            $completedStr = $assignment->completed_at ? $assignment->completed_at->setTimezone($tz)->format('M j, Y g:ia') : null;

            if ($assignment->created_at && $assignment->completed_at) {
                $td  = $assignment->created_at->diff($assignment->completed_at);
                $totalTaStr = $td->days >= 1
                    ? ($td->days . 'd ' . $td->h . 'h')
                    : ($td->h >= 1 ? ($td->h . 'h ' . $td->i . 'm') : ($td->i . 'm'));
            } else {
                $totalTaStr = null;
            }

            if ($assignment->accepted_at && $assignment->submitted_at) {
                $rd  = $assignment->accepted_at->diff($assignment->submitted_at);
                $readerTaStr = $rd->days >= 1
                    ? ($rd->days . 'd ' . $rd->h . 'h')
                    : ($rd->h >= 1 ? ($rd->h . 'h ' . $rd->i . 'm') : ($rd->i . 'm'));
            } else {
                $readerTaStr = null;
            }
        @endphp
        <div class="mt-3 pt-3 border-t border-gray-100 flex flex-wrap gap-x-6 gap-y-1 text-xs text-gray-500">
            @if($acceptedStr)
                <span>Accepted: <span class="font-medium text-gray-700">{{ $acceptedStr }}</span></span>
            @endif
            @if($completedStr)
                <span>Completed: <span class="font-medium text-gray-700">{{ $completedStr }}</span></span>
            @endif
            @if($totalTaStr)
                <span>Total turnaround: <span class="font-medium text-gray-700">{{ $totalTaStr }}</span></span>
            @endif
            @if($readerTaStr)
                <span>Reader turnaround: <span class="font-medium text-gray-700">{{ $readerTaStr }}</span></span>
            @endif
        </div>
    </div>

    @if($vendor !== 'wd')

        {{-- SR Coverage --}}

        @if($s->sr_logline)
            <div class="mb-6">
                <h2 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Logline</h2>
                <p class="text-gray-800 leading-relaxed">{{ $s->sr_logline }}</p>
            </div>
        @endif

        @if($s->sr_synopsis)
            <div class="mb-6">
                <h2 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Synopsis</h2>
                <p class="text-gray-800 leading-relaxed whitespace-pre-line">{{ $s->sr_synopsis }}</p>
            </div>
        @endif

        @if($s->sr_notes)
            <div class="mb-6">
                <h2 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Notes</h2>
                <p class="text-gray-800 leading-relaxed whitespace-pre-line">{{ $s->sr_notes }}</p>
            </div>
        @endif

        {{-- Scoresheet --}}
        @php
            $scores = [];
            foreach ($scoreItems as $item) {
                $val = $s->{'sr_score_' . $item['key']};
                if ($val !== null) $scores[$item['key']] = (int) $val;
            }
            $avg = count($scores) > 0 ? round(array_sum($scores) / count($scores)) : null;
        @endphp
        @if(!empty($scores))
            <div class="mb-6">
                <h2 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3 flex items-center gap-3">
                    Scoresheet
                    @if($avg)
                        <span class="text-sm font-bold text-gray-700 normal-case tracking-normal">Avg: {{ $avg }}</span>
                    @endif
                </h2>
                <div class="space-y-1.5">
                    @foreach($scoreItems as $i => $item)
                        @php $val = $scores[$item['key']] ?? null; @endphp
                        @if($val !== null)
                            @php $color = $val >= 80 ? '#16a34a' : ($val >= 65 ? '#ca8a04' : '#dc2626'); @endphp
                            <div class="flex items-center gap-2">
                                <span class="text-gray-400 text-xs w-5 shrink-0 text-right">{{ $i + 1 }}.</span>
                                <span class="flex-1 text-xs text-gray-700 min-w-0">{{ $item['label'] }}</span>
                                <span class="font-bold tabular-nums text-sm w-8 text-right shrink-0" style="color: {{ $color }}">{{ $val }}</span>
                                <div class="w-20 shrink-0 bg-gray-100 rounded-full h-1.5">
                                    <div class="h-1.5 rounded-full" style="width: {{ (($val - 50) / 50) * 100 }}%; background-color: {{ $color }}"></div>
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Final Assessment --}}
        <div class="border-t border-gray-100 pt-4 flex flex-wrap gap-6 text-sm">
            @if($s->sr_bechdel)
                <div>
                    <span class="text-gray-400 text-xs font-medium uppercase tracking-wide block mb-0.5">Bechdel</span>
                    {{ $s->sr_bechdel }}
                </div>
            @endif
            @if($s->sr_diversity)
                <div>
                    <span class="text-gray-400 text-xs font-medium uppercase tracking-wide block mb-0.5">Diversity</span>
                    {{ $s->sr_diversity }}
                </div>
            @endif
            @if($s->sr_recommendation)
                <div>
                    <span class="text-gray-400 text-xs font-medium uppercase tracking-wide block mb-0.5">Recommendation</span>
                    <span class="font-semibold text-indigo-700">{{ $s->sr_recommendation }}</span>
                </div>
            @endif
        </div>

    @else

        {{-- WD Coverage --}}

        @if($s->wd_form || $s->wd_mpaa_rating)
            <div class="flex flex-wrap gap-6 mb-6 text-sm">
                @if($s->wd_form)
                    <div>
                        <span class="text-gray-400 text-xs font-medium uppercase tracking-wide block mb-0.5">Form of Material</span>
                        {{ $s->wd_form }}
                    </div>
                @endif
                @if($s->wd_mpaa_rating)
                    <div>
                        <span class="text-gray-400 text-xs font-medium uppercase tracking-wide block mb-0.5">MPAA Rating</span>
                        {{ $s->wd_mpaa_rating }}
                    </div>
                @endif
            </div>
        @endif

        @if($s->wd_logline)
            <div class="mb-6">
                <h2 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Logline</h2>
                <p class="text-gray-800 leading-relaxed">{{ $s->wd_logline }}</p>
            </div>
        @endif

        @if($s->wd_synopsis)
            <div class="mb-6">
                <h2 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Synopsis</h2>
                <p class="text-gray-800 leading-relaxed whitespace-pre-line">{{ $s->wd_synopsis }}</p>
            </div>
        @endif

        @foreach($wdSections as $section)
            @php
                $score = $s->{'wd_score_' . $section['key']};
                $notes = $s->{'wd_notes_' . $section['key']};
            @endphp
            @if($score || $notes)
                <div class="mb-5">
                    <h2 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2 flex items-center gap-2">
                        {{ $section['label'] }}
                        @if($score)
                            <span class="text-sm font-bold text-gray-700 normal-case tracking-normal">{{ $score }}</span>
                        @endif
                    </h2>
                    @if($notes)
                        <p class="text-gray-800 leading-relaxed whitespace-pre-line">{{ $notes }}</p>
                    @endif
                </div>
            @endif
        @endforeach

        @if($s->wd_script_recommendations)
            <div class="mb-6">
                <h2 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Script Recommendations</h2>
                <p class="text-gray-800 leading-relaxed whitespace-pre-line">{{ $s->wd_script_recommendations }}</p>
            </div>
        @endif

        @if($s->wd_recommend_writer || $s->wd_recommend_material)
            <div class="border-t border-gray-100 pt-4 flex flex-wrap gap-6 text-sm">
                @if($s->wd_recommend_writer)
                    <div>
                        <span class="text-gray-400 text-xs font-medium uppercase tracking-wide block mb-0.5">Recommend Writer?</span>
                        <span class="font-semibold text-indigo-700">{{ $s->wd_recommend_writer }}</span>
                    </div>
                @endif
                @if($s->wd_recommend_material)
                    <div>
                        <span class="text-gray-400 text-xs font-medium uppercase tracking-wide block mb-0.5">Recommend Material?</span>
                        <span class="font-semibold text-indigo-700">{{ $s->wd_recommend_material }}</span>
                    </div>
                @endif
            </div>
        @endif

    @endif

</body>
</html>
