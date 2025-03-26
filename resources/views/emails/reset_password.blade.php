<p>You are receiving this email because we received a password reset request for your account.</p>

<p>Click this link to reset your password:</p>

<a href="{{ url('http://localhost:5009/reset-password?token=' . $token . '&email=' . $email) }}">Reset Password</a>

<p>If you did not request a password reset, no further action is required.</p>