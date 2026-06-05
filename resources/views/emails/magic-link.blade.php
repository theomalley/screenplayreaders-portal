<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Your login link</title>
</head>
<body style="margin:0;padding:0;background:#f9fafb;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f9fafb;padding:40px 16px;">
    <tr>
        <td align="center">
            <table width="100%" style="max-width:520px;background:#ffffff;border-radius:8px;border:1px solid #e5e7eb;padding:40px 36px;">
                <tr>
                    <td style="padding-bottom:24px;border-bottom:1px solid #f3f4f6;">
                        <p style="margin:0;font-size:15px;font-weight:600;color:#111827;">Screenplay Readers Portal</p>
                    </td>
                </tr>
                <tr>
                    <td style="padding-top:28px;">
                        <p style="margin:0 0 8px;font-size:20px;font-weight:700;color:#111827;">Hi {{ $firstName }},</p>
                        <p style="margin:0 0 24px;font-size:15px;color:#4b5563;line-height:1.6;">
                            Here's your one-click login link for the Screenplay Readers Portal.
                            It works once and expires in <strong>15 minutes</strong>.
                        </p>
                        <table cellpadding="0" cellspacing="0">
                            <tr>
                                <td style="border-radius:6px;background:#1f2937;">
                                    <a href="{{ $loginUrl }}"
                                       style="display:inline-block;padding:13px 28px;font-size:15px;font-weight:600;color:#ffffff;text-decoration:none;letter-spacing:0.01em;">
                                        Log in to the portal →
                                    </a>
                                </td>
                            </tr>
                        </table>
                        <p style="margin:24px 0 0;font-size:13px;color:#9ca3af;line-height:1.6;">
                            If you didn't request this, you can ignore this email — your account is safe.<br />
                            Link not working? Copy and paste this URL into your browser:<br />
                            <span style="color:#6b7280;word-break:break-all;">{{ $loginUrl }}</span>
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
