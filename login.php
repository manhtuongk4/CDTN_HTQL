<?php
require_once 'config/database.php';

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax'
]);

session_start();

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

function e($value)
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function appHomeUrl()
{
    if (function_exists('getBaseUrl')) {
        return rtrim(getBaseUrl(), '/') . '/index.php';
    }

    return 'index.php';
}

function clearLoginSession()
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', $params['secure'] ?? false, $params['httponly'] ?? true);
    }

    session_destroy();
    session_start();
}

if (isset($_GET['force']) || isset($_GET['logout'])) {
    clearLoginSession();
}

$error = '';
$redirectTo = appHomeUrl();
$loginToken = '';
$loginSuccess = false;

// Luôn chuyển về trang chủ sau khi đăng nhập để tránh lỗi nhân đôi /quan_ly_vat_tu
// và tránh tự quay lại trang nội bộ cũ như products.php.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tenDangNhap = trim($_POST['username'] ?? '');
    $matKhau = (string)($_POST['password'] ?? '');
    $redirectTo = appHomeUrl();

    if ($tenDangNhap === '' || $matKhau === '') {
        $error = 'Vui lòng nhập đầy đủ tên đăng nhập và mật khẩu!';
    } else {
        try {
            $stmt = $pdo->prepare('SELECT * FROM nhanvien WHERE TenDangNhap = ? AND MatKhau = ? LIMIT 1');
            $stmt->execute([$tenDangNhap, $matKhau]);
            $user = $stmt->fetch();

            if ($user && (int)$user['TrangThai'] === 1) {
                session_regenerate_id(true);

                $loginToken = bin2hex(random_bytes(32));
                $_SESSION['MaNV'] = $user['MaNV'];
                $_SESSION['TenDangNhap'] = $user['TenDangNhap'];
                $_SESSION['Hoten'] = $user['Hoten'];
                $_SESSION['VaiTro'] = $user['VaiTro'];
                $_SESSION['SoDienThoai'] = $user['SoDienThoai'];
                $_SESSION['Avatar'] = $user['Avatar'] ?? '';
                $_SESSION['LOGIN_TOKEN'] = $loginToken;
                $_SESSION['LOGIN_AT'] = time();

                $loginSuccess = true;
            } else {
                $error = 'Tên đăng nhập hoặc mật khẩu không chính xác, hoặc tài khoản đã bị khóa!';
            }
        } catch (PDOException $e) {
            $error = 'Lỗi hệ thống: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập - Quản lý Vật tư Y tế</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #3e97ff;
            --primary-dark: #1b84ff;
            --secondary: #7c3aed;
            --success: #17c653;
            --danger: #f1416c;
            --warning: #f6c000;
            --text: #252f4a;
            --muted: #78829d;
            --border: #e4e6ef;
            --soft: #f5f8fa;
            --white: #ffffff;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            color: var(--text);
            background:
                radial-gradient(circle at 15% 15%, rgba(62, 151, 255, .26), transparent 28%),
                radial-gradient(circle at 85% 20%, rgba(124, 58, 237, .22), transparent 26%),
                linear-gradient(135deg, #eef6ff 0%, #f8f5ff 46%, #ffffff 100%);
            overflow-x: hidden;
        }

        .auth-shell {
            min-height: 100vh;
            display: grid;
            grid-template-columns: minmax(420px, .95fr) minmax(430px, 1.05fr);
            padding: 24px;
            gap: 24px;
        }

        .auth-hero {
            position: relative;
            overflow: hidden;
            border-radius: 30px;
            padding: 42px;
            min-height: calc(100vh - 48px);
            color: white;
            background:
                linear-gradient(135deg, rgba(27, 132, 255, .96), rgba(124, 58, 237, .94)),
                url('assets/images/auth-bg.webp');
            background-size: cover;
            background-position: center;
            box-shadow: 0 24px 60px rgba(30, 64, 175, .25);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .auth-hero::before,
        .auth-hero::after {
            content: '';
            position: absolute;
            border-radius: 999px;
            background: rgba(255, 255, 255, .14);
            filter: blur(.2px);
        }

        .auth-hero::before {
            width: 260px;
            height: 260px;
            top: -80px;
            right: -70px;
        }

        .auth-hero::after {
            width: 180px;
            height: 180px;
            bottom: 110px;
            left: -50px;
        }

        .brand {
            position: relative;
            z-index: 2;
            display: inline-flex;
            align-items: center;
            gap: 13px;
            font-size: 18px;
            font-weight: 800;
            letter-spacing: -.02em;
        }

        .brand-logo {
            width: 46px;
            height: 46px;
            border-radius: 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, .18);
            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, .2);
            font-size: 24px;
        }

        .hero-content {
            position: relative;
            z-index: 2;
            max-width: 610px;
        }

        .hero-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(255, 255, 255, .16);
            color: rgba(255, 255, 255, .92);
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 20px;
        }

        .hero-title {
            font-size: clamp(34px, 4vw, 56px);
            line-height: 1.05;
            letter-spacing: -.055em;
            margin: 0 0 18px;
            font-weight: 800;
        }

        .hero-text {
            max-width: 520px;
            font-size: 15px;
            line-height: 1.75;
            color: rgba(255, 255, 255, .82);
            margin: 0;
        }

        .hero-metrics {
            position: relative;
            z-index: 2;
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
            margin-top: 36px;
        }

        .metric-card {
            padding: 18px;
            border-radius: 20px;
            background: rgba(255, 255, 255, .14);
            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, .15);
            backdrop-filter: blur(14px);
        }

        .metric-card .value {
            font-size: 24px;
            font-weight: 800;
            margin-bottom: 5px;
        }

        .metric-card .label {
            font-size: 12px;
            color: rgba(255, 255, 255, .78);
            line-height: 1.4;
        }

        .auth-main {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 34px 16px;
        }

        .login-card {
            width: min(100%, 500px);
            background: rgba(255, 255, 255, .86);
            backdrop-filter: blur(18px);
            border: 1px solid rgba(255, 255, 255, .75);
            box-shadow: 0 24px 70px rgba(15, 23, 42, .12);
            border-radius: 30px;
            padding: 42px;
        }

        .login-heading {
            text-align: center;
            margin-bottom: 32px;
        }

        .login-icon {
            width: 70px;
            height: 70px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 22px;
            background: linear-gradient(135deg, rgba(62, 151, 255, .12), rgba(124, 58, 237, .14));
            font-size: 34px;
            margin-bottom: 18px;
        }

        .login-heading h1 {
            margin: 0 0 9px;
            font-size: 30px;
            font-weight: 800;
            letter-spacing: -.035em;
            color: var(--text);
        }

        .login-heading p {
            margin: 0;
            font-size: 14px;
            color: var(--muted);
            line-height: 1.55;
        }

        .alert {
            border-radius: 16px;
            padding: 14px 16px;
            margin-bottom: 22px;
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: center;
            font-size: 13px;
            font-weight: 600;
            line-height: 1.55;
        }

        .alert-danger {
            background: #fff5f8;
            color: #d9214e;
            border: 1px solid #ffd6e2;
        }

        .alert-success {
            background: #e8fff3;
            color: #047536;
            border: 1px solid #b8f7d4;
        }

        .alert-close {
            border: 0;
            background: transparent;
            color: inherit;
            cursor: pointer;
            font-size: 20px;
            line-height: 1;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 13px;
            font-weight: 700;
            color: #3f4254;
        }

        .input-wrap {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #a1a5b7;
            font-size: 16px;
        }

        .form-control {
            width: 100%;
            height: 52px;
            border: 1px solid #e1e3ea;
            border-radius: 16px;
            background: #f9f9f9;
            color: var(--text);
            padding: 0 16px 0 46px;
            outline: none;
            font: inherit;
            font-size: 14px;
            font-weight: 500;
            transition: all .2s ease;
        }

        .form-control:focus {
            border-color: var(--primary);
            background: #fff;
            box-shadow: 0 0 0 4px rgba(62, 151, 255, .11);
        }

        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            width: 34px;
            height: 34px;
            border: 0;
            background: transparent;
            cursor: pointer;
            color: #7e8299;
            border-radius: 10px;
        }

        .password-toggle:hover {
            background: #eef6ff;
            color: var(--primary);
        }

        .form-control.password {
            padding-right: 48px;
        }

        .login-btn {
            width: 100%;
            height: 52px;
            border: 0;
            border-radius: 16px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: #fff;
            font-size: 14px;
            font-weight: 800;
            letter-spacing: .01em;
            cursor: pointer;
            box-shadow: 0 14px 28px rgba(62, 151, 255, .28);
            transition: all .22s ease;
            margin-top: 8px;
        }

        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 18px 34px rgba(62, 151, 255, .36);
        }

        .login-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 18px;
            gap: 12px;
            color: var(--muted);
            font-size: 12px;
        }

        .security-note {
            margin-top: 26px;
            padding: 15px;
            border-radius: 18px;
            background: #f4f9ff;
            color: #5e6278;
            font-size: 12px;
            line-height: 1.6;
            border: 1px dashed #cfe6ff;
        }

        .footer {
            text-align: center;
            margin-top: 24px;
            color: var(--muted);
            font-size: 12px;
        }

        @media (max-width: 980px) {
            .auth-shell {
                grid-template-columns: 1fr;
                padding: 16px;
            }

            .auth-hero {
                min-height: auto;
                padding: 30px;
            }

            .hero-metrics {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 560px) {
            .login-card {
                padding: 28px 22px;
                border-radius: 24px;
            }

            .auth-main {
                padding: 18px 0;
            }
        }
    </style>

    <?php if ($loginSuccess): ?>
        <script>
            sessionStorage.setItem('qlyvt_session_token', <?php echo json_encode($loginToken); ?>);
            sessionStorage.setItem('qlyvt_logged_in_at', String(Date.now()));
            window.location.replace(<?php echo json_encode($redirectTo); ?>);
        </script>
        <noscript>
            <meta http-equiv="refresh" content="1;url=<?php echo e($redirectTo); ?>">
        </noscript>
    <?php endif; ?>
</head>

<body>
    <div class="auth-shell">
        <section class="auth-hero" aria-label="Giới thiệu hệ thống">
            <div class="brand">
                <div class="brand-logo">💊</div>
                <span>Vật tư Y tế</span>
            </div>

            <div class="hero-content">
                <div class="hero-pill">✨ Hệ thống quản lý kho y tế</div>
                <h2 class="hero-title">Kiểm soát tồn kho, đơn hàng và báo cáo trong một nơi.</h2>
                <p class="hero-text">
                    Theo dõi nhập kho, xuất kho, hàng cận date, đối tác và báo cáo tài chính với giao diện quản trị hiện đại, rõ ràng và bảo mật hơn.
                </p>

                <div class="hero-metrics">
                    <div class="metric-card">
                        <div class="value">24/7</div>
                        <div class="label">Theo dõi dữ liệu kho mọi lúc</div>
                    </div>
                    <div class="metric-card">
                        <div class="value">FIFO</div>
                        <div class="label">Ưu tiên lô gần hết hạn trước</div>
                    </div>
                    <div class="metric-card">
                        <div class="value">VAT</div>
                        <div class="label">Báo cáo doanh thu, chi phí, thuế</div>
                    </div>
                </div>
            </div>

            <div class="hero-text" style="position:relative;z-index:2;font-size:12px">
                © <?php echo date('Y'); ?> Hệ thống quản lý vật tư y tế
            </div>
        </section>

        <main class="auth-main">
            <section class="login-card">
                <div class="login-heading">
                    <div class="login-icon">🔐</div>
                    <h1>Đăng nhập</h1>
                    <p>Hệ thống cần đăng nhập lại để xác thực bảo vệ dữ liệu nội bộ.</p>
                </div>

                <?php if ($loginSuccess): ?>
                    <div class="alert alert-success">
                        <span>Đăng nhập thành công. Đang chuyển hướng...</span>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <span><?php echo e($error); ?></span>
                        <button class="alert-close" type="button" onclick="this.parentElement.remove();">×</button>
                    </div>
                <?php endif; ?>

                <form method="POST" autocomplete="off">
                    <input type="hidden" name="redirect" value="<?php echo e(appHomeUrl()); ?>">

                    <div class="form-group">
                        <label for="username">Tên đăng nhập</label>
                        <div class="input-wrap">
                            <span class="input-icon">👤</span>
                            <input class="form-control" type="text" id="username" name="username" placeholder="Nhập tên đăng nhập" required autofocus>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="password">Mật khẩu</label>
                        <div class="input-wrap">
                            <span class="input-icon">🔑</span>
                            <input class="form-control password" type="password" id="password" name="password" placeholder="Nhập mật khẩu" required>
                            <button class="password-toggle" type="button" id="togglePassword" aria-label="Hiện hoặc ẩn mật khẩu">👁</button>
                        </div>
                    </div>

                    <button class="login-btn" type="submit">Đăng nhập hệ thống</button>
                </form>

                <div class="login-meta">
                    <span>Session chỉ hợp lệ trong tab hiện tại</span>
                    <span>Không lưu đăng nhập</span>
                </div>

                <div class="security-note">
                    Khi đóng hệ thống, mở hệ thống mới hoặc trực tiếp một đường link nội bộ, hệ thống sẽ yêu cầu đăng nhập lại.
                </div>

                <div class="footer">Quản lý vật tư y tế • Phiên bản nội bộ</div>
            </section>
        </main>
    </div>

    <script>
        const togglePassword = document.getElementById('togglePassword');
        const password = document.getElementById('password');
        if (togglePassword && password) {
            togglePassword.addEventListener('click', function() {
                const isHidden = password.type === 'password';
                password.type = isHidden ? 'text' : 'password';
                this.textContent = isHidden ? '🙈' : '👁';
            });
        }
    </script>
</body>

</html>