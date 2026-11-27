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
    // The room's own built-in chrome, decluttered — leaveButton stays on deliberately: the
    // reader (or customer) ends the call themselves, nothing on this page does it for them.
    $embedRoomUrl = $joinUrl ? $joinUrl . '&people=off&logo=off&leaveButton=on' : null;

    // "With {first name}" banner, shown on both sides. $counterpart can be a real name or a
    // fallback phrase ("the customer" / "your reader") from the controller — only take the
    // first token when it looks like an actual name, so the fallback still reads naturally.
    $counterpartTrimmed = trim((string) $counterpart);
    $counterpartFirstName = ($counterpartTrimmed !== '' && ! str_starts_with($counterpartTrimmed, 'the ') && ! str_starts_with($counterpartTrimmed, 'your '))
        ? explode(' ', $counterpartTrimmed)[0]
        : $counterpartTrimmed;
@endphp

@if ($embedRoomUrl)
    <script type="module" src="https://cdn.srv.whereby.com/embed/v3-embed.js"></script>
@endif

<div class="call-card">
    <h1 class="call-title">{{ $assignment->script_title }}</h1>

    @if ($counterpartFirstName !== '')
        <p class="call-with">With {{ $counterpartFirstName }}</p>
    @endif
    @if ($slotStart)
        <p class="call-meta">{{ $slotStart->format('l, F j, Y \a\t g:i A T') }}</p>
    @endif
    @if ($durationMinutes)
        <p class="call-meta">{{ $durationMinutes }} minutes</p>
    @endif

    @if ($slotStart && $slotEnd)
        <div
            class="call-timer"
            x-data="callCountdown('{{ $slotStart->toIso8601String() }}', '{{ $slotEnd->toIso8601String() }}')"
            x-init="start()"
            :class="stateClass"
        >
            <div class="call-timer-value" x-text="display"></div>
            <div class="call-timer-label" x-text="label"></div>
        </div>
    @endif

    @if ($embedRoomUrl)
        <whereby-embed room="{{ $embedRoomUrl }}" class="call-embed"></whereby-embed>
    @else
        <p class="call-not-ready">
            {{ $callPageSettings['call_page_not_ready_text'] }}
        </p>
    @endif

    <p class="call-consent">
        {{ $callPageSettings['call_page_consent_text'] }}
    </p>
</div>
