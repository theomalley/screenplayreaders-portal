<div class="call-notes" x-data="callNotes({{ $assignment->id }})" x-init="load()">
    <div class="call-notes-header">
        <label class="call-notes-label" for="call-notes-textarea">Your notes</label>
        <span class="call-notes-saved" x-show="justSaved" x-transition.opacity>Saved</span>
    </div>
    <textarea
        id="call-notes-textarea"
        class="call-notes-textarea"
        x-model="text"
        @input="save()"
        placeholder="Jot down or paste notes to refer to during the call…"
    ></textarea>
    <p class="call-notes-hint">Private to this browser — not saved to the order or shared with anyone.</p>
</div>
