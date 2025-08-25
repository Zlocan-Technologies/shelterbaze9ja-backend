<!DOCTYPE html>
<html lang="en-US">
<head>
    <meta charset="utf-8">
    <title>User Completed Profile</title>
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
    <h2>Hello,</h2>
    
    <p>User {{$user->full_name}} ({{$user->email}}) has completed their profile and requires verification..</p>

    <div class="footer">
        &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
    </div>
</div>

</body>
</html>
