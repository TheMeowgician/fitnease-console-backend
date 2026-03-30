<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject }}</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f4f4f5; font-family: Arial, Helvetica, sans-serif; -webkit-font-smoothing: antialiased;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color: #f4f4f5;">
        <tr>
            <td align="center" style="padding: 40px 20px;">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="max-width: 600px; width: 100%; background-color: #ffffff; border-radius: 12px; overflow: hidden;">

                    <!-- Header -->
                    <tr>
                        <td style="background-color: #4CAF50; padding: 32px 40px; text-align: center;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 28px; font-weight: bold; letter-spacing: 1px;">FitNEase</h1>
                            <p style="margin: 8px 0 0; color: rgba(255,255,255,0.85); font-size: 13px;">Your Fitness Companion</p>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding: 32px 40px; color: #333333; font-size: 15px; line-height: 1.6;">
                            {!! $body !!}
                        </td>
                    </tr>

                    <!-- Button (optional) -->
                    @if(!empty($buttonText) && !empty($buttonUrl))
                    <tr>
                        <td style="padding: 0 40px 32px; text-align: center;">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center">
                                <tr>
                                    <td style="background-color: #4CAF50; border-radius: 8px;">
                                        <a href="{{ $buttonUrl }}" target="_blank" style="display: inline-block; color: #ffffff; text-decoration: none; padding: 14px 32px; font-size: 16px; font-weight: bold; font-family: Arial, Helvetica, sans-serif;">{{ $buttonText }}</a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    @endif

                    <!-- Footer -->
                    <tr>
                        <td style="padding: 24px 40px; background-color: #f8faf8; border-top: 1px solid #e8e8e8; text-align: center;">
                            <p style="margin: 0; color: #999999; font-size: 12px;">FitNEase &mdash; Re:Coders</p>
                            <p style="margin: 4px 0 0; color: #999999; font-size: 11px;">This email was sent from the FitNEase team.</p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
