<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Activate your SAMS account</title>
</head>

<body style="margin:0;padding:24px;background-color:#f5f6fb;font-family:'Segoe UI',Arial,sans-serif;color:#1c1c1c;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td align="center">
                <table role="presentation" width="520" cellpadding="0" cellspacing="0"
                    style="background:#ffffff;border-radius:20px;padding:40px 32px;box-shadow:0 15px 45px rgba(15,23,42,0.08);">
                    <tr>
                        <td style="text-align:center;">
                            <p style="font-size:14px;letter-spacing:0.1em;color:#7f56d9;margin:0 0 16px;">SAMS</p>
                            <h1 style="font-size:26px;margin:0 0 16px;color:#101828;">Welcome, {{ $user->name }}</h1>
                            <p style="font-size:15px;line-height:1.6;color:#475467;margin:0 0 24px;">
                                Tap the button below to activate your account and unlock your dashboard.
                            </p>
                            <a href="{{ config('app.frontend_url') }}/verify-email?code={{ urlencode($code) }}"
                                style="display:inline-block;padding:14px 32px;background:#7f56d9;color:#ffffff;text-decoration:none;border-radius:999px;font-weight:600;box-shadow:0 10px 20px rgba(127,86,217,0.35);">
                                Activate account
        </a>
                            <p style="font-size:12px;color:#98a2b3;margin:32px 0 0;">
                                This secure link expires in 24 hours. If the button doesn't work, copy and paste this URL into
                                your browser:
                            </p>
                            <p style="font-size:12px;color:#667085;word-break:break-all;margin:8px 0 0;">
                                {{ config('app.frontend_url') }}/verify-email?code={{ urlencode($code) }}
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>

</html>