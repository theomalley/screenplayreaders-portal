<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-4">
            <a href="{{ route('settings.index') }}" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Coverage Submission Page</h2>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            @if(session('success'))
                <div class="bg-green-50 border border-green-200 rounded-lg p-4 text-sm text-green-700">
                    {{ session('success') }}
                </div>
            @endif

            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <p class="text-sm text-gray-500 mb-5">
                    Content below appears beneath the "Assignment Submitted for QC" confirmation message.
                    Accepts raw HTML. Leave blank for no additional content.
                </p>

                <form method="POST" action="{{ route('settings.coverage-success.update') }}">
                    @csrf
                    @method('PATCH')

                    <div>
                        <x-input-label for="content" value="Custom HTML" />
                        <textarea id="content" name="content" rows="14"
                                  class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm font-mono focus:border-indigo-500 focus:ring-indigo-500"
                                  placeholder="<p>Your HTML here...</p>">{{ old('content', $content) }}</textarea>
                        <x-input-error :messages="$errors->get('content')" class="mt-1" />
                    </div>

                    <div class="flex items-center justify-end gap-3 mt-5">
                        <a href="{{ route('settings.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
                        <x-primary-button>Save</x-primary-button>
                    </div>
                </form>
            </div>

            @php $preview = \App\Models\Setting::getValue('coverage_success_html', ''); @endphp
            @if($preview)
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-4">Preview</p>
                    <div class="text-sm text-gray-700 prose prose-sm max-w-none">
                        {!! $preview !!}
                    </div>
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
