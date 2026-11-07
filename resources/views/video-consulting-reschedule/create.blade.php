<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Propose New Times</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            @if ($errors->any())
                <div class="bg-red-50 border border-red-200 rounded-lg p-4 text-sm text-red-700">
                    <ul class="list-disc list-inside space-y-0.5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <p class="text-sm text-gray-500 mb-4">
                    Propose 5 alternate times for your {{ $assignment->script_title }} call (order #{{ $assignment->order_number }}).
                    The customer will pick one via an emailed link — your original time stays reserved until they do.
                </p>

                <form method="POST" action="{{ route('video-consulting-reschedule.store', $assignment) }}" class="space-y-4">
                    @csrf

                    @for ($i = 0; $i < 5; $i++)
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Option {{ $i + 1 }}</label>
                            <input type="datetime-local" name="slots[]" required
                                   class="border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        </div>
                    @endfor

                    <div class="flex justify-end pt-2 border-t border-gray-100">
                        <x-primary-button>Send to Customer</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
