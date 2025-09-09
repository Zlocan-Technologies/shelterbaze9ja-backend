<!DOCTYPE html>
<html lang="en-US">
<head>
    <meta charset="utf-8">
    <title>Email Verification</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
            color: #333;
        }
        .container {
            max-width: 600px;
            background: #ffffff;
            margin: 40px auto;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        .otp {
            display: inline-block;
            background: #f0f0f0;
            font-size: 20px;
            font-weight: bold;
            padding: 10px 20px;
            border-radius: 6px;
            letter-spacing: 2px;
            margin: 20px 0;
        }
        p {
            line-height: 1.6;
        }
        .footer {
            font-size: 12px;
            color: #888;
            margin-top: 30px;
        }
    </style>
</head>
<body>

<div class="container">
    <h2>Hello {{ $user->name ?? 'there' }},</h2>

    <p>Thank you for signing up with <strong>{{ config('app.name') }}</strong>!</p>

    <p>Before we can complete your registration, please verify your email address.</p>

    <p>Use the OTP code below in your app to verify your email:</p>

    <div class="otp">{{ $code }}</div>

    <p><strong>Note:</strong> This code will expire in 10 minutes.</p>

    <p>If you didn’t request this, you can safely ignore this email.</p>

    <div class="footer">
        &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
    </div>
</div>

</body>
</html>
