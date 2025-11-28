<!DOCTYPE html>
<html lang="en" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Reset your SAMS password</title>
    <style type="text/css">
        /* Reset styles */
        body,
        table,
        td,
        a {
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }

        table,
        td {
            mso-table-lspace: 0pt;
            mso-table-rspace: 0pt;
        }

        img {
            -ms-interpolation-mode: bicubic;
            border: 0;
            height: auto;
            line-height: 100%;
            outline: none;
            text-decoration: none;
        }

        table {
            border-collapse: collapse !important;
        }

        body {
            height: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
            font-family: 'Segoe UI', Helvetica, Arial, sans-serif;
            background-color: #0f172a;
            color: #f8fafc;
        }

        /* Mobile styles */
        @media screen and (max-width: 600px) {
            .email-container {
                width: 100% !important;
                margin: auto !important;
            }

            .fluid-img {
                width: 100% !important;
                max-width: 100% !important;
                height: auto !important;
            }

            .stack-column {
                display: block !important;
                width: 100% !important;
                max-width: 100% !important;
                direction: ltr !important;
            }

            .mobile-padding {
                padding-left: 20px !important;
                padding-right: 20px !important;
            }

            .mobile-font {
                font-size: 24px !important;
            }
        }
    </style>
    <!--[if gte mso 9]>
    <xml>
        <o:OfficeDocumentSettings>
            <o:AllowPNG/>
            <o:PixelsPerInch>96</o:PixelsPerInch>
        </o:OfficeDocumentSettings>
    </xml>
    <![endif]-->
</head>

<body style="margin: 0; padding: 0; background-color: #0f172a;">
    <center>
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%"
            style="background-color: #0f172a;">
            <tr>
                <td style="padding: 40px 10px;">
                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="600"
                        class="email-container"
                        style="background-color: #1e293b; border-radius: 16px; overflow: hidden; margin: auto; border: 1px solid #334155;">
                        
                        <!-- Header -->
                        <tr>
                            <td style="padding: 40px 40px 20px 40px; text-align: center;" class="mobile-padding">
                                <h2 style="margin: 0; color: #38bdf8; font-size: 24px; font-weight: 700; letter-spacing: 2px;">SAMS</h2>
                            </td>
                        </tr>

                        <!-- Body -->
                        <tr>
                            <td style="padding: 0 40px 40px 40px; text-align: center;" class="mobile-padding">
                                <h1 class="mobile-font" style="margin: 0 0 20px 0; font-size: 32px; font-weight: 600; color: #f1f5f9;">
                                    Password Reset
                                </h1>
                                <p style="margin: 0 0 30px 0; font-size: 16px; line-height: 1.6; color: #cbd5e1;">
                                    We received a request to reset your password. Click the button below to create a new one.
                                </p>

                                <!-- Button -->
                                <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin: auto;">
                                    <tr>
                                        <td style="border-radius: 50px; background: #38bdf8;">
                                            <a href="{{ config('app.frontend_url') }}/reset-password?code={{ urlencode($code) }}"
                                                style="background: #38bdf8; border: 1px solid #38bdf8; font-family: sans-serif; font-size: 16px; line-height: 1.1; text-align: center; text-decoration: none; display: block; border-radius: 50px; font-weight: bold; padding: 16px 32px; color: #0f172a;">
                                                <span style="color:#0f172a;">Reset Password</span>
                                            </a>
                                        </td>
                                    </tr>
                                </table>

                                <p style="margin: 40px 0 0 0; font-size: 14px; line-height: 1.5; color: #64748b;">
                                    This link expires in 30 minutes. If you didn't request a password reset, you can safely ignore this email.
                                </p>
                            </td>
                        </tr>

                        <!-- Footer -->
                        <tr>
                            <td style="padding: 20px; background-color: #0f172a; text-align: center; border-top: 1px solid #334155;">
                                <p style="margin: 0; font-size: 12px; color: #64748b;">
                                    &copy; {{ date('Y') }} SAMS. All rights reserved.
                                </p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </center>
</body>

</html>