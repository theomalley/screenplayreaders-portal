<div>
    <label class="block text-xs font-medium text-gray-700 mb-1">Partner Name</label>
    <input type="text" name="name" x-model="form.name" required maxlength="255"
           class="block w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
</div>
<div>
    <label class="block text-xs font-medium text-gray-700 mb-1">Partner Page URL</label>
    <input type="url" name="url" x-model="form.url" required maxlength="500"
           placeholder="https://partnersite.com/about"
           class="block w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
    <p class="mt-1 text-xs text-gray-400">The specific page that should contain your link, not just the homepage.</p>
</div>
<div>
    <label class="block text-xs font-medium text-gray-700 mb-1">Check Interval (minutes)</label>
    <div class="flex gap-2 items-center flex-wrap">
        <input type="number" name="check_interval_minutes" x-model.number="form.check_interval_minutes"
               min="5" max="43200" required
               class="w-28 border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
        <span class="text-xs text-gray-400">Common: 60 (hourly) · 1440 (daily) · 10080 (weekly)</span>
    </div>
</div>
<div class="flex items-center gap-2">
    <input type="hidden" name="active" value="0">
    <input type="checkbox" name="active" value="1" id="add_active" x-model="form.active" checked
           class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
    <label for="add_active" class="text-sm text-gray-700">Active (monitoring enabled)</label>
</div>
<div>
    <label class="block text-xs font-medium text-gray-700 mb-1">WooCommerce Coupon Code <span class="text-gray-400">(optional)</span></label>
    <input type="text" name="coupon_code" x-model="form.coupon_code" maxlength="255"
           placeholder="e.g. PARTNER20"
           class="block w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
    <p class="mt-1 text-xs text-gray-400">When set, the coupon is auto-enabled when a backlink is found and auto-disabled when the link goes missing.</p>
</div>
<div>
    <label class="block text-xs font-medium text-gray-700 mb-1">Notes <span class="text-gray-400">(optional)</span></label>
    <textarea name="notes" x-model="form.notes" rows="2" maxlength="1000"
              placeholder="e.g. Listed on their resources page since Jan 2025"
              class="block w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
</div>
