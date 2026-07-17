<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Payroll</h2>
            <form method="GET" action="{{ route('payroll.index') }}">
                <select name="period" onchange="this.form.submit()"
                    class="text-sm border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                    @foreach(\App\Http\Controllers\PayrollController::$PERIODS as $key => $label)
                        <option value="{{ $key }}" @selected($period === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </form>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            @if(session('success'))
                <div class="bg-green-50 border border-green-200 text-green-700 rounded-lg px-4 py-3 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            {{-- Current pay-period snapshot — what is owed right now --}}
            <div class="bg-indigo-600 rounded-lg shadow px-5 py-4 text-white flex flex-wrap items-center justify-between gap-4">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-wide opacity-75">Current Pay Period — {{ $currentPeriod['label'] }}</div>
                    <div class="mt-1 flex flex-wrap items-center gap-4 text-sm opacity-90">
                        <span>1099 Pay: <strong>${{ number_format($currentPeriod['pay_1099'], 2) }}</strong></span>
                        <span>Non-1099 Pay: <strong>${{ number_format($currentPeriod['pay_non_1099'], 2) }}</strong></span>
                    </div>
                    <div class="mt-1 text-xs opacity-75">
                        Next payout: {{ $nextPayout->format('D M j') }} at {{ $nextPayout->format('g:i A') }} PT
                        @if($schedule['override'])
                            <span class="text-amber-200">(override active)</span>
                        @endif
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-xs font-semibold uppercase tracking-wide opacity-75">Owed So Far</div>
                    <div class="mt-0.5 text-3xl font-extrabold">${{ number_format($currentPeriod['total'], 2) }}</div>
                </div>
            </div>

            {{-- Editor Pay --}}
            @if($byEditor->isEmpty())
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 px-6 py-12 text-center text-gray-400 text-sm">
                    No editors configured.
                </div>
            @else
                @foreach($byEditor as $ed)
                    @include('payroll._editor-pay-card', ['ed' => $ed])
                @endforeach
            @endif

            {{-- Reader Pay --}}
            @if($byReader->isEmpty())
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 px-6 py-12 text-center text-gray-400 text-sm">
                    No unpaid coverages. All readers are paid up.
                </div>
            @else
                @foreach($byReader as $rd)
                    @include('payroll._reader-pay-card', ['rd' => $rd])
                @endforeach
            @endif

            {{-- Unified Payment History --}}
            @include('payroll._history')

        </div>
    </div>
</x-app-layout>
