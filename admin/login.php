<?php
require_once dirname(__DIR__) . '/includes/data.php';

if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        $error = 'Security validation failed (CSRF token expired). Please refresh and try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        $loginResult = login_user($username, $password);
        if ($loginResult['success']) {
            $user = $loginResult['user'];
            header('Location: index.php?msg=' . urlencode('Welcome back, ' . $user['name'] . '!'));
            exit;
        } else {
            $error = $loginResult['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Admin Portal Login | Tabeeb Contractor</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@600;700&display=swap" rel="stylesheet">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" sizes="64x64" href="/images/favicon.png">

    <style>
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #041933 0%, #07274d 60%, #0d386b 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            color: #0f172a;
        }

        .login-card {
            background: #ffffff;
            border-radius: 20px;
            width: 100%;
            max-width: 440px;
            padding: 40px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .login-brand {
            text-align: center;
            margin-bottom: 28px;
        }

        .login-brand img {
            max-height: 48px;
            margin-bottom: 12px;
        }

        .login-brand h1 {
            font-family: 'Outfit', sans-serif;
            font-size: 24px;
            color: #07274d;
            margin-bottom: 6px;
        }

        .login-brand p {
            font-size: 13px;
            color: #64748b;
        }

        .error-alert {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 13px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #334155;
            margin-bottom: 8px;
        }

        .input-wrap {
            position: relative;
        }

        .form-input {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            font-size: 14px;
            color: #0f172a;
            outline: none;
            transition: all 0.2s ease;
            font-family: inherit;
        }

        .form-input:focus {
            border-color: #00b4d8;
            box-shadow: 0 0 0 3px rgba(0, 180, 216, 0.15);
        }

        .pw-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #64748b;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            padding: 4px 8px;
        }

        .btn-login {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #07274d, #00b4d8);
            border: none;
            border-radius: 10px;
            color: #ffffff;
            font-weight: 600;
            font-size: 15px;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(0, 180, 216, 0.25);
            margin-top: 8px;
        }

        .btn-login:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(0, 180, 216, 0.35);
        }

        .back-link {
            text-align: center;
            margin-top: 24px;
        }

        .back-link a {
            color: #64748b;
            text-decoration: none;
            font-size: 13px;
            transition: color 0.2s;
        }

        .back-link a:hover {
            color: #07274d;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-brand">
            <img src="/images/logo-horizontal.png?v=3.3" alt="Tabeeb Contractor">
            <h1>Admin Portal</h1>
            <p>Secure Portal Login</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="error-alert">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <?= csrf_input_field() ?>
            <div class="form-group">
                <label class="form-label" for="username">Username or Email</label>
                <div class="input-wrap">
                    <input type="text" id="username" name="username" class="form-input" placeholder="Enter username" required autofocus value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <div class="input-wrap">
                    <input type="password" id="password" name="password" class="form-input" placeholder="••••••••••••" required>
                    <button type="button" class="pw-toggle" id="togglePwBtn">Show</button>
                </div>
            </div>

            <button type="submit" class="btn-login">Sign In to Dashboard</button>
        </form>

        <div class="back-link">
            <a href="/">← Return to Public Website</a>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const togglePwBtn = document.getElementById('togglePwBtn');
            const pwInput = document.getElementById('password');

            if (togglePwBtn && pwInput) {
                togglePwBtn.addEventListener('click', () => {
                    const isPassword = (pwInput.type === 'password');
                    pwInput.type = isPassword ? 'text' : 'password';
                    togglePwBtn.textContent = isPassword ? 'Hide' : 'Show';
                });
            }
        });
    </script>
</body>
</html>
