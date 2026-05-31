<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Followup Questions — Screenplay Readers</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Aleo:ital,wght@0,400;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <style>
        /* SR brand: #f7f4e6 yellow | #2b4158 dark blue | #e4e9f2 light blue */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            background: #f7f4e6;
            font-family: 'Aleo', Georgia, serif;
            font-size: 16px;
            line-height: 1.6;
            color: #333;
            min-height: 100vh;
            padding: 3rem 1rem;
        }

        .wrap {
            max-width: 640px;
            margin: 0 auto;
        }

        /* Header */
        .page-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .page-header h1 {
            font-size: 1.75rem;
            font-weight: 700;
            color: #2b4158;
            line-height: 1.2;
        }
        .page-header .order-num {
            margin-top: 0.35rem;
            font-size: 0.875rem;
            color: #666;
        }

        /* Success banner */
        .notice-success {
            background: #e6f4ea;
            border: 1px solid #b7dfbe;
            border-radius: 6px;
            padding: 0.75rem 1rem;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            color: #2d6a4f;
        }

        /* Slot card */
        .slot-card {
            background: #fff;
            border: 1px solid #d5cfc0;
            border-radius: 8px;
            padding: 1.25rem 1.5rem;
            margin-bottom: 1.25rem;
        }
        .slot-label {
            font-size: 0.9rem;
            font-weight: 600;
            color: #2b4158;
            margin-bottom: 0.75rem;
        }
        .slot-label .reader-id {
            font-family: "Courier New", Courier, monospace;
            color: #1e73be;
        }
        .slot-label .type-label {
            font-weight: 400;
            color: #888;
        }

        /* Locked state */
        .locked-text {
            background: #f5f5f5;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 0.6rem 0.75rem;
            font-size: 0.9rem;
            color: #555;
            font-style: italic;
        }
        .locked-note {
            margin-top: 0.5rem;
            font-size: 0.8rem;
            color: #b45309;
        }

        /* Textarea */
        textarea {
            width: 100%;
            border: 1px solid #ccc;
            border-radius: 5px;
            padding: 0.6rem 0.75rem;
            font-size: 0.9rem;
            font-family: 'Aleo', Georgia, serif;
            line-height: 1.5;
            resize: vertical;
            color: #333;
            background: #fff;
            transition: border-color 0.15s;
        }
        textarea:focus {
            outline: none;
            border-color: #2b4158;
            box-shadow: 0 0 0 2px rgba(43, 65, 88, 0.12);
        }
        .field-error {
            margin-top: 0.3rem;
            font-size: 0.8rem;
            color: #c0392b;
        }

        /* Submit row */
        .form-footer {
            display: flex;
            justify-content: flex-end;
            margin-top: 0.5rem;
        }
        .btn-submit {
            background: #2b4158;
            color: #fff;
            border: none;
            border-radius: 5px;
            padding: 0.6rem 1.5rem;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.15s;
            letter-spacing: 0.01em;
        }
        .btn-submit:hover {
            background: #1e2f40;
        }

        /* Footer */
        .page-footer {
            margin-top: 2.5rem;
            text-align: center;
            font-size: 0.78rem;
            color: #999;
        }
        .page-footer a {
            color: #2b4158;
            text-decoration: none;
        }
        .page-footer a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
<div class="wrap">

    <div class="page-header">
        <h1>Followup Questions</h1>
        <p class="order-num">Order #{{ $followupToken->order_number }}</p>
    </div>

    @php
        $beforeHtml = \App\Models\Setting::getValue('followup_before_html', '');
        $afterHtml  = \App\Models\Setting::getValue('followup_after_html', '');
    @endphp

    @if ($beforeHtml)
        <div class="followup-inject">{!! $beforeHtml !!}</div>
    @endif

    @if (session('submitted'))
        <div class="notice-success">
            Your questions have been submitted.
        </div>
    @endif

    <form method="POST" action="{{ route('followup.submit', $token) }}">
        @csrf

        @foreach ($slots as $slot)
            <div class="slot-card">
                <p class="slot-label">
                    Your questions for reader <span class="reader-id">{{ $slot['initials'] }}</span>
                    <span class="type-label">({{ $slot['type_label'] }})</span>
                </p>

                @if ($slot['locked'])
                    <div class="locked-text">{{ $slot['existing_text'] }}</div>
                    <p class="locked-note">These questions have already been sent to your reader and can no longer be edited.</p>
                    <input type="hidden" name="questions[{{ $slot['assignment_id'] }}]" value="" />
                @else
                    <textarea
                        name="questions[{{ $slot['assignment_id'] }}]"
                        rows="5"
                        maxlength="3000"
                        placeholder="Type your questions here…"
                    >{{ old('questions.'.$slot['assignment_id'], $slot['existing_text']) }}</textarea>
                    @error('questions.'.$slot['assignment_id'])
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                @endif
            </div>
        @endforeach

        @php $anyEditable = collect($slots)->contains(fn($s) => ! $s['locked']); @endphp

        @if ($anyEditable)
            <div class="form-footer">
                <button type="submit" class="btn-submit">Submit Questions</button>
            </div>
        @endif

    </form>

    @if ($afterHtml)
        <div class="followup-inject">{!! $afterHtml !!}</div>
    @endif

    <p class="page-footer">
        <a href="https://screenplayreaders.com">Screenplay Readers</a>
    </p>

</div>
</body>
</html>
