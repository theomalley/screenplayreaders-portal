<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Call Rescheduled — Screenplay Readers</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Aleo:ital,wght@0,400;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { background: #f7f4e6; font-family: 'Aleo', Georgia, serif; font-size: 16px; line-height: 1.6; color: #333; min-height: 100vh; padding: 3rem 1rem; }
        .wrap { max-width: 560px; margin: 0 auto; }
        .page-header { text-align: center; margin-bottom: 2rem; }
        .page-header h1 { font-size: 1.75rem; font-weight: 700; color: #2b4158; line-height: 1.2; }
        .card { background: #fff; border: 1px solid #d5cfc0; border-radius: 8px; padding: 1.5rem; text-align: center; }
        .new-time { font-size: 1.15rem; font-weight: 600; color: #2b4158; margin-top: 0.5rem; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="page-header">
        <h1>You're All Set</h1>
    </div>
    <div class="card">
        <p>Your {{ $assignment->script_title }} call (order #{{ $assignment->order_number }}) is now confirmed for:</p>
        <p class="new-time">{{ $offer->chosen_slot?->format('l, F j, Y \a\t g:i A T') }}</p>
    </div>
</div>
</body>
</html>
