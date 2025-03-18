<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Password Has Been Reset</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #4f46e5;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 5px 5px 0 0;
        }
        .content {
            padding: 20px;
            border: 1px solid #dddddd;
            border-top: none;
            border-radius: 0 0 5px 5px;
        }
        .password-box {
            background-color: #f3f4f6;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            text-align: center;
            border: 1px dashed #d1d5db;
        }
        .new-password {
            font-family: monospace;
            font-size: 20px;
            font-weight: bold;
            color: #4f46e5;
        }
        .footer {
            margin-top: 20px;
            font-size: 12px;
            color: #666666;
            text-align: center;
        }
        .warning {
            color: #b91c1c;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Password Reset Successful</h1>
    </div>
    <div class="content">
        <p>Hello,</p>
        <p>Your password has been reset successfully. Here is your new password:</p>
        
        <div class="password-box">
            <div class="new-password">{{ $newPassword }}</div>
        </div>
        
        <p class="warning">Please change this password after you log in for better security.</p>
        
        <p>For security reasons, we recommend:</p>
        <ul>
            <li>Log in as soon as possible with this temporary password</li>
            <li>Change your password immediately to something only you would know</li>
            <li>Do not share this password with anyone</li>
            <li>Delete this email after you've logged in successfully</li>
        </ul>
        
        <p>Regards,<br>Your Application Team</p>
    </div>
    <div class="footer">
        <p>If you did not request this password reset, please contact support immediately.</p>
    </div>
</body>
</html>