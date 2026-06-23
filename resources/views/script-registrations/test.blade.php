<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Script Registration</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">

            @if($errors->any())
                <div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-800 rounded-md text-sm">
                    {{ $errors->first() }}
                </div>
            @endif
            @if(session('success'))
                <div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-800 rounded-md text-sm">
                    {{ session('success') }}
                </div>
            @endif

            @if($result)
                <div class="mb-6 bg-amber-50 border border-amber-200 rounded-lg p-5">
                    <h3 class="text-sm font-semibold text-amber-800 uppercase tracking-wider mb-3">Test Registration Queued</h3>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 text-sm">
                        <div>
                            <span class="text-amber-600 text-xs uppercase">Registration ID</span>
                            <div class="font-mono font-medium text-amber-900">{{ $result['registration_id'] }}</div>
                        </div>
                        <div>
                            <span class="text-amber-600 text-xs uppercase">Variation</span>
                            <div class="font-medium text-amber-900">{{ $result['variation'] }}</div>
                        </div>
                        <div>
                            <span class="text-amber-600 text-xs uppercase">Delivering to</span>
                            <div class="font-medium text-amber-900">{{ $result['email'] }}</div>
                        </div>
                    </div>
                    @if($result['unlimited_url'])
                        <div class="mt-3 pt-3 border-t border-amber-200" x-data="{ copied: false }">
                            <span class="text-amber-600 text-xs uppercase">Unlimited Registration URL</span>
                            <div class="flex items-center gap-2 mt-1">
                                <input type="text" readonly value="{{ $result['unlimited_url'] }}"
                                    class="flex-1 text-xs font-mono bg-amber-100 border-amber-300 rounded text-amber-900 py-1">
                                <button type="button"
                                    @click="navigator.clipboard.writeText(@js($result['unlimited_url'])).then(() => { copied = true; setTimeout(() => copied = false, 2000) })"
                                    class="px-2 py-1 text-xs font-semibold text-amber-700 bg-amber-200 rounded hover:bg-amber-300 transition">
                                    <span x-show="!copied">Copy</span>
                                    <span x-show="copied" x-cloak>Copied!</span>
                                </button>
                            </div>
                        </div>
                    @endif
                    <div class="mt-3 pt-3 border-t border-amber-200">
                        <a href="{{ route('script-registrations.show', $result['id']) }}"
                           class="text-sm text-amber-800 font-medium hover:text-amber-900 underline">
                            View registration &rarr;
                        </a>
                    </div>
                </div>
            @endif

            <form method="POST" action="{{ route('script-registrations.test.run') }}"
                  x-data="{
                      randomize() {
                          const titles = ['The Last Screenplay', 'Midnight at Dawn', 'Crimson Tide II', 'Untitled Horror', 'Love in the Time of AI', 'The Silent Witness', 'Parallel Worlds'];
                          const firsts = ['James', 'Sarah', 'Michael', 'Emily', 'David', 'Olivia', 'Robert', 'Sophia'];
                          const lasts = ['Johnson', 'Williams', 'Garcia', 'Miller', 'Davis', 'Anderson', 'Taylor', 'Wilson'];
                          this.$refs.title.value = titles[Math.floor(Math.random() * titles.length)];
                          this.$refs.page_count.value = Math.floor(Math.random() * 140) + 20;
                          this.$refs.author_first.value = firsts[Math.floor(Math.random() * firsts.length)];
                          this.$refs.author_last.value = lasts[Math.floor(Math.random() * lasts.length)];
                          const typeOpts = this.$refs.type_of_work.options;
                          this.$refs.type_of_work.selectedIndex = Math.floor(Math.random() * (typeOpts.length - 1)) + 1;
                          const varOpts = this.$refs.variation_id.options;
                          this.$refs.variation_id.selectedIndex = Math.floor(Math.random() * (varOpts.length - 1)) + 1;
                      }
                  }">
                @csrf

                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-5 py-3 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wider">Test Registration</h3>
                        <button type="button" @click="randomize()"
                                class="px-3 py-1 text-xs font-semibold text-indigo-700 bg-indigo-100 rounded-full hover:bg-indigo-200 transition">
                            Randomize
                        </button>
                    </div>

                    <div class="px-5 py-4 space-y-4">

                        <p class="text-sm text-gray-500">
                            Creates a test <code class="text-xs bg-gray-100 px-1 py-0.5 rounded">ScriptRegistration</code> record
                            and dispatches the full pipeline: certificate PDF generation + email delivery.
                            Uses placeholder address data. The certificate will be generated in Drive and emailed to the address below.
                        </p>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="sm:col-span-2">
                                <label for="test_email" class="block text-sm font-medium text-gray-700">Deliver to Email <span class="text-red-500">*</span></label>
                                <input type="email" name="test_email" id="test_email" required
                                    value="{{ old('test_email', auth()->user()->email) }}"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500">
                            </div>

                            <div>
                                <label for="variation_id" class="block text-sm font-medium text-gray-700">Variation <span class="text-red-500">*</span></label>
                                <select name="variation_id" id="variation_id" x-ref="variation_id" required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500">
                                    <option value="">Select…</option>
                                    @foreach($variations as $id => $label)
                                        <option value="{{ $id }}" @selected(old('variation_id') == $id)>{{ $label }} ({{ $id }})</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label for="type_of_work" class="block text-sm font-medium text-gray-700">Type of Work <span class="text-red-500">*</span></label>
                                <select name="type_of_work" id="type_of_work" x-ref="type_of_work" required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500">
                                    <option value="">Select…</option>
                                    @foreach($workTypes as $type)
                                        <option value="{{ $type }}" @selected(old('type_of_work') === $type)>{{ $type }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="sm:col-span-2">
                                <label for="title" class="block text-sm font-medium text-gray-700">Script Title <span class="text-red-500">*</span></label>
                                <input type="text" name="title" id="title" x-ref="title" required
                                    value="{{ old('title') }}"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500">
                            </div>

                            <div>
                                <label for="page_count" class="block text-sm font-medium text-gray-700">Page Count <span class="text-red-500">*</span></label>
                                <input type="number" name="page_count" id="page_count" x-ref="page_count" required min="1" max="9999"
                                    value="{{ old('page_count') }}"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500">
                            </div>

                            <div class="hidden sm:block"></div>

                            <div>
                                <label for="author_first" class="block text-sm font-medium text-gray-700">Author First <span class="text-red-500">*</span></label>
                                <input type="text" name="author_first" id="author_first" x-ref="author_first" required
                                    value="{{ old('author_first') }}"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500">
                            </div>

                            <div>
                                <label for="author_last" class="block text-sm font-medium text-gray-700">Author Last <span class="text-red-500">*</span></label>
                                <input type="text" name="author_last" id="author_last" x-ref="author_last" required
                                    value="{{ old('author_last') }}"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                        </div>

                        <div class="pt-2 text-xs text-gray-400">
                            Address will use placeholder data (123 Test Street, Los Angeles, CA 90001).
                        </div>

                    </div>

                    <div class="px-5 py-3 bg-gray-50 border-t border-gray-200 flex justify-end gap-3">
                        <button type="submit"
                            class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700 transition">
                            Create Test Registration &amp; Send
                        </button>
                    </div>
                </div>
            </form>

        </div>
    </div>
</x-app-layout>
