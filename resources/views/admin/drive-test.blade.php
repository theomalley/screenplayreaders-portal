<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Google Drive — Connection Test</h2>
    </x-slot>

    <div class="py-8 max-w-2xl mx-auto px-4">

        @if (session('fileId'))
            <div class="mb-6 p-4 bg-green-50 border border-green-300 rounded text-sm space-y-2">
                <p class="font-semibold text-green-800">Upload succeeded.</p>
                <p><span class="font-medium">File ID:</span> <code class="select-all">{{ session('fileId') }}</code></p>
                <p>
                    <a href="{{ session('viewLink') }}" target="_blank"
                       class="text-blue-600 underline mr-4">View (reader link)</a>
                    <a href="{{ session('dlUrl') }}" target="_blank"
                       class="text-blue-600 underline">Download (admin link)</a>
                </p>
                <p class="text-xs text-gray-500 mt-2">
                    The view link embeds via iframe with no download button1. The download link is admin-only — don't expose it to readers.
                </p>
                <div class="mt-3 border rounded overflow-hidden" style="height:500px;">
                    <iframe src="{{ session('viewLink') }}" class="w-full h-full" frameborder="0" allowfullscreen></iframe>
                </div>
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-4 p-3 bg-red-50 border border-red-300 rounded text-sm text-red-700">
                @foreach ($errors->all() as $error) <p>{{ $error }}</p> @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('admin.drive-test.post') }}" enctype="multipart/form-data"
              class="space-y-4 bg-white p-6 rounded shadow">
            @csrf
            <p class="text-sm text-gray-600">
                Upload any PDF to verify the Google Drive service account connection.
                The file lands in <code>scripts/0/</code> in Drive (test folder).
            </p>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">PDF file</label>
                <input type="file" name="script" accept="application/pdf" required
                       class="block w-full text-sm text-gray-700 border border-gray-300 rounded px-3 py-2">
            </div>
            <button type="submit"
                    class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded hover:bg-indigo-700">
                Upload to Drive
            </button>
        </form>
    </div>
</x-app-layout>
