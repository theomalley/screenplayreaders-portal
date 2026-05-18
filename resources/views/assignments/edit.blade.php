<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-4">
            <a href="{{ route('assignments.index') }}" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Edit Assignment</h2>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                @php
                    $isOld      = session()->hasOldInput();
                    $v          = fn($field, $default) => $isOld ? old($field) : $default;
                    $rushActive = $isOld ? (bool) old('rush') : (bool) $assignment->rush;
                @endphp

                <form id="update-form" method="POST" action="{{ route('assignments.update', $assignment) }}" class="p-6 space-y-5"
                      x-data="{
                          vendor: '{{ $v('vendor', $assignment->vendor) }}',
                          assignmentType: '{{ $v('assignment_type', $assignment->assignment_type ?? '') }}',
                          pageCount: '{{ $v('page_count', $assignment->page_count) }}',
                          customOversizedFee: '{{ $v('custom_oversized_fee', '') }}',
                          rush: {{ $rushActive ? 'true' : 'false' }},
                          requestedReaderId: '{{ $v('requested_reader_id', $assignment->requested_reader_id ?? '') }}',
                          overrideRate: true,
                          updatePayDisplay() {
                              if (this.overrideRate) return;
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
                              const el     = document.getElementById('pay_rate_display');
                              const hidden = document.getElementById('pay_rate_hidden');
                              if (!el) return;

                              let sharedMod = 0;
                              const pages = parseInt(this.pageCount, 10);
                              if (!isNaN(pages)) {
                                  if (pages >= 121 && pages <= 160) {
                                      sharedMod += parseFloat(oversized121[this.vendor] || 0);
                                  } else if (pages >= 161) {
                                      const fee = parseFloat(this.customOversizedFee);
                                      if (!isNaN(fee)) sharedMod += fee;
                                  }
                              }
                              if (this.rush)
                                  sharedMod += parseFloat({ sr: r.rate_sr_rush, wd: r.rate_wd_rush }[this.vendor] || 0);

                              const reqRate = parseFloat({ sr: r.rate_sr_request, wd: r.rate_wd_request }[this.vendor] || 0);
                              const reqMod  = this.requestedReaderId ? reqRate : 0;

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
                              const total = parseFloat(base) + sharedMod + reqMod;
                              el.textContent = '$' + total.toFixed(2);
                              el.className = 'text-sm font-semibold text-gray-900';
                              if (hidden) hidden.value = total.toFixed(2);
                          },
                          init() { /* overrideRate starts true; pay_rate_hidden pre-set via value attr */ }
                      }">
                    @csrf
                    @method('PATCH')

                    {{-- Vendor --}}
                    <div>
                        <x-input-label value="Vendor" />
                        <div class="mt-2 flex gap-6">
                            <label class="flex items-center gap-2 text-sm font-medium text-gray-700 cursor-pointer">
                                <input type="radio" name="vendor" value="sr"
                                    {{ $v('vendor', $assignment->vendor) === 'sr' ? 'checked' : '' }}
                                    @change="vendor = 'sr'; updatePayDisplay()"
                                    class="text-indigo-600 border-gray-300 focus:ring-indigo-500" />
                                SR
                            </label>
                            <label class="flex items-center gap-2 text-sm font-medium text-gray-700 cursor-pointer">
                                <input type="radio" name="vendor" value="wd"
                                    {{ $v('vendor', $assignment->vendor) === 'wd' ? 'checked' : '' }}
                                    @change="vendor = 'wd'; updatePayDisplay()"
                                    class="text-indigo-600 border-gray-300 focus:ring-indigo-500" />
                                WD
                            </label>
                        </div>
                        <x-input-error :messages="$errors->get('vendor')" class="mt-1" />
                    </div>

                    {{-- Assignment Type SR --}}
                    <div x-show="vendor === 'sr'">
                        <x-input-label for="assignment_type_sr" value="Assignment Type" />
                        <select id="assignment_type_sr" name="assignment_type"
                            :disabled="vendor !== 'sr'"
                            @change="assignmentType = $event.target.value; updatePayDisplay()"
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                            <option value="">— Select type —</option>
                            <option value="script_coverage"  {{ $v('assignment_type', $assignment->assignment_type) === 'script_coverage'  ? 'selected' : '' }}>Script Coverage</option>
                            <option value="notes_only"       {{ $v('assignment_type', $assignment->assignment_type) === 'notes_only'       ? 'selected' : '' }}>Notes-Only</option>
                            <option value="deep_dive"        {{ $v('assignment_type', $assignment->assignment_type) === 'deep_dive'        ? 'selected' : '' }}>Deep-Dive Dev Notes</option>
                            <option value="short"            {{ $v('assignment_type', $assignment->assignment_type) === 'short'            ? 'selected' : '' }}>Short Coverage</option>
                            <option value="budget"           {{ $v('assignment_type', $assignment->assignment_type) === 'budget'           ? 'selected' : '' }}>Budget Coverage</option>
                            <option value="book"             {{ $v('assignment_type', $assignment->assignment_type) === 'book'             ? 'selected' : '' }}>Book Coverage</option>
                        </select>
                        <x-input-error :messages="$errors->get('assignment_type')" class="mt-1" />
                    </div>

                    {{-- Assignment Type WD --}}
                    <div x-show="vendor === 'wd'">
                        <x-input-label for="assignment_type_wd" value="Assignment Type" />
                        <select id="assignment_type_wd" name="assignment_type"
                            :disabled="vendor !== 'wd'"
                            @change="assignmentType = $event.target.value; updatePayDisplay()"
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                            <option value="">— Select type —</option>
                            <option value="coverage"          {{ $v('assignment_type', $assignment->assignment_type) === 'coverage'          ? 'selected' : '' }}>Coverage</option>
                            <option value="development_notes" {{ $v('assignment_type', $assignment->assignment_type) === 'development_notes' ? 'selected' : '' }}>Development Notes</option>
                        </select>
                        <x-input-error :messages="$errors->get('assignment_type')" class="mt-1" />
                    </div>

                    {{-- Page Count --}}
                    <div>
                        <x-input-label for="page_count" value="Page Count" />
                        <input type="number" id="page_count" name="page_count"
                            min="1" step="1"
                            value="{{ $v('page_count', $assignment->page_count) }}"
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
                                    {{ $rushActive ? 'checked' : '' }}
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
                            @change="requestedReaderId = $event.target.value; updatePayDisplay()"
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                            <option value="">None</option>
                            @foreach ($readers as $reader)
                                <option value="{{ $reader->id }}"
                                    {{ $v('requested_reader_id', $assignment->requested_reader_id) == $reader->id ? 'selected' : '' }}>
                                    {{ $reader->readerProfile?->initials ?? $reader->name }}
                                </option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('requested_reader_id')" class="mt-1" />
                    </div>

                    {{-- Pay Rate --}}
                    <div class="pt-4 border-t border-gray-100">
                        <x-input-label value="Pay Rate" />

                        <input type="hidden" id="pay_rate_hidden" name="pay_rate"
                            value="{{ $v('pay_rate', $assignment->pay_rate) }}" />

                        <div x-show="overrideRate" class="mt-1">
                            <div class="flex items-center gap-1">
                                <span class="text-gray-400 text-sm">$</span>
                                <input type="number" id="pay_rate_override"
                                    min="0" step="0.01" placeholder="0.00"
                                    value="{{ $v('pay_rate', $assignment->pay_rate) }}"
                                    @input="document.getElementById('pay_rate_hidden').value = $event.target.value"
                                    class="block w-32 border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" />
                            </div>
                        </div>

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

                        <div class="mt-2 flex items-center gap-2">
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
                                value="{{ $v('order_number', $assignment->order_number) }}"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" />
                            <x-input-error :messages="$errors->get('order_number')" class="mt-1" />
                        </div>

                        <div>
                            <x-input-label for="script_title" value="Title" />
                            <input type="text" id="script_title" name="script_title"
                                value="{{ $v('script_title', $assignment->script_title) }}"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" />
                            <x-input-error :messages="$errors->get('script_title')" class="mt-1" />
                        </div>

                        <div>
                            <x-input-label for="writer_name" value="Writer Name" />
                            <input type="text" id="writer_name" name="writer_name"
                                value="{{ $v('writer_name', $assignment->writer_name) }}"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" />
                            <x-input-error :messages="$errors->get('writer_name')" class="mt-1" />
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="date" value="Date" />
                                <input type="date" id="date" name="date"
                                    value="{{ $v('date', $assignment->created_at->toDateString()) }}"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" />
                                <x-input-error :messages="$errors->get('date')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label for="time" value="Time" />
                                <input type="time" id="time" name="time"
                                    value="{{ $v('time', $assignment->created_at->format('H:i')) }}"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" />
                                <x-input-error :messages="$errors->get('time')" class="mt-1" />
                            </div>
                        </div>

                        <div>
                            <x-input-label for="assigned_reader_id" value="Assigned Reader" />
                            <select id="assigned_reader_id" name="assigned_reader_id"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                                <option value="">None</option>
                                @foreach ($readers as $reader)
                                    <option value="{{ $reader->id }}"
                                        {{ $v('assigned_reader_id', $assignment->assigned_reader_id) == $reader->id ? 'selected' : '' }}>
                                        {{ $reader->readerProfile?->initials ?? $reader->name }}
                                        — {{ $reader->readerProfile?->displayName() ?? $reader->name }}
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('assigned_reader_id')" class="mt-1" />
                        </div>

                        <div>
                            <x-input-label for="status" value="Status" />
                            <select id="status" name="status"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                                <option value="incoming"   {{ $v('status', $assignment->status) === 'incoming'   ? 'selected' : '' }}>Pending</option>
                                <option value="unassigned" {{ $v('status', $assignment->status) === 'unassigned' ? 'selected' : '' }}>Available</option>
                                <option value="assigned"   {{ $v('status', $assignment->status) === 'assigned'   ? 'selected' : '' }}>Assigned</option>
                                <option value="qc"         {{ $v('status', $assignment->status) === 'qc'         ? 'selected' : '' }}>QC</option>
                                <option value="completed"  {{ $v('status', $assignment->status) === 'completed'  ? 'selected' : '' }}>Completed</option>
                                <option value="on_hold"    {{ $v('status', $assignment->status) === 'on_hold'    ? 'selected' : '' }}>On Hold</option>
                                <option value="cancelled"  {{ $v('status', $assignment->status) === 'cancelled'  ? 'selected' : '' }}>Cancelled</option>
                            </select>
                            <x-input-error :messages="$errors->get('status')" class="mt-1" />
                        </div>

                        <div>
                            <x-input-label for="notes" value="Notes" />
                            <textarea id="notes" name="notes" rows="3"
                                placeholder="Internal notes (not visible to readers)"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">{{ $v('notes', $assignment->notes) }}</textarea>
                            <x-input-error :messages="$errors->get('notes')" class="mt-1" />
                        </div>

                    </div>

                </form>

                {{-- Actions — outside the edit form so the delete form is never nested --}}
                <div class="flex items-center justify-between px-6 pb-6 pt-4 border-t border-gray-100">
                    <form method="POST" action="{{ route('assignments.destroy', $assignment) }}"
                          onsubmit="return confirm('Permanently delete this assignment? This cannot be undone.')">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                                class="inline-flex items-center px-3 py-1.5 bg-white border border-red-300 rounded text-xs font-medium text-red-600 hover:bg-red-50 transition">
                            Delete Assignment
                        </button>
                    </form>
                    <div class="flex items-center gap-3">
                        <a href="{{ route('assignments.index') }}"
                           class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
                        <x-primary-button form="update-form">Save Changes</x-primary-button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    window._srRates = @json($rates);
    </script>
</x-app-layout>
