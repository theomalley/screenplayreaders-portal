<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Reschedule Your Call — Screenplay Readers</title>
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
        .card { background: #fff; border: 1px solid #d5cfc0; border-radius: 8px; padding: 1.25rem 1.5rem; margin-bottom: 1.25rem; }
        .slot-option { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 0; border-bottom: 1px solid #f0ede0; cursor: pointer; }
        .slot-option:last-child { border-bottom: none; }
        .slot-option input { width: 18px; height: 18px; accent-color: #2b4158; }
        .slot-option span { font-size: 0.95rem; }
        .form-footer { display: flex; justify-content: flex-end; margin-top: 1rem; }
        .btn-submit { background: #2b4158; color: #fff; border: none; border-radius: 5px; padding: 0.6rem 1.5rem; font-size: 0.9rem; font-family: 'Aleo', Georgia, serif; font-weight: 600; cursor: pointer; }
        .btn-submit:hover { background: #1e2f40; }
        .field-error { margin-top: 0.75rem; font-size: 0.85rem; color: #c0392b; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="page-header">
        <h1>Pick a New Time</h1>
        <p class="order-num">Order #{{ $assignment->order_number }}</p>
    </div>

    @if ($errors->any())
        <div class="card field-error">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('video-consulting-reschedule-offer.submit', $token) }}">
        @csrf
        <div class="card">
            @foreach ($offer->candidate_slots as $i => $slot)
                <label class="slot-option">
                    <input type="radio" name="chosen_slot" value="{{ $slot }}" required>
                    <span>{{ \Carbon\Carbon::parse($slot)->format('l, F j, Y \a\t g:i A T') }}</span>
                </label>
            @endforeach
        </div>
        <div class="form-footer">
            <button type="submit" class="btn-submit">Confirm This Time</button>
        </div>
    </form>
</div>
</body>
</html>
