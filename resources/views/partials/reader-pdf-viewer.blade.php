{{-- Continuous-scroll PDF viewer UI: header bar (title, go-to-page, search,
     notes, write coverage), search bar, canvas area, selection toolbar, and
     notes side panel. Requires a parent x-data="readerPdfViewer(url, assignmentId, csrfToken)"
     scope. Pass standalone=true to hide the modal close (×) button. --}}
<div class="flex items-center justify-between px-4 py-2 bg-gray-900 shrink-0 gap-4">
    <span class="text-sm text-gray-200 font-medium truncate min-w-0">{{ $assignment->drive_script_filename ?? $assignment->script_title }}</span>
    <div class="flex items-center gap-3 shrink-0">
        <div class="flex items-center gap-2 text-xs text-gray-400">
            <span x-show="loading" x-text="totalPages > 0 ? 'Rendering ' + currentPage + ' of ' + totalPages + '…' : 'Loading…'"></span>
            <span x-show="!loading && totalPages > 0" class="flex items-center gap-1.5">
                Go to page
                <input type="number" min="1" :max="totalPages"
                       @change="scrollToPage($event.target.value)"
                       @keydown.enter.prevent="scrollToPage($event.target.value)"
                       class="w-14 text-center bg-gray-700 border border-gray-600 rounded text-xs text-gray-200 px-1 py-0.5" />
                / <span x-text="totalPages"></span>
            </span>
        </div>
        <button type="button"
                @click="searchOpen = !searchOpen; if (searchOpen) $nextTick(() => $refs.searchInput?.focus())"
                :class="searchOpen ? 'bg-indigo-600 text-white' : 'bg-gray-700 text-gray-300 hover:bg-gray-600'"
                class="flex items-center gap-1.5 px-2 py-1 rounded text-xs font-medium transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5 shrink-0">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
            </svg>
            Search
        </button>
        <button type="button"
                @click="notesOpen = !notesOpen; if (notesOpen && !notesLoaded) loadNotes()"
                :class="notesOpen ? 'bg-indigo-600 text-white' : 'bg-gray-700 text-gray-300 hover:bg-gray-600'"
                class="flex items-center gap-1.5 px-2 py-1 rounded text-xs font-medium transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5 shrink-0">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
            </svg>
            Notes
            <span x-show="notes.length > 0" x-text="'(' + notes.length + ')'" class="text-[10px] opacity-75"></span>
        </button>
        @can('submitCoverage', $assignment)
            <a href="{{ route('coverage.show', $assignment) }}"
               class="flex items-center gap-1.5 px-2 py-1 rounded text-xs font-medium bg-green-600 text-white hover:bg-green-500 transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5 shrink-0">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0zM12 2.25l1.5 1.5M21.75 12h-2.25M12 21.75v-2.25M2.25 12h2.25" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75l1.5 1.5L15 9" />
                </svg>
                Write Coverage
            </a>
        @endcan
        @unless($standalone ?? false)
            <button @click="open = false" type="button"
                    class="text-gray-400 hover:text-white text-2xl leading-none px-1">×</button>
        @endunless
    </div>
</div>
{{-- Search bar row --}}
<div x-show="searchOpen" x-cloak
     class="px-4 py-2.5 bg-gray-800 border-t border-gray-700 shrink-0">
    <div class="flex items-center gap-2">
        <input x-model="searchQuery"
               @input="doSearch()"
               x-ref="searchInput"
               type="search"
               placeholder="Search script…"
               class="flex-1 bg-gray-700 border border-gray-600 rounded px-2.5 py-1.5 text-xs text-gray-200 placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-indigo-400 min-w-0" />
        <button x-show="searchQuery" @click="searchQuery = ''; searchResults = []" type="button"
                class="text-gray-400 hover:text-white text-lg leading-none shrink-0">×</button>
    </div>
    <div x-show="searchResults.length > 0" class="mt-2 flex flex-wrap items-baseline gap-x-2 gap-y-1.5">
        <span class="text-[10px] text-gray-400 shrink-0"
              x-text="searchResults.length + (searchResults.length === 1 ? ' match — page:' : ' matches — pages:')"></span>
        <div class="flex flex-wrap gap-1">
            <template x-for="pg in searchResults" :key="pg">
                <button @click="searchOpen = false; $nextTick(() => scrollToPage(pg))" type="button"
                        class="inline-flex px-2 py-0.5 bg-indigo-600 hover:bg-indigo-500 text-white text-[10px] rounded font-medium transition-colors"
                        x-text="pg"></button>
            </template>
        </div>
    </div>
    <div x-show="!pageTexts.length && searchQuery.trim()"
         class="mt-1.5 text-[10px] text-gray-500 italic">Indexing pages…</div>
    <div x-show="pageTexts.length > 0 && searchQuery.trim() && !searchResults.length"
         class="mt-1.5 text-[10px] text-gray-500 italic">No matches found</div>
