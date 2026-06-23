<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Your Script — Screenplay Readers</title>
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

        .sr-reg { max-width: 720px; margin: 0 auto; }

        /* ── Header ── */
        .sr-reg__page-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .sr-reg__page-header h1 {
            font-size: 1.6rem;
            font-weight: 700;
            color: #0f766e;
            line-height: 1.2;
        }
        .sr-reg__page-header .sr-reg__sub {
            margin-top: 0.35rem;
            font-size: 0.9rem;
            color: #6b7280;
        }

        /* ── Flash messages ── */
        .sr-reg__flash--success {
            background: #f0fdfa;
            border: 1px solid #99f6e4;
            border-radius: 8px;
            padding: 0.85rem 1rem;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            color: #065f46;
            font-weight: 500;
        }
        .sr-reg__flash--error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 8px;
            padding: 0.85rem 1rem;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            color: #991b1b;
        }

        /* ── Sections ── */
        .sr-reg__section {
            margin-bottom: 2rem;
            padding: 1.5rem;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
        }
        .sr-reg__heading {
            font-size: 1.25rem;
            font-weight: 700;
            color: #0f766e;
            margin: 0 0 1.25rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid #ccfbf1;
            letter-spacing: -0.01em;
        }

        /* ── Grid / Rows ── */
        .sr-reg__row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        .sr-reg__full { grid-column: 1 / -1; }

        @media (max-width: 560px) {
            .sr-reg__row { grid-template-columns: 1fr; }
        }

        /* ── Fields ── */
        .sr-reg__field label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.3rem;
        }
        .sr-reg__req { color: #dc2626; }

        .sr-reg__field input[type="text"],
        .sr-reg__field input[type="email"],
        .sr-reg__field input[type="number"],
        .sr-reg__field input[type="tel"],
        .sr-reg__field select {
            width: 100%;
            padding: 0.5rem 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 0.95rem;
            color: #1a1a1a;
            background: #fff;
            transition: border-color 0.15s, box-shadow 0.15s;
        }
        .sr-reg__field input:focus,
        .sr-reg__field select:focus {
            outline: none;
            border-color: #0d9488;
            box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.1);
        }

        .sr-reg__field input[type="file"] {
            padding: 0.4rem;
            border: 1px dashed #d1d5db;
            border-radius: 6px;
            background: #f9fafb;
            cursor: pointer;
            width: 100%;
            font-size: 0.9rem;
            color: #6b7280;
        }
        .sr-reg__field input[type="file"]:hover {
            border-color: #0d9488;
            background: #f0fdfa;
        }

        .sr-reg__hint {
            font-size: 0.8rem;
            color: #6b7280;
            margin-top: 0.25rem;
        }
        .sr-reg__hint--warn {
            color: #b45309;
            font-weight: 500;
        }

        .sr-reg__field-error {
            margin-top: 0.3rem;
            font-size: 0.8rem;
            color: #dc2626;
            font-weight: 500;
        }

        /* ── Submit ── */
        .sr-reg__confirm {
            border-color: #99f6e4;
            background: #f0fdfa;
            text-align: center;
        }
        .sr-reg__btn {
            background: #0f766e;
            color: #fff;
            border: none;
            border-radius: 6px;
            padding: 0.7rem 2rem;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.15s;
        }
        .sr-reg__btn:hover { background: #0d9488; }

        /* ── Logo + footer ── */
        .sr-reg__logo-link { display: block; text-align: center; margin-bottom: 1rem; }
        .sr-reg__logo {
            max-width: 220px;
            height: auto;
            opacity: 0.85;
            transition: opacity 0.15s;
        }
        .sr-reg__logo:hover { opacity: 1; }
        .sr-reg__footer {
            text-align: center;
            margin-top: 1rem;
            font-size: 0.85rem;
        }
        .sr-reg__footer a {
            color: #0f766e;
            text-decoration: none;
        }
        .sr-reg__footer a:hover { text-decoration: underline; }

        /* ── Registrations list ── */
        .sr-reg__list-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }
        .sr-reg__list-table th {
            text-align: left;
            font-weight: 600;
            color: #374151;
            padding: 0.5rem 0.6rem;
            border-bottom: 2px solid #ccfbf1;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        .sr-reg__list-table td {
            padding: 0.5rem 0.6rem;
            border-bottom: 1px solid #f3f4f6;
            color: #4b5563;
        }
        .sr-reg__list-table tr:last-child td { border-bottom: none; }
        .sr-reg__status {
            display: inline-block;
            padding: 0.15rem 0.5rem;
            border-radius: 10px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .sr-reg__status--completed { background: #d1fae5; color: #065f46; }
        .sr-reg__status--pending   { background: #fef3c7; color: #92400e; }
        .sr-reg__status--failed    { background: #fee2e2; color: #991b1b; }
        .sr-reg__empty {
            text-align: center;
            padding: 1rem;
            color: #9ca3af;
            font-size: 0.9rem;
        }
        @media (max-width: 560px) {
            .sr-reg__list-table th:nth-child(4),
            .sr-reg__list-table td:nth-child(4) { display: none; }
        }
    </style>
</head>
<body>
<div class="sr-reg">

    <div class="sr-reg__page-header">
        <a href="https://screenplayreaders.com/script-registration" class="sr-reg__logo-link">
            <img src="https://screenplayreaders.com/wp-content/themes/generatepress_child/images/logo_login_640x168.png" alt="Screenplay Readers" class="sr-reg__logo">
        </a>
        <h1>Register Your Script</h1>
        <p class="sr-reg__sub">Unlimited Registration for {{ $parent->customer_name }}</p>
    </div>

    @if(session('success'))
        <div class="sr-reg__flash--success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="sr-reg__flash--error">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    {{-- Registered Scripts --}}
    <section class="sr-reg__section">
        <h2 class="sr-reg__heading">Your Registered Scripts</h2>

        @if($registrations->isEmpty())
            <div class="sr-reg__empty">No scripts registered yet. Use the form below to register your first.</div>
        @else
            <table class="sr-reg__list-table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Reg #</th>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($registrations as $reg)
                        <tr>
                            <td>{{ $reg->script_title }}</td>
                            <td style="font-family:monospace;font-size:0.8rem">{{ $reg->registration_id }}</td>
                            <td>{{ $reg->registered_at ? $reg->registered_at->format('M j, Y') : '—' }}</td>
                            <td>{{ $reg->type_of_work }}</td>
                            <td>
                                <span class="sr-reg__status sr-reg__status--{{ $reg->status }}">
                                    {{ $reg->status === 'completed' ? 'Registered' : ucfirst($reg->status) }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>

    <form method="POST" action="{{ route('script-registration.public.submit', $token) }}" enctype="multipart/form-data" class="sr-reg__form">
        @csrf

        {{-- Section 1: Script Details --}}
        <section class="sr-reg__section">
            <h2 class="sr-reg__heading">Script Details</h2>

            <div class="sr-reg__row">
                <div class="sr-reg__field sr-reg__full">
                    <label for="sr_title">Script or Document Title <span class="sr-reg__req">*</span></label>
                    <input type="text" id="sr_title" name="sr_title" required value="{{ old('sr_title') }}" maxlength="255">
                    @error('sr_title') <div class="sr-reg__field-error">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="sr-reg__row">
                <div class="sr-reg__field">
                    <label for="sr_page_count">Page Count <span class="sr-reg__req">*</span></label>
                    <input type="number" id="sr_page_count" name="sr_page_count" min="1" max="9999" required value="{{ old('sr_page_count') }}">
                    @error('sr_page_count') <div class="sr-reg__field-error">{{ $message }}</div> @enderror
                </div>
                <div class="sr-reg__field">
                    <label for="sr_type_of_work">Type of Work <span class="sr-reg__req">*</span></label>
                    <select id="sr_type_of_work" name="sr_type_of_work" required>
                        <option value="">Select…</option>
                        @foreach($workTypes as $type)
                            <option value="{{ $type }}" @selected(old('sr_type_of_work') === $type)>{{ $type }}</option>
                        @endforeach
                    </select>
                    @error('sr_type_of_work') <div class="sr-reg__field-error">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="sr-reg__row">
                <div class="sr-reg__field sr-reg__full">
                    <label for="sr_file">Upload Your File <span class="sr-reg__req">*</span></label>
                    <input type="file" id="sr_file" name="sr_file" required accept=".pdf,.docx,.fdx,.fdr,.fadein,.fountain">
                    <small class="sr-reg__hint">Max 5 MB — .pdf, .docx, .fdx, .fdr, .fadein, .fountain</small>
                    @error('sr_file') <div class="sr-reg__field-error">{{ $message }}</div> @enderror
                </div>
            </div>
        </section>

        {{-- Section 2: Author Information --}}
        <section class="sr-reg__section">
            <h2 class="sr-reg__heading">Author Information</h2>

            <div class="sr-reg__row">
                <div class="sr-reg__field">
                    <label for="sr_author_first">First Name <span class="sr-reg__req">*</span></label>
                    <input type="text" id="sr_author_first" name="sr_author_first" required value="{{ old('sr_author_first') }}">
                    @error('sr_author_first') <div class="sr-reg__field-error">{{ $message }}</div> @enderror
                </div>
                <div class="sr-reg__field">
                    <label for="sr_author_last">Last Name <span class="sr-reg__req">*</span></label>
                    <input type="text" id="sr_author_last" name="sr_author_last" required value="{{ old('sr_author_last') }}">
                    @error('sr_author_last') <div class="sr-reg__field-error">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="sr-reg__row">
                <div class="sr-reg__field sr-reg__full">
                    <label for="sr_additional_authors">Additional Authors</label>
                    <input type="text" id="sr_additional_authors" name="sr_additional_authors" value="{{ old('sr_additional_authors') }}" placeholder="(optional) Separate names with commas">
                    @error('sr_additional_authors') <div class="sr-reg__field-error">{{ $message }}</div> @enderror
                </div>
            </div>
        </section>

        {{-- Section 3: Contact & Address --}}
        <section class="sr-reg__section">
            <h2 class="sr-reg__heading">Contact & Address</h2>

            <div class="sr-reg__row">
                <div class="sr-reg__field sr-reg__full">
                    <label for="sr_email">Email <span class="sr-reg__req">*</span></label>
                    <input type="email" id="sr_email" name="sr_email" required value="{{ old('sr_email', $parent->email) }}">
                    <small class="sr-reg__hint sr-reg__hint--warn">Your registration certificate will be sent to this address.</small>
                    @error('sr_email') <div class="sr-reg__field-error">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="sr-reg__row">
                <div class="sr-reg__field sr-reg__full">
                    <label for="sr_street_address">Street Address <span class="sr-reg__req">*</span></label>
                    <input type="text" id="sr_street_address" name="sr_street_address" required value="{{ old('sr_street_address') }}">
                    @error('sr_street_address') <div class="sr-reg__field-error">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="sr-reg__row">
                <div class="sr-reg__field">
                    <label for="sr_city">City <span class="sr-reg__req">*</span></label>
                    <input type="text" id="sr_city" name="sr_city" required value="{{ old('sr_city') }}">
                    @error('sr_city') <div class="sr-reg__field-error">{{ $message }}</div> @enderror
                </div>
                <div class="sr-reg__field">
                    <label for="sr_state_or_province">State / Province <span class="sr-reg__req">*</span></label>
                    <input type="text" id="sr_state_or_province" name="sr_state_or_province" required value="{{ old('sr_state_or_province') }}">
                    @error('sr_state_or_province') <div class="sr-reg__field-error">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="sr-reg__row">
                <div class="sr-reg__field">
                    <label for="sr_postal_or_zip">Postal / ZIP Code <span class="sr-reg__req">*</span></label>
                    <input type="text" id="sr_postal_or_zip" name="sr_postal_or_zip" required value="{{ old('sr_postal_or_zip') }}">
                    @error('sr_postal_or_zip') <div class="sr-reg__field-error">{{ $message }}</div> @enderror
                </div>
                <div class="sr-reg__field">
                    <label for="sr_country">Country <span class="sr-reg__req">*</span></label>
                    <input type="text" id="sr_country" name="sr_country" required value="{{ old('sr_country') }}">
                    @error('sr_country') <div class="sr-reg__field-error">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="sr-reg__row">
                <div class="sr-reg__field">
                    <label for="sr_phone">Phone <span class="sr-reg__req">*</span></label>
                    <input type="tel" id="sr_phone" name="sr_phone" required value="{{ old('sr_phone') }}">
                    @error('sr_phone') <div class="sr-reg__field-error">{{ $message }}</div> @enderror
                </div>
                <div class="sr-reg__field">
                    <label for="sr_unique_id">Unique ID</label>
                    <input type="text" id="sr_unique_id" name="sr_unique_id" value="{{ old('sr_unique_id') }}" placeholder="(optional)">
                    <small class="sr-reg__hint">Last 4 of SSN, driver's license #, or copyright #</small>
                    @error('sr_unique_id') <div class="sr-reg__field-error">{{ $message }}</div> @enderror
                </div>
            </div>
        </section>

        {{-- Submit --}}
        <section class="sr-reg__section sr-reg__confirm">
            <button type="submit" class="sr-reg__btn">Register Script</button>
        </section>
    </form>

    <div class="sr-reg__footer">
        <a href="https://screenplayreaders.com/script-registration">&larr; Back to Script Registration</a>
    </div>

</div>
</body>
</html>
