<!doctype html>
<html>
<body style="font-family: -apple-system, Helvetica, Arial, sans-serif; color: #1f2937; max-width: 560px; margin: 0 auto; padding: 24px;">
    <h2 style="margin: 0 0 16px;">Your video consulting call is booked</h2>
    <p>Order #{{ $assignment->order_number }} is confirmed for:</p>
    <p style="font-size: 18px; font-weight: 600; margin: 16px 0;">
        {{ $assignment->videoConsultingHold?->slot_start?->format('l, F j, Y \a\t g:i A T') }}
    </p>
    <p>We'll email you the call link before your session.</p>
    <p style="color: #6b7280; font-size: 13px; margin-top: 32px;">Screenplay Readers</p>
</body>
</html>
