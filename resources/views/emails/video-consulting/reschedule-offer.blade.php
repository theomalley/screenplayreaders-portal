<!doctype html>
<html>
<body style="font-family: -apple-system, Helvetica, Arial, sans-serif; color: #1f2937; max-width: 560px; margin: 0 auto; padding: 24px;">
    <h2 style="margin: 0 0 16px;">We need to pick a new time for your call</h2>
    <p>Your reader needs to reschedule your {{ $assignment->script_title }} call (order #{{ $assignment->order_number }}). Please pick one of the times below — your original time stays reserved until you do.</p>
    <p style="margin: 24px 0;">
        <a href="{{ $offerUrl }}" style="display: inline-block; background: #4f46e5; color: #fff; text-decoration: none; padding: 12px 20px; border-radius: 6px; font-weight: 600;">
            Choose a new time
        </a>
    </p>
    <p style="color: #6b7280; font-size: 13px;">This link expires in 48 hours. If you don't hear from us, you can also just reply to this email.</p>
    <p style="color: #6b7280; font-size: 13px; margin-top: 32px;">Screenplay Readers</p>
</body>
</html>
