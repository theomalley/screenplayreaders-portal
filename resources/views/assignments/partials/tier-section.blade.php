{{-- Shared by assignments/index.blade.php's admin view — one instance per dynamic tier (plus
     one for "no tier assigned"). Expects: $sectionTitle, $sectionAssignments, $isOnboarding (bool). --}}
@php $isOnboarding = $isOnboarding ?? false; @endphp
<div class="mb-6">
    <h3 class="text-xs font-semibold {{ $isOnboarding ? 'text-amber-500' : 'text-gray-400' }} uppercase tracking-wider mb-2 px-1">{{ $sectionTitle }}</h3>
    <div class="bg-white rounded-lg shadow-sm border {{ $isOnboarding ? 'border-amber-200' : 'border-gray-200' }} overflow-hidden" x-data="tableSort()">
        <div class="flex flex-wrap items-center gap-1.5 px-4 py-2 bg-gray-50 border-b border-gray-200">
            <span class="text-[10px] font-medium text-gray-500 uppercase tracking-wide mr-1">Sort:</span>
        @foreach (['date' => 'Date', 'age' => 'Age', 'rush' => 'Rush', 'type' => 'Type', 'rate' => 'Rate', 'status' => 'Status', 'acceptedby' => 'Accepted by'] as $sf => $sl)
            <button type="button" @click="setSort('{{ $sf }}')"
                    :class="sortBy === '{{ $sf }}' ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-500 border-gray-300 hover:bg-gray-100'"
                    class="inline-flex items-center px-2 py-0.5 rounded border text-[11px] transition-colors whitespace-nowrap">
                {{ $sl }}<span x-show="sortBy === '{{ $sf }}'" x-text="sortDir === 'asc' ? ' ↑' : ' ↓'" class="ml-0.5"></span>
            </button>
        @endforeach
        </div>
        <div class="overflow-x-auto">
        <table class="w-full min-w-[600px] divide-y divide-gray-200 text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap w-52">Order Details</th>
                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assignment</th>
                <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap w-44">Accepted by</th>
            </tr>
        </thead>
            <tbody class="bg-white divide-y divide-gray-100" x-ref="sortTbody">
                @foreach ($sectionAssignments as $assignment)
                    @include('assignments.partials.admin-assignment-row')
                @endforeach
            </tbody>
        </table>
        </div>
    </div>
</div>
