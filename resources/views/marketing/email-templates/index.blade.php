<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Email Templates</h2>
            <a href="{{ route('marketing.email-campaigns.create') }}"
               class="px-3 py-1.5 text-sm bg-indigo-600 text-white rounded hover:bg-indigo-700">
                New Campaign
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">

            @if(session('success'))
                <div class="mb-4 px-4 py-2 bg-green-50 border border-green-200 text-green-700 text-sm rounded">{{ session('success') }}</div>
            @endif

            @if($templates->isEmpty())
                <div class="text-center py-16 text-gray-400 text-sm">
                    No templates saved yet. Open a campaign, go to the HTML Source tab, and click "Save as Template".
                </div>
            @else
                <div class="space-y-3"
                     x-data="{
                        editingId: null,
                        editName: '',
                        async saveName(id) {
                            if (!this.editName.trim()) return;
                            await fetch(`/marketing/email-templates/${id}`, {
                                method: 'PATCH',
                                headers: {
                                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                },
                                body: JSON.stringify({ name: this.editName })
                            });
                            document.getElementById('tpl-name-' + id).textContent = this.editName;
                            this.editingId = null;
                        },
                        async deleteTemplate(id, name) {
                            if (!confirm('Delete template "' + name + '"?')) return;
                            const r = await fetch(`/marketing/email-templates/${id}`, {
                                method: 'DELETE',
                                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }
                            });
                            if (r.ok) document.getElementById('tpl-row-' + id).remove();
                        }
                     }">
                    @foreach($templates as $tpl)
                        <div id="tpl-row-{{ $tpl->id }}"
                             class="bg-white border border-gray-200 rounded-lg px-5 py-4 flex items-center gap-4">

                            {{-- Name / inline edit --}}
                            <div class="flex-1 min-w-0">
                                <span id="tpl-name-{{ $tpl->id }}"
                                      x-show="editingId !== {{ $tpl->id }}"
                                      class="text-sm font-medium text-gray-800">{{ $tpl->name }}</span>

                                <input type="text"
                                       x-show="editingId === {{ $tpl->id }}" x-cloak
                                       x-model="editName"
                                       @keydown.enter="saveName({{ $tpl->id }})"
                                       @keydown.escape="editingId = null"
                                       class="text-sm border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 w-full">

                                <p class="text-xs text-gray-400 mt-0.5">Updated {{ $tpl->updated_at->diffForHumans() }}</p>
                            </div>

                            {{-- Actions --}}
                            <div class="flex items-center gap-3 shrink-0">
                                {{-- Preview button (opens HTML in a modal) --}}
                                <button type="button"
                                        @click="
                                            fetch('/marketing/email-templates/{{ $tpl->id }}', { headers: { Accept: 'application/json' } })
                                                .then(r => r.json())
                                                .then(j => {
                                                    document.getElementById('preview-iframe').srcdoc = j.html;
                                                    document.getElementById('preview-modal').classList.remove('hidden');
                                                });
                                        "
                                        class="text-xs text-gray-500 hover:text-gray-700 border border-gray-200 rounded px-2 py-1">
                                    Preview
                                </button>

                                {{-- Edit name --}}
                                <button type="button"
                                        @click="editingId = {{ $tpl->id }}; editName = '{{ addslashes($tpl->name) }}'; $nextTick(() => $el.closest('.flex').querySelector('input').focus())"
                                        x-show="editingId !== {{ $tpl->id }}"
                                        class="text-xs text-gray-500 hover:text-gray-700 border border-gray-200 rounded px-2 py-1">
                                    Rename
                                </button>
                                <button type="button"
                                        @click="saveName({{ $tpl->id }})"
                                        x-show="editingId === {{ $tpl->id }}" x-cloak
                                        class="text-xs text-indigo-600 hover:text-indigo-800 border border-indigo-200 rounded px-2 py-1">
                                    Save
                                </button>

                                {{-- Delete --}}
                                <button type="button"
                                        @click="deleteTemplate({{ $tpl->id }}, '{{ addslashes($tpl->name) }}')"
                                        class="text-xs text-red-400 hover:text-red-600">
                                    Delete
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

        </div>
    </div>

    {{-- Preview modal --}}
    <div id="preview-modal"
         class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
         @click.self="document.getElementById('preview-modal').classList.add('hidden')">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl flex flex-col" style="max-height: 90vh;">
            <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200">
                <span class="text-sm font-medium text-gray-700">Template Preview</span>
                <button type="button"
                        onclick="document.getElementById('preview-modal').classList.add('hidden')"
                        class="text-gray-400 hover:text-gray-600 text-lg leading-none">&times;</button>
            </div>
            <div class="flex-1 overflow-auto p-3 bg-gray-100">
                <iframe id="preview-iframe" sandbox="allow-same-origin"
                        class="w-full bg-white rounded border border-gray-200"
                        style="height: 70vh;"></iframe>
            </div>
        </div>
    </div>

</x-app-layout>
