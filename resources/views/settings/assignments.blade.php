<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Settings</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">

            @include('settings._nav')

            <div class="space-y-6">

            @if(session('success'))
                <div class="bg-green-50 border border-green-200 rounded-lg px-4 py-3 text-sm text-green-700">
                    {{ session('success') }}
                </div>
            @endif

            {{-- Reader Capacity Override --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 class="text-sm font-semibold text-gray-800 mb-1">Reader Capacity Override</h3>
                <p class="text-xs text-gray-500 mb-4">
                    Set a single concurrent-assignment cap that applies to <strong>all readers</strong>, overriding their individual limits.
                    Leave blank (or set to 0) to use each reader's own setting.
                </p>

                @if ($capacityOverride > 0)
                    <div class="mb-4 inline-flex items-center gap-2 px-3 py-1.5 bg-amber-50 border border-amber-200 rounded text-xs text-amber-800">
                        <svg class="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                        Override active — all readers capped at <strong class="mx-0.5">{{ $capacityOverride }}</strong> assignment{{ $capacityOverride === 1 ? '' : 's' }}.
                    </div>
                @endif

                <form method="POST" action="{{ route('settings.capacity-override') }}">
                    @csrf
                    @method('PATCH')
                    <div class="flex items-end gap-3 flex-wrap">
                        <div>
                            <x-input-label for="capacity_override" value="Max concurrent assignments (all readers)" />
                            <input type="number" id="capacity_override" name="capacity_override"
                                   min="0" max="99" step="1"
                                   value="{{ $capacityOverride > 0 ? $capacityOverride : '' }}"
                                   placeholder="e.g. 3"
                                   class="mt-1 block w-28 border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" />
                            <x-input-error class="mt-1" :messages="$errors->get('capacity_override')" />
                        </div>
                        <x-primary-button>Save</x-primary-button>
                        @if ($capacityOverride > 0)
                            <button type="submit" name="capacity_override" value="0"
                                    class="inline-flex items-center px-3 py-2 bg-white border border-gray-300 rounded-md text-sm font-medium text-gray-600 hover:bg-gray-50 transition">
                                Clear override
                            </button>
                        @endif
                    </div>
                    <div class="mt-3">
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="hidden" name="capacity_override_excludes_rush_requests" value="0" />
                            <input type="checkbox" name="capacity_override_excludes_rush_requests" value="1"
                                   {{ $capacityOverrideExcludesRushRequests ? 'checked' : '' }}
                                   class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                            <span class="text-sm text-gray-700">Reader Requests and Rush orders do not apply to this cap</span>
                        </label>
                    </div>
                </form>
            </div>

            @if($isAdmin)

            {{-- Assignment Age Colour Thresholds --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 class="text-sm font-semibold text-gray-800 mb-1">Assignment Age Colours</h3>
                <p class="text-xs text-gray-500 mb-4">
                    Set the hour thresholds at which each assignment type's age text changes colour.
                    <span class="inline-block w-2 h-2 rounded-full bg-green-500 mx-0.5"></span> Green = fresh,
                    <span class="inline-block w-2 h-2 rounded-full bg-yellow-400 mx-0.5"></span> Yellow,
                    <span class="inline-block w-2 h-2 rounded-full bg-orange-400 mx-0.5"></span> Orange,
                    <span class="inline-block w-2 h-2 rounded-full bg-red-500 mx-0.5"></span> Red = overdue.
                </p>
                <form method="POST" action="{{ route('settings.age-thresholds') }}">
                    @csrf
                    @method('PATCH')
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-xs text-gray-500 uppercase tracking-wide border-b border-gray-100">
                                    <th class="text-left py-2 pr-4 font-medium">Service</th>
                                    <th class="text-center py-2 px-3 font-medium">
                                        <span class="inline-block w-2 h-2 rounded-full bg-yellow-400 mr-1"></span>Yellow after (hours)
                                    </th>
                                    <th class="text-center py-2 px-3 font-medium">
                                        <span class="inline-block w-2 h-2 rounded-full bg-orange-400 mr-1"></span>Orange after (hours)
                                    </th>
                                    <th class="text-center py-2 px-3 font-medium">
                                        <span class="inline-block w-2 h-2 rounded-full bg-red-500 mr-1"></span>Red after (hours)
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                @foreach ($ageThresholdTypes as $type => $label)
                                    <tr>
                                        <td class="py-2 pr-4 text-gray-700 font-medium whitespace-nowrap">{{ $label }}</td>
                                        <td class="py-2 px-3 text-center">
                                            <input type="number" name="yellow_{{ $type }}"
                                                   value="{{ $ageThresholds[$type]['yellow'] }}"
                                                   min="1" max="8760" required
                                                   class="w-16 text-center border-gray-300 rounded-md shadow-sm text-sm focus:ring-yellow-400 focus:border-yellow-400">
                                        </td>
                                        <td class="py-2 px-3 text-center">
                                            <input type="number" name="orange_{{ $type }}"
                                                   value="{{ $ageThresholds[$type]['orange'] }}"
                                                   min="1" max="8760" required
                                                   class="w-16 text-center border-gray-300 rounded-md shadow-sm text-sm focus:ring-orange-400 focus:border-orange-400">
                                        </td>
                                        <td class="py-2 px-3 text-center">
                                            <input type="number" name="red_{{ $type }}"
                                                   value="{{ $ageThresholds[$type]['red'] }}"
                                                   min="1" max="8760" required
                                                   class="w-16 text-center border-gray-300 rounded-md shadow-sm text-sm focus:ring-red-500 focus:border-red-500">
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4">
                        <x-primary-button>Save Thresholds</x-primary-button>
                    </div>
                </form>
            </div>

            {{-- Reader Download Watermark --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6"
                 x-data="{
                     showName: {{ $watermarkSettings['watermark_show_name'] ? 'true' : 'false' }},
                     showOrder: {{ $watermarkSettings['watermark_show_order'] ? 'true' : 'false' }},
                     showDatetime: {{ $watermarkSettings['watermark_show_datetime'] ? 'true' : 'false' }},
                     showRef: {{ $watermarkSettings['watermark_show_ref'] ? 'true' : 'false' }},
                     customText: @js($watermarkSettings['watermark_custom_text']),
                     get preview() {
                         const parts = [];
                         if (this.customText.trim()) parts.push(this.customText.trim());
                         if (this.showName) parts.push('Jane Reader');
                         if (this.showOrder) parts.push('Order #SR-12345');
                         if (this.showDatetime) parts.push('Jun 10, 2026 3:45pm');
                         if (this.showRef) parts.push('Ref DL-42');
                         return parts.length ? parts.join(' · ') : 'Screenplay Readers';
                     }
                 }">
                <h3 class="text-sm font-semibold text-gray-800 mb-1">Reader Download Watermark</h3>
                <p class="text-xs text-gray-500 mb-4">Choose which fields appear in the diagonal watermark tiled across scripts readers download.</p>

                <form method="POST" action="{{ route('settings.watermark') }}" class="space-y-4">
                    @csrf
                    @method('PATCH')

                    <div class="space-y-2">
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" name="watermark_show_name" value="1" x-model="showName"
                                   class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                            <span class="text-sm text-gray-700">Reader name</span>
                        </label>
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" name="watermark_show_order" value="1" x-model="showOrder"
                                   class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                            <span class="text-sm text-gray-700">Order number</span>
                        </label>
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" name="watermark_show_datetime" value="1" x-model="showDatetime"
                                   class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                            <span class="text-sm text-gray-700">Download date &amp; time</span>
                        </label>
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" name="watermark_show_ref" value="1" x-model="showRef"
                                   class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                            <span class="text-sm text-gray-700">Download reference ID</span>
                        </label>
                    </div>

                    <div>
                        <x-input-label for="watermark_custom_text" value="Custom text (optional)" />
                        <input type="text" id="watermark_custom_text" name="watermark_custom_text" maxlength="200"
                               x-model="customText"
                               placeholder="e.g. Confidential — Property of Screenplay Readers"
                               class="mt-1 block w-full text-sm rounded-md border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500" />
                        <p class="mt-1 text-xs text-gray-400">Prepended to the watermark, e.g. a confidentiality notice or company name.</p>
                        <x-input-error class="mt-1" :messages="$errors->get('watermark_custom_text')" />
                    </div>

                    <div>
                        <p class="text-xs text-gray-500 mb-1">Preview:</p>
                        <p class="text-sm font-mono bg-gray-50 border border-gray-200 rounded px-3 py-2 text-gray-600" x-text="preview"></p>
                    </div>

                    <div class="flex justify-end">
                        <x-primary-button>Save Watermark Settings</x-primary-button>
                    </div>
                </form>
            </div>

            {{-- Filename Conventions --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h3 class="text-sm font-semibold text-gray-800 mb-1">Filename Conventions</h3>
                    <p class="text-xs text-gray-500">
                        Coverage docs use: <code class="bg-gray-100 rounded px-1 py-0.5 text-xs">ordernumber_YYYYMMDD_Title_WLast_<span class="text-indigo-600">suffix</span>-ReaderInitials.pdf</code>
                    </p>
                </div>

                <form method="POST" action="{{ route('admin.filenames.update') }}">
                    @csrf
                    @method('PATCH')

                    <div class="px-6 py-4 border-b border-gray-100">
                        <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Screenplay Readers (SR)</h4>
                        <div class="space-y-3" x-data="{
                            sr_script_coverage: '{{ $filenameSuffixes['filename_suffix_sr_script_coverage'] }}',
                            sr_notes_only:      '{{ $filenameSuffixes['filename_suffix_sr_notes_only'] }}',
                            sr_deep_dive:       '{{ $filenameSuffixes['filename_suffix_sr_deep_dive'] }}',
                            sr_book:            '{{ $filenameSuffixes['filename_suffix_sr_book'] }}',
                            sr_budget:          '{{ $filenameSuffixes['filename_suffix_sr_budget'] }}',
                            sr_short:           '{{ $filenameSuffixes['filename_suffix_sr_short'] }}',
                        }">
                            @foreach([
                                ['key' => 'sr_script_coverage', 'label' => 'Script Coverage'],
                                ['key' => 'sr_notes_only',      'label' => 'Notes Only'],
                                ['key' => 'sr_deep_dive',       'label' => 'Advanced Script Coverage'],
                                ['key' => 'sr_book',            'label' => 'Book Coverage'],
                                ['key' => 'sr_budget',          'label' => 'Budget Coverage'],
                                ['key' => 'sr_short',           'label' => 'Short Coverage'],
                            ] as $row)
                                <div class="grid grid-cols-[160px_160px_1fr] gap-4 items-center">
                                    <label class="text-sm text-gray-700">{{ $row['label'] }}</label>
                                    <input type="text"
                                           name="filename_suffix_{{ $row['key'] }}"
                                           x-model="{{ $row['key'] }}"
                                           placeholder="suffix"
                                           class="rounded-md border-gray-300 shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500 font-mono" />
                                    <p class="text-xs text-gray-400 font-mono truncate">
                                        19192_…_GLucas_<span class="text-indigo-600" x-text="{{ $row['key'] }} || '…'"></span>-KD.pdf
                                    </p>
                                </div>
                                @error('filename_suffix_' . $row['key'])
                                    <p class="text-xs text-red-600 col-start-2">{{ $message }}</p>
                                @enderror
                            @endforeach
                        </div>
                    </div>

                    <div class="px-6 py-4 border-b border-gray-100">
                        <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Writer's Digest (WD)</h4>
                        <div class="space-y-3" x-data="{
                            wd_coverage:          '{{ $filenameSuffixes['filename_suffix_wd_coverage'] }}',
                            wd_development_notes: '{{ $filenameSuffixes['filename_suffix_wd_development_notes'] }}',
                        }">
                            @foreach([
                                ['key' => 'wd_coverage',          'label' => 'Coverage'],
                                ['key' => 'wd_development_notes', 'label' => 'Development Notes'],
                            ] as $row)
                                <div class="grid grid-cols-[160px_160px_1fr] gap-4 items-center">
                                    <label class="text-sm text-gray-700">{{ $row['label'] }}</label>
                                    <input type="text"
                                           name="filename_suffix_{{ $row['key'] }}"
                                           x-model="{{ $row['key'] }}"
                                           placeholder="suffix"
                                           class="rounded-md border-gray-300 shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500 font-mono" />
                                    <p class="text-xs text-gray-400 font-mono truncate">
                                        WD_…_GLucas_<span class="text-indigo-600" x-text="{{ $row['key'] }} || '…'"></span>-KD.pdf
                                    </p>
                                </div>
                                @error('filename_suffix_' . $row['key'])
                                    <p class="text-xs text-red-600 col-start-2">{{ $message }}</p>
                                @enderror
                            @endforeach
                        </div>
                    </div>

                    <div class="px-6 py-4">
                        <x-primary-button>Save Conventions</x-primary-button>
                    </div>
                </form>
            </div>

            {{-- QC Saved Replies --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6"
                 x-data="{
                     replies: {{ Js::from($qcSavedReplies) }},
                     addReply() {
                         this.replies.push({ name: '', body: '' });
                         this.$nextTick(() => {
                             const inputs = this.$el.querySelectorAll('input[name*=\"[name]\"]');
                             inputs[inputs.length - 1]?.focus();
                         });
                     },
                     removeReply(idx) {
                         this.replies.splice(idx, 1);
                     }
                 }">
                <h3 class="text-sm font-semibold text-gray-800 mb-1">QC Saved Replies</h3>
                <p class="text-xs text-gray-500 mb-4">
                    Quick-insert notes shown as checkboxes in the "Send Back to Reader" modal on the QC review page.
                    Check one or more to append the text into the notes field before sending.
                </p>

                <form method="POST" action="{{ route('settings.qc-saved-replies') }}">
                    @csrf
                    @method('PATCH')

                    <div class="space-y-3 mb-4">
                        <template x-for="(reply, idx) in replies" :key="idx">
                            <div class="flex gap-2 items-start p-3 bg-gray-50 rounded-md border border-gray-200">
                                <div class="flex flex-col gap-2 flex-1 min-w-0">
                                    <input type="text"
                                           :name="'replies[' + idx + '][name]'"
                                           :value="replies[idx].name"
                                           @input="replies[idx].name = $event.target.value"
                                           placeholder="Reply name (e.g. Too much formatting talk)"
                                           maxlength="100"
                                           class="w-full text-sm border border-gray-300 rounded px-2.5 py-1.5 focus:outline-none focus:ring-1 focus:ring-indigo-400" />
                                    <textarea
                                           :name="'replies[' + idx + '][body]'"
                                           @input="replies[idx].body = $event.target.value"
                                           placeholder="Text inserted into the notes field…"
                                           rows="2"
                                           maxlength="2000"
                                           x-text="replies[idx].body"
                                           class="w-full text-sm border border-gray-300 rounded px-2.5 py-1.5 resize-none focus:outline-none focus:ring-1 focus:ring-indigo-400"></textarea>
                                </div>
                                <button type="button" @click="removeReply(idx)"
                                        class="shrink-0 mt-1 text-gray-400 hover:text-red-500 transition-colors"
                                        title="Remove">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>
                        </template>
                    </div>

                    <div class="flex items-center justify-between">
                        <button type="button" @click="addReply()"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm text-indigo-600 border border-indigo-300 rounded-md hover:bg-indigo-50 transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            Add reply
                        </button>
                        <x-primary-button>Save replies</x-primary-button>
                    </div>
                </form>
            </div>

            {{-- Coverage Submission Page --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 class="text-sm font-semibold text-gray-800 mb-1">Coverage Submission Page</h3>
                <p class="text-xs text-gray-500 mb-4">
                    Content shown beneath the "Assignment Submitted for QC" confirmation. Accepts raw HTML. Leave blank for none.
                </p>

                <form method="POST" action="{{ route('settings.coverage-success.update') }}">
                    @csrf
                    @method('PATCH')
                    <textarea name="content" rows="8"
                              class="block w-full border-gray-300 rounded-md shadow-sm text-sm font-mono focus:border-indigo-500 focus:ring-indigo-500"
                              placeholder="<p>Your HTML here...</p>">{{ old('content', $coverageSuccessHtml) }}</textarea>
                    <x-input-error :messages="$errors->get('content')" class="mt-1" />

                    @if($coverageSuccessHtml)
                        <details class="mt-3">
                            <summary class="text-xs text-gray-400 cursor-pointer select-none hover:text-gray-600">Preview</summary>
                            <div class="mt-2 p-4 bg-gray-50 border border-gray-200 rounded-md text-sm text-gray-700 prose prose-sm max-w-none">
                                {!! $coverageSuccessHtml !!}
                            </div>
                        </details>
                    @endif

                    <div class="flex justify-end mt-4">
                        <x-primary-button>Save</x-primary-button>
                    </div>
                </form>
            </div>

            {{-- Coverage Form — Dev Autofill --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 class="text-sm font-semibold text-gray-800 mb-1">Coverage Form — Dev Autofill</h3>
                <p class="text-xs text-gray-500 mb-4">
                    Show the "DEV: Autofill test data" button on the Write Coverage form. Enable per role for testing; disable before going live.
                </p>
                <form method="POST" action="{{ route('settings.dev-autofill') }}">
                    @csrf
                    @method('PATCH')
                    <div class="space-y-3">
                        @foreach (['admin' => 'Admins', 'editor' => 'Editors', 'reader' => 'Readers'] as $role => $label)
                            <label class="flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" name="dev_autofill_{{ $role }}" value="1"
                                       {{ $devAutofill[$role] ? 'checked' : '' }}
                                       class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                                <span class="text-sm text-gray-700">Show autofill button for <strong>{{ $label }}</strong></span>
                            </label>
                        @endforeach
                    </div>
                    <div class="flex justify-end mt-4">
                        <x-primary-button>Save</x-primary-button>
                    </div>
                </form>
            </div>

            @if($wordCounts !== null)
            {{-- Word Count Minimums --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 class="text-sm font-semibold text-gray-800 mb-1">Coverage Word Count Minimums</h3>
                <p class="text-xs text-gray-500 mb-4">
                    Set minimum word counts for each coverage field. Readers cannot submit coverage until these minimums are met
                    (unless the assignment is marked <em>Exempt from word counts</em>). Set a field to 0 to require no minimum.
                </p>

                <form method="POST" action="{{ route('settings.word-counts') }}" class="space-y-6">
                    @csrf
                    @method('PATCH')

                    <div class="flex items-center gap-3">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="hidden" name="wc_enabled" value="0" />
                            <input type="checkbox" name="wc_enabled" value="1"
                                {{ $wordCounts['wc_enabled'] ? 'checked' : '' }}
                                class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                            <span class="text-sm font-medium text-gray-700">Enable word count minimums globally</span>
                        </label>
                        @if(!$wordCounts['wc_enabled'])
                            <span class="text-xs text-amber-600 font-medium">Currently disabled — no word counts enforced</span>
                        @endif
                    </div>

                    <div class="border-t border-gray-100 pt-5 space-y-4">
                        <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">SR Coverage</h4>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                            <div>
                                <x-input-label value="Logline (min words)" />
                                <input type="number" name="wc_sr_logline" min="0" max="99999"
                                    value="{{ old('wc_sr_logline', $wordCounts['wc_sr_logline']) }}"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" />
                                <p class="mt-0.5 text-xs text-gray-400">Applies to SR logline field</p>
                            </div>
                            <div>
                                <x-input-label value="Synopsis (min words)" />
                                <input type="number" name="wc_sr_synopsis" min="0" max="99999"
                                    value="{{ old('wc_sr_synopsis', $wordCounts['wc_sr_synopsis']) }}"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" />
                                <p class="mt-0.5 text-xs text-gray-400">Script Coverage & Book types</p>
                            </div>
                        </div>
                        <div>
                            <p class="text-xs font-medium text-gray-600 mb-2">Notes — minimum words by assignment type</p>
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                                @foreach([
                                    'wc_sr_notes_script_coverage' => 'Script Coverage',
                                    'wc_sr_notes_notes_only'      => 'Notes-Only',
                                    'wc_sr_notes_short'           => 'Short',
                                    'wc_sr_notes_deep_dive'       => 'Advanced Script Coverage',
                                    'wc_sr_notes_budget'          => 'Budget',
                                    'wc_sr_notes_book'            => 'Book',
                                ] as $key => $label)
                                    <div>
                                        <x-input-label :value="$label" />
                                        <input type="number" name="{{ $key }}" min="0" max="99999"
                                            value="{{ old($key, $wordCounts[$key]) }}"
                                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" />
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-gray-100 pt-5 space-y-4">
                        <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">WD Coverage</h4>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                            <div>
                                <x-input-label value="Logline (min words)" />
                                <input type="number" name="wc_wd_logline" min="0" max="99999"
                                    value="{{ old('wc_wd_logline', $wordCounts['wc_wd_logline']) }}"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" />
                            </div>
                            <div>
                                <x-input-label value="Synopsis (min words)" />
                                <input type="number" name="wc_wd_synopsis" min="0" max="99999"
                                    value="{{ old('wc_wd_synopsis', $wordCounts['wc_wd_synopsis']) }}"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" />
                                <p class="mt-0.5 text-xs text-gray-400">Coverage type only</p>
                            </div>
                        </div>
                        <div>
                            <p class="text-xs font-medium text-gray-600 mb-2">Notes — total minimum words by assignment type</p>
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                                @foreach([
                                    'wc_wd_notes_coverage'          => 'Coverage',
                                    'wc_wd_notes_development_notes' => 'Development Notes',
                                ] as $key => $label)
                                    <div>
                                        <x-input-label :value="$label" />
                                        <input type="number" name="{{ $key }}" min="0" max="99999"
                                            value="{{ old($key, $wordCounts[$key]) }}"
                                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" />
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end border-t border-gray-100 pt-4">
                        <x-primary-button>Save word count settings</x-primary-button>
                    </div>
                </form>
            </div>
            @endif

            @if($blockedReaderLimits !== null)
            {{-- Block-Reader Limits --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 class="text-sm font-semibold text-gray-800 mb-1">Block-Reader Limits</h3>
                <p class="text-xs text-gray-500 mb-4">
                    Sets how many readers a customer can block on the script upload form. Applies to coverage orders only.
                </p>

                <form method="POST" action="{{ route('settings.blocked-reader-limits') }}" class="space-y-4">
                    @csrf
                    @method('PATCH')
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                        <div>
                            <x-input-label value="1-reader orders" />
                            <input type="number" name="max_blockable_1r" min="0" max="10"
                                value="{{ old('max_blockable_1r', $blockedReaderLimits['max_blockable_1r']) }}"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" />
                            <p class="mt-0.5 text-xs text-gray-400">Max readers a customer can block</p>
                        </div>
                        <div>
                            <x-input-label value="2-reader / 3-reader orders" />
                            <input type="number" name="max_blockable_multi" min="0" max="10"
                                value="{{ old('max_blockable_multi', $blockedReaderLimits['max_blockable_multi']) }}"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" />
                            <p class="mt-0.5 text-xs text-gray-400">Max readers a customer can block</p>
                        </div>
                    </div>
                    <div class="flex justify-end border-t border-gray-100 pt-4">
                        <x-primary-button>Save block-reader limits</x-primary-button>
                    </div>
                </form>
            </div>
            @endif

            {{-- Tier-2 Release Window --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 class="text-sm font-semibold text-gray-800 mb-1">Tier-2 Release Window</h3>
                <p class="text-xs text-gray-500 mb-4">
                    If a Tier 1 assignment sits unaccepted for this many hours, it also becomes available to Tier 2 readers (in addition to Tier 1).
                </p>

                <form method="POST" action="{{ route('settings.tier2-release-hours') }}">
                    @csrf
                    @method('PATCH')
                    <div class="flex items-end gap-3 flex-wrap">
                        <div>
                            <x-input-label for="tier2_release_hours" value="Hours before Tier 2 release" />
                            <input type="number" id="tier2_release_hours" name="tier2_release_hours"
                                   min="1" max="720" step="1" required
                                   value="{{ old('tier2_release_hours', $tier2ReleaseHours) }}"
                                   class="mt-1 block w-28 border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" />
                            <x-input-error class="mt-1" :messages="$errors->get('tier2_release_hours')" />
                        </div>
                        <x-primary-button>Save</x-primary-button>
                    </div>
                </form>
            </div>

            @endif
            </div>
        </div>
    </div>
</x-app-layout>
