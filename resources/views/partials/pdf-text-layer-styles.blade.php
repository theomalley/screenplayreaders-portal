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
</style>
