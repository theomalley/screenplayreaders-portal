<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Followup Questions — Screenplay Readers</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen py-10">
<div class="max-w-2xl mx-auto px-4">

    {{-- Header --}}
    <div class="mb-8 text-center">
        <h1 class="text-2xl font-semibold text-gray-800">Followup Questions</h1>
        <p class="mt-2 text-sm text-gray-500">Order #{{ $followupToken->order_number }}</p>
    </div>

    @if (session('submitted'))
        <div class="mb-6 px-4 py-3 bg-green-50 border border-green-200 rounded-lg text-sm text-green-800">
            Your questions have been submitted. Our team will review them before forwarding to your reader.
        </div>
    @endif

    <form method="POST" action="{{ route('followup.submit', $token) }}" class="space-y-6">
        @csrf

        @foreach ($slots as $slot)
            <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-5">
                <div class="mb-3">
                    <p class="text-sm font-semibold text-gray-800">
                        Your questions for reader <span class="font-mono text-indigo-600">{{ $slot['initials'] }}</span>
                        <span class="ml-1 text-gray-400 font-normal">({{ $slot['type_label'] }})</span>
                    </p>
                </div>

                @if ($slot['locked'])
                    <div class="px-3 py-2 bg-gray-50 border border-gray-200 rounded text-sm text-gray-500 italic">
                        {{ $slot['existing_text'] }}
                    </div>
                    <p class="mt-2 text-xs text-amber-600">These questions have already been sent to your reader and can no longer be edited.</p>
                    {{-- Keep a hidden field so the slot is submitted but ignored server-side --}}
                    <input type="hidden" name="questions[{{ $slot['assignment_id'] }}]" value="" />
                @else
                    <textarea
                        name="questions[{{ $slot['assignment_id'] }}]"
                        rows="5"
                        maxlength="3000"
                        placeholder="Type your questions here…"
                        class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-400 resize-y"
                    >{{ old('questions.'.$slot['assignment_id'], $slot['existing_text']) }}</textarea>
                    @error('questions.'.$slot['assignment_id'])
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                @endif
            </div>
        @endforeach

        @php $anyEditable = collect($slots)->contains(fn($s) => ! $s['locked']); @endphp

        @if ($anyEditable)
            <div class="flex justify-end">
                <button type="submit"
                        class="inline-flex items-center px-5 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-md hover:bg-indigo-700 transition">
                    Submit Questions
                </button>
            </div>
        @endif

    </form>

    <p class="mt-8 text-center text-xs text-gray-400">
        Screenplay Readers · Questions are reviewed by our team before being forwarded to your reader.
    </p>

</div>
</body>
</html>
