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
                <form method="POST" action="{{ route('assignments.store') }}" class="p-6 space-y-5" enctype="multipart/form-data"
                      x-data="{
                          vendor: '{{ old('vendor', 'sr') }}',
                          assignmentType: '{{ old('assignment_type', '') }}',
                          pageCount: '{{ old('page_count', '') }}',
                          customOversizedFee: '{{ old('custom_oversized_fee', '') }}',
                          rush: {{ old('rush') ? 'true' : 'false' }},
                          numReaders: '{{ old('num_readers', '1') }}',
                          requestedReaders: ['{{ old('requested_reader_id_1', '') }}', '{{ old('requested_reader_id_2', '') }}', '{{ old('requested_reader_id_3', '') }}'],
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

                              // Shared modifiers: oversized + rush (same for every sub-assignment)
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

                              // Reader request modifier — per slot, since each reader may or may not be requested
                              const reqRate = parseFloat({ sr: r.rate_sr_request, wd: r.rate_wd_request }[this.vendor] || 0);
                              const modFor  = (i) => this.requestedReaders[i] ? reqRate : 0;

                              if (this.numReaders !== '1') {
                                  const typeA  = this.vendor === 'sr' ? 'script_coverage' : 'coverage';
                                  const typeB  = this.vendor === 'sr' ? 'notes_only'       : 'development_notes';
                                  const labelA = this.vendor === 'sr' ? 'SC'               : 'Coverage';
                                  const labelB = this.vendor === 'sr' ? 'NO'               : 'Dev Notes';
                                  const baseA  = parseFloat((map[this.vendor] || {})[typeA] || 0);
                                  const baseB  = parseFloat((map[this.vendor] || {})[typeB] || 0);
                                  const n      = parseInt(this.numReaders);
                                  const parts  = [];
                                  for (let i = 0; i < n; i++) {
                                      const base  = i === 0 ? baseA : baseB;
                                      const label = i === 0 ? labelA : labelB;
                                      parts.push('$' + (base + sharedMod + modFor(i)).toFixed(2) + ' (' + label + ')');
                                  }
                                  el.textContent = parts.join(' + ');
                                  el.className = 'text-sm font-semibold text-gray-900';
                                  if (hidden) hidden.value = '';
                                  return;
                              }

                              // 1R path
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
                              const total = parseFloat(base) + sharedMod + modFor(0);
                              el.textContent = '$' + total.toFixed(2);
                              el.className = 'text-sm font-semibold text-gray-900';
                              if (hidden) hidden.value = total.toFixed(2);
                          },
                          init() { this.updatePayDisplay(); },
                          createInvoice: {{ old('create_invoice') ? 'true' : 'false' }},
                          invoiceClientId: '{{ old('invoice_client_id', '') }}',
                          batchClientIds: {{ \App\Models\Client::where('batch_invoicing', true)->pluck('id') }},
                          get isBatchClient() { return this.batchClientIds.includes(parseInt(this.invoiceClientId)); }
                      }">
                    @csrf

                    {{-- Invoice --}}
                    @if(\App\Models\Client::count() > 0)
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
                                            <option value="{{ $client->id }}" {{ old('invoice_client_id') == $client->id ? 'selected' : '' }}>
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
                    @endif

                    {{-- Vendor: SR is default --}}
                    <div>
                        <x-input-label value="Vendor" />
                        <div class="mt-2 flex gap-6">
                            <label class="flex items-center gap-2 text-sm font-medium text-gray-700 cursor-pointer">
                                <input type="radio" name="vendor" value="sr"
                                    {{ old('vendor', 'sr') === 'sr' ? 'checked' : '' }}
                                    @change="vendor = 'sr'; assignmentType = ''; requestedReaders = ['', '', '']; updatePayDisplay()"
                                    class="text-indigo-600 border-gray-300 focus:ring-indigo-500" />
                                SR
                            </label>
                            <label class="flex items-center gap-2 text-sm font-medium text-gray-700 cursor-pointer">
                                <input type="radio" name="vendor" value="wd"
                                    {{ old('vendor', 'sr') === 'wd' ? 'checked' : '' }}
                                    @change="vendor = 'wd'; assignmentType = ''; numReaders = '1'; requestedReaders = ['', '', '']; updatePayDisplay()"
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
                            <option value="1" {{ old('num_readers', '1') === '1' ? 'selected' : '' }}>1R</option>
                            <option value="2" {{ old('num_readers', '1') === '2' ? 'selected' : '' }}>2R</option>
                            <option value="3" {{ old('num_readers', '1') === '3' ? 'selected' : '' }}>3R</option>
                        </select>
                        <x-input-error :messages="$errors->get('num_readers')" class="mt-1" />
                    </div>

                    {{-- Assignment Type (SR — 1R: all options) --}}
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

                    {{-- Assignment Type (SR — 2R/3R: locked to Script Coverage) --}}
                    <div x-show="vendor === 'sr' && numReaders !== '1'">
                        <x-input-label for="assignment_type_sr_multi" value="Assignment Type" />
                        <select id="assignment_type_sr_multi"
                            disabled
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm bg-gray-50 text-gray-500 cursor-not-allowed">
                            <option value="script_coverage" selected>Script Coverage</option>
                        </select>
                        <p class="mt-1 text-xs text-gray-400">Notes-Only coverage(s) auto-created for the additional reader(s).</p>
                    </div>

                    {{-- Assignment Type (WD) --}}
                    <div x-show="vendor === 'wd'">
                        <x-input-label for="assignment_type_wd" value="Assignment Type" />
                        <select id="assignment_type_wd" name="assignment_type"
                            :disabled="vendor !== 'wd'"
                            @change="assignmentType = $event.target.value; updatePayDisplay()"
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                            <option value="">— Select type —</option>
                            <option value="coverage"          {{ old('assignment_type') === 'coverage'          ? 'selected' : '' }}>Coverage</option>
                            <option value="development_notes" {{ old('assignment_type') === 'development_notes' ? 'selected' : '' }}>Development Notes</option>
                        </select>
                        <x-input-error :messages="$errors->get('assignment_type')" class="mt-1" />
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

                    {{-- Reader Request(s) --}}
                    <div class="space-y-3">

                        {{-- Slot 1 — always visible --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700">
                                <span x-text="numReaders !== '1' ? 'Reader Request 1' : 'Reader Request'"></span>
                            </label>
                            <select name="requested_reader_id_1"
                                @change="requestedReaders[0] = $event.target.value; updatePayDisplay()"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                                <option value="">None</option>
                                @foreach ($readers as $reader)
                                    <option value="{{ $reader->id }}"
                                        :disabled="requestedReaders[1] === '{{ $reader->id }}' || requestedReaders[2] === '{{ $reader->id }}'"
                                        {{ old('requested_reader_id_1') == $reader->id ? 'selected' : '' }}>
                                        {{ $reader->readerProfile?->initials ?? $reader->name }}
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('requested_reader_id_1')" class="mt-1" />
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
                            <x-input-error :messages="$errors->get('requested_reader_id_2')" class="mt-1" />
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
                            <x-input-error :messages="$errors->get('requested_reader_id_3')" class="mt-1" />
                        </div>

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
                            <x-input-label for="helpscout_ticket_number" value="HelpScout Ticket #" />
                            <input type="text" id="helpscout_ticket_number" name="helpscout_ticket_number"
                                value="{{ old('helpscout_ticket_number') }}"
                                placeholder="e.g. 9731"
                                class="mt-1 block w-40 border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" />
                            <p class="mt-1 text-xs text-gray-400">For manually created orders — the # shown at the top of the HelpScout ticket.</p>
                            <x-input-error :messages="$errors->get('helpscout_ticket_number')" class="mt-1" />
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
                                <option value="on_hold_customer" {{ old('status', 'incoming') === 'on_hold_customer' ? 'selected' : '' }}>On Hold – Customer</option>
                                <option value="on_hold_sr"       {{ old('status', 'incoming') === 'on_hold_sr'       ? 'selected' : '' }}>On Hold – SR</option>
                                <option value="cancelled"  {{ old('status', 'incoming') === 'cancelled'  ? 'selected' : '' }}>Cancelled</option>
                            </select>
                            <x-input-error :messages="$errors->get('status')" class="mt-1" />
                        </div>

                    </div>

                    {{-- Script upload (optional) --}}
                    <div class="pt-4 border-t border-gray-100" x-data="{ fileName: '' }">
                        <x-input-label value="Script (optional)" />
                        <p class="text-xs text-gray-400 mb-2">PDF only · max 50 MB · can also be uploaded after creation</p>

                        <input type="file" id="script_upload" name="script" accept="application/pdf"
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
                        </label>

                        <x-input-error :messages="$errors->get('script')" class="mt-1" />
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
