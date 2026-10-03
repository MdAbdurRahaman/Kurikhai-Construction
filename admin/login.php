<?php
require_once dirname(__DIR__) . '/includes/data.php';

if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $user = verify_user_credentials($username, $password);
    if ($user) {
        session_regenerate_id(true);
        $_SESSION['tabeeb_admin_user'] = [
            'id' => $user['id'],
            'name' => $user['name'],
            'username' => $user['username'],
            'email' => $user['email'],
            'role' => $user['role']
        ];
        header('Location: index.php?msg=' . urlencode('Welcome back, ' . $user['name'] . '!'));
        exit;
    } else {
        $error = 'Invalid username or password. Please verify your credentials.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
            margin-bottom: 22px;
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
            font-family: inherit;
            color: #0f172a;
            outline: none;
            transition: all 0.2s ease;
        }

        .form-input:focus {
            border-color: #00b4d8;
            box-shadow: 0 0 0 3px rgba(0, 180, 216, 0.15);
        }

        .pw-toggle {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #94a3b8;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
        }

        .btn-login {
            width: 100%;
            padding: 13px 20px;
            background: #07274d;
            color: #ffffff;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s ease, transform 0.1s ease;
            margin-top: 8px;
        }

        .btn-login:hover {
            background: #0d386b;
            transform: translateY(-1px);
        }

        /* Default credentials helper box */
        .demo-creds-box {
            margin-top: 26px;
            padding: 16px;
            background: #f8fafc;
            border: 1px dashed #cbd5e1;
            border-radius: 12px;
            font-size: 12px;
            color: #475569;
        }

        .demo-creds-title {
            font-weight: 700;
            color: #07274d;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .demo-fill-btn {
            background: #e0f2fe;
            color: #0284c7;
            border: 1px solid #bae6fd;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
            cursor: pointer;
        }
        .demo-fill-btn:hover {
            background: #0284c7;
            color: #fff;
        }

        .creds-line {
            display: flex;
            justify-content: space-between;
            margin-bottom: 4px;
        }

        .creds-line code {
            font-family: monospace;
            background: #e2e8f0;
            padding: 2px 6px;
            border-radius: 4px;
            color: #0f172a;
        }

        .back-link {
            text-align: center;
            margin-top: 24px;
        }
        .back-link a {
            color: #64748b;
            font-size: 13px;
            text-decoration: none;
        }
        .back-link a:hover {
            color: #00b4d8;
        }
    </style>
</head>
<body>

    <div class="login-card">
        <div class="login-brand">
            <img src="/images/logo-horizontal.png?v=3.3" alt="Tabeeb Contractor">
            <h1>Admin Portal</h1>
            <p>Manage blog posts, users, and social share links</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="error-alert">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <div class="form-group">
                <label class="form-label" for="username">Username or Email</label>
                <div class="input-wrap">
                    <input type="text" id="username" name="username" class="form-input" placeholder="admin" required autofocus value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
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

        <!-- Convenient Default Credentials Box -->
        <div class="demo-creds-box">
            <div class="demo-creds-title">
                <span>🔑 Default Administrator Access:</span>
                <button type="button" class="demo-fill-btn" id="fillDemoBtn">Auto Fill</button>
            </div>
            <div class="creds-line">
                <span>Username:</span>
                <code>admin</code>
            </div>
            <div class="creds-line">
                <span>Password:</span>
                <code>TabeebAdmin@2026!</code>
            </div>
        </div>

        <div class="back-link">
            <a href="/">← Return to Public Website</a>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const togglePwBtn = document.getElementById('togglePwBtn');
            const pwInput = document.getElementById('password');
            const fillDemoBtn = document.getElementById('fillDemoBtn');
            const usernameInput = document.getElementById('username');

            if (togglePwBtn && pwInput) {
                togglePwBtn.addEventListener('click', () => {
                    const isPassword = (pwInput.type === 'password');
                    pwInput.type = isPassword ? 'text' : 'password';
                    togglePwBtn.textContent = isPassword ? 'Hide' : 'Show';
                });
            }

            if (fillDemoBtn && usernameInput && pwInput) {
                fillDemoBtn.addEventListener('click', () => {
                    usernameInput.value = 'admin';
                    pwInput.value = 'TabeebAdmin@2026!';
                });
            }
        });
    </script>
</body>
</html>
