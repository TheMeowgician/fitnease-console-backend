<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body style="margin: 0; padding: 0; background-color: #eef2f7; font-family: 'Poppins', Arial, Helvetica, sans-serif; -webkit-font-smoothing: antialiased;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color: #eef2f7;">
        <tr>
            <td align="center" style="padding: 40px 20px;">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="max-width: 600px; width: 100%; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,0.08);">

                    <!-- Header with blue background -->
                    <tr>
                        <td style="background-color: #4a9eca; padding: 40px 40px 30px; text-align: center;">
                            <img src="https://fitnease-console-frontend.vercel.app/fitnease-logo.png" alt="FitNEase" width="72" height="72" style="display: block; margin: 0 auto 16px; width: 72px; height: 72px; border-radius: 16px; background-color: #ffffff; padding: 8px;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 26px; font-weight: 700; letter-spacing: 0.5px;">FitNEase</h1>
                            <p style="margin: 6px 0 0; color: rgba(255,255,255,0.8); font-size: 13px; font-weight: 400;">Your Personal Tabata Companion</p>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="background-color: #ffffff; padding: 36px 40px; color: #333333; font-size: 15px; line-height: 1.7;">
                            {!! $body !!}
                        </td>
                    </tr>

                    <!-- Button (optional) -->
                    @if(!empty($buttonText) && !empty($buttonUrl))
                    <tr>
                        <td style="background-color: #ffffff; padding: 0 40px 36px; text-align: center;">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center">
                                <tr>
                                    <td style="background-color: #4a9eca; border-radius: 10px; box-shadow: 0 2px 8px rgba(74,158,202,0.3);">
                                        <a href="{{ $buttonUrl }}" target="_blank" style="display: inline-block; color: #ffffff; text-decoration: none; padding: 14px 36px; font-size: 15px; font-weight: 600; font-family: 'Poppins', Arial, Helvetica, sans-serif; letter-spacing: 0.3px;">{{ $buttonText }}</a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    @endif

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f7f9fb; padding: 24px 40px; text-align: center; border-top: 1px solid #e9eef3;">
                            <p style="margin: 0; color: #8896a6; font-size: 12px; font-weight: 500;">FitNEase &mdash; Re:Coders</p>
                            <p style="margin: 6px 0 0; color: #a8b4c0; font-size: 11px; font-weight: 400;">This email was sent from the FitNEase team.</p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
