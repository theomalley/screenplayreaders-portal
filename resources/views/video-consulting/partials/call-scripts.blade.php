<script>
    document.addEventListener('alpine:init', () => {
        // Purely client-side countdown against the visitor's own clock — informational only,
        // never ends the call or touches Whereby. Ticks every second from start() until the
        // element is removed (clearInterval on x-data teardown isn't needed here since this
        // page is never swapped without a full reload).
        Alpine.data('callCountdown', (startIso, endIso) => ({
            start() {
                this.startAt = startIso ? new Date(startIso) : null;
                this.endAt = endIso ? new Date(endIso) : null;
                this.tick();
                setInterval(() => this.tick(), 1000);
            },
            startAt: null,
            endAt: null,
            display: '',
            label: '',
            stateClass: '',
            tick() {
                const now = new Date();

                if (this.startAt && now < this.startAt) {
                    this.label = 'Starts in';
                    this.display = this.formatDuration(this.startAt - now);
                    this.stateClass = 'call-timer-upcoming';
                    return;
                }

                if (this.endAt && now > this.endAt) {
                    this.label = 'Over by';
                    this.display = this.formatDuration(now - this.endAt);
                    this.stateClass = 'call-timer-over';
                    return;
                }

                if (this.endAt) {
                    this.label = 'Time remaining';
                    this.display = this.formatDuration(this.endAt - now);
                    this.stateClass = 'call-timer-active';
                    return;
                }

                this.label = '';
                this.display = '';
                this.stateClass = '';
            },
            formatDuration(ms) {
                const totalSeconds = Math.max(0, Math.floor(ms / 1000));
                const hours = Math.floor(totalSeconds / 3600);
                const minutes = Math.floor((totalSeconds % 3600) / 60);
                const seconds = totalSeconds % 60;
                const pad = (n) => String(n).padStart(2, '0');

                return hours > 0
                    ? `${hours}:${pad(minutes)}:${pad(seconds)}`
                    : `${pad(minutes)}:${pad(seconds)}`;
            },
        }));

        // Reader's private scratch notes — saved only to this browser's localStorage, never
        // sent to the server. Debounced so we're not writing on every keystroke.
        Alpine.data('callNotes', (assignmentId) => ({
            text: '',
            justSaved: false,
            saveTimer: null,
            storageKey: `sr-call-notes-${assignmentId}`,
            load() {
                try {
                    this.text = localStorage.getItem(this.storageKey) || '';
                } catch (e) {
                    this.text = '';
                }
            },
            save() {
                clearTimeout(this.saveTimer);
                this.saveTimer = setTimeout(() => {
                    try {
                        localStorage.setItem(this.storageKey, this.text);
                        this.justSaved = true;
                        setTimeout(() => { this.justSaved = false; }, 1500);
                    } catch (e) {
                        // localStorage unavailable (private window, blocked storage, etc) —
                        // notes just won't persist; nothing else on the page depends on this.
                    }
                }, 400);
            },
        }));
    });
</script>
