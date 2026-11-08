<!doctype html>
<html>
<body style="font-family: -apple-system, Helvetica, Arial, sans-serif; color: #1f2937; max-width: 560px; margin: 0 auto; padding: 24px;">
    <h2 style="margin: 0 0 16px;">Your call link is ready</h2>
    <p>Your video consulting call (order #{{ $assignment->order_number }}) is scheduled for:</p>
    <p style="font-size: 18px; font-weight: 600; margin: 16px 0;">
        {{ $assignment->videoConsultingHold?->slot_start?->format('l, F j, Y \a\t g:i A T') }}
    </p>
    <p>
        <a href="{{ $assignment->whereby_room_url }}" style="display: inline-block; background: #1f2937; color: #fff; padding: 12px 20px; border-radius: 6px; text-decoration: none;">
            Join Call
        </a>
    </p>
    <p style="color: #6b7280; font-size: 13px;">If the button doesn't work, copy this link: {{ $assignment->whereby_room_url }}</p>
    <p style="color: #6b7280; font-size: 13px; margin-top: 32px;">Screenplay Readers</p>
</body>
</html>
