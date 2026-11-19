@php
    $hold = $assignment->videoConsultingHold;
    $slotStart = $hold?->slot_start;
    $slotEnd = $hold?->slot_end;
    $durationMinutes = $slotStart && $slotEnd ? $slotStart->diffInMinutes($slotEnd) : null;
    // Audio-only is the reader's own current profile setting, read live (not snapshotted at
    // booking time) — cameraAccess=off fully disables the camera for both parties (the
    // browser never even requests permission), not just starting muted/toggleable.
    $audioOnly = ($assignment->assignedReader?->readerProfile?->video_consulting_call_mode ?? 'video') === 'audio';
    $joinUrl = $assignment->whereby_room_url
        ? $assignment->whereby_room_url . (str_contains($assignment->whereby_room_url, '?') ? '&' : '?') . 'chat=off' . ($audioOnly ? '&cameraAccess=off' : '')
        : null;
@endphp

<div class="call-card">
    <h1 class="call-title">{{ $assignment->script_title }}</h1>

    @if ($slotStart)
        <p class="call-meta">{{ $slotStart->format('l, F j, Y \a\t g:i A T') }}</p>
    @endif
    @if ($durationMinutes)
        <p class="call-meta">{{ $durationMinutes }} minutes with {{ $counterpart }}</p>
    @endif

    @if ($joinUrl)
        <a href="{{ $joinUrl }}" target="_blank" rel="noopener" class="call-join-btn">
            Join Call ↗
        </a>
    @else
        <p class="call-not-ready">
            {{ $callPageSettings['call_page_not_ready_text'] }}
        </p>
    @endif

    <p class="call-consent">
        {{ $callPageSettings['call_page_consent_text'] }}
    </p>
</div>