</div>
<div class="flex-1 flex min-h-0">
    <div x-ref="canvasWrap" class="flex-1 overflow-auto flex flex-col items-center gap-4 bg-gray-800 py-6 px-4">
        <div x-show="loading && totalPages === 0" class="text-gray-400 text-sm mt-8">Loading…</div>
    </div>
    {{-- Floating selection toolbar (highlight / add to note) --}}
    <div x-show="selectionToolbar.show" x-cloak
         :style="`left: ${selectionToolbar.x}px; top: ${selectionToolbar.y}px`"
         class="fixed z-[60] flex gap-1 bg-gray-900 text-white rounded-md shadow-lg overflow-hidden text-xs border border-gray-700">
        <button type="button" @click="saveHighlight()"
                class="px-2 py-1.5 hover:bg-gray-700 flex items-center gap-1.5 whitespace-nowrap">
            <span class="w-2.5 h-2.5 rounded-sm bg-yellow-400 inline-block"></span> Highlight
        </button>
        <button type="button" @click="addSelectionToNote()"
                class="px-2 py-1.5 hover:bg-gray-700 border-l border-gray-700 whitespace-nowrap">
            Add to Note
        </button>
        <button type="button" @click="highlightAndAddToNote()"
                class="px-2 py-1.5 hover:bg-gray-700 border-l border-gray-700 flex items-center gap-1.5 whitespace-nowrap">
            <span class="w-2.5 h-2.5 rounded-sm bg-yellow-400 inline-block"></span> Both
        </button>
    </div>
    {{-- Reading notes side panel --}}
    <div x-show="notesOpen" x-cloak
         class="w-72 flex flex-col bg-gray-900 border-l border-gray-700 shrink-0">
        <div class="px-3 py-2 border-b border-gray-700 flex items-center justify-between shrink-0">
            <span class="text-sm font-medium text-gray-200">Reading Notes</span>
            <button @click="notesOpen = false" type="button" class="text-gray-400 hover:text-white text-xl leading-none">×</button>
        </div>
        <div class="flex-1 overflow-y-auto px-3 py-3 space-y-2">
            <template x-for="n in notes" :key="n.id">
                <div class="group bg-gray-800 border border-gray-700 rounded px-2.5 py-2">
                    <p class="text-xs text-gray-200 whitespace-pre-wrap leading-snug" x-text="n.body"></p>
                    <div class="flex items-center justify-between mt-1">
                        <p class="text-[10px] text-gray-500">
                            <span x-show="n.page_number" class="font-medium text-gray-400" x-text="'p. ' + n.page_number + ' · '"></span><span x-text="n.created_at"></span>
                        </p>
                        <button @click="deleteNote(n.id)" type="button"
                                class="opacity-0 group-hover:opacity-100 text-red-400 hover:text-red-300 text-[10px] transition-opacity leading-none">Delete</button>
                    </div>
                </div>
            </template>
            <div x-show="notes.length === 0" class="text-xs text-gray-500 text-center py-6 italic">No notes yet</div>
        </div>
        <div class="px-3 py-3 border-t border-gray-700 shrink-0">
            <textarea x-model="noteBody" rows="3" placeholder="Jot a note…"
                      @keydown.ctrl.enter.prevent="addNote()"
                      class="w-full text-xs bg-gray-800 border border-gray-600 rounded px-2 py-1.5 text-gray-200 placeholder-gray-500 resize-none focus:outline-none focus:ring-1 focus:ring-indigo-500"></textarea>
            <div class="flex items-center justify-between mt-1.5">
                <span class="text-[10px] text-gray-500">Ctrl+Enter to save</span>
                <button type="button" @click="addNote()" :disabled="noteSaving || !noteBody.trim()"
                        class="px-2 py-1 text-[10px] bg-indigo-600 text-white rounded hover:bg-indigo-500 disabled:opacity-50 transition-colors"
                        x-text="noteSaving ? 'Saving…' : (currentPage ? 'Add Note (p. ' + currentPage + ')' : 'Add Note')"></button>
            </div>
        </div>
    </div>
</div>
