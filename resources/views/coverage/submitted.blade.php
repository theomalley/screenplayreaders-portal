<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Coverage Submitted</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-lg mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-8 text-center">

                {{-- Checkmark --}}
                <div class="flex justify-center mb-5">
                    <div class="w-16 h-16 rounded-full bg-green-100 flex items-center justify-center">
                        <svg class="w-9 h-9 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                </div>

                <h1 class="text-2xl font-bold text-gray-900 mb-2">Assignment Submitted for QC</h1>

                @if(session('submitted_title'))
                    <p class="text-gray-500 text-sm mb-6">{{ session('submitted_title') }}</p>
                @else
                    <div class="mb-6"></div>
                @endif

                <a href="{{ route('assignments.index') }}"
                   class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-md transition-colors">
                    Back to Assignments
                </a>

                @php $customHtml = \App\Models\Setting::getValue('coverage_success_html', ''); @endphp
                @if($customHtml)
                    <div class="mt-8 pt-6 border-t border-gray-100 text-left text-sm text-gray-700 prose prose-sm max-w-none">
                        {!! $customHtml !!}
                    </div>
                @endif

            </div>
        </div>
    </div>
</x-app-layout>
