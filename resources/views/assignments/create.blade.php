<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-4">
            <a href="{{ route('assignments.index') }}" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Create Assignment</h2>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                <form method="POST" action="{{ route('assignments.store') }}" class="p-6 space-y-5"
                      x-data="{
                          vendor: '{{ old('vendor', 'sr') }}',
                          assignmentType: '{{ old('assignment_type', '') }}',
                          pageCount: '{{ old('page_count', '') }}',
                          customOversizedFee: '{{ old('custom_oversized_fee', '') }}',
                          rush: {{ old('rush') ? 'true' : 'false' }},
                          numReaders: '{{ old('num_readers', '1') }}',
                          requestedReader: '{{ old('requested_reader_id', '') }}',
                          overrideRate: false,
                          updatePayDisplay() {
                              if (this.overrideRate && this.numReaders === '1') return;
                              const r = window._srRates;
                              const map = {
                                  sr: {
                                      script_coverage: r.rate_sr_script_coverage,
                                      notes_only:      r.rate_sr_notes_only,
                                      deep_dive:       r.rate_sr_deep_dive,
                                      short:           r.rate_sr_short,
                                      budget:          r.rate_sr_budget,
                                  },
                                  wd: {
                                      coverage:          r.rate_wd_coverage,
                                      development_notes: r.rate_wd_development_notes,
                                  },
                              };
                              const oversized121 = { sr: r.rate_sr_oversized_121_160, wd: r.rate_wd_oversized_121_160 };
                              const el = document.getElementById('pay_rate_display');
                              const hidden = document.getElementById('pay_rate_hidden');
                              if (!el) return;

                              // Compute modifiers once — applied to every sub-assignment
                              let mod = 0;
                              const pages = parseInt(this.pageCount, 10);
                              if (!isNaN(pages)) {
                                  if (pages >= 121 && pages <= 160) {
                                      mod += parseFloat(oversized121[this.vendor] || 0);
                                  } else if (pages >= 161) {
                                      const fee = parseFloat(this.customOversizedFee);
                                      if (!isNaN(fee)) mod += fee;
                                  }
                              }
                              if (this.rush)
                                  mod += parseFloat({ sr: r.rate_sr_rush, wd: r.rate_wd_rush }[this.vendor] || 0);
                              if (this.requestedReader)
                                  mod += parseFloat({ sr: r.rate_sr_request, wd: r.rate_wd_request }[this.vendor] || 0);

                              if (this.numReaders !== '1') {
                                  const typeA  = this.vendor === 'sr' ? 'script_coverage'   : 'coverage';
                                  const typeB  = this.vendor === 'sr' ? 'notes_only'         : 'development_notes';
                                  const labelA = this.vendor === 'sr' ? 'SC'                 : 'Coverage';
                                  const labelB = this.vendor === 'sr' ? 'NO'                 : 'Dev Notes';
                                  const rateA  = parseFloat((map[this.vendor] || {})[typeA] || 0) + mod;
                                  const rateB  = parseFloat((map[this.vendor] || {})[typeB] || 0) + mod;
                                  const numB   = parseInt(this.numReaders) - 1;
                                  const parts  = ['$' + rateA.toFixed(2) + ' (' + labelA + ')'];
                                  for (let i = 0; i < numB; i++) parts.push('$' + rateB.toFixed(2) + ' (' + labelB + ')');
                                  el.textContent = parts.join(' + ');
                                  el.className = 'text-sm font-semibold text-gray-900';
                                  if (hidden) hidden.value = '';
                                  return;
                              }

                              if (!this.assignmentType) {
                                  el.textContent = '—';
                                  el.className = 'text-sm text-gray-400';
                                  if (hidden) hidden.value = '';
                                  return;
                              }
                              if (this.vendor === 'sr' && this.assignmentType === 'book') {
                                  el.textContent = 'Custom (set per assignment)';
                                  el.className = 'text-sm text-gray-500 italic';
                                  if (hidden) hidden.value = '';
                                  return;
                              }
                              const base = (map[this.vendor] || {})[this.assignmentType];
                              if (base === undefined) {
                                  el.textContent = '—';
                                  el.className = 'text-sm text-gray-400';
                                  if (hidden) hidden.value = '';
                                  return;
                              }
                              const total = parseFloat(base) + mod;
                              el.textContent = '$' + total.toFixed(2);
                              el.className = 'text-sm font-semibold text-gray-900';
                              if (hidden) hidden.value = total.toFixed(2);
                          },
                          init() { this.updatePayDisplay(); }
                      }">
                    @csrf

                    {{-- Vendor: SR is default --}}
                    <div>
                        <x-input-label value="Vendor" />
                        <div class="mt-2 flex gap-6">
                            <label class="flex items-center gap-2 text-sm font-medium text-gray-700 cursor-pointer">
                                <input type="radio" name="vendor" value="sr"
                                    {{ old('vendor', 'sr') === 'sr' ? 'checked' : '' }}
                                    @change="vendor = 'sr'; assignmentType = ''; updatePayDisplay()"
                                    class="text-indigo-600 border-gray-300 focus:ring-indigo-500" />
                                SR
                            </label>
                            <label class="flex items-center gap-2 text-sm font-medium text-gray-700 cursor-pointer">
                                <input type="radio" name="vendor" value="wd"
                                    {{ old('vendor', 'sr') === 'wd' ? 'checked' : '' }}
                                    @change="vendor = 'wd'; assignmentType = ''; updatePayDisplay()"
                                    class="text-indigo-600 border-gray-300 focus:ring-indigo-500" />
                                WD
                            </label>
                        </div>
                        <x-input-error :messages="$errors->get('vendor')" class="mt-1" />
                    </div>

                    {{-- Assignment Type (SR) --}}
                    <div x-show="vendor === 'sr' && numReaders === '1'">
                        <x-input-label for="assignment_type_sr" value="Assignment Type" />
                        <select id="assignment_type_sr" name="assignment_type"
                            :disabled="vendor !== 'sr' || numReaders !== '1'"
                            @change="assignmentType = $event.target.value; updatePayDisplay()"
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                            <option value="">— Select type —</option>
                            <option value="script_coverage"  {{ old('assignment_type') === 'script_coverage'  ? 'selected' : '' }}>Script Coverage</option>
                            <option value="notes_only"       {{ old('assignment_type') === 'notes_only'       ? 'selected' : '' }}>Notes-Only</option>
                            <option value="deep_dive"        {{ old('assignment_type') === 'deep_dive'        ? 'selected' : '' }}>Deep-Dive Dev Notes</option>
                            <option value="short"            {{ old('assignment_type') === 'short'            ? 'selected' : '' }}>Short Coverage</option>
                            <option value="budget"           {{ old('assignment_type') === 'budget'           ? 'selected' : '' }}>Budget Coverage</option>
                            <option value="book"             {{ old('assignment_type') === 'book'             ? 'selected' : '' }}>Book Coverage</option>
                        </select>
                        <x-input-error :messages="$errors->get('assignment_type')" class="mt-1" />
                    </div>

                    {{-- Assignment Type (WD) --}}
                    <div x-show="vendor === 'wd' && numReaders === '1'">
                        <x-input-label for="assignment_type_wd" value="Assignment Type" />
                        <select id="assignment_type_wd" name="assignment_type"
                            :disabled="vendor !== 'wd' || numReaders !== '1'"
                            @change="assignmentType = $event.target.value; updatePayDisplay()"
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                            <option value="">— Select type —</option>
                            <option value="coverage"          {{ old('assignment_type') === 'coverage'          ? 'selected' : '' }}>Coverage</option>
                            <option value="development_notes" {{ old('assignment_type') === 'development_notes' ? 'selected' : '' }}>Development Notes</option>
                        </select>
                        <x-input-error :messages="$errors->get('assignment_type')" class="mt-1" />
                    </div>

                    {{-- # of Readers --}}
                    <div>
                        <x-input-label for="num_readers" value="# of Readers" />
                        <select id="num_readers" name="num_readers"
                            @change="numReaders = $event.target.value; updatePayDisplay()"
                            class="mt-1 block w-24 border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                            <option value="1" {{ old('num_readers', '1') === '1' ? 'selected' : '' }}>1R</option>
                            <option value="2" {{ old('num_readers', '1') === '2' ? 'selected' : '' }}>2R</option>
                            <option value="3" {{ old('num_readers', '1') === '3' ? 'selected' : '' }}>3R</option>
                        </select>
                        <x-input-error :messages="$errors->get('num_readers')" class="mt-1" />
                    </div>

                    {{-- Page Count --}}
                    <div>
                        <x-input-label for="page_count" value="Page Count" />
                        <input type="number" id="page_count" name="page_count"
                            min="1" step="1" placeholder="e.g. 95"
                            x-model="pageCount"
                            @input="updatePayDisplay()"
                            class="mt-1 block w-24 border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" />
                        <x-input-error :messages="$errors->get('page_count')" class="mt-1" />
                    </div>

                    {{-- Turnaround --}}
                    <div>
                        <x-input-label value="Turnaround" />
                        <div class="mt-2 flex items-center gap-3">
                            <label class="flex items-center gap-2 cursor-pointer select-none">
                                <input type="checkbox" name="rush" value="1"
                                    {{ old('rush') ? 'checked' : '' }}
                                    @change="rush = $event.target.checked; updatePayDisplay()"
                                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 focus:ring-offset-0" />
                                <span class="text-sm text-gray-700">Rush</span>
                            </label>
                            <span x-show="!rush" class="text-sm text-gray-400">Standard</span>
                            <span x-show="rush" class="text-sm font-bold text-amber-600 uppercase tracking-wide">Rush</span>
                        </div>
                    </div>

                    {{-- Reader Request --}}
                    <div>
                        <x-input-label for="requested_reader_id" value="Reader Request" />
                        <select id="requested_reader_id" name="requested_reader_id"
                            @change="requestedReader = $event.target.value; updatePayDisplay()"
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                            <option value="">None</option>
                            @foreach ($readers as $reader)
                                <option value="{{ $reader->id }}"
                                    {{ old('requested_reader_id') == $reader->id ? 'selected' : '' }}>
                                    {{ $reader->readerProfile?->initials ?? $reader->name }}
                                </option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('requested_reader_id')" class="mt-1" />
                    </div>

                    {{-- Pay Rate display --}}
                    <div class="pt-4 border-t border-gray-100">
                        <x-input-label value="Pay Rate" />

                        <input type="hidden" id="pay_rate_hidden" name="pay_rate" value="" />

                        <div x-show="!overrideRate" class="mt-1 px-3 py-2 bg-gray-50 border border-gray-200 rounded-md min-h-[38px] flex items-center">
                            <span id="pay_rate_display" class="text-sm text-gray-400">—</span>
                        </div>

                        <div x-show="parseInt(pageCount) >= 161 && !overrideRate" class="mt-3">
                            <x-input-label for="custom_oversized_fee" value="Oversized Fee (161+ pages)" />
                            <div class="mt-1 flex items-center gap-1">
                                <span class="text-gray-400 text-sm">+$</span>
                                <input type="number" id="custom_oversized_fee" name="custom_oversized_fee"
                                    min="0" step="0.01" placeholder="0.00"
                                    x-model="customOversizedFee"
                                    @input="updatePayDisplay()"
                                    class="block w-28 border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" />
                            </div>
                        </div>

                        <div x-show="overrideRate" class="mt-1">
                            <input type="number" id="pay_rate_override"
                                min="0" step="0.01" placeholder="0.00"
                                @input="document.getElementById('pay_rate_hidden').value = $event.target.value"
                                class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" />
                        </div>

                        <div x-show="numReaders === '1'" class="mt-2 flex items-center gap-2">
                            <input type="checkbox" id="override_rate" x-model="overrideRate"
                                @change="if (!overrideRate) updatePayDisplay()"
                                class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 focus:ring-offset-0" />
                            <label for="override_rate" class="text-xs text-gray-500 cursor-pointer select-none">Override pay rate</label>
                        </div>
                    </div>

                    {{-- Assignment Details --}}
                    <div class="pt-4 border-t border-gray-100 space-y-5">

                        <div>
                            <x-input-label for="order_number" value="Order #" />
                            <input type="text" id="order_number" name="order_number"
                                value="{{ old('order_number') }}"
                                placeholder="e.g. 12345"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" />
                            <x-input-error :messages="$errors->get('order_number')" class="mt-1" />
                        </div>

                        <div>
                            <x-input-label for="script_title" value="Title" />
                            <input type="text" id="script_title" name="script_title"
                                value="{{ old('script_title') }}"
                                placeholder="Script title"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" />
                            <x-input-error :messages="$errors->get('script_title')" class="mt-1" />
                        </div>

                        <div>
                            <x-input-label for="writer_name" value="Writer Name" />
                            <input type="text" id="writer_name" name="writer_name"
                                value="{{ old('writer_name') }}"
                                placeholder="e.g. John Smith"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" />
                            <x-input-error :messages="$errors->get('writer_name')" class="mt-1" />
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="date" value="Date" />
                                <input type="date" id="date" name="date"
                                    value="{{ old('date', now()->toDateString()) }}"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" />
                                <x-input-error :messages="$errors->get('date')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label for="time" value="Time" />
                                @php
                                    $defaultTime = now()->setMinutes(now()->minute < 30 ? 0 : 30)->format('H:i');
                                @endphp
                                <select id="time" name="time"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                                    @for ($h = 0; $h < 24; $h++)
                                        @foreach ([0, 30] as $m)
                                            @php $t = sprintf('%02d:%02d', $h, $m); @endphp
                                            <option value="{{ $t }}" {{ old('time', $defaultTime) === $t ? 'selected' : '' }}>{{ $t }}</option>
                                        @endforeach
                                    @endfor
                                </select>
                                <x-input-error :messages="$errors->get('time')" class="mt-1" />
                            </div>
                        </div>

                        <div>
                            <x-input-label for="status" value="Status" />
                            <select id="status" name="status"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                                <option value="incoming"   {{ old('status', 'incoming') === 'incoming'   ? 'selected' : '' }}>Pending</option>
                                <option value="unassigned" {{ old('status', 'incoming') === 'unassigned' ? 'selected' : '' }}>Available</option>
                                <option value="assigned"   {{ old('status', 'incoming') === 'assigned'   ? 'selected' : '' }}>Assigned</option>
                                <option value="qc"         {{ old('status', 'incoming') === 'qc'         ? 'selected' : '' }}>QC</option>
                                <option value="completed"  {{ old('status', 'incoming') === 'completed'  ? 'selected' : '' }}>Completed</option>
                                <option value="on_hold"    {{ old('status', 'incoming') === 'on_hold'    ? 'selected' : '' }}>On Hold</option>
                                <option value="cancelled"  {{ old('status', 'incoming') === 'cancelled'  ? 'selected' : '' }}>Cancelled</option>
                            </select>
                            <x-input-error :messages="$errors->get('status')" class="mt-1" />
                        </div>

                    </div>

                    {{-- Actions --}}
                    <div class="flex items-center justify-end gap-3 pt-2 border-t border-gray-100">
                        <a href="{{ route('assignments.index') }}"
                           class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
                        <x-primary-button>Create Assignment</x-primary-button>
                    </div>

                </form>
            </div>
        </div>
    </div>

    <script>
    window._srRates = @json($rates);
    </script>
</x-app-layout>
