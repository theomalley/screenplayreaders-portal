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
                      x-data="assignmentForm(
                          '{{ old('vendor', 'sr') }}',
                          '{{ old('assignment_type', '') }}',
                          {{ old('rush') ? 'true' : 'false' }},
                          '{{ old('requested_reader_id', '') }}',
                          '{{ old('page_count', '') }}',
                          '{{ old('assigned_reader_id', '') }}',
                          '{{ old('status', 'incoming') }}',
                          '{{ old('pay_rate', '') }}',
                          @json($rates)
                      )">
                    @csrf

                    {{-- Order number --}}
                    <div>
                        <x-input-label for="order_number" value="Order Number" />
                        <x-text-input id="order_number" name="order_number" type="text"
                            class="mt-1 block w-full"
                            value="{{ old('order_number') }}"
                            placeholder="e.g. 10042"
                            required autofocus />
                        <x-input-error :messages="$errors->get('order_number')" class="mt-1" />
                    </div>

                    {{-- Vendor + Assignment Type --}}
                    <div class="grid grid-cols-2 gap-3 items-start">
                        <div>
                            <x-input-label value="Vendor" />
                            <div class="mt-2 flex gap-4">
                                <label class="flex items-center gap-1.5 text-sm font-medium text-gray-700 cursor-pointer">
                                    <input type="radio" name="vendor" value="sr" x-model="vendor"
                                        @change="vendor = 'sr'; assignmentType = ''; rateNote = ''"
                                        class="text-indigo-600 border-gray-300 focus:ring-indigo-500" />
                                    SR
                                </label>
                                <label class="flex items-center gap-1.5 text-sm font-medium text-gray-700 cursor-pointer">
                                    <input type="radio" name="vendor" value="wd" x-model="vendor"
                                        @change="vendor = 'wd'; assignmentType = ''; rateNote = ''"
                                        class="text-indigo-600 border-gray-300 focus:ring-indigo-500" />
                                    WD
                                </label>
                            </div>
                            <x-input-error :messages="$errors->get('vendor')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="assignment_type" value="Assignment Type" />
                            <select id="assignment_type" name="assignment_type"
                                x-model="assignmentType"
                                @change="assignmentType = $event.target.value; computeRate()"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                                <option value="">— Set later —</option>
                                <optgroup label="SR" x-show="vendor === 'sr'">
                                    <option value="script_coverage">Script Coverage</option>
                                    <option value="notes_only">Notes-Only Coverage</option>
                                    <option value="short">Short Coverage</option>
                                    <option value="deep_dive">Deep-Dive Development Notes</option>
                                    <option value="budget">Budget Script Coverage</option>
                                    <option value="book">Book Coverage</option>
                                </optgroup>
                                <optgroup label="WD" x-show="vendor === 'wd'">
                                    <option value="coverage">Coverage</option>
                                    <option value="development_notes">Development Notes</option>
                                </optgroup>
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
                                value="{{ old('script_title') }}"
                                required />
                            <x-input-error :messages="$errors->get('script_title')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="writer_name" value="Writer" />
                            <x-text-input id="writer_name" name="writer_name" type="text"
                                class="mt-1 block w-full"
                                value="{{ old('writer_name') }}"
                                required />
                            <x-input-error :messages="$errors->get('writer_name')" class="mt-1" />
                        </div>
                    </div>

                    {{-- Page count + Pay rate --}}
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <x-input-label for="page_count" value="Page Count" />
                            <x-text-input id="page_count" name="page_count" type="number"
                                class="mt-1 block w-full"
                                :value="pageCount"
                                @input="pageCount = $event.target.value; computeRate()"
                                min="1" max="9999"
                                required />
                            <x-input-error :messages="$errors->get('page_count')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="pay_rate" value="Pay Rate ($)" />
                            <x-text-input id="pay_rate" name="pay_rate" type="number"
                                :value="payRate"
                                @input="payRate = $event.target.value"
                                :readonly="!overrideRate"
                                :class="!overrideRate ? 'bg-gray-50 cursor-not-allowed' : ''"
                                class="mt-1 block w-full"
                                min="0" step="0.01"
                                required />
                            <div class="mt-1.5 flex items-center gap-2">
                                <input type="checkbox" id="override_rate" x-model="overrideRate"
                                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 focus:ring-offset-0" />
                                <label for="override_rate" class="text-xs text-gray-500 cursor-pointer select-none">Override auto-rate</label>
                            </div>
                            <p x-show="rateNote && !overrideRate" x-cloak class="mt-1 text-xs text-indigo-500" x-text="rateNote"></p>
                            <x-input-error :messages="$errors->get('pay_rate')" class="mt-1" />
                        </div>
                    </div>

                    {{-- Custom oversized fee: shown when pages > 160 and not book --}}
                    <div x-show="Number(pageCount) > 160 && !(vendor === 'sr' && assignmentType === 'book')" x-cloak>
                        <x-input-label value="Custom Oversized Fee ($)" />
                        <x-text-input type="number" x-ref="customOversized" @input="computeRate()"
                            min="0" step="0.01" class="mt-1 block w-full"
                            placeholder="Fee for scripts over 160 pages" />
                    </div>

                    {{-- Book pay rate: shown only for SR book coverage --}}
                    <div x-show="vendor === 'sr' && assignmentType === 'book'" x-cloak>
                        <x-input-label value="Book Pay Rate ($)" />
                        <x-text-input type="number" x-ref="bookPayRate" @input="computeRate()"
                            min="0" step="0.01" class="mt-1 block w-full"
                            placeholder="Custom rate for book coverage" />
                    </div>

                    {{-- Rush + Status --}}
                    <div class="grid grid-cols-2 gap-3 items-start">
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
                        <div>
                            <x-input-label for="status" value="Initial Status" />
                            <select id="status" name="status" x-model="statusValue"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                                <option value="incoming">Incoming (hold for review)</option>
                                <option value="unassigned">Unassigned (publish now)</option>
                                <option value="assigned" x-show="assignedReaderId">Assigned</option>
                            </select>
                            <x-input-error :messages="$errors->get('status')" class="mt-1" />
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
                                <option value="{{ $reader->id }}" {{ old('requested_reader_id') == $reader->id ? 'selected' : '' }}>
                                    {{ $reader->readerProfile?->initials ? '[' . $reader->readerProfile->initials . '] ' : '' }}{{ $reader->name }}
                                </option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('requested_reader_id')" class="mt-1" />
                    </div>

                    {{-- Assigned reader --}}
                    <div>
                        <x-input-label for="assigned_reader_id" value="Assign to Reader (optional)" />
                        <select id="assigned_reader_id" name="assigned_reader_id"
                            x-model="assignedReaderId"
                            @change="onAssignedReaderChange()"
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                            <option value="">— None (goes to pool) —</option>
                            @foreach ($readers as $reader)
                                <option value="{{ $reader->id }}" {{ old('assigned_reader_id') == $reader->id ? 'selected' : '' }}>
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
                            placeholder="Any special instructions or context…">{{ old('notes') }}</textarea>
                        <x-input-error :messages="$errors->get('notes')" class="mt-1" />
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
    function assignmentForm(initialVendor, initialType, initialRush, initialRequestedReaderId, initialPageCount, initialAssignedReaderId, initialStatus, initialPayRate, rates) {
        return {
            vendor:            initialVendor || 'sr',
            assignmentType:    initialType || '',
            isRush:            initialRush,
            requestedReaderId: String(initialRequestedReaderId || ''),
            pageCount:         initialPageCount || '',
            assignedReaderId:  String(initialAssignedReaderId || ''),
            statusValue:       initialStatus,
            payRate:           initialPayRate || '',
            overrideRate:      false,
            rates:             rates,
            rateNote:          '',

            onAssignedReaderChange() {
                if (this.assignedReaderId && this.statusValue === 'incoming') {
                    this.statusValue = 'assigned';
                }
                if (!this.assignedReaderId && this.statusValue === 'assigned') {
                    this.statusValue = 'incoming';
                }
            },

            computeRate() {
                if (this.overrideRate) return;
                if (!this.assignmentType) { this.rateNote = ''; return; }

                const pages = parseInt(this.pageCount) || 0;
                const r = this.rates;
                let base = 0, rush = 0, request = 0, oversized = 0;

                if (this.vendor === 'sr') {
                    const bases = {
                        script_coverage: r.rate_sr_script_coverage,
                        notes_only:      r.rate_sr_notes_only,
                        short:           r.rate_sr_short,
                        deep_dive:       r.rate_sr_deep_dive,
                        budget:          r.rate_sr_budget,
                        book:            0,
                    };
                    if (this.assignmentType === 'book') {
                        base = parseFloat(this.$refs.bookPayRate?.value) || 0;
                    } else {
                        base = bases[this.assignmentType] ?? 0;
                        if (pages >= 121 && pages <= 160) oversized = r.rate_sr_oversized_121_160;
                        else if (pages > 160)             oversized = parseFloat(this.$refs.customOversized?.value) || 0;
                    }
                    rush    = this.isRush ? r.rate_sr_rush : 0;
                    request = this.requestedReaderId ? r.rate_sr_request : 0;

                } else if (this.vendor === 'wd') {
                    const bases = {
                        coverage:          r.rate_wd_coverage,
                        development_notes: r.rate_wd_development_notes,
                    };
                    base = bases[this.assignmentType] ?? 0;
                    if (pages >= 121 && pages <= 160) oversized = r.rate_wd_oversized_121_160;
                    else if (pages > 160)             oversized = parseFloat(this.$refs.customOversized?.value) || 0;
                    request = this.requestedReaderId ? r.rate_wd_request : 0;
                } else {
                    this.rateNote = ''; return;
                }

                const total = base + rush + request + oversized;
                this.payRate = total.toFixed(2);

                const parts = [];
                if (base > 0)      parts.push(`$${base.toFixed(2)} base`);
                if (rush > 0)      parts.push(`$${rush.toFixed(2)} rush`);
                if (request > 0)   parts.push(`$${request.toFixed(2)} request`);
                if (oversized > 0) parts.push(`$${oversized.toFixed(2)} oversized`);
                this.rateNote = parts.length ? parts.join(' + ') + ' — edit if needed' : '';
            },
        };
    }
    </script>
</x-app-layout>
