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
                <form method="POST" action="{{ route('assignments.update', $assignment) }}" class="p-6 space-y-5">
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
                    <div x-data="{ vendor: '{{ old('vendor', $assignment->vendor) }}' }" class="grid grid-cols-2 gap-3 items-start">
                        <div>
                            <x-input-label value="Vendor" />
                            <div class="mt-2 flex gap-4">
                                <label class="flex items-center gap-1.5 text-sm font-medium text-gray-700 cursor-pointer">
                                    <input type="radio" name="vendor" value="sr" x-model="vendor"
                                        class="text-indigo-600 border-gray-300 focus:ring-indigo-500" />
                                    SR
                                </label>
                                <label class="flex items-center gap-1.5 text-sm font-medium text-gray-700 cursor-pointer">
                                    <input type="radio" name="vendor" value="wd" x-model="vendor"
                                        class="text-indigo-600 border-gray-300 focus:ring-indigo-500" />
                                    WD
                                </label>
                            </div>
                            <x-input-error :messages="$errors->get('vendor')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="assignment_type" value="Assignment Type (optional)" />
                            <select id="assignment_type" name="assignment_type"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                                <option value="">— Set later —</option>
                                <template x-if="vendor === 'sr'">
                                    <optgroup label="SR">
                                        @foreach (['script_coverage' => 'Script Coverage', 'notes_only' => 'Notes Only', 'short' => 'Short Coverage', 'deep_dive' => 'Deep-Dive Dev Notes', 'budget' => 'Budget Coverage', 'book' => 'Book Coverage'] as $val => $label)
                                            <option value="{{ $val }}" {{ old('assignment_type', $assignment->assignment_type) === $val ? 'selected' : '' }}>{{ $label }}</option>
                                        @endforeach
                                    </optgroup>
                                </template>
                                <template x-if="vendor === 'wd'">
                                    <optgroup label="WD">
                                        @foreach (['coverage' => 'Coverage', 'development_notes' => 'Development Notes'] as $val => $label)
                                            <option value="{{ $val }}" {{ old('assignment_type', $assignment->assignment_type) === $val ? 'selected' : '' }}>{{ $label }}</option>
                                        @endforeach
                                    </optgroup>
                                </template>
                            </select>
                            <x-input-error :messages="$errors->get('assignment_type')" class="mt-1" />
                        </div>
                    </div>

                    {{-- Script title --}}
                    <div>
                        <x-input-label for="script_title" value="Script Title" />
                        <x-text-input id="script_title" name="script_title" type="text"
                            class="mt-1 block w-full"
                            value="{{ old('script_title', $assignment->script_title) }}"
                            required />
                        <x-input-error :messages="$errors->get('script_title')" class="mt-1" />
                    </div>

                    {{-- Author --}}
                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <x-input-label for="author_first_initial" value="Author Initial" />
                            <x-text-input id="author_first_initial" name="author_first_initial" type="text"
                                class="mt-1 block w-full uppercase"
                                value="{{ old('author_first_initial', $assignment->author_first_initial) }}"
                                maxlength="1"
                                required />
                            <x-input-error :messages="$errors->get('author_first_initial')" class="mt-1" />
                        </div>
                        <div class="col-span-2">
                            <x-input-label for="author_last_name" value="Author Last Name" />
                            <x-text-input id="author_last_name" name="author_last_name" type="text"
                                class="mt-1 block w-full"
                                value="{{ old('author_last_name', $assignment->author_last_name) }}"
                                required />
                            <x-input-error :messages="$errors->get('author_last_name')" class="mt-1" />
                        </div>
                    </div>

                    {{-- Page count + Pay rate --}}
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <x-input-label for="page_count" value="Page Count" />
                            <x-text-input id="page_count" name="page_count" type="number"
                                class="mt-1 block w-full"
                                value="{{ old('page_count', $assignment->page_count) }}"
                                min="1" max="9999"
                                required />
                            <x-input-error :messages="$errors->get('page_count')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="pay_rate" value="Pay Rate ($)" />
                            <x-text-input id="pay_rate" name="pay_rate" type="number"
                                class="mt-1 block w-full"
                                value="{{ old('pay_rate', $assignment->pay_rate) }}"
                                min="0" step="0.01"
                                required />
                            <x-input-error :messages="$errors->get('pay_rate')" class="mt-1" />
                        </div>
                    </div>

                    {{-- Status + Rush --}}
                    <div class="grid grid-cols-2 gap-3 items-start">
                        <div>
                            <x-input-label for="status" value="Status" />
                            <select id="status" name="status"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                                @foreach ([
                                    'incoming'   => 'Incoming',
                                    'unassigned' => 'Unassigned',
                                    'assigned'   => 'Assigned',
                                    'completed'  => 'Completed',
                                    'qc'         => 'QC',
                                    'on_hold'    => 'On Hold',
                                    'cancelled'  => 'Cancelled',
                                ] as $value => $label)
                                    <option value="{{ $value }}"
                                        {{ old('status', $assignment->status) === $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('status')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label value="Type" />
                            <div class="mt-2 flex items-center gap-2 h-9">
                                <input id="rush" name="rush" type="checkbox" value="1"
                                    {{ old('rush', $assignment->rush) ? 'checked' : '' }}
                                    class="rounded border-gray-300 text-amber-500 shadow-sm focus:ring-amber-500" />
                                <label for="rush" class="text-sm text-gray-700 font-medium">Rush (24h turnaround)</label>
                            </div>
                        </div>
                    </div>

                    {{-- Requested reader --}}
                    <div>
                        <x-input-label for="requested_reader_id" value="Requested Reader (optional)" />
                        <select id="requested_reader_id" name="requested_reader_id"
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
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                            <option value="">— None —</option>
                            @foreach ($readers as $reader)
                                <option value="{{ $reader->id }}"
                                    {{ old('assigned_reader_id', $assignment->assigned_reader_id) == $reader->id ? 'selected' : '' }}>
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
</x-app-layout>
