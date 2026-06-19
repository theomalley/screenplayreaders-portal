{{-- PDF.js text layer + highlight overlay styles, shared by the script viewers in
     assignments/show.blade.php and assignments/index.blade.php. --}}
<style>
    .textLayer {
        position: absolute;
        inset: 0;
        overflow: hidden;
        line-height: 1;
        text-align: initial;
        transform-origin: 0 0;
        z-index: 2;
    }
    .textLayer span,
    .textLayer br {
        color: transparent;
        position: absolute;
        white-space: pre;
        cursor: text;
        transform-origin: 0% 0%;
    }
    .textLayer ::selection {
        background: rgba(99, 102, 241, 0.35);
    }
    .highlight-layer {
        position: absolute;
        inset: 0;
        z-index: 1;
        pointer-events: none;
    }
    .highlight-mark {
        position: absolute;
        background: rgba(250, 204, 21, 0.45);
        mix-blend-mode: multiply;
        cursor: pointer;
        pointer-events: auto;
    }
    .proof-marks-layer {
        position: absolute;
        inset: 0;
        z-index: 1;
        pointer-events: none;
    }
    .proof-mark-strikethrough {
        position: absolute;
        pointer-events: auto;
        cursor: pointer;
    }
    .proof-mark-strikethrough::after {
        content: '';
        position: absolute;
        left: 0;
        right: 0;
        top: 50%;
        border-top: 2px solid red;
    }
    .proof-mark-correction {
        position: absolute;
        color: red;
        font-size: 9px;
        font-weight: bold;
        pointer-events: auto;
        cursor: pointer;
        white-space: nowrap;
        line-height: 1;
    }
    .proof-mark-note {
        position: absolute;
        color: red;
        font-size: 11px;
        font-weight: 600;
        pointer-events: auto;
        cursor: grab;
        line-height: 1.3;
        padding: 2px 4px;
        border: 1px solid transparent;
        border-radius: 2px;
        min-width: 20px;
        min-height: 14px;
        box-sizing: border-box;
        user-select: none;
    }
    .proof-mark-note:hover {
        border-color: rgba(255, 0, 0, 0.4);
    }
    .proof-mark-note.proof-note-selected {
        border-color: red;
        outline: 1px dashed rgba(255, 0, 0, 0.5);
        outline-offset: 1px;
    }
    .proof-mark-note.proof-note-editing {
        cursor: text;
        user-select: text;
        outline: 1px solid red;
        outline-offset: 1px;
    }
    .proof-mark-note.bg-clear { background: transparent; }
    .proof-mark-note.bg-white { background: rgba(255, 255, 255, 0.92); }
    .proof-mark-note.bg-dark  { background: rgba(0, 0, 0, 0.78); color: #ff4444; }
    .proof-note-toolbar {
        position: absolute;
        top: -24px;
        left: 0;
        display: flex;
        gap: 3px;
        align-items: center;
        background: #1f2937;
        border: 1px solid #374151;
        border-radius: 4px;
        padding: 2px 4px;
        z-index: 10;
        white-space: nowrap;
    }
    .proof-note-toolbar button {
        width: 14px;
        height: 14px;
        border-radius: 50%;
        border: 1px solid #6b7280;
        cursor: pointer;
        padding: 0;
    }
    .proof-note-toolbar button.active { border-color: red; box-shadow: 0 0 0 1px red; }
    .proof-note-toolbar .proof-note-delete {
        width: auto;
        height: auto;
        border-radius: 2px;
        font-size: 9px;
        color: #ef4444;
        background: transparent;
        border: none;
        padding: 0 2px;
        cursor: pointer;
    }
    .proof-note-resize {
        position: absolute;
        right: -3px;
        bottom: -3px;
        width: 8px;
        height: 8px;
        background: red;
        cursor: nwse-resize;
        border-radius: 1px;
        pointer-events: auto;
    }
    .proof-mark-arrow,
    .proof-mark-freehand {
        position: absolute;
        inset: 0;
        pointer-events: none;
    }
    .proof-mark-arrow line {
        pointer-events: auto;
        cursor: pointer;
    }
</style>
