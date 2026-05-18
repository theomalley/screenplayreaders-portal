<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-4">
            <a href="{{ route('assignments.index') }}" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Edit Assignment #{{ $assignment->order_number }}
            </h2>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                <form method="POST" action="{{ route('assignments.update', $assignment) }}" class="p-6 space-y-5"
                      x-data="assignmentForm(
                          '{{ old('vendor', $assignment->vendor) }}',
                          '{{ old('assignment_type', $assignment->assignment_type) }}',
                          {{ old('rush', $assignment->rush) ? 'true' : 'false' }},
                          '{{ old('requested_reader_id', $assignment->requested_reader_id ?? '') }}',
                          '{{ old('page_count', $assignment->page_count) }}',
                          '{{ old('assigned_reader_id', $assignment->assigned_reader_id ?? '') }}',
                          '{{ old('status', $assignment->status) }}',
                          @json($rates)
                      )">
                    @csrf
                    @method('PATCH')

                    {{-- Order number --}}
                    <div>
                        <x-input-label for="order_number" value="Order Number" />
                        <x-text-input id="order_number" name="order_number" type="text"
                            class="mt-1 block w-full"
                            value="{{ old('order_number', $assignment->order_number) }}"
                            required autofocus />
                        <x-input-error :messages="$errors->get('order_number')" class="mt-1" />
                    </div>

                    {{-- Vendor + Assignment Type --}}
                    <div class="grid grid-cols-2 gap-3 items-start">
                        <div>
                            <x-input-label value="Vendor" />
                            <div class="mt-2 flex gap-4">
                                <label class="flex items-center gap-1.5 text-sm font-medium text-gray-700 cursor-pointer">
                                    <input type="radio" name="vendor" value="sr"
                                        :checked="vendor === 'sr'"
                                        @change="vendor = 'sr'; assignmentType = ''; computeRate()"
                                        class="text-indigo-600 border-gray-300 focus:ring-indigo-500" />
                                    SR
                                </label>
                                <label class="flex items-center gap-1.5 text-sm font-medium text-gray-700 cursor-pointer">
                                    <input type="radio" name="vendor" value="wd"
                                        :checked="vendor === 'wd'"
                                        @change="vendor = 'wd'; assignmentType = ''; computeRate()"
                                        class="text-indigo-600 border-gray-300 focus:ring-indigo-500" />
                                    WD
                                </label>
                            </div>
                            <x-input-error :messages="$errors->get('vendor')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="assignment_type" value="Assignment Type" />
                            {{-- SR types: shown when vendor = sr --}}
                            <select x-show="vendor === 'sr'" x-cloak
                                id="assignment_type" name="assignment_type"
                                :disabled="vendor !== 'sr'"
                                @change="assignmentType = $event.target.value; computeRate()"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                                <option value="">— Set later —</option>
                                <option value="script_coverage"  :selected="assignmentType === 'script_coverage'">Script Coverage</option>
                                <option value="notes_only"       :selected="assignmentType === 'notes_only'">Notes-Only Coverage</option>
                                <option value="short"            :selected="assignmentType === 'short'">Short Coverage</option>
                                <option value="deep_dive"        :selected="assignmentType === 'deep_dive'">Deep-Dive Dev Notes</option>
                                <option value="budget"           :selected="assignmentType === 'budget'">Budget Script Coverage</option>
                                <option value="book"             :selected="assignmentType === 'book'">Book Coverage</option>
                            </select>
                            {{-- WD types: shown when vendor = wd --}}
                            <select x-show="vendor === 'wd'" x-cloak
                                name="assignment_type"
                                :disabled="vendor !== 'wd'"
                                @change="assignmentType = $event.target.value; computeRate()"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                                <option value="">— Set later —</option>
                                <option value="coverage"          :selected="assignmentType === 'coverage'">Coverage</option>
                                <option value="development_notes" :selected="assignmentType === 'development_notes'">Development Notes</option>
                            </select>
                            <x-input-error :messages="$errors->get('assignment_type')" class="mt-1" />
                        </div>
                    </div>

                    {{-- Script title + Writer --}}
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <x-input-label for="script_title" value="Script Title" />
                            <x-text-input id="script_title" name="script_title" type="text"
                                class="mt-1 block w-full"
                                value="{{ old('script_title', $assignment->script_title) }}"
                                required />
                            <x-input-error :messages="$errors->get('script_title')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="writer_name" value="Writer" />
                            <x-text-input id="writer_name" name="writer_name" type="text"
                                class="mt-1 block w-full"
                                value="{{ old('writer_name', $assignment->writer_name) }}"
                                required />
                            <x-input-error :messages="$errors->get('writer_name')" class="mt-1" />
                        </div>
                    </div>

                    {{-- Page count + Pay rate --}}
                    <div class="grid grid-cols-2 gap-3 items-start">
                        <div>
                            <x-input-label for="page_count" value="Page Count" />
                            <input id="page_count" name="page_count" type="number"
                                class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                :value="pageCount"
                                @input="pageCount = $event.target.value; computeRate()"
                                min="1" max="9999"
                                required />
                            <x-input-error :messages="$errors->get('page_count')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label value="Pay Rate" />
                            <input type="hidden" name="pay_rate" :value="payRate" />

                            <div x-show="!overrideRate" class="mt-1">
                                <div class="px-3 py-2 bg-gray-50 border border-gray-200 rounded-md min-h-[38px] flex items-center">
                                    <span x-text="payRate ? '$' + parseFloat(payRate).toFixed(2) : '—'"
                                          :class="payRate ? 'text-sm font-semibold text-gray-900' : 'text-sm text-gray-400'"></span>
                                </div>
                                <p x-show="rateNote" x-cloak class="mt-1 text-xs text-indigo-500" x-text="rateNote"></p>
                            </div>

                            <input x-show="overrideRate" x-cloak
                                type="number" x-model="payRate"
                                class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm"
                                min="0" step="0.01" placeholder="0.00" />

                            <div class="mt-1.5 flex items-center gap-2">
                                <input type="checkbox" id="override_rate" x-model="overrideRate"
                                    @change="if (!overrideRate) computeRate()"
                                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 focus:ring-offset-0" />
                                <label for="override_rate" class="text-xs text-gray-500 cursor-pointer select-none">Override auto-rate</label>
                            </div>
                            <x-input-error :messages="$errors->get('pay_rate')" class="mt-1" />
                        </div>
                    </div>

                    {{-- Custom oversized fee: shown when pages > 160 and not SR book --}}
                    <div x-show="Number(pageCount) > 160 && !(vendor === 'sr' && assignmentType === 'book')" x-cloak>
                        <x-input-label value="Custom Oversized Fee ($)" />
                        <x-text-input type="number" x-model="customOversizedInput" @input="computeRate()"
                            min="0" step="0.01" class="mt-1 block w-full"
                            placeholder="Fee for scripts over 160 pages" />
                    </div>

                    {{-- Book pay rate: shown only for SR book coverage --}}
                    <div x-show="vendor === 'sr' && assignmentType === 'book'" x-cloak>
                        <x-input-label value="Book Pay Rate ($)" />
                        <x-text-input type="number" x-model="bookPayRateInput" @input="computeRate()"
                            min="0" step="0.01" class="mt-1 block w-full"
                            placeholder="Custom rate for book coverage" />
                    </div>

                    {{-- Status + Rush --}}
                    <div class="grid grid-cols-2 gap-3 items-start">
                        <div>
                            <x-input-label for="status" value="Status" />
                            <select id="status" name="status" x-model="statusValue"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                                <option value="incoming">Incoming</option>
                                <option value="unassigned">Unassigned</option>
                                <option value="assigned">Assigned</option>
                                <option value="completed">Completed</option>
                                <option value="qc">QC</option>
                                <option value="on_hold">On Hold</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                            <x-input-error :messages="$errors->get('status')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label value="Rush" />
                            <div class="mt-2 flex items-center gap-2 h-9">
                                <input id="rush" name="rush" type="checkbox" value="1"
                                    x-model="isRush"
                                    @change="isRush = $event.target.checked; computeRate()"
                                    class="rounded border-gray-300 text-amber-500 shadow-sm focus:ring-amber-500" />
                                <label for="rush" class="text-sm text-gray-700 font-medium">Rush (24h turnaround)</label>
                            </div>
                        </div>
                    </div>

                    {{-- Requested reader --}}
                    <div>
                        <x-input-label for="requested_reader_id" value="Requested Reader (optional)" />
                        <select id="requested_reader_id" name="requested_reader_id"
                            x-model="requestedReaderId"
                            @change="requestedReaderId = $event.target.value; computeRate()"
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                            <option value="">— None —</option>
                            @foreach ($readers as $reader)
                                <option value="{{ $reader->id }}"
                                    {{ old('requested_reader_id', $assignment->requested_reader_id) == $reader->id ? 'selected' : '' }}>
                                    {{ $reader->readerProfile?->initials ? '[' . $reader->readerProfile->initials . '] ' : '' }}{{ $reader->name }}
                                </option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('requested_reader_id')" class="mt-1" />
                    </div>

                    {{-- Assigned reader --}}
                    <div>
                        <x-input-label for="assigned_reader_id" value="Assigned Reader (optional)" />
                        <select id="assigned_reader_id" name="assigned_reader_id"
                            x-model="assignedReaderId"
                            @change="onAssignedReaderChange()"
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                            <option value="">— None —</option>
                            @foreach ($readers as $reader)
                                <option value="{{ $reader->id }}">
                                    {{ $reader->readerProfile?->initials ? '[' . $reader->readerProfile->initials . '] ' : '' }}{{ $reader->name }}
                                </option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('assigned_reader_id')" class="mt-1" />
                    </div>

                    {{-- Notes --}}
                    <div>
                        <x-input-label for="notes" value="Notes (optional)" />
                        <textarea id="notes" name="notes" rows="3"
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                            placeholder="Any special instructions or context…">{{ old('notes', $assignment->notes) }}</textarea>
                        <x-input-error :messages="$errors->get('notes')" class="mt-1" />
                    </div>

                    {{-- Actions --}}
                    <div class="flex items-center justify-end gap-3 pt-2 border-t border-gray-100">
                        <a href="{{ route('assignments.index') }}"
                           class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
                        <x-primary-button>Save Changes</x-primary-button>
                    </div>

                </form>
            </div>
        </div>
    </div>

    <script>
    function assignmentForm(initialVendor, initialType, initialRush, initialRequestedReaderId, initialPageCount, initialAssignedReaderId, initialStatus, rates) {
        return {
            vendor:               initialVendor || 'sr',
            assignmentType:       initialType || '',
            isRush:               initialRush,
            requestedReaderId:    String(initialRequestedReaderId || ''),
            pageCount:            initialPageCount || '',
            assignedReaderId:     String(initialAssignedReaderId || ''),
            statusValue:          initialStatus,
            payRate:              '',
            overrideRate:         false,
            rates:                rates,
            rateNote:             '',
            bookPayRateInput:     '',
            customOversizedInput: '',
            init() {
                this.computeRate();
            },

            onAssignedReaderChange() {
                if (this.assignedReaderId && this.statusValue === 'unassigned') {
                    this.statusValue = 'assigned';
                }
                if (!this.assignedReaderId && this.statusValue === 'assigned') {
                    this.statusValue = 'unassigned';
                }
            },

            computeRate() {
                if (this.overrideRate) return;
                if (!this.assignmentType) { this.payRate = ''; this.rateNote = ''; return; }

                const pages = parseInt(this.pageCount) || 0;
                const r = this.rates;
                let base = 0, rush = 0, request = 0, oversized = 0;

                if (this.vendor === 'sr') {
                    const srBases = {
                        script_coverage: r.rate_sr_script_coverage,
                        notes_only:      r.rate_sr_notes_only,
                        short:           r.rate_sr_short,
                        deep_dive:       r.rate_sr_deep_dive,
                        budget:          r.rate_sr_budget,
                    };
                    if (this.assignmentType === 'book') {
                        base = parseFloat(this.bookPayRateInput) || 0;
                    } else {
                        base     = parseFloat(srBases[this.assignmentType]) || 0;
                        if (pages >= 121 && pages <= 160) oversized = parseFloat(r.rate_sr_oversized_121_160) || 0;
                        else if (pages > 160)             oversized = parseFloat(this.customOversizedInput) || 0;
                    }
                    rush    = this.isRush ? (parseFloat(r.rate_sr_rush) || 0) : 0;
                    request = this.requestedReaderId ? (parseFloat(r.rate_sr_request) || 0) : 0;

                } else if (this.vendor === 'wd') {
                    const wdBases = {
                        coverage:          r.rate_wd_coverage,
                        development_notes: r.rate_wd_development_notes,
                    };
                    base    = parseFloat(wdBases[this.assignmentType]) || 0;
                    if (pages >= 121 && pages <= 160) oversized = parseFloat(r.rate_wd_oversized_121_160) || 0;
                    else if (pages > 160)             oversized = parseFloat(this.customOversizedInput) || 0;
                    request = this.requestedReaderId ? (parseFloat(r.rate_wd_request) || 0) : 0;

                } else {
                    this.payRate = ''; this.rateNote = ''; return;
                }

                const total = base + rush + request + oversized;
                this.payRate = total > 0 ? total.toFixed(2) : '';

                const parts = [];
                if (base > 0)      parts.push(`$${base.toFixed(2)} base`);
                if (rush > 0)      parts.push(`$${rush.toFixed(2)} rush`);
                if (request > 0)   parts.push(`$${request.toFixed(2)} request`);
                if (oversized > 0) parts.push(`$${oversized.toFixed(2)} oversized`);
                this.rateNote = parts.join(' + ');
            },
        };
    }
    </script>
</x-app-layout>
