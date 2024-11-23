<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Activation Code</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            color: #333;
            padding: 20px;
        }
        .container {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
        }
        .activation-code {
            font-size: 18px;
            font-weight: bold;
            color: #007bff;
        }
        .footer {
            margin-top: 20px;
            font-size: 12px;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Hello,</h2>
        <p>Thank you for subscribing with us. Your activation code is:</p>
        <p class="activation-code">{{ $code }}</p>
        <p>Please use this code to activate your account.</p>

        <div class="footer">
            <p>If you did not request this, please ignore this email.</p>
            <p>&copy; {{ date('Y') }} Efficiecny Labs. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
