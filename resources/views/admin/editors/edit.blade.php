<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-4">
            <a href="{{ route('team.index') }}" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $user->isAdmin() ? 'Admin' : 'Editor' }} Profile — {{ $user->name }}
            </h2>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                <form id="update-form" method="POST" action="{{ route('admin.editors.update', $user) }}" class="p-6 space-y-5"
                      enctype="multipart/form-data">
                    @csrf
                    @method('PATCH')

                    @if ($errors->any())
                        <div class="bg-red-50 border border-red-200 rounded-lg p-4 text-sm text-red-700">
                            <ul class="list-disc list-inside space-y-0.5">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    {{-- Photo --}}
                    @php
                        $currentPhotoUrl      = $profile?->photo ? asset('storage/' . $profile->photo) : '';
                        $pendingPhotoUrl      = $profile?->photo_pending ? asset('storage/' . $profile->photo_pending) : null;
                        $pendingAboutPhotoUrl = $profile?->about_photo_pending ? asset('storage/' . $profile->about_photo_pending) : null;
                        $pendingBioContent    = $profile?->bio_pending;
                    @endphp
                    <div x-data="{ previewUrl: '{{ $currentPhotoUrl }}' }">
                        <x-input-label value="Photo" />
                        <div class="mt-2 flex items-center gap-4">
                            <div class="relative w-16 h-16 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-700 text-lg font-mono font-semibold shrink-0 overflow-hidden">
                                <img x-show="previewUrl" :src="previewUrl" alt="" class="absolute inset-0 w-full h-full object-cover" />
                                <span x-show="!previewUrl">{{ $profile?->initials ?? '?' }}</span>
                            </div>
                            <div class="flex-1">
                                <input type="file" name="photo" id="photo" accept="image/*"
                                       @change="if ($event.target.files[0]) {
                                           const r = new FileReader();
                                           r.onload = e => previewUrl = e.target.result;
                                           r.readAsDataURL($event.target.files[0]);
                                       }"
                                       class="block w-full text-sm text-gray-500
                                              file:mr-3 file:py-1.5 file:px-3
                                              file:rounded file:border file:border-gray-300
                                              file:text-xs file:font-medium file:text-gray-700
                                              file:bg-white hover:file:bg-gray-50 file:cursor-pointer
                                              cursor-pointer" />
                                <p class="mt-1 text-xs text-gray-400">JPEG or PNG, max 4 MB. Leave blank to keep current photo.</p>
                            </div>
                        </div>
                        <x-input-error :messages="$errors->get('photo')" class="mt-1" />
                    </div>

                    @if ($pendingAboutPhotoUrl)
                        @include('partials.approval-panel', [
                            'type'        => 'about-photo',
                            'approveUrl'  => route('admin.approvals.about-photo.approve', $user),
                            'rejectUrl'   => route('admin.approvals.about-photo.reject', $user),
                            'preview'     => '<img src="'.e($pendingAboutPhotoUrl).'" class="w-12 h-12 rounded-full object-cover shrink-0" alt="Pending about photo" />',
                            'label'       => 'Pending about page photo waiting for approval.',
                        ])
                    @endif

                    {{-- Initials --}}
                    <div>
                        <x-input-label for="initials" value="Initials" />
                        <div class="mt-1 flex items-center gap-3">
                            <x-text-input
                                id="initials" name="initials" type="text"
                                class="block w-24 uppercase tracking-widest font-mono text-center text-lg"
                                value="{{ old('initials', $profile?->initials) }}"
                                maxlength="3"
                                placeholder="AB"
                                oninput="this.value = this.value.toUpperCase()"
                                required />
                            <p class="text-xs text-gray-400">1–3 uppercase letters</p>
                        </div>
                        <x-input-error :messages="$errors->get('initials')" class="mt-1" />
                    </div>

                    {{-- Name — fall back to splitting users.name if profile fields not yet set --}}
                    @php
                        $nameParts    = explode(' ', $user->name, 2);
                        $fallbackFirst = $nameParts[0] ?? '';
                        $fallbackLast  = $nameParts[1] ?? '';
                    @endphp
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <x-input-label for="first_name" value="First Name" />
                            <x-text-input id="first_name" name="first_name" type="text"
                                class="mt-1 block w-full"
                                value="{{ old('first_name', $profile?->first_name ?? $fallbackFirst) }}"
                                required />
                            <x-input-error :messages="$errors->get('first_name')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="last_name" value="Last Name" />
                            <x-text-input id="last_name" name="last_name" type="text"
                                class="mt-1 block w-full"
                                value="{{ old('last_name', $profile?->last_name ?? $fallbackLast) }}"
                                required />
                            <x-input-error :messages="$errors->get('last_name')" class="mt-1" />
                        </div>
                    </div>

                    <div>
                        <x-input-label for="title" value="Title" />
                        <x-text-input id="title" name="title" type="text"
                            class="mt-1 block w-full"
                            value="{{ old('title', $profile?->title) }}"
                            placeholder="e.g. Senior Editor, Managing Editor" />
                        <x-input-error :messages="$errors->get('title')" class="mt-1" />
                    </div>

                    {{-- PayPal --}}
                    <div>
                        <x-input-label for="paypal_email" value="PayPal Email" />
                        <x-text-input id="paypal_email" name="paypal_email" type="email"
                            class="mt-1 block w-full"
                            value="{{ old('paypal_email', $profile?->paypal_email) }}"
                            placeholder="optional" />
                        <x-input-error :messages="$errors->get('paypal_email')" class="mt-1" />
                    </div>

                    {{-- Availability --}}
                    <div class="pt-4 border-t border-gray-100 space-y-4">
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Availability</p>

                        <div>
                            <x-input-label value="Status" />
                            <div class="mt-2 flex gap-6">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="availability" value="available"
                                           {{ old('availability', $profile?->availability ?? 'available') === 'available' ? 'checked' : '' }}
                                           class="text-green-600 focus:ring-green-500" />
                                    <span class="text-sm font-medium text-green-700">Available</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="availability" value="unavailable"
                                           {{ old('availability', $profile?->availability ?? 'available') === 'unavailable' ? 'checked' : '' }}
                                           class="text-red-600 focus:ring-red-500" />
                                    <span class="text-sm font-medium text-red-700">Unavailable</span>
                                </label>
                            </div>
                            <x-input-error :messages="$errors->get('availability')" class="mt-1" />
                        </div>

                        <div>
                            <x-input-label for="availability_message" value="Availability Note" />
                            <textarea id="availability_message" name="availability_message"
                                      rows="2"
                                      class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                      placeholder="Optional — e.g. back Jan 15, on vacation, etc.">{{ old('availability_message', $profile?->availability_message) }}</textarea>
                            <p class="mt-1 text-xs text-gray-400">Visible to admin only.</p>
                            <x-input-error :messages="$errors->get('availability_message')" class="mt-1" />
                        </div>

                        <div>
                            <x-input-label for="upload_warning" value="Upload Form Warning" />
                            <textarea id="upload_warning" name="upload_warning"
                                      rows="3"
                                      class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                      placeholder="Optional — shown to customers on the upload form when they select this editor.">{{ old('upload_warning', $profile?->upload_warning) }}</textarea>
                            <p class="mt-1 text-xs text-gray-400">Leave blank for no warning.</p>
                            <x-input-error :messages="$errors->get('upload_warning')" class="mt-1" />
                        </div>

                        <div>
                            <x-input-label for="bio" value="Website Bio" />
                            <div x-data="{
                                wrap(b,a){const t=this.$refs.ta,s=t.selectionStart,e=t.selectionEnd,v=t.value.slice(s,e);t.value=t.value.slice(0,s)+b+v+a+t.value.slice(e);t.selectionStart=s+b.length;t.selectionEnd=s+b.length+v.length;t.focus()},
                                link(){const u=prompt('URL (include https://)');if(u)this.wrap('<a href=\''+u+'\'>', '</a>')}
                            }">
                                <div class="flex items-center gap-1 mt-1 mb-1">
                                    <button type="button" @click="wrap('<b>','</b>')" class="px-2 py-0.5 text-xs font-bold border border-gray-300 rounded bg-white hover:bg-gray-100 leading-5">B</button>
                                    <button type="button" @click="wrap('<i>','</i>')" class="px-2 py-0.5 text-xs italic border border-gray-300 rounded bg-white hover:bg-gray-100 leading-5">I</button>
                                    <button type="button" @click="link()" class="px-2 py-0.5 text-xs border border-gray-300 rounded bg-white hover:bg-gray-100 leading-5">Link</button>
                                </div>
                                <textarea x-ref="ta" id="bio" name="bio"
                                          rows="5"
                                          class="block w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                          maxlength="5000"
                                          placeholder="Displayed on the public website. HTML is supported.">{{ old('bio', $profile?->bio) }}</textarea>
                            </div>
                            <p class="mt-1 text-xs text-gray-400">HTML allowed — &lt;b&gt;, &lt;i&gt;, &lt;a href=""&gt;, etc. Max 5000 characters.</p>
                            <x-input-error :messages="$errors->get('bio')" class="mt-1" />
                            <div class="mt-2 px-3 py-2 bg-gray-50 border border-gray-200 rounded text-xs text-gray-500 space-y-1">
                                <p class="font-medium text-gray-600">Website shortcodes</p>
                                <p><code class="bg-white px-1 rounded border border-gray-200 select-all">[sr_staff_photo id="{{ $user->id }}" float="left" width="200" height="200" gap="1.5em"]</code></p>
                                <p><code class="bg-white px-1 rounded border border-gray-200 select-all">[sr_staff_bio id="{{ $user->id }}"]</code></p>
                                <p class="text-gray-400 italic">Changes take up to 30 seconds to appear on the website due to caching.</p>
                            </div>
                            @if ($pendingBioContent)
                                @include('partials.approval-panel', [
                                    'type'        => 'bio',
                                    'approveUrl'  => route('admin.approvals.bio.approve', $user),
                                    'rejectUrl'   => route('admin.approvals.bio.reject', $user),
                                    'preview'     => '<div class="text-xs text-gray-600 italic line-clamp-3">'.e(strip_tags($pendingBioContent)).'</div>',
                                    'label'       => 'Pending bio waiting for approval:',
                                ])
                            @endif
                        </div>

                        <div>
                            <x-input-label for="custom_message" value="Staff Card Message" />
                            <textarea id="custom_message" name="custom_message"
                                      rows="2"
                                      class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                      maxlength="200"
                                      placeholder="Optional — short message shown when readers click this editor's icon. Overrides bio if set.">{{ old('custom_message', $profile?->custom_message) }}</textarea>
                            <p class="mt-1 text-xs text-gray-400">Up to 200 characters. Plain text only. If set, shown instead of bio when readers click this editor's staff icon.</p>
                            <x-input-error :messages="$errors->get('custom_message')" class="mt-1" />
                        </div>

                        <div>
                            <x-input-label for="timezone" value="Timezone" />
                            <x-timezone-select name="timezone" :selected="old('timezone', $profile?->timezone ?? 'UTC')" class="mt-1 block w-full" />
                            <p class="mt-1 text-xs text-gray-400">Used for displaying and entering assignment dates.</p>
                            <x-input-error :messages="$errors->get('timezone')" class="mt-1" />
                        </div>
                    </div>

                    {{-- Account --}}
                    <div class="pt-4 border-t border-gray-100 space-y-4">
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Account</p>

                        <div>
                            <x-input-label for="email" value="Email" />
                            <x-text-input id="email" name="email" type="email"
                                class="mt-1 block w-full"
                                value="{{ old('email', $user->email) }}"
                                required />
                            <x-input-error :messages="$errors->get('email')" class="mt-1" />
                        </div>

                        <div>
                            <x-input-label for="password" value="New Password" />
                            <x-text-input id="password" name="password" type="password"
                                class="mt-1 block w-full"
                                placeholder="Leave blank to keep current password" />
                            <x-input-error :messages="$errors->get('password')" class="mt-1" />
                        </div>

                        <div>
                            <x-input-label for="password_confirmation" value="Confirm New Password" />
                            <x-text-input id="password_confirmation" name="password_confirmation" type="password"
                                class="mt-1 block w-full"
                                placeholder="Leave blank to keep current password" />
                        </div>
                    </div>

                </form>

                @if(auth()->user()->isAdmin() && $user->isEditor())
                <div class="px-6 py-5 border-t border-gray-100">
                    <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Admin Settings</div>
                    <div>
                        <x-input-label for="editor_role" value="Role" />
                        <p class="mt-0.5 text-xs text-gray-500 mb-1">Changing to Reader moves this user to the readers list.</p>
                        <select id="editor_role" name="role" form="update-form"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                            <option value="editor" selected>Editor</option>
                            <option value="reader">Reader</option>
                        </select>
                    </div>
                </div>
                @endif

                <div class="flex items-center justify-between px-6 py-4 border-t border-gray-100">
                    @if($user->isEditor() && auth()->user()->isAdmin())
                    <form method="POST" action="{{ route('admin.editors.destroy', $user) }}"
                          onsubmit="return confirm('Permanently delete this editor? This cannot be undone.')">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                                class="inline-flex items-center px-3 py-1.5 bg-white border border-red-300 rounded text-xs font-medium text-red-600 hover:bg-red-50 transition">
                            Delete Editor
                        </button>
                    </form>
                    @else
                    <div></div>
                    @endif
                    <div class="flex items-center gap-3">
                        <a href="{{ route('team.index') }}"
                           class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
                        <x-primary-button form="update-form">Save Profile</x-primary-button>
                    </div>
                </div>
            </div>
        {{-- ── EDITOR RATES ── (editors only) --}}
        @if($user->isEditor())
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-5 py-4 bg-gray-50 border-b border-gray-200">
                <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Editor Rates</h3>
                <p class="text-xs text-gray-400 mt-0.5">Commission rate and weekly flat pay for this editor.</p>
            </div>
            <form method="POST" action="{{ route('admin.editors.updateRates', $user) }}" class="p-5 space-y-4">
                @csrf
                @method('PATCH')
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="editor_commission" value="Commission Rate" />
                        <div class="mt-1 flex items-center gap-1">
                            <x-text-input id="editor_commission" name="editor_commission" type="number"
                                class="block w-24 text-right"
                                value="{{ old('editor_commission', $profile?->editor_commission) }}"
                                min="0" max="100" step="0.01"
                                placeholder="0.00" />
                            <span class="text-gray-400 text-sm">%</span>
                        </div>
                        <x-input-error :messages="$errors->get('editor_commission')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="editor_weekly_flat" value="Weekly Flat Pay" />
                        <div class="mt-1 flex items-center gap-1">
                            <span class="text-gray-400 text-sm">$</span>
                            <x-text-input id="editor_weekly_flat" name="editor_weekly_flat" type="number"
                                class="block w-28 text-right"
                                value="{{ old('editor_weekly_flat', $profile?->editor_weekly_flat) }}"
                                min="0" max="9999.99" step="0.01"
                                placeholder="0.00" />
                        </div>
                        <x-input-error :messages="$errors->get('editor_weekly_flat')" class="mt-1" />
                    </div>
                </div>
                <div class="flex justify-end">
                    <x-primary-button>Save Rates</x-primary-button>
                </div>
            </form>
        </div>

        {{-- ── COMMISSION CONFIG ── (editors only) --}}
        @php $commissionConfig = $profile?->productCommissionsKeyed() ?? collect(); @endphp
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-5 py-4 bg-gray-50 border-b border-gray-200">
                <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Commission Config</h3>
                <p class="text-xs text-gray-400 mt-0.5">
                    Toggle which products earn commission and set a custom fixed commission amount per occurrence.
                    Leave blank to use this editor's commission rate.
                </p>
            </div>
            <form method="POST" action="{{ route('admin.editors.commissions', $user) }}">
                @csrf
                @method('PATCH')
                <table class="min-w-full divide-y divide-gray-100 text-sm">
                    <thead class="bg-gray-50 text-xs font-medium text-gray-500 uppercase tracking-wide">
                        <tr>
                            <th class="px-5 py-2 text-left">Product / Service</th>
                            <th class="px-4 py-2 text-center">Earns Commission</th>
                            <th class="px-4 py-2 text-right">Custom Commission</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach(\App\Models\EditorProductCommission::PRODUCTS as $productId => $product)
                        @php
                            $existing = $commissionConfig->get($productId);
                            $enabled  = $existing ? $existing->commission_enabled : $product['commission'];
                            $custom   = $existing?->custom_amount;
                        @endphp
                        <tr class="hover:bg-gray-50 {{ !$enabled ? 'opacity-50' : '' }}">
                            <td class="px-5 py-2 text-gray-700">{{ $product['label'] }}</td>
                            <td class="px-4 py-2 text-center">
                                <input type="checkbox"
                                    name="commissions[{{ $productId }}][enabled]"
                                    value="1"
                                    {{ $enabled ? 'checked' : '' }}
                                    class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            </td>
                            <td class="px-4 py-2 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <span class="text-gray-400 text-xs">$</span>
                                    <input type="number" step="0.01" min="0"
                                        name="commissions[{{ $productId }}][amount]"
                                        value="{{ $custom !== null ? number_format((float)$custom, 2) : '' }}"
                                        placeholder="global %"
                                        class="w-24 text-right text-sm border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="flex justify-end px-5 py-4 border-t border-gray-100">
                    <x-primary-button>Save Commission Config</x-primary-button>
                </div>
            </form>
        </div>
        @endif

        </div>
    </div>
</x-app-layout>
