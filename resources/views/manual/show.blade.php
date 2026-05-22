<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Reader Manual</h2>
            @if(auth()->user()->isAdmin())
                <button type="button" x-data x-on:click="$dispatch('manual-edit-open')"
                        class="inline-flex items-center px-3 py-1.5 bg-white border border-gray-300 rounded text-xs font-medium text-gray-700 hover:bg-gray-50 transition">
                    Edit
                </button>
            @endif
        </div>
    </x-slot>

    <div class="py-6" x-data="{ editing: false }" @manual-edit-open.window="editing = true">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">

            @if (session('success'))
                <div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-800 rounded-md text-sm">
                    {{ session('success') }}
                </div>
            @endif

            {{-- Edit form (admin only) --}}
            @if(auth()->user()->isAdmin())
                <div x-show="editing" x-cloak class="mb-6 bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                    <form method="POST" action="{{ route('manual.update') }}">
                        @csrf
                        @method('PATCH')
                        <label class="block text-xs font-medium text-gray-700 mb-1">Paste full page source or HTML fragment</label>
                        <p class="text-xs text-gray-400 mb-2">
                            On your WordPress page: View Source → Select All → paste here.
                            Stylesheets and content are extracted automatically.
                            Pasting a plain HTML fragment also works.
                        </p>
                        <details class="mb-3 text-xs">
                            <summary class="cursor-pointer text-indigo-600 hover:text-indigo-800 font-medium select-none">Ratebook placeholders</summary>
                            <div class="mt-2 p-3 bg-gray-50 border border-gray-200 rounded font-mono text-gray-600 grid grid-cols-2 gap-x-6 gap-y-1">
                                @foreach(\App\Models\Setting::ratesForForms() as $key => $value)
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="text-indigo-700">[[{{ $key }}]]</span>
                                        <span class="text-gray-400">${{ number_format($value, 2) }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </details>
                        <textarea name="source_html" rows="24"
                                  class="w-full font-mono text-sm border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-400 resize-y"
                                  placeholder="Paste full page HTML source or an HTML fragment…">{{ $content }}</textarea>
                        <div class="flex items-center justify-end gap-3 mt-3">
                            <button type="button" @click="editing = false"
                                    class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 transition">
                                Cancel
                            </button>
                            <button type="submit"
                                    class="px-4 py-2 text-sm font-semibold text-white bg-indigo-600 rounded hover:bg-indigo-500 transition">
                                Save
                            </button>
                        </div>
                    </form>
                </div>
            @endif

            {{-- Preview --}}
            <div x-show="!editing">
                @if($hasContent)
                    <iframe id="manual-frame"
                            src="{{ route('manual.frame') }}"
                            class="w-full border-0 rounded-lg shadow-sm bg-white"
                            style="min-height: 400px"
                            scrolling="no">
                    </iframe>
                    <script>
                        window.addEventListener('message', function (e) {
                            if (e.data && e.data.manualHeight) {
                                document.getElementById('manual-frame').style.height = e.data.manualHeight + 'px';
                            }
                        });
                    </script>
                @else
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-12 text-center text-gray-400 text-sm">
                        @if(auth()->user()->isAdmin())
                            No content yet. Click <strong>Edit</strong> to add content.
                        @else
                            The Reader Manual hasn't been published yet.
                        @endif
                    </div>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>
