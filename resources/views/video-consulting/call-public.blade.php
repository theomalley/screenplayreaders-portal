<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Your Video Call — Screenplay Readers</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Aleo:ital,wght@0,400;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { background: #f7f4e6; font-family: 'Aleo', Georgia, serif; font-size: 16px; line-height: 1.6; color: #333; min-height: 100vh; padding: 3rem 1rem; }
        .wrap { max-width: 560px; margin: 0 auto; }
        .page-header { text-align: center; margin-bottom: 2rem; }
        .page-header h1 { font-size: 1.75rem; font-weight: 700; color: #2b4158; line-height: 1.2; }
        .page-header .order-num { margin-top: 0.35rem; font-size: 0.875rem; color: #666; }
        .call-card { background: #fff; border: 1px solid #d5cfc0; border-radius: 8px; padding: 1.5rem; text-align: center; }
        .call-title { font-size: 1.25rem; font-weight: 700; color: #2b4158; margin-bottom: 0.25rem; }
        .call-with { font-size: 1rem; color: #444; margin-bottom: 0.5rem; }
        .call-meta { font-size: 0.95rem; color: #555; margin-bottom: 0.25rem; }
        .call-not-ready { margin-top: 1.25rem; font-size: 0.9rem; color: #8a6d00; background: #fff8e1; border: 1px solid #f0dfa0; border-radius: 6px; padding: 0.75rem 1rem; }
        .call-consent { margin-top: 1.5rem; font-size: 0.8rem; color: #888; }
        .page-header .logo { max-height: 60px; max-width: 100%; margin: 0 auto 1.25rem; display: block; }

        .call-timer { margin: 1.25rem 0; }
        .call-timer-value { font-size: 3rem; font-weight: 700; font-family: 'SF Mono', 'Menlo', monospace; color: #2b4158; line-height: 1; }
        .call-timer-label { font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.05em; color: #777; margin-top: 0.25rem; }
        .call-timer-over .call-timer-value { color: #b45309; }
        .call-timer-upcoming .call-timer-value { color: #555; }

        .call-embed { display: block; width: 100%; height: 70vh; border: 0; border-radius: 8px; margin-top: 1rem; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="page-header">
        @if (! empty($callPageSettings['call_page_logo_url']))
            <img class="logo" src="{{ $callPageSettings['call_page_logo_url'] }}" alt="Screenplay Readers">
        @endif
        <h1>{{ $callPageSettings['call_page_heading'] }}</h1>
        <p class="order-num">Order #{{ $assignment->order_number }}</p>
    </div>

    @include('video-consulting.partials.call-details')
</div>
@vite(['resources/js/app.js'])
@include('video-consulting.partials.call-scripts')
</body>
</html>
