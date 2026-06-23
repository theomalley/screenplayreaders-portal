<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Partner Program — Screenplay Readers</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            background: #f8fafb;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            font-size: 16px;
            line-height: 1.5;
            color: #1a1a1a;
            min-height: 100vh;
            padding: 2.5rem 1rem 4rem;
        }

        .sr-partner { max-width: 540px; margin: 0 auto; }

        .sr-partner__header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .sr-partner__header h1 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #2b4158;
            line-height: 1.2;
        }
        .sr-partner__header p {
            margin-top: 0.4rem;
            font-size: 0.9rem;
            color: #6b7280;
        }

        .sr-partner__logo {
            display: block;
            margin: 0 auto 1.25rem;
            height: 38px;
        }

        .sr-partner__card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 1.75rem 1.5rem;
        }

        .sr-partner__field { margin-bottom: 1.1rem; }
        .sr-partner__field:last-of-type { margin-bottom: 0; }

        .sr-partner__label {
            display: block;
            font-size: 0.8rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.3rem;
        }

        .sr-partner__input,
        .sr-partner__textarea {
            width: 100%;
            padding: 0.55rem 0.7rem;
            font-size: 0.9rem;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            color: #1a1a1a;
            background: #fff;
            transition: border-color 0.15s;
        }
        .sr-partner__input:focus,
        .sr-partner__textarea:focus {
            outline: none;
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        .sr-partner__textarea { resize: vertical; min-height: 70px; }

        .sr-partner__hint {
            font-size: 0.78rem;
            color: #9ca3af;
            margin-top: 0.2rem;
        }

        .sr-partner__error {
            font-size: 0.78rem;
            color: #dc2626;
            margin-top: 0.2rem;
        }

        .sr-partner__honeypot {
            position: absolute;
            left: -9999px;
            opacity: 0;
            height: 0;
            width: 0;
            overflow: hidden;
            tab-index: -1;
        }

        .sr-partner__submit {
            display: block;
            width: 100%;
            margin-top: 1.5rem;
            padding: 0.65rem 1rem;
            font-size: 0.95rem;
            font-weight: 600;
            color: #fff;
            background: #4f46e5;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: background 0.15s;
        }
        .sr-partner__submit:hover { background: #4338ca; }

        .sr-partner__flash {
            border-radius: 8px;
            padding: 0.85rem 1rem;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
        }
        .sr-partner__flash--success {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #166534;
        }
        .sr-partner__flash--error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }

        .sr-partner__coupon-box {
            margin-top: 0.75rem;
            background: #fff;
            border: 2px dashed #6366f1;
            border-radius: 6px;
            padding: 0.6rem 1rem;
            text-align: center;
        }
        .sr-partner__coupon-code {
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: 1.3em;
            font-weight: 700;
            color: #4f46e5;
            letter-spacing: 0.05em;
        }

        .sr-partner__footer {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.8rem;
            color: #9ca3af;
        }
        .sr-partner__footer a {
            color: #6366f1;
            text-decoration: none;
        }
        .sr-partner__footer a:hover { text-decoration: underline; }
    </style>
</head>
<body>

<div class="sr-partner">

    <div class="sr-partner__header">
        <a href="https://screenplayreaders.com">
            <img src="https://screenplayreaders.com/wp-content/uploads/screenplay-readers-coverage-service-logo-dark.png"
                 alt="Screenplay Readers" class="sr-partner__logo">
        </a>
        <h1>Partner Program Application</h1>
        <p>Link to us and get a coupon code to share with your audience.</p>
    </div>

    @if(session('success'))
        <div class="sr-partner__flash sr-partner__flash--success">
            <strong>Application received!</strong> We'll review your site and activate monitoring shortly.
            @if(session('coupon_code'))
                <div class="sr-partner__coupon-box">
                    <div>Your coupon code:</div>
                    <div class="sr-partner__coupon-code">{{ session('coupon_code') }}</div>
                </div>
                <p style="margin-top:0.5rem;font-size:0.82rem;color:#6b7280;">
                    We'll configure the discount amount once your application is approved. Hold on to this code.
                </p>
            @endif
        </div>
    @else

        @if($errors->any())
            <div class="sr-partner__flash sr-partner__flash--error">
                <strong>Please fix the following:</strong>
                <ul style="margin:0.3rem 0 0 1.2rem;padding:0;">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="sr-partner__card">
            <form method="POST" action="{{ route('partner-apply') }}">
                @csrf

                <div class="sr-partner__honeypot" aria-hidden="true">
                    <label for="website_url">Leave this empty</label>
                    <input type="text" name="website_url" id="website_url" value="" autocomplete="off" tabindex="-1">
                </div>

                <div class="sr-partner__field">
                    <label class="sr-partner__label" for="name">Partner / Company Name</label>
                    <input type="text" name="name" id="name" required maxlength="255"
                           value="{{ old('name') }}"
                           class="sr-partner__input"
                           placeholder="e.g. ScriptWriters Weekly">
                    @error('name') <div class="sr-partner__error">{{ $message }}</div> @enderror
                </div>

                <div class="sr-partner__field">
                    <label class="sr-partner__label" for="url">Page URL containing your link to us</label>
                    <input type="url" name="url" id="url" required maxlength="500"
                           value="{{ old('url') }}"
                           class="sr-partner__input"
                           placeholder="https://yoursite.com/resources">
                    <div class="sr-partner__hint">The specific page where your link to screenplayreaders.com appears.</div>
                    @error('url') <div class="sr-partner__error">{{ $message }}</div> @enderror
                </div>

                <div class="sr-partner__field">
                    <label class="sr-partner__label" for="email">Contact Email</label>
                    <input type="email" name="email" id="email" required maxlength="255"
                           value="{{ old('email') }}"
                           class="sr-partner__input"
                           placeholder="you@yoursite.com">
                    @error('email') <div class="sr-partner__error">{{ $message }}</div> @enderror
                </div>

                <div class="sr-partner__field">
                    <label class="sr-partner__label" for="notes">Notes <span style="font-weight:400;color:#9ca3af;">(optional)</span></label>
                    <textarea name="notes" id="notes" maxlength="1000"
                              class="sr-partner__textarea"
                              placeholder="Anything you'd like us to know — where the link is placed, your audience, etc.">{{ old('notes') }}</textarea>
                    @error('notes') <div class="sr-partner__error">{{ $message }}</div> @enderror
                </div>

                <button type="submit" class="sr-partner__submit">Submit Application</button>
            </form>
        </div>

    @endif

    <div class="sr-partner__footer">
        <a href="https://screenplayreaders.com">screenplayreaders.com</a>
    </div>

</div>

</body>
</html>
