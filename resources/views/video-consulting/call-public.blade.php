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
        .call-title { font-size: 1.25rem; font-weight: 700; color: #2b4158; margin-bottom: 0.5rem; }
        .call-meta { font-size: 0.95rem; color: #555; margin-bottom: 0.25rem; }
        .call-join-btn { display: inline-block; margin-top: 1.25rem; background: #2b4158; color: #fff; border: none; border-radius: 5px; padding: 0.7rem 1.75rem; font-size: 0.95rem; font-family: 'Aleo', Georgia, serif; font-weight: 600; text-decoration: none; }
        .call-join-btn:hover { background: #1e2f40; }
        .call-not-ready { margin-top: 1.25rem; font-size: 0.9rem; color: #8a6d00; background: #fff8e1; border: 1px solid #f0dfa0; border-radius: 6px; padding: 0.75rem 1rem; }
        .call-consent { margin-top: 1.5rem; font-size: 0.8rem; color: #888; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="page-header">
        <h1>Your Upcoming Call</h1>
        <p class="order-num">Order #{{ $assignment->order_number }}</p>
    </div>

    @include('video-consulting.partials.call-details')
</div>
</body>
</html>
