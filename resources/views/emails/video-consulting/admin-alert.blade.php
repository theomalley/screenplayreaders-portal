<!doctype html>
<html>
<body style="font-family: -apple-system, Helvetica, Arial, sans-serif; color: #1f2937; max-width: 560px; margin: 0 auto; padding: 24px;">
    <h2 style="margin: 0 0 16px;">Video Consulting — {{ $heading }}</h2>
    <p>{{ $message }}</p>
    <table style="font-size: 14px; margin: 16px 0; border-collapse: collapse;">
        <tr><td style="padding: 2px 12px 2px 0; color: #6b7280;">Order</td><td>#{{ $assignment->order_number }}</td></tr>
        <tr><td style="padding: 2px 12px 2px 0; color: #6b7280;">Script</td><td>{{ $assignment->script_title }}</td></tr>
        <tr><td style="padding: 2px 12px 2px 0; color: #6b7280;">Reader</td><td>{{ $assignment->assignedReader?->readerProfile?->initials ?? $assignment->assignedReader?->name ?? '—' }}</td></tr>
    </table>
    <p>
        <a href="{{ route('assignments.show', $assignment) }}" style="color: #4f46e5;">Review in the portal →</a>
    </p>
</body>
</html>
