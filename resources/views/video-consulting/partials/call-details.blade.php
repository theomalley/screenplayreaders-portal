@php
    $hold = $assignment->videoConsultingHold;
    $slotStart = $hold?->slot_start;
    $slotEnd = $hold?->slot_end;
    $durationMinutes = $slotStart && $slotEnd ? $slotStart->diffInMinutes($slotEnd) : null;
@endphp

<div class="call-card">
    <h1 class="call-title">{{ $assignment->script_title }}</h1>

    @if ($slotStart)
        <p class="call-meta">{{ $slotStart->format('l, F j, Y \a\t g:i A T') }}</p>
    @endif
    @if ($durationMinutes)
        <p class="call-meta">{{ $durationMinutes }} minutes with {{ $counterpart }}</p>
    @endif

    @if ($assignment->whereby_room_url)
        <a href="{{ $assignment->whereby_room_url }}" target="_blank" rel="noopener" class="call-join-btn">
            Join Call ↗
        </a>
    @else
        <p class="call-not-ready">
            Your room isn't set up yet — check back closer to your call time.
        </p>
    @endif

    <p class="call-consent">
        This call may be recorded for quality purposes with both parties' consent, obtained at
        booking.
    </p>
</div>
