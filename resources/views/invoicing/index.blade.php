<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Invoices</h2>
            <a href="{{ route('invoicing.create') }}"
               class="inline-flex items-center px-3 py-1.5 bg-indigo-600 border border-transparent rounded text-xs font-medium text-white hover:bg-indigo-700 transition">
                + Create Invoice
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            @if(session('success'))
                <div class="px-4 py-3 rounded bg-green-50 border border-green-200 text-green-800 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            @if($errors->has('invoice'))
                <div class="px-4 py-3 rounded bg-red-50 border border-red-200 text-red-800 text-sm">
                    {{ $errors->first('invoice') }}
                </div>
            @endif

            {{-- Outstanding --}}
            <div class="bg-white shadow-sm sm:rounded-lg">
                <div class="px-4 py-3 border-b border-gray-100">
                    <h3 class="text-sm font-semibold text-gray-700">Outstanding</h3>
                </div>
                @if($outstanding->isEmpty())
                    <div class="px-4 py-8 text-center text-sm text-gray-400">No outstanding invoices.</div>
                @else
                    <div class="overflow-x-auto">
                        @include('invoicing._invoice_table', ['invoices' => $outstanding, 'showPaid' => false])
                    </div>
                @endif
            </div>

            {{-- Paid --}}
            @if($paid->isNotEmpty())
                <div class="bg-white shadow-sm sm:rounded-lg">
                    <div class="px-4 py-3 border-b border-gray-100">
                        <h3 class="text-sm font-semibold text-gray-700">Paid</h3>
                    </div>
                    <div class="overflow-x-auto">
                        @include('invoicing._invoice_table', ['invoices' => $paid, 'showPaid' => true])
                    </div>
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
