<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Reset your SAMS password</title>
</head>

<body style="margin:0;padding:24px;background-color:#0f172a;font-family:'Segoe UI',Arial,sans-serif;color:#f8fafc;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td align="center">
                <table role="presentation" width="520" cellpadding="0" cellspacing="0"
                    style="background:#0b1120;border-radius:24px;padding:40px 32px;border:1px solid rgba(148,163,184,0.2);">
                    <tr>
                        <td style="text-align:center;">
                            <p style="font-size:14px;letter-spacing:0.2em;color:#38bdf8;margin:0 0 16px;">SAMS</p>
                            <h1 style="font-size:24px;margin:0 0 16px;color:#f1f5f9;">Password reset</h1>
                            <p style="font-size:15px;line-height:1.7;color:#cbd5f5;margin:0 0 24px;">
                                We received a request to reset the password for {{ $email }}. Click the button below to choose
                                a new one.
                            </p>
                            <a href="{{ config('app.frontend_url') }}/reset-password?token={{ urlencode($token) }}&email={{ urlencode($email) }}"
                                style="display:inline-block;padding:14px 32px;background:#38bdf8;color:#0f172a;text-decoration:none;border-radius:16px;font-weight:600;box-shadow:0 15px 30px rgba(56,189,248,0.35);">
                                Create a new password
                            </a>
                            <p style="font-size:12px;color:#94a3b8;margin:32px 0 0;">
                                This secure link expires in 30 minutes. If you didn't ask for this, just ignore this email—your
                                password won't change.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>

</html>