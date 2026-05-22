<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Filename Conventions</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-8">

            @if(session('success'))
                <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                    {{ session('success') }}
                </div>
            @endif

            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h3 class="text-base font-semibold text-gray-900">Script Filename</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        Scripts use the base pattern only (no suffix).<br>
                        Format: <code class="text-xs bg-gray-100 rounded px-1 py-0.5">ordernumber_YYYYMMDD_Title_WLast.pdf</code>
                        &nbsp;·&nbsp; e.g. <code class="text-xs bg-gray-100 rounded px-1 py-0.5">19192_20260601_Star-Wars_GLucas.pdf</code>
                    </p>
                </div>

                <div class="px-6 py-4 border-b border-gray-100">
                    <h3 class="text-base font-semibold text-gray-900">Coverage Filename</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        Coverage docs append a service suffix and reader initials to the base.<br>
                        Format: <code class="text-xs bg-gray-100 rounded px-1 py-0.5">ordernumber_YYYYMMDD_Title_WLast_<span class="text-indigo-600">suffix</span>-ReaderInitials.pdf</code>
                    </p>
                </div>

                <form method="POST" action="{{ route('admin.filenames.update') }}">
                    @csrf
                    @method('patch')

                    {{-- SR Types --}}
                    <div class="px-6 py-4 border-b border-gray-100">
                        <h4 class="text-sm font-semibold text-gray-700 mb-3">Screenplay Readers (SR) Services</h4>
                        <div class="space-y-4" x-data="{
                            sr_script_coverage:   '{{ $suffixes['filename_suffix_sr_script_coverage'] }}',
                            sr_notes_only:        '{{ $suffixes['filename_suffix_sr_notes_only'] }}',
                            sr_deep_dive:         '{{ $suffixes['filename_suffix_sr_deep_dive'] }}',
                            sr_book:              '{{ $suffixes['filename_suffix_sr_book'] }}',
                            sr_budget:            '{{ $suffixes['filename_suffix_sr_budget'] }}',
                            sr_short:             '{{ $suffixes['filename_suffix_sr_short'] }}',
                        }">
                            @php
                                $srRows = [
                                    ['key' => 'sr_script_coverage',  'label' => 'Script Coverage',  'model' => 'sr_script_coverage'],
                                    ['key' => 'sr_notes_only',       'label' => 'Notes Only',        'model' => 'sr_notes_only'],
                                    ['key' => 'sr_deep_dive',        'label' => 'Deep Dive',         'model' => 'sr_deep_dive'],
                                    ['key' => 'sr_book',             'label' => 'Book Coverage',     'model' => 'sr_book'],
                                    ['key' => 'sr_budget',           'label' => 'Budget Coverage',   'model' => 'sr_budget'],
                                    ['key' => 'sr_short',            'label' => 'Short Coverage',    'model' => 'sr_short'],
                                ];
                            @endphp
                            @foreach($srRows as $row)
                                <div class="grid grid-cols-[1fr_180px_1fr] gap-4 items-center">
                                    <label class="text-sm text-gray-700 font-medium">{{ $row['label'] }}</label>
                                    <div>
                                        <input type="text"
                                               name="filename_suffix_{{ $row['key'] }}"
                                               x-model="{{ $row['model'] }}"
                                               placeholder="suffix"
                                               class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500 font-mono"
                                               @input="{{ $row['model'] }} = $event.target.value" />
                                        @error('filename_suffix_' . $row['key'])
                                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <p class="text-xs text-gray-400 font-mono break-all">
                                        19192_20260601_Star-Wars_GLucas_<span class="text-indigo-600" x-text="{{ $row['model'] }} || '…'"></span>-KD.pdf
                                    </p>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- WD Types --}}
                    <div class="px-6 py-4 border-b border-gray-100">
                        <h4 class="text-sm font-semibold text-gray-700 mb-3">Writer's Digest (WD) Services</h4>
                        <div class="space-y-4" x-data="{
                            wd_coverage:           '{{ $suffixes['filename_suffix_wd_coverage'] }}',
                            wd_development_notes:  '{{ $suffixes['filename_suffix_wd_development_notes'] }}',
                        }">
                            @php
                                $wdRows = [
                                    ['key' => 'wd_coverage',          'label' => 'Coverage',           'model' => 'wd_coverage'],
                                    ['key' => 'wd_development_notes', 'label' => 'Development Notes',  'model' => 'wd_development_notes'],
                                ];
                            @endphp
                            @foreach($wdRows as $row)
                                <div class="grid grid-cols-[1fr_180px_1fr] gap-4 items-center">
                                    <label class="text-sm text-gray-700 font-medium">{{ $row['label'] }}</label>
                                    <div>
                                        <input type="text"
                                               name="filename_suffix_{{ $row['key'] }}"
                                               x-model="{{ $row['model'] }}"
                                               placeholder="suffix"
                                               class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500 font-mono"
                                               @input="{{ $row['model'] }} = $event.target.value" />
                                        @error('filename_suffix_' . $row['key'])
                                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <p class="text-xs text-gray-400 font-mono break-all">
                                        WD_20260601_Star-Wars_GLucas_<span class="text-indigo-600" x-text="{{ $row['model'] }} || '…'"></span>-KD.pdf
                                    </p>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="px-6 py-4">
                        <x-primary-button>Save conventions</x-primary-button>
                    </div>
                </form>
            </div>

        </div>
    </div>
</x-app-layout>
