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
            {{-- Assignment identity heading --}}
            <div class="mb-3 px-1">
                <div class="text-2xl font-bold text-gray-900 leading-tight">
                    #{{ $assignment->order_number }}
                    &nbsp;·&nbsp;
                    {{ $assignment->script_title }}
                </div>
                <div class="text-base text-gray-500 mt-0.5">by {{ $assignment->writer_name }}</div>
            </div>

            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                @php
                    $isOld        = session()->hasOldInput();
                    $v            = fn($field, $default) => $isOld ? old($field) : $default;
                    $rushActive   = $isOld ? (bool) old('rush') : (bool) $assignment->rush;
                    $siblingCount = $assignment->order_number
                        ? \App\Models\Assignment::where('order_number', $assignment->order_number)->count()
                        : 1;
                @endphp

                <form id="update-form" method="POST" action="{{ route('assignments.update', $assignment) }}" class="p-6 space-y-5"
                      x-data="{
                          vendor: '{{ $v('vendor', $assignment->vendor) }}',
                          assignmentType: '{{ $v('assignment_type', $assignment->assignment_type ?? '') }}',
                          pageCount: '{{ $v('page_count', $assignment->page_count) }}',
                          customOversizedFee: '{{ $v('custom_oversized_fee', '') }}',
                          rush: {{ $rushActive ? 'true' : 'false' }},
                          numReaders: '{{ $v('num_readers', (string) min($siblingCount, 3)) }}',
                          requestedReaders: ['{{ $v('requested_reader_id', $assignment->requested_reader_id ?? '') }}', '', ''],
                          overrideRate: true,
                          updatePayDisplay() {
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
                              const typeLabels = {
                                  script_coverage:   'Script Coverage',
                                  notes_only:        'Notes-Only',
                                  deep_dive:         'Deep-Dive Dev Notes',
                                  short:             'Short Coverage',
                                  budget:            'Budget Coverage',
                                  coverage:          'WD Coverage',
                                  development_notes: 'WD Dev Notes',
                              };
                              const oversized121 = { sr: r.rate_sr_oversized_121_160, wd: r.rate_wd_oversized_121_160 };
                              const el        = document.getElementById('pay_rate_display');
                              const hidden    = document.getElementById('pay_rate_hidden');
                              const breakdown = document.getElementById('pay_rate_breakdown');
                              if (breakdown) breakdown.textContent = '';

                              const pages   = parseInt(this.pageCount, 10);
                              const reqRate = parseFloat({ sr: r.rate_sr_request, wd: r.rate_wd_request }[this.vendor] || 0);
                              const hasReq  = !!this.requestedReaders[0];

                              // Build breakdown parts (always, regardless of override mode)
                              if (this.assignmentType && !(this.vendor === 'sr' && this.assignmentType === 'book')) {
                                  const base = (map[this.vendor] || {})[this.assignmentType];
                                  if (base !== undefined && breakdown) {
                                      const parts = [typeLabels[this.assignmentType] + ' $' + parseFloat(base).toFixed(2)];
                                      if (!isNaN(pages)) {
                                          if (pages >= 121 && pages <= 160) {
                                              const fee = parseFloat(oversized121[this.vendor] || 0);
                                              if (fee) parts.push('Oversized 121–160pp $' + fee.toFixed(2));
                                          } else if (pages >= 161) {
                                              const fee = parseFloat(this.customOversizedFee);
                                              if (!isNaN(fee) && fee > 0) parts.push('Oversized 161+pp $' + fee.toFixed(2));
                                          }
                                      }
                                      if (this.rush) {
                                          const fee = parseFloat({ sr: r.rate_sr_rush, wd: r.rate_wd_rush }[this.vendor] || 0);
                                          if (fee) parts.push('Rush $' + fee.toFixed(2));
                                      }
                                      if (hasReq && reqRate) parts.push('Reader Request $' + reqRate.toFixed(2));
                                      if (parts.length > 1) breakdown.textContent = parts.join(' + ');
                                  }
                              }

                              // Update display and hidden value only when not in override mode
                              if (this.overrideRate) return;
                              if (!el) return;

                              let sharedMod = 0;
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
                              const total = parseFloat(base) + sharedMod + (hasReq ? reqRate : 0);
                              el.textContent = '$' + total.toFixed(2);
                              el.className = 'text-sm font-semibold text-gray-900';
                              if (hidden) hidden.value = total.toFixed(2);
                          },
                          init() { this.$nextTick(() => this.updatePayDisplay()); },
                          createInvoice: false,
                          invoiceClientId: '{{ old('invoice_client_id', $assignment->client_id ?? '') }}',
                          batchClientIds: {{ \App\Models\Client::where('batch_invoicing', true)->pluck('id') }},
                          get isBatchClient() { return this.batchClientIds.includes(parseInt(this.invoiceClientId)); }
                      }">
                    @csrf
                    @method('PATCH')

                    {{-- Invoice (only show if no invoice already exists for this assignment) --}}
                    @if(\App\Models\Client::count() > 0 && $assignment->invoices->isEmpty())
                    <div class="pb-4 border-b border-gray-100">
                        <div class="flex items-center gap-3">
                            <input type="hidden" name="create_invoice" value="0">
                            <input type="checkbox" id="create_invoice" name="create_invoice" value="1"
                                   x-model="createInvoice"
                                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                            <label for="create_invoice" class="text-sm font-medium text-gray-700 cursor-pointer">Invoice</label>
                        </div>
                        <div x-show="createInvoice" x-cloak class="mt-3 space-y-3">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <x-input-label for="invoice_client_id" value="Client" />
                                    <select id="invoice_client_id" name="invoice_client_id"
                                        x-model="invoiceClientId"
                                        class="mt-1 block w-full text-sm rounded-md border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                                        <option value="">— Select client —</option>
                                        @foreach(\App\Models\Client::orderBy('name')->get() as $client)
                                            <option value="{{ $client->id }}" {{ (old('invoice_client_id', $assignment->client_id) == $client->id) ? 'selected' : '' }}>
                                                {{ $client->name }} ({{ $client->batch_invoicing ? 'Batch' : ($client->invoice_type === 'stripe' ? 'Stripe' : 'PDF') }})
                                            </option>
                                        @endforeach
                                    </select>
                                    <x-input-error :messages="$errors->get('invoice_client_id')" class="mt-1" />
                                </div>
                                <div>
                                    <x-input-label for="invoice_amount" value="Invoice Amount ($)" />
                                    <div class="mt-1 relative">
                                        <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 text-sm">$</span>
                                        <x-text-input id="invoice_amount" name="invoice_amount" type="number"
                                            step="0.01" min="0.01"
                                            class="block w-full pl-7"
                                            value="{{ old('invoice_amount') }}" />
                                    </div>
                                    <x-input-error :messages="$errors->get('invoice_amount')" class="mt-1" />
                                </div>
                            </div>
                            <p x-show="isBatchClient" x-cloak class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded px-3 py-2">
                                This client uses batch invoicing — this amount will be added to their open weekly invoice, not sent immediately.
                            </p>
                        </div>
                    </div>
                    @elseif($assignment->invoices->isNotEmpty())
                    <div class="pb-4 border-b border-gray-100 text-xs text-gray-400">
                        Invoice #{{ $assignment->invoices->first()->invoice_number }} already generated for this assignment.
                    </div>
                    @endif

                    {{-- Vendor --}}
                    <div>
                        <x-input-label value="Vendor" />
                        <div class="mt-2 flex gap-6">
                            <label class="flex items-center gap-2 text-sm font-medium text-gray-700 cursor-pointer">
                                <input type="radio" name="vendor" value="sr"
                                    {{ $v('vendor', $assignment->vendor) === 'sr' ? 'checked' : '' }}
                                    @change="vendor = 'sr'; requestedReaders = ['', '', '']; updatePayDisplay()"
                                    class="text-indigo-600 border-gray-300 focus:ring-indigo-500" />
                                SR
                            </label>
                            <label class="flex items-center gap-2 text-sm font-medium text-gray-700 cursor-pointer">
                                <input type="radio" name="vendor" value="wd"
                                    {{ $v('vendor', $assignment->vendor) === 'wd' ? 'checked' : '' }}
                                    @change="vendor = 'wd'; numReaders = '1'; requestedReaders = ['', '', '']; updatePayDisplay()"
                                    class="text-indigo-600 border-gray-300 focus:ring-indigo-500" />
                                WD
                            </label>
                        </div>
                        <x-input-error :messages="$errors->get('vendor')" class="mt-1" />
                    </div>

                    {{-- # of Readers (SR only) --}}
                    <div x-show="vendor === 'sr'">
                        <x-input-label for="num_readers" value="# of Readers" />
                        <select id="num_readers" name="num_readers"
                            @change="numReaders = $event.target.value; updatePayDisplay()"
                            class="mt-1 block w-24 border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                            @php $defaultReaders = (string) min($siblingCount, 3); @endphp
                            <option value="1" {{ $v('num_readers', $defaultReaders) === '1' ? 'selected' : '' }}>1R</option>
                            <option value="2" {{ $v('num_readers', $defaultReaders) === '2' ? 'selected' : '' }}>2R</option>
                            <option value="3" {{ $v('num_readers', $defaultReaders) === '3' ? 'selected' : '' }}>3R</option>
                        </select>
                    </div>

                    {{-- Assignment Type SR (1R — all options) --}}
                    <div x-show="vendor === 'sr' && numReaders === '1'">
                        <x-input-label for="assignment_type_sr" value="Assignment Type" />
                        <select id="assignment_type_sr" name="assignment_type"
                            :disabled="vendor !== 'sr' || numReaders !== '1'"
                            @change="assignmentType = $event.target.value; updatePayDisplay()"
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                            <option value="">— Select type —</option>
                            <option value="script_coverage"  {{ $v('assignment_type', $assignment->assignment_type) === 'script_coverage'  ? 'selected' : '' }}>Script Coverage</option>
                            <option value="notes_only"       {{ $v('assignment_type', $assignment->assignment_type) === 'notes_only'       ? 'selected' : '' }}>Notes-Only</option>
                            <option value="deep_dive"        {{ $v('assignment_type', $assignment->assignment_type) === 'deep_dive'        ? 'selected' : '' }}>Deep-Dive Dev Notes</option>
                            <option value="short"            {{ $v('assignment_type', $assignment->assignment_type) === 'short'            ? 'selected' : '' }}>Short Coverage</option>
                            <option value="budget"           {{ $v('assignment_type', $assignment->assignment_type) === 'budget'           ? 'selected' : '' }}>Budget Coverage</option>
                            <option value="book"             {{ $v('assignment_type', $assignment->assignment_type) === 'book'             ? 'selected' : '' }}>Book Coverage</option>
                            <option value="formatting"       {{ $v('assignment_type', $assignment->assignment_type) === 'formatting'       ? 'selected' : '' }}>Formatting</option>
                            <option value="proofreading"     {{ $v('assignment_type', $assignment->assignment_type) === 'proofreading'     ? 'selected' : '' }}>Proofreading</option>
                        </select>
                        <x-input-error :messages="$errors->get('assignment_type')" class="mt-1" />
                    </div>

                    {{-- Assignment Type SR (2R/3R — locked to Script Coverage) --}}
                    <div x-show="vendor === 'sr' && numReaders !== '1'">
                        <x-input-label for="assignment_type_sr_multi" value="Assignment Type" />
                        <select id="assignment_type_sr_multi" disabled
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm bg-gray-50 text-gray-500 cursor-not-allowed">
                            <option value="script_coverage" selected>Script Coverage</option>
                        </select>
                        <p class="mt-1 text-xs text-gray-400">Notes-Only coverage(s) auto-created for the additional reader(s).</p>
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

                    {{-- Reader Request(s) --}}
                    <div class="space-y-3">

                        {{-- Slot 1 — always visible --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700">
                                <span x-text="numReaders !== '1' ? 'Reader Request 1' : 'Reader Request'"></span>
                            </label>
                            <select name="requested_reader_id"
                                @change="requestedReaders[0] = $event.target.value; updatePayDisplay()"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                                <option value="">None</option>
                                @foreach ($readers as $reader)
                                    <option value="{{ $reader->id }}"
                                        :disabled="requestedReaders[1] === '{{ $reader->id }}' || requestedReaders[2] === '{{ $reader->id }}'"
                                        {{ $v('requested_reader_id', $assignment->requested_reader_id) == $reader->id ? 'selected' : '' }}>
                                        {{ $reader->readerProfile?->initials ?? $reader->name }}
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('requested_reader_id')" class="mt-1" />
                        </div>

                        {{-- Slot 2 — visible for 2R and 3R --}}
                        <div x-show="numReaders === '2' || numReaders === '3'">
                            <x-input-label value="Reader Request 2" />
                            <select name="requested_reader_id_2"
                                :disabled="numReaders === '1'"
                                @change="requestedReaders[1] = $event.target.value; updatePayDisplay()"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                                <option value="">None</option>
                                @foreach ($readers as $reader)
                                    <option value="{{ $reader->id }}"
                                        :disabled="requestedReaders[0] === '{{ $reader->id }}' || requestedReaders[2] === '{{ $reader->id }}'"
                                        {{ old('requested_reader_id_2') == $reader->id ? 'selected' : '' }}>
                                        {{ $reader->readerProfile?->initials ?? $reader->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Slot 3 — visible for 3R only --}}
                        <div x-show="numReaders === '3'">
                            <x-input-label value="Reader Request 3" />
                            <select name="requested_reader_id_3"
                                :disabled="numReaders !== '3'"
                                @change="requestedReaders[2] = $event.target.value; updatePayDisplay()"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                                <option value="">None</option>
                                @foreach ($readers as $reader)
                                    <option value="{{ $reader->id }}"
                                        :disabled="requestedReaders[0] === '{{ $reader->id }}' || requestedReaders[1] === '{{ $reader->id }}'"
                                        {{ old('requested_reader_id_3') == $reader->id ? 'selected' : '' }}>
                                        {{ $reader->readerProfile?->initials ?? $reader->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

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
                        <p id="pay_rate_breakdown" class="mt-1.5 text-xs text-gray-400 leading-snug"></p>
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
                            @php $localCreatedAt = $assignment->created_at->copy()->setTimezone($appTimezone); @endphp
                            <div>
                                <x-input-label for="date" value="Date" />
                                <input type="date" id="date" name="date"
                                    value="{{ $v('date', $localCreatedAt->toDateString()) }}"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" />
                                <x-input-error :messages="$errors->get('date')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label for="time" value="Time ({{ $appTimezone }})" />
                                <input type="time" id="time" name="time"
                                    value="{{ $v('time', $localCreatedAt->format('H:i')) }}"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" />
                                <x-input-error :messages="$errors->get('time')" class="mt-1" />
                            </div>
                        </div>

                        <div>
                            <x-input-label for="assigned_reader_id" value="Assigned Reader" />
                            <select id="assigned_reader_id" name="assigned_reader_id"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                                <option value="">None</option>
                                @foreach ($assignableUsers as $aUser)
                                    @php
                                        $aInitials = $aUser->readerProfile?->initials
                                            ?? $aUser->editorProfile?->initials
                                            ?? strtoupper(substr($aUser->name, 0, 2));
                                        $aName = $aUser->readerProfile?->displayName()
                                            ?? $aUser->editorProfile?->displayName()
                                            ?? $aUser->name;
                                    @endphp
                                    <option value="{{ $aUser->id }}"
                                        {{ $v('assigned_reader_id', $assignment->assigned_reader_id) == $aUser->id ? 'selected' : '' }}>
                                        {{ $aInitials }} — {{ $aName }}
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
                                <option value="on_hold_customer" {{ $v('status', $assignment->status) === 'on_hold_customer' ? 'selected' : '' }}>On Hold – Customer</option>
                                <option value="on_hold_sr"       {{ $v('status', $assignment->status) === 'on_hold_sr'       ? 'selected' : '' }}>On Hold – SR</option>
                                <option value="cancelled"  {{ $v('status', $assignment->status) === 'cancelled'  ? 'selected' : '' }}>Cancelled</option>
                            </select>
                            <x-input-error :messages="$errors->get('status')" class="mt-1" />
                        </div>

                        {{-- Auto-release to Available --}}
                        @if ($assignment->status !== \App\Models\Assignment::STATUS_UNASSIGNED)
                        <div>
                            <x-input-label for="available_at" value="Auto-release to Available" />
                            @php
                                $availableAtLocal = $assignment->available_at
                                    ? $assignment->available_at->copy()->setTimezone($appTimezone)->format('Y-m-d\TH:i')
                                    : '';
                            @endphp
                            <input type="datetime-local" id="available_at" name="available_at"
                                value="{{ old('available_at', $availableAtLocal) }}"
                                class="mt-1 block w-56 border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" />
                            <p class="mt-1 text-xs text-gray-400">
                                Status will be set to Available automatically at this date/time ({{ $appTimezone }}).
                                Clear the field to cancel.
                                @if ($assignment->available_at)
                                    <span class="text-amber-600 font-medium">
                                        Scheduled: {{ $assignment->available_at->copy()->setTimezone($appTimezone)->format('D M j, Y g:i A') }}
                                    </span>
                                @endif
                            </p>
                            <x-input-error :messages="$errors->get('available_at')" class="mt-1" />
                        </div>
                        @endif

                        <div>
                            <x-input-label for="notes" value="Notes" />
                            <textarea id="notes" name="notes" rows="3"
                                placeholder="Notes (visible to readers)"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">{{ $v('notes', $assignment->notes) }}</textarea>
                            <x-input-error :messages="$errors->get('notes')" class="mt-1" />
                        </div>

                        <div>
                            <label class="flex items-start gap-3 cursor-pointer">
                                <input type="hidden" name="exempt_from_word_counts" value="0" />
                                <input type="checkbox" id="exempt_from_word_counts" name="exempt_from_word_counts" value="1"
                                    {{ old('exempt_from_word_counts', $assignment->exempt_from_word_counts ?? false) ? 'checked' : '' }}
                                    class="mt-0.5 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                                <span class="text-sm text-gray-700">
                                    <span class="font-medium">Exempt from word counts</span>
                                    <span class="text-gray-400 ml-1">— reader may submit coverage even if word count minimums are not met</span>
                                </span>
                            </label>
                        </div>

                        <div>
                            <x-input-label for="helpscout_ticket_number" value="HelpScout Ticket #" />
                            <input type="text" id="helpscout_ticket_number" name="helpscout_ticket_number"
                                value="{{ $v('helpscout_ticket_number', $assignment->helpscout_ticket_number) }}"
                                placeholder="e.g. 9731"
                                class="mt-1 block w-40 border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" />
                            <p class="mt-1 text-xs text-gray-400">For manually created orders — the # shown at the top of the HelpScout ticket.</p>
                            <x-input-error :messages="$errors->get('helpscout_ticket_number')" class="mt-1" />
                        </div>

                    </div>

                </form>

                {{-- Note History --}}
                @if ($notes->isNotEmpty())
                <div class="px-6 pb-6 border-t border-gray-100 pt-5">
                    <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Note History</h3>
                    <div class="space-y-3">
                        @foreach ($notes as $note)
                            @php
                                $nAuthor     = $note->author;
                                $nInitials   = $nAuthor?->readerProfile?->initials ?? ($nAuthor ? strtoupper(substr($nAuthor->name, 0, 2)) : '??');
                                $nPhotoRaw   = $nAuthor?->readerProfile?->photo ?? $nAuthor?->editorProfile?->photo;
                                $nPhotoUrl   = $nPhotoRaw ? asset('storage/' . $nPhotoRaw) : null;
                            @endphp
                            <div class="bg-blue-50 border border-blue-200 rounded-lg px-4 py-3">
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="relative inline-flex items-center justify-center w-6 h-6 rounded-full bg-gray-200 text-gray-700 text-[10px] font-mono font-semibold shrink-0">
                                        @if ($nPhotoUrl)
                                            <span class="absolute inset-0 rounded-full overflow-hidden">
                                                <img src="{{ $nPhotoUrl }}" alt="{{ $nInitials }}" class="w-full h-full object-cover" />
                                            </span>
                                        @else
                                            {{ $nInitials }}
                                        @endif
                                    </span>
                                    <span class="text-xs font-medium text-gray-700">{{ $nAuthor?->name }}</span>
                                    <span class="text-[10px] text-gray-400">{{ $note->created_at->setTimezone($appTimezone)->format('M j, Y g:ia') }}</span>
                                </div>
                                <div class="text-sm text-gray-800 whitespace-pre-wrap">{{ $note->body }}</div>
                                @foreach ($note->replies as $reply)
                                    <div class="mt-2 ml-4 pl-3 border-l-2 border-indigo-200">
                                        <div class="flex items-center gap-1.5 mb-0.5">
                                            <span class="text-[10px] font-medium text-indigo-600">{{ $reply->author?->name }}</span>
                                            <span class="text-[10px] text-gray-400">{{ $reply->created_at->setTimezone($appTimezone)->format('M j, Y g:ia') }}</span>
                                        </div>
                                        <div class="text-sm text-gray-700 whitespace-pre-wrap">{{ $reply->body }}</div>
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </div>
                @endif

                {{-- Editor Notes (admin/editor only — not visible to readers) --}}
                <div class="px-6 pb-6 border-t border-gray-100 pt-5">
                    <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">
                        Internal Notes
                        <span class="ml-1 text-[10px] font-normal text-gray-400 normal-case tracking-normal">(admin &amp; editors only)</span>
                    </h3>

                    @if ($editorNotes->isNotEmpty())
                    <div class="space-y-2 mb-4">
                        @foreach ($editorNotes as $eNote)
                        @php
                            $eAuthor   = $eNote->author;
                            $ePhotoRaw = $eAuthor?->editorProfile?->photo;
                            $ePhotoUrl = $ePhotoRaw ? asset('storage/' . $ePhotoRaw) : null;
                            $eInitials = $eAuthor?->editorProfile?->initials
                                ?? ($eAuthor ? strtoupper(substr($eAuthor->name, 0, 2)) : '??');
                        @endphp
                        <div class="bg-amber-50 border border-amber-200 rounded-lg px-4 py-3">
                            <div class="flex items-start gap-2">
                                <div class="flex items-center gap-2 shrink-0 mt-0.5">
                                    <span class="relative inline-flex items-center justify-center w-6 h-6 rounded-full bg-amber-200 text-amber-800 text-[10px] font-mono font-semibold overflow-hidden">
                                        @if ($ePhotoUrl)
                                            <img src="{{ $ePhotoUrl }}" alt="{{ $eInitials }}" class="absolute inset-0 w-full h-full object-cover" />
                                        @else
                                            {{ $eInitials }}
                                        @endif
                                    </span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 mb-1">
                                        <span class="text-xs font-medium text-gray-700">{{ $eAuthor?->name }}</span>
                                        <span class="text-[10px] text-gray-400">{{ $eNote->created_at->setTimezone($appTimezone)->format('M j, Y g:ia') }}</span>
                                    </div>
                                    <div class="text-sm text-gray-800 whitespace-pre-wrap">{{ $eNote->body }}</div>
                                </div>
                                <form method="POST" action="{{ route('assignment-editor-notes.destroy', $eNote) }}"
                                      onsubmit="return confirm('Delete this note?')" class="shrink-0">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-amber-400 hover:text-red-500 text-xs underline whitespace-nowrap">Delete</button>
                                </form>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    @endif

                    <form method="POST" action="{{ route('assignment-editor-notes.store', $assignment) }}" class="flex gap-2 items-end">
                        @csrf
                        <textarea name="body" rows="2" placeholder="Add an internal note…" maxlength="2000" required
                                  class="flex-1 text-sm border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                        <button type="submit"
                                class="px-3 py-1.5 text-xs font-medium text-white bg-amber-500 hover:bg-amber-600 rounded-md shadow-sm whitespace-nowrap">
                            Add Note
                        </button>
                    </form>
                </div>

                {{-- Actions — outside the edit form so the delete form is never nested --}}
                <div class="flex items-center justify-between px-6 pb-6 pt-4 border-t border-gray-100">
                    <div class="flex items-center gap-2">
                        <form method="POST" action="{{ route('assignments.destroy', $assignment) }}"
                              onsubmit="return confirm('Permanently delete this assignment? This cannot be undone.')">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                    class="inline-flex items-center px-3 py-1.5 bg-white border border-red-300 rounded text-xs font-medium text-red-600 hover:bg-red-50 transition">
                                Delete Assignment
                            </button>
                        </form>

                        @if ($assignment->vendor === 'sr' && $assignment->order_number && $siblingCount < 3)
                            <form method="POST" action="{{ route('assignments.addReader', $assignment) }}">
                                @csrf
                                <button type="submit"
                                        class="inline-flex items-center px-3 py-1.5 bg-white border border-indigo-300 rounded text-xs font-medium text-indigo-600 hover:bg-indigo-50 transition"
                                        title="{{ $siblingCount }}R on this order — click to add a Notes-Only reader">
                                    + Add Reader
                                    <span class="ml-1 text-indigo-400">({{ $siblingCount }}R → {{ $siblingCount + 1 }}R)</span>
                                </button>
                            </form>
                        @endif
                    </div>
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

    {{-- Script upload — separate form so enctype doesn't affect the PATCH form --}}
    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 pb-8">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 px-6 py-5">
            @if ($assignment->drive_script_file_id)
                @php
                    $viewUrl = route('assignments.streamScript', $assignment);
                @endphp
                <div class="mb-4">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Assignment File</p>
                    <p class="text-sm text-gray-800 font-medium mb-2">{{ $assignment->drive_script_filename ?? 'script.pdf' }}</p>
                    <div class="flex items-center gap-4" x-data="pdfViewer(@js($viewUrl))">
                        <button @click="openViewer()" type="button"
                                class="text-sm text-indigo-600 hover:text-indigo-800">View</button>
                        @if (\App\Support\Permission::check('script.download'))
                            <a href="{{ route('assignments.downloadScript', $assignment) }}"
                               class="text-sm text-indigo-600 hover:text-indigo-800">Download</a>
                        @endif

                        {{-- Full-screen script preview modal --}}
                        <div x-show="open" x-cloak x-ref="modal"
                             @keydown.escape.window="open = false"
                             tabindex="-1"
                             class="fixed inset-0 z-50 flex flex-col bg-black/80">
                            <div class="flex items-center justify-between px-4 py-2 bg-gray-900 shrink-0 gap-2 flex-wrap">
                                <span class="text-sm text-gray-200 font-medium truncate min-w-0">{{ $assignment->drive_script_filename ?? 'Script' }}</span>
                                <div class="flex items-center gap-2 shrink-0">
                                    @if (\App\Support\Permission::check('script.download'))
                                        <a href="{{ route('assignments.downloadScript', $assignment) }}"
                                           class="px-2 py-1 bg-gray-700 hover:bg-gray-600 rounded text-xs text-white whitespace-nowrap">Download</a>
                                    @endif
                                    @if (\App\Support\Permission::check('script.print'))
                                        <a href="{{ route('assignments.streamScript', $assignment) }}" target="_blank" rel="noopener"
                                           class="px-2 py-1 bg-gray-700 hover:bg-gray-600 rounded text-xs text-white whitespace-nowrap">Print</a>
                                    @endif
                                    <form method="POST" action="{{ route('assignments.removePages', $assignment) }}"
                                          onsubmit="return confirm('Remove title page (page 1)?')">
                                        @csrf
                                        <input type="hidden" name="pages" value="1">
                                        <button type="submit"
                                                class="px-2 py-1 bg-red-700 hover:bg-red-600 rounded text-xs text-white whitespace-nowrap">
                                            Remove title page
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('assignments.removePages', $assignment) }}"
                                          onsubmit="return confirm('Remove last page?')">
                                        @csrf
                                        <input type="hidden" name="pages" value="last">
                                        <button type="submit"
                                                class="px-2 py-1 bg-red-700 hover:bg-red-600 rounded text-xs text-white whitespace-nowrap">
                                            Remove last page
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('assignments.removePages', $assignment) }}"
                                          class="flex items-center gap-1"
                                          x-data="{ pg: '' }"
                                          @submit.prevent="if (pg.trim()) { if (confirm('Remove page ' + pg + '?')) $el.submit(); }">
                                        @csrf
                                        <input type="text" name="pages" x-model="pg" placeholder="pg #"
                                               class="w-14 text-xs bg-gray-700 border border-gray-600 rounded px-1.5 py-1 text-gray-200 placeholder-gray-500 focus:outline-none focus:border-indigo-400">
                                        <button type="submit"
                                                class="px-2 py-1 bg-red-700 hover:bg-red-600 rounded text-xs text-white">
                                            Remove
                                        </button>
                                    </form>
                                    <button @click="open = false" type="button"
                                            class="text-gray-400 hover:text-white text-2xl leading-none ml-2 px-1">×</button>
                                </div>
                            </div>
                            <div class="flex items-center justify-center gap-3 px-4 py-1.5 bg-gray-800 shrink-0 border-t border-gray-700">
                                <span x-show="loading" x-text="totalPages > 0 ? 'Rendering ' + currentPage + ' of ' + totalPages + '…' : 'Loading…'" class="text-xs text-gray-400"></span>
                                <span x-show="!loading && totalPages > 0" class="flex items-center gap-1.5 text-xs text-gray-400">
                                    Go to page
                                    <input type="number" min="1" :max="totalPages"
                                           @change="scrollToPage($event.target.value)"
                                           @keydown.enter.prevent="scrollToPage($event.target.value)"
                                           class="w-14 text-center bg-gray-700 border border-gray-600 rounded text-xs text-gray-200 px-1 py-0.5" />
                                    / <span x-text="totalPages"></span>
                                </span>
                            </div>
                            <div x-ref="canvasWrap" class="flex-1 overflow-auto flex flex-col items-center gap-4 bg-gray-800 py-6 px-4">
                                <div x-show="loading && totalPages === 0" class="text-gray-400 text-sm mt-8">Loading…</div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Page removal --}}
                <div class="mb-4 pb-4 border-b border-gray-100">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-2">Remove pages</p>
                    <div class="flex flex-wrap gap-2">
                        <form method="POST" action="{{ route('assignments.removePages', $assignment) }}"
                              onsubmit="return confirm('Remove title page (page 1)?')">
                            @csrf
                            <input type="hidden" name="pages" value="1">
                            <button type="submit"
                                    class="px-3 py-1.5 text-xs font-medium bg-gray-100 text-gray-700 rounded hover:bg-red-50 hover:text-red-700 border border-gray-200 hover:border-red-200 transition">
                                Remove title page
                            </button>
                        </form>

                        <form method="POST" action="{{ route('assignments.removePages', $assignment) }}"
                              x-data="{}"
                              onsubmit="return confirm('Remove last page?')">
                            @csrf
                            <input type="hidden" name="pages" value="last">
                            <button type="submit"
                                    class="px-3 py-1.5 text-xs font-medium bg-gray-100 text-gray-700 rounded hover:bg-red-50 hover:text-red-700 border border-gray-200 hover:border-red-200 transition">
                                Remove last page
                            </button>
                        </form>

                        <form method="POST" action="{{ route('assignments.removePages', $assignment) }}"
                              class="flex items-center gap-2"
                              onsubmit="return this.querySelector('input[name=pages]').value.trim() !== '' || false">
                            @csrf
                            <input type="text" name="pages" placeholder="e.g. 1, 5, 103"
                                   class="w-36 text-xs border border-gray-300 rounded px-2 py-1.5 focus:ring-indigo-500 focus:border-indigo-500">
                            <button type="submit"
                                    class="px-3 py-1.5 text-xs font-medium bg-gray-100 text-gray-700 rounded hover:bg-red-50 hover:text-red-700 border border-gray-200 hover:border-red-200 transition">
                                Remove
                            </button>
                        </form>
                    </div>
                </div>

                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-3">{{ $assignment->drive_script_file_id ? 'Replace script file' : 'Upload script' }}</p>
            @endif
            <form method="POST"
                  action="{{ route('assignments.uploadScript', $assignment) }}"
                  enctype="multipart/form-data"
                  x-data="{ fileName: '' }">
                @csrf
                <input type="file" id="script_upload" name="script" accept="application/pdf" required
                       class="sr-only"
                       @change="fileName = $event.target.files[0]?.name || ''">

                <label for="script_upload"
                       class="flex flex-col items-center justify-center w-full border-2 border-dashed rounded-lg px-6 py-6 cursor-pointer transition"
                       :class="fileName ? 'border-indigo-400 bg-indigo-50' : 'border-gray-300 bg-gray-50 hover:border-indigo-400 hover:bg-indigo-50'">
                    <svg class="w-8 h-8 mb-2" :class="fileName ? 'text-indigo-500' : 'text-gray-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                              d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <p class="text-sm font-medium" :class="fileName ? 'text-indigo-700' : 'text-gray-500'"
                       x-text="fileName || 'Click to choose a PDF'"></p>
                    <p class="text-xs text-gray-400 mt-1" x-show="!fileName">PDF only · max 50 MB</p>
                </label>

                <button type="submit"
                        x-show="fileName"
                        class="mt-3 w-full px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded hover:bg-indigo-700 transition">
                    {{ $assignment->drive_script_file_id ? 'Replace Script' : 'Upload Script' }}
                </button>
            </form>
            @if ($errors->has('script'))
                <p class="mt-1 text-xs text-red-600">{{ $errors->first('script') }}</p>
            @endif
        </div>
    </div>

    @push('scripts')
    <script>
    document.addEventListener('alpine:init', () => {
        if (Alpine._data?.pdfViewer) return;

        async function ensurePdfJs() {
            if (window.pdfjsLib) return;
            await new Promise((resolve, reject) => {
                const s = document.createElement('script');
                s.src = 'https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/build/pdf.min.js';
                s.onload = () => {
                    window.pdfjsLib.GlobalWorkerOptions.workerSrc =
                        'https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/build/pdf.worker.min.js';
                    resolve();
                };
                s.onerror = () => reject(new Error('PDF.js failed to load'));
                document.head.appendChild(s);
            });
        }

        Alpine.data('pdfViewer', (url) => {
            let pdfDoc = null;
            let pages  = [];

            return {
                open: false,
                url: url,
                currentPage: 0,
                totalPages: 0,
                loading: false,

                async openViewer() {
                    this.open = true;
                    await this.$nextTick();
                    this.$refs.modal?.focus();
                    if (!pdfDoc) await this.loadPdf();
                },

                async loadPdf() {
                    this.loading = true;
                    try {
                        await ensurePdfJs();
                        pdfDoc = await pdfjsLib.getDocument({
                            url: this.url,
                            withCredentials: true,
                        }).promise;
                        this.totalPages = pdfDoc.numPages;
                        await this.renderAllPages();
                    } catch (e) {
                        console.error('PDF load error:', e);
                    } finally {
                        this.loading = false;
                    }
                },

                async renderAllPages() {
                    const wrap = this.$refs.canvasWrap;
                    pages = [];
                    const maxW = Math.max(wrap.clientWidth - 48, 200);
                    for (let i = 1; i <= this.totalPages; i++) {
                        this.currentPage = i;
                        const page = await pdfDoc.getPage(i);
                        const base = page.getViewport({ scale: 1 });
                        const scale = Math.min(maxW / base.width, 2.0);
                        const vp = page.getViewport({ scale });
                        const canvas = document.createElement('canvas');
                        canvas.width  = vp.width;
                        canvas.height = vp.height;
                        canvas.className = 'shadow-2xl shrink-0';
                        wrap.appendChild(canvas);
                        pages.push(canvas);
                        await page.render({ canvasContext: canvas.getContext('2d'), viewport: vp }).promise;
                    }
                },

                scrollToPage(num) {
                    const n = Math.max(1, Math.min(parseInt(num) || 1, this.totalPages));
                    if (pages[n - 1]) pages[n - 1].scrollIntoView({ behavior: 'smooth' });
                },
            };
        });
    });
    </script>
    @endpush
</x-app-layout>
