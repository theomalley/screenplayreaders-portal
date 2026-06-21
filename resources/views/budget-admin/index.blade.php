<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Budget Admin</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">

            <a href="{{ route('budget-admin.crew-rates') }}"
               class="block bg-white rounded-lg shadow-sm border border-gray-200 px-5 py-4 hover:border-indigo-300 hover:shadow transition">
                <h3 class="text-base font-semibold text-gray-800">Crew Rates</h3>
                <p class="text-sm text-gray-500 mt-1">97 crew positions across 5 guilds with tiered union/non-union rates</p>
            </a>

            <a href="{{ route('budget-admin.fringes') }}"
               class="block bg-white rounded-lg shadow-sm border border-gray-200 px-5 py-4 hover:border-indigo-300 hover:shadow transition">
                <h3 class="text-base font-semibold text-gray-800">Fringe Rates</h3>
                <p class="text-sm text-gray-500 mt-1">Payroll taxes (FICA, Medicare, FUI) and union pension/health benefit rates</p>
            </a>

            <a href="{{ route('budget-admin.states') }}"
               class="block bg-white rounded-lg shadow-sm border border-gray-200 px-5 py-4 hover:border-indigo-300 hover:shadow transition">
                <h3 class="text-base font-semibold text-gray-800">State Rates</h3>
                <p class="text-sm text-gray-500 mt-1">SUI rates, wage ceilings, minimum wages, and tax incentive info by state</p>
            </a>

            <a href="{{ route('budget-admin.allocations') }}"
               class="block bg-white rounded-lg shadow-sm border border-gray-200 px-5 py-4 hover:border-indigo-300 hover:shadow transition">
                <h3 class="text-base font-semibold text-gray-800">Department Allocations</h3>
                <p class="text-sm text-gray-500 mt-1">Budget percentage allocated to each department at each of 8 budget classes</p>
            </a>

            <a href="{{ route('budget-admin.guild-mappings') }}"
               class="block bg-white rounded-lg shadow-sm border border-gray-200 px-5 py-4 hover:border-indigo-300 hover:shadow transition">
                <h3 class="text-base font-semibold text-gray-800">Guild Tier Mappings</h3>
                <p class="text-sm text-gray-500 mt-1">Which rate tier code each guild uses at each budget class</p>
            </a>

        </div>
    </div>
</x-app-layout>
