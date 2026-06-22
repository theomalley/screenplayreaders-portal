<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Your Script — Screenplay Readers</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Aleo:ital,wght@0,400;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <style>
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

        .wrap { max-width: 640px; margin: 0 auto; }

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
        .page-header .sub {
            margin-top: 0.35rem;
            font-size: 0.875rem;
            color: #666;
        }

        .notice-success {
            background: #e6f4ea;
            border: 1px solid #b7dfbe;
            border-radius: 6px;
            padding: 0.75rem 1rem;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            color: #2d6a4f;
        }

        .notice-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 6px;
            padding: 0.75rem 1rem;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            color: #991b1b;
        }

        .form-card {
            background: #fff;
            border: 1px solid #d5cfc0;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.25rem;
        }
        .form-card h2 {
            font-size: 1rem;
            font-weight: 600;
            color: #2b4158;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #eee;
        }

        .field-group {
            margin-bottom: 1rem;
        }
        .field-group label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            color: #444;
            margin-bottom: 0.25rem;
        }
        .field-group .hint {
            font-size: 0.78rem;
            color: #888;
            font-weight: 400;
        }

        input[type="text"],
        input[type="email"],
        input[type="tel"],
        input[type="number"],
        select {
            width: 100%;
            border: 1px solid #ccc;
            border-radius: 5px;
            padding: 0.5rem 0.75rem;
            font-size: 0.9rem;
            font-family: 'Aleo', Georgia, serif;
            color: #333;
            background: #fff;
            transition: border-color 0.15s;
        }
        input:focus, select:focus {
            outline: none;
            border-color: #2b4158;
            box-shadow: 0 0 0 2px rgba(43, 65, 88, 0.12);
        }

        input[type="file"] {
            font-family: 'Aleo', Georgia, serif;
            font-size: 0.85rem;
        }

        .field-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .field-error {
            margin-top: 0.3rem;
            font-size: 0.8rem;
            color: #c0392b;
        }

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
            padding: 0.6rem 2rem;
            font-size: 0.95rem;
            font-family: 'Aleo', Georgia, serif;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.15s;
        }
        .btn-submit:hover { background: #1e2f40; }

        @media (max-width: 500px) {
            .field-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="wrap">

    <div class="page-header">
        <h1>Register Your Script</h1>
        <p class="sub">Unlimited Registration for {{ $parent->customer_name }}</p>
    </div>

    @if(session('success'))
        <div class="notice-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="notice-error">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('script-registration.public.submit', $token) }}" enctype="multipart/form-data">
        @csrf

        <div class="form-card">
            <h2>Script Information</h2>

            <div class="field-group">
                <label for="sr_title">Script or Document Title <span style="color:#c0392b">*</span></label>
                <input type="text" id="sr_title" name="sr_title" required value="{{ old('sr_title') }}">
                @error('sr_title') <div class="field-error">{{ $message }}</div> @enderror
            </div>

            <div class="field-row">
                <div class="field-group">
                    <label for="sr_page_count">Page Count <span style="color:#c0392b">*</span></label>
                    <input type="number" id="sr_page_count" name="sr_page_count" min="1" max="9999" required value="{{ old('sr_page_count') }}">
                    @error('sr_page_count') <div class="field-error">{{ $message }}</div> @enderror
                </div>
                <div class="field-group">
                    <label for="sr_type_of_work">Type of Work <span style="color:#c0392b">*</span></label>
                    <select id="sr_type_of_work" name="sr_type_of_work" required>
                        <option value="">Select…</option>
                        @foreach($workTypes as $type)
                            <option value="{{ $type }}" @selected(old('sr_type_of_work') === $type)>{{ $type }}</option>
                        @endforeach
                    </select>
                    @error('sr_type_of_work') <div class="field-error">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="field-group">
                <label for="sr_file">Upload File <span style="color:#c0392b">*</span></label>
                <span class="hint">PDF, DOCX, FDX, FDR, Fadein, or Fountain — 5 MB max</span>
                <input type="file" id="sr_file" name="sr_file" required accept=".pdf,.docx,.fdx,.fdr,.fadein,.fountain">
                @error('sr_file') <div class="field-error">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="form-card">
            <h2>Author Information</h2>

            <div class="field-row">
                <div class="field-group">
                    <label for="sr_author_first">First Name <span style="color:#c0392b">*</span></label>
                    <input type="text" id="sr_author_first" name="sr_author_first" required value="{{ old('sr_author_first') }}">
                    @error('sr_author_first') <div class="field-error">{{ $message }}</div> @enderror
                </div>
                <div class="field-group">
                    <label for="sr_author_last">Last Name <span style="color:#c0392b">*</span></label>
                    <input type="text" id="sr_author_last" name="sr_author_last" required value="{{ old('sr_author_last') }}">
                    @error('sr_author_last') <div class="field-error">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="field-group">
                <label for="sr_additional_authors">Additional Authors <span class="hint">(optional)</span></label>
                <input type="text" id="sr_additional_authors" name="sr_additional_authors" value="{{ old('sr_additional_authors') }}">
                @error('sr_additional_authors') <div class="field-error">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="form-card">
            <h2>Contact & Address</h2>

            <div class="field-group">
                <label for="sr_street_address">Street Address <span style="color:#c0392b">*</span></label>
                <input type="text" id="sr_street_address" name="sr_street_address" required value="{{ old('sr_street_address') }}">
                @error('sr_street_address') <div class="field-error">{{ $message }}</div> @enderror
            </div>

            <div class="field-row">
                <div class="field-group">
                    <label for="sr_city">City <span style="color:#c0392b">*</span></label>
                    <input type="text" id="sr_city" name="sr_city" required value="{{ old('sr_city') }}">
                    @error('sr_city') <div class="field-error">{{ $message }}</div> @enderror
                </div>
                <div class="field-group">
                    <label for="sr_state_or_province">State / Province <span style="color:#c0392b">*</span></label>
                    <input type="text" id="sr_state_or_province" name="sr_state_or_province" required value="{{ old('sr_state_or_province') }}">
                    @error('sr_state_or_province') <div class="field-error">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="field-row">
                <div class="field-group">
                    <label for="sr_postal_or_zip">Postal / Zip Code <span style="color:#c0392b">*</span></label>
                    <input type="text" id="sr_postal_or_zip" name="sr_postal_or_zip" required value="{{ old('sr_postal_or_zip') }}">
                    @error('sr_postal_or_zip') <div class="field-error">{{ $message }}</div> @enderror
                </div>
                <div class="field-group">
                    <label for="sr_country">Country <span style="color:#c0392b">*</span></label>
                    <input type="text" id="sr_country" name="sr_country" required value="{{ old('sr_country') }}">
                    @error('sr_country') <div class="field-error">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="field-row">
                <div class="field-group">
                    <label for="sr_phone">Phone <span style="color:#c0392b">*</span></label>
                    <input type="tel" id="sr_phone" name="sr_phone" required value="{{ old('sr_phone') }}">
                    @error('sr_phone') <div class="field-error">{{ $message }}</div> @enderror
                </div>
                <div class="field-group">
                    <label for="sr_email">Email <span style="color:#c0392b">*</span></label>
                    <input type="email" id="sr_email" name="sr_email" required value="{{ old('sr_email', $parent->email) }}">
                    @error('sr_email') <div class="field-error">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="field-group">
                <label for="sr_unique_id">Unique ID <span class="hint">(optional — e.g. WGA registration number)</span></label>
                <input type="text" id="sr_unique_id" name="sr_unique_id" value="{{ old('sr_unique_id') }}">
                @error('sr_unique_id') <div class="field-error">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="form-footer">
            <button type="submit" class="btn-submit">Register Script</button>
        </div>
    </form>

</div>
</body>
</html>
