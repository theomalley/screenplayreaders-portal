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

    <div class="py-6" x-data="{ editing: false, html: @js($html) }" @manual-edit-open.window="editing = true">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">

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
                        <label class="block text-xs font-medium text-gray-600 mb-2">HTML Content</label>
                        <textarea name="html" rows="20"
                                  class="w-full font-mono text-sm border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-400 resize-y"
                                  placeholder="Paste or type HTML here…">{{ old('html', $html) }}</textarea>
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

            {{-- Rendered content --}}
            <div x-show="!editing">
                @if($html)
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 prose max-w-none">
                        {!! $html !!}
                    </div>
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
