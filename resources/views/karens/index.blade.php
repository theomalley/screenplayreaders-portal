<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Karen List</h2>
    </x-slot>

    @php
        $canManage = \App\Support\Permission::check('karens.manage');
        $canDelete = auth()->user()?->isAdmin();
    @endphp

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-4">

            <p class="text-sm text-gray-500">
                Customers we don't ever want to do business with again. Every script upload is
                automatically checked against this list by name and email — a match flags the
                assignment with a Karen alert.
            </p>

            <div x-data="karenList(@js($karens->map(fn ($k) => [
                    'id'           => $k->id,
                    'first_name'   => $k->first_name ?? '',
                    'last_name'    => $k->last_name ?? '',
                    'email'        => $k->email ?? '',
                    'notes'        => $k->notes ?? '',
                    'flagged_date' => $k->flagged_date?->format('Y-m-d') ?? '',
                ])->values()), @js($canManage), @js((bool) $canDelete))"
                 class="bg-white shadow-sm sm:rounded-lg overflow-hidden">

                <div x-show="flash" x-transition x-text="flash"
                     class="px-4 py-2 text-sm text-green-800 bg-green-50 border-b border-green-200"></div>
                <div x-show="error" x-transition x-text="error"
                     class="px-4 py-2 text-sm text-red-800 bg-red-50 border-b border-red-200"></div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm divide-y divide-gray-100">
                        <thead class="bg-gray-50">
                            <tr>
                                <template x-for="col in columns" :key="col.key">
                                    <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider cursor-pointer hover:text-gray-700 select-none"
                                        @click="sort(col.key)">
                                        <span x-text="col.label"></span>
                                        <span x-show="sortCol === col.key" x-text="sortAsc ? '↑' : '↓'" class="ml-0.5"></span>
                                    </th>
                                </template>
                                <th class="px-4 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <template x-for="row in sorted" :key="row.id ?? row._key">
                                <tr class="hover:bg-gray-50" :class="row.saving ? 'opacity-60' : ''"
                                    :data-row-key="row.id ?? row._key">
                                    <td class="px-4 py-2">
                                        <input type="text" x-model="row.first_name" @change="save(row)"
                                               :disabled="!canManage"
                                               class="w-full border-0 bg-transparent focus:ring-1 focus:ring-indigo-400 rounded px-1 py-0.5 disabled:text-gray-500 font-mono text-xs">
                                    </td>
                                    <td class="px-4 py-2">
                                        <input type="text" x-model="row.last_name" @change="save(row)"
                                               :disabled="!canManage"
                                               class="w-full border-0 bg-transparent focus:ring-1 focus:ring-indigo-400 rounded px-1 py-0.5 disabled:text-gray-500 font-mono text-xs">
                                    </td>
                                    <td class="px-4 py-2">
                                        <input type="email" x-model="row.email" @change="save(row)"
                                               :disabled="!canManage"
                                               class="w-full border-0 bg-transparent focus:ring-1 focus:ring-indigo-400 rounded px-1 py-0.5 disabled:text-gray-500 font-mono text-xs">
                                    </td>
                                    <td class="px-4 py-2">
                                        <input type="text" :value="row.notes" @click="openNotes(row)" readonly
                                               placeholder="—"
                                               class="w-full border-0 bg-transparent focus:ring-1 focus:ring-indigo-400 rounded px-1 py-0.5 font-mono text-xs cursor-pointer">
                                    </td>
                                    <td class="px-4 py-2 whitespace-nowrap">
                                        <input type="date" x-model="row.flagged_date" @change="save(row)"
                                               :disabled="!canManage"
                                               :class="row.flagged_date ? '' : 'date-empty'"
                                               class="border-0 bg-transparent focus:ring-1 focus:ring-indigo-400 rounded px-1 py-0.5 disabled:text-gray-500 font-mono text-xs">
                                    </td>
                                    <td class="px-4 py-2 text-right whitespace-nowrap">
                                        <button x-show="canDelete && row.id" type="button" @click="remove(row)"
                                                class="text-xs text-red-600 hover:underline">Delete</button>
                                    </td>
                                </tr>
                            </template>
                            <tr x-show="rows.length === 0">
                                <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-400">No Karens on the list.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div x-show="canManage" class="px-4 py-3 border-t border-gray-100">
                    <button type="button" @click="addRow()"
                            class="inline-flex items-center px-3 py-1.5 bg-indigo-600 border border-transparent rounded text-xs font-medium text-white hover:bg-indigo-700 transition">
                        + Add Karen
                    </button>
                </div>

                <div x-show="notesModal" x-cloak
                     @keydown.escape.window="closeNotes()"
                     @click.self="closeNotes()"
                     class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
                    <div class="bg-white rounded-lg shadow-xl w-full max-w-lg flex flex-col max-h-[80vh]">
                        <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                            <h3 class="text-sm font-semibold text-gray-700"
                                x-text="notesModal ? [notesModal.first_name, notesModal.last_name].filter(Boolean).join(' ') || 'Notes' : ''"></h3>
                            <button type="button" @click="closeNotes()" class="text-gray-400 hover:text-gray-600 text-lg leading-none">&times;</button>
                        </div>
                        <div class="p-4 overflow-y-auto">
                            <template x-if="notesModal">
                                <textarea x-model="notesModal.notes" :readonly="!canManage" rows="10"
                                          placeholder="—"
                                          class="w-full border border-gray-200 rounded p-2 text-sm font-mono focus:ring-1 focus:ring-indigo-400 focus:border-indigo-400"></textarea>
                            </template>
                        </div>
                        <div class="px-4 py-3 border-t border-gray-100 flex justify-end gap-2">
                            <button type="button" @click="closeNotes()"
                                    class="px-3 py-1.5 text-xs font-medium text-gray-600 hover:text-gray-800">Close</button>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <style>
    /* Native date inputs always render the mm/dd/yyyy format hint even when empty —
       hide it so an unset date reads as truly blank instead of a placeholder value. */
    input[type="date"].date-empty::-webkit-datetime-edit-fields-wrapper,
    input[type="date"].date-empty::-webkit-datetime-edit-text,
    input[type="date"].date-empty::-webkit-datetime-edit-month-field,
    input[type="date"].date-empty::-webkit-datetime-edit-day-field,
    input[type="date"].date-empty::-webkit-datetime-edit-year-field {
        color: transparent;
    }
    </style>

    @push('scripts')
    <script>
    function karenList(initialRows, canManage, canDelete) {
        return {
            rows: initialRows,
            canManage,
            canDelete,
            columns: [
                { key: 'first_name',   label: 'First Name' },
                { key: 'last_name',    label: 'Last Name' },
                { key: 'email',        label: 'Email' },
                { key: 'notes',        label: 'Notes' },
                { key: 'flagged_date', label: 'Date' },
            ],
            sortCol: 'last_name',
            sortAsc: true,
            flash: '',
            error: '',
            _seq: 0,
            notesModal: null,

            openNotes(row) {
                this.notesModal = row;
            },
            closeNotes() {
                if (this.canManage && this.notesModal) this.save(this.notesModal);
                this.notesModal = null;
            },

            get sorted() {
                return [...this.rows].sort((a, b) => {
                    const av = (a[this.sortCol] ?? '').toString().toLowerCase();
                    const bv = (b[this.sortCol] ?? '').toString().toLowerCase();
                    const cmp = av.localeCompare(bv);
                    return this.sortAsc ? cmp : -cmp;
                });
            },
            sort(col) {
                if (this.sortCol === col) {
                    this.sortAsc = !this.sortAsc;
                } else {
                    this.sortCol = col;
                    this.sortAsc = true;
                }
            },
            addRow() {
                const key = 'new-' + (this._seq++);
                this.rows.push({
                    _key: key,
                    id: null,
                    first_name: '', last_name: '', email: '', notes: '',
                    flagged_date: '',
                });
                // New rows sort to the top of a 100+ row table — without this the
                // click reads as "does nothing" since the row lands off-screen.
                this.$nextTick(() => {
                    const el = document.querySelector(`[data-row-key="${key}"] input`);
                    if (el) {
                        el.scrollIntoView({ block: 'center', behavior: 'smooth' });
                        el.focus();
                    }
                });
            },
            csrf() {
                return document.querySelector('meta[name=csrf-token]')?.content ?? '';
            },
            flashMsg(msg) {
                this.flash = msg;
                this.error = '';
                setTimeout(() => { this.flash = ''; }, 2000);
            },
            flashErr(msg) {
                this.error = msg;
                this.flash = '';
            },
            save(row) {
                if (!this.canManage) return;
                row.saving = true;
                const isNew = !row.id;
                const url = isNew ? @js(route('karens.store')) : @js(url('/karens')) + '/' + row.id;
                const body = {
                    first_name: row.first_name,
                    last_name: row.last_name,
                    email: row.email,
                    notes: row.notes,
                    flagged_date: row.flagged_date,
                };

                fetch(url, {
                    method: isNew ? 'POST' : 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrf(),
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify(body),
                })
                .then(async (r) => {
                    row.saving = false;
                    if (!r.ok) throw new Error('Save failed');
                    const data = await r.json().catch(() => null);
                    if (isNew && data?.karen?.id) row.id = data.karen.id;
                    this.flashMsg('Saved.');
                })
                .catch(() => {
                    row.saving = false;
                    this.flashErr('Could not save — please try again.');
                });
            },
            remove(row) {
                if (!this.canDelete || !row.id) return;
                if (!confirm('Remove this Karen from the list?')) return;
                fetch(@js(url('/karens')) + '/' + row.id, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': this.csrf(),
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                })
                .then((r) => {
                    if (!r.ok) throw new Error('Delete failed');
                    this.rows = this.rows.filter(r2 => r2 !== row);
                    this.flashMsg('Removed.');
                })
                .catch(() => this.flashErr('Could not delete — please try again.'));
            },
        };
    }
    </script>
    @endpush
</x-app-layout>
