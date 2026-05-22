<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Permissions</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-5">

            @if(session('success'))
                <div class="bg-green-50 border border-green-200 rounded-lg px-4 py-3 text-sm text-green-700">
                    {{ session('success') }}
                </div>
            @endif

            <p class="text-sm text-gray-500">
                Controls which roles can access each feature. Admin is always granted all permissions and cannot be changed.
            </p>

            <form method="POST" action="{{ route('admin.permissions.update') }}">
                @csrf

                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide w-1/3">Feature</th>
                                @foreach(\App\Support\Permission::ROLES as $role)
                                    <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide whitespace-nowrap
                                        {{ $role === 'admin' ? 'text-indigo-400' : 'text-gray-500' }}">
                                        {{ ucfirst($role) }}
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach(\App\Support\Permission::FEATURES as $feature => $label)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-5 py-3 text-gray-700">{{ $label }}</td>
                                    @foreach(\App\Support\Permission::ROLES as $role)
                                        @php
                                            $checked  = $grid[$feature][$role] ?? false;
                                            $isAdmin  = $role === 'admin';
                                            $inputName = 'perm_' . $role . '_' . str_replace('.', '_', $feature);
                                        @endphp
                                        <td class="px-4 py-3 text-center">
                                            @if($isAdmin)
                                                {{-- Admin always granted — locked visual --}}
                                                <span title="Admin always has access"
                                                      class="inline-flex items-center justify-center w-5 h-5 rounded bg-indigo-100 text-indigo-500">
                                                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                    </svg>
                                                </span>
                                            @else
                                                <input type="checkbox"
                                                       name="{{ $inputName }}"
                                                       {{ $checked ? 'checked' : '' }}
                                                       class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500 cursor-pointer">
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="flex justify-end pt-2">
                    <x-primary-button>Save Permissions</x-primary-button>
                </div>
            </form>

        </div>
    </div>
</x-app-layout>
