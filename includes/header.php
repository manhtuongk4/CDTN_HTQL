<?php
if (!isset($pdo)) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/quan_ly_vat_tu/config/database.php';
}
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
requireLogin();

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

$currentLoginToken = $_SESSION['LOGIN_TOKEN'] ?? '';
$currentPathForLogin = ($_SERVER['REQUEST_URI'] ?? '/quan_ly_vat_tu/index.php');
$loginRedirectUrl = getBaseUrl() . '/login.php?force=1&redirect=' . rawurlencode($currentPathForLogin);

if (!function_exists('headerNormalizeAvatarPath')) {
    function headerNormalizeAvatarPath($path)
    {
        $path = trim((string)($path ?? ''));
        if ($path === '') {
            return '';
        }

        $path = str_replace('\\', '/', $path);
        $path = preg_replace('#^\./+#', '', $path);

        if (preg_match('/^https?:\/\//i', $path) || str_starts_with($path, 'data:')) {
            return $path;
        }

        if (str_starts_with($path, '/')) {
            return $path;
        }

        $path = preg_replace('#^(quan_ly_vat_tu|Quan_ly_vat_tu)/#i', '', ltrim($path, '/'));
        return getBaseUrl() . '/' . ltrim($path, '/');
    }
}

if (!function_exists('headerInitials')) {
    function headerInitials($name)
    {
        $name = trim((string)$name);
        if ($name === '') {
            return 'U';
        }

        $parts = preg_split('/\s+/u', $name);
        $first = mb_substr($parts[0], 0, 1, 'UTF-8');
        $last = count($parts) > 1 ? mb_substr($parts[count($parts) - 1], 0, 1, 'UTF-8') : '';

        return mb_strtoupper($first . $last, 'UTF-8');
    }
}

$currentHeaderUser = [
    'Hoten' => $_SESSION['Hoten'] ?? 'User',
    'VaiTro' => $_SESSION['VaiTro'] ?? 2,
    'Avatar' => $_SESSION['Avatar'] ?? ''
];

if (!empty($_SESSION['MaNV'])) {
    try {
        $stmtHeaderUser = $pdo->prepare('SELECT Hoten, VaiTro, Avatar FROM nhanvien WHERE MaNV = ? LIMIT 1');
        $stmtHeaderUser->execute([$_SESSION['MaNV']]);
        $dbHeaderUser = $stmtHeaderUser->fetch();

        if ($dbHeaderUser) {
            $currentHeaderUser = $dbHeaderUser;
            $_SESSION['Hoten'] = $dbHeaderUser['Hoten'];
            $_SESSION['VaiTro'] = $dbHeaderUser['VaiTro'];
            $_SESSION['Avatar'] = $dbHeaderUser['Avatar'] ?? '';
        }
    } catch (PDOException $e) {
        try {
            $stmtHeaderUser = $pdo->prepare('SELECT Hoten, VaiTro FROM nhanvien WHERE MaNV = ? LIMIT 1');
            $stmtHeaderUser->execute([$_SESSION['MaNV']]);
            $dbHeaderUser = $stmtHeaderUser->fetch();

            if ($dbHeaderUser) {
                $currentHeaderUser['Hoten'] = $dbHeaderUser['Hoten'];
                $currentHeaderUser['VaiTro'] = $dbHeaderUser['VaiTro'];
                $_SESSION['Hoten'] = $dbHeaderUser['Hoten'];
                $_SESSION['VaiTro'] = $dbHeaderUser['VaiTro'];
            }
        } catch (PDOException $ignored) {
            // Giữ thông tin trong session nếu không đọc được DB.
        }
    }
}

$currentHeaderAvatar = headerNormalizeAvatarPath($currentHeaderUser['Avatar'] ?? '');
$currentHeaderInitials = headerInitials($currentHeaderUser['Hoten'] ?? 'User');
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) : 'Hệ thống quản lý vật tư y tế'; ?></title>

    <script>
        (function() {
            const serverToken = <?php echo json_encode($currentLoginToken); ?>;
            const loginUrl = <?php echo json_encode($loginRedirectUrl); ?>;
            const tabToken = sessionStorage.getItem('qlyvt_session_token');

            if (!serverToken || tabToken !== serverToken) {
                sessionStorage.removeItem('qlyvt_session_token');
                sessionStorage.removeItem('qlyvt_logged_in_at');
                window.location.replace(loginUrl);
            }
        })();
    </script>

    <script>
        (function() {
            const savedTheme = localStorage.getItem('app-theme') || 'light';
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();
    </script>

    <!-- CSS Files -->
    <link rel="stylesheet" href="<?php echo getBaseUrl(); ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?php echo getBaseUrl(); ?>/assets/css/dashboard.css">
    <link rel="stylesheet" href="<?php echo getBaseUrl(); ?>/assets/css/reports.css">
    <link rel="stylesheet" href="<?php echo getBaseUrl(); ?>/assets/css/inventory.css">
    <link rel="stylesheet" href="<?php echo getBaseUrl(); ?>/assets/css/login.css">

    <!-- Chart.js Library -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>

    <style>
        :root {
            --app-header-height: 68px;
            --app-sidebar-width: 286px;
            --app-sidebar-collapsed-width: 86px;
        }

        :root[data-theme="dark"] {
            --light-bg: #0f172a;
            --text-dark: #e5e7eb;
            --text-light: #9ca3af;
            --border-color: #263244;
            --shadow: 0 8px 24px rgba(0, 0, 0, .28);
            --shadow-lg: 0 18px 45px rgba(0, 0, 0, .35);
        }

        :root[data-theme="dark"] body {
            background: #0f172a !important;
            color: #d1d5db;
        }

        :root[data-theme="dark"] .card,
        :root[data-theme="dark"] .users-card,
        :root[data-theme="dark"] .debts-card,
        :root[data-theme="dark"] .kt-card,
        :root[data-theme="dark"] .report-table,
        :root[data-theme="dark"] .chart-wrapper,
        :root[data-theme="dark"] .stat-card-large,
        :root[data-theme="dark"] .users-stat-card,
        :root[data-theme="dark"] .debts-stat-card,
        :root[data-theme="dark"] .kt-stat,
        :root[data-theme="dark"] .summary-card {
            background: #111827 !important;
            border-color: #263244 !important;
            color: #d1d5db;
        }

        :root[data-theme="dark"] .card-header,
        :root[data-theme="dark"] .card-body,
        :root[data-theme="dark"] .users-card-header,
        :root[data-theme="dark"] .debts-card-header,
        :root[data-theme="dark"] .kt-card-head,
        :root[data-theme="dark"] .kt-card-body,
        :root[data-theme="dark"] .report-table-header {
            background: #111827 !important;
            border-color: #263244 !important;
        }

        :root[data-theme="dark"] input,
        :root[data-theme="dark"] select,
        :root[data-theme="dark"] textarea,
        :root[data-theme="dark"] .users-input,
        :root[data-theme="dark"] .users-select,
        :root[data-theme="dark"] .debts-input,
        :root[data-theme="dark"] .debts-select,
        :root[data-theme="dark"] .kt-input,
        :root[data-theme="dark"] .kt-select,
        :root[data-theme="dark"] .kt-textarea {
            background: #0b1220 !important;
            border-color: #263244 !important;
            color: #e5e7eb !important;
        }

        :root[data-theme="dark"] table th,
        :root[data-theme="dark"] .table th,
        :root[data-theme="dark"] .users-table th,
        :root[data-theme="dark"] .debts-table th,
        :root[data-theme="dark"] .kt-table th {
            background: #0b1220 !important;
            color: #9ca3af !important;
            border-color: #263244 !important;
        }

        :root[data-theme="dark"] table td,
        :root[data-theme="dark"] .table td,
        :root[data-theme="dark"] .users-table td,
        :root[data-theme="dark"] .debts-table td,
        :root[data-theme="dark"] .kt-table td {
            border-color: #263244 !important;
            color: #d1d5db !important;
        }

        :root[data-theme="dark"] tr:hover td,
        :root[data-theme="dark"] .table tr:hover td,
        :root[data-theme="dark"] .users-table tr:hover td,
        :root[data-theme="dark"] .debts-table tr:hover td,
        :root[data-theme="dark"] .kt-table tr:hover td {
            background: #172033 !important;
        }

        body {
            min-height: 100vh;
        }

        .navbar {
            min-height: var(--app-header-height);
            position: sticky;
            top: 0;
            z-index: 1000;
            background: rgba(255, 255, 255, .96) !important;
            backdrop-filter: blur(14px);
            border-bottom: 1px solid var(--border-color);
            box-shadow: 0 6px 22px rgba(15, 23, 42, .06);
        }

        :root[data-theme="dark"] .navbar {
            background: rgba(17, 24, 39, .96) !important;
            border-color: #263244;
        }

        .navbar-brand {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-size: 18px;
            font-weight: 700;
            color: var(--text-dark);
        }

        .quick-search {
            position: relative;
            display: flex;
            align-items: center;
            min-width: 320px;
        }

        .navbar-search {
            height: 42px;
            border-radius: 12px !important;
            padding: 0 44px 0 16px !important;
            min-width: 320px;
            border: 1px solid #cbd5e1 !important;
            background: #ffffff !important;
            color: #1f2937 !important;
            box-shadow: 0 2px 8px rgba(15, 23, 42, .06);
            outline: none;
            transition: all .25s;
        }

        .navbar-search::placeholder {
            color: #64748b;
        }

        .navbar-search:focus {
            border-color: var(--primary-color) !important;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, .16), 0 4px 12px rgba(15, 23, 42, .08);
        }

        .quick-search-btn {
            position: absolute;
            right: 6px;
            width: 32px;
            height: 32px;
            border: 0;
            border-radius: 10px;
            background: rgba(102, 126, 234, .12);
            color: var(--primary-color);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all .25s;
        }

        .quick-search-btn:hover {
            background: var(--primary-color);
            color: #fff;
        }

        :root[data-theme="dark"] .navbar-search {
            background: #0b1220 !important;
            border-color: #334155 !important;
            color: #e5e7eb !important;
            box-shadow: none;
        }

        :root[data-theme="dark"] .navbar-search::placeholder {
            color: #94a3b8;
        }

        :root[data-theme="dark"] .quick-search-btn {
            background: rgba(102, 126, 234, .18);
            color: #c7d2fe;
        }

        .navbar-right {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .theme-toggle {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            background: #fff;
            color: var(--text-dark);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 18px;
            transition: all .25s;
        }

        .theme-toggle:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow);
            border-color: var(--primary-color);
            color: var(--primary-color);
        }

        :root[data-theme="dark"] .theme-toggle {
            background: #0b1220;
            color: #e5e7eb;
            border-color: #263244;
        }

        .user-menu {
            display: inline-flex;
            align-items: center;
            gap: 11px;
            padding: 7px 10px;
            border-radius: 14px;
            transition: all .25s;
        }

        .user-menu:hover {
            background: var(--light-bg);
        }

        .navbar-avatar {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            flex-shrink: 0;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: #fff;
            font-size: 14px;
            font-weight: 700;
            line-height: 1;
            box-shadow: 0 2px 8px rgba(0, 0, 0, .12);
        }

        .navbar-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .user-dropdown {
            border-radius: 12px;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-lg);
            overflow: hidden;
        }

        :root[data-theme="dark"] .user-dropdown {
            background: #111827;
            border-color: #263244;
        }

        :root[data-theme="dark"] .user-dropdown a {
            color: #d1d5db;
        }

        :root[data-theme="dark"] .user-dropdown a:hover {
            background: #172033;
        }

        @media (max-width: 900px) {
            .quick-search {
                display: none;
            }

            .navbar-brand {
                font-size: 16px;
            }
        }
    </style>
</head>

<body>
    <!-- Navbar -->
    <nav class="navbar">
        <div class="navbar-left">
            <div class="navbar-brand">
                <span>💊</span>
                <span>Hệ Thống Vật tư Y tế</span>
            </div>
            <form class="quick-search" id="quickSearchForm" autocomplete="off">
                <input type="search" class="navbar-search" id="quickSearchInput" placeholder="Tìm kiếm nhanh: sản phẩm, kho, công nợ..." aria-label="Tìm kiếm nhanh">
                <button type="submit" class="quick-search-btn" title="Tìm kiếm" aria-label="Tìm kiếm">⌕</button>
            </form>
        </div>
        <div class="navbar-right">
            <button type="button" class="theme-toggle" id="themeToggle" title="Chuyển sáng/tối" aria-label="Chuyển sáng/tối">
                <span id="themeToggleIcon">🌙</span>
            </button>

            <div class="user-menu" onclick="toggleUserMenu()">
                <div class="navbar-avatar">
                    <?php if ($currentHeaderAvatar): ?>
                        <img src="<?php echo htmlspecialchars($currentHeaderAvatar); ?>" alt="<?php echo htmlspecialchars($currentHeaderUser['Hoten']); ?>">
                    <?php else: ?>
                        <?php echo htmlspecialchars($currentHeaderInitials); ?>
                    <?php endif; ?>
                </div>
                <div>
                    <div style="font-size: 14px; font-weight: 600; color: var(--text-dark);"><?php echo htmlspecialchars($currentHeaderUser['Hoten']); ?></div>
                    <div style="font-size: 12px; color: var(--text-light);"><?php echo getRoleName($currentHeaderUser['VaiTro']); ?></div>
                </div>
                <div style="font-size: 11px; margin-left: 2px; color: var(--text-light);">▼</div>
            </div>
            <div id="userDropdown" class="user-dropdown">
                <a href="<?php echo getBaseUrl(); ?>/modules/auth/change_password.php">🔐 Đổi mật khẩu</a>
                <a href="<?php echo getBaseUrl(); ?>/modules/auth/logout.php" class="logout">🚪 Đăng xuất</a>
            </div>
        </div>
    </nav>

    <script>
        window.toggleUserMenu = window.toggleUserMenu || function() {
            const dropdown = document.getElementById('userDropdown');
            if (dropdown) dropdown.classList.toggle('show');
        };

        document.addEventListener('click', function(event) {
            const menu = document.querySelector('.user-menu');
            const dropdown = document.getElementById('userDropdown');
            if (dropdown && menu && !menu.contains(event.target) && !dropdown.contains(event.target)) {
                dropdown.classList.remove('show');
            }
        });

        (function() {
            const form = document.getElementById('quickSearchForm');
            const input = document.getElementById('quickSearchInput');
            const baseUrl = <?php echo json_encode(getBaseUrl()); ?>;

            const routes = [{
                    keys: ['trang chủ', 'dashboard', 'home', 'tong quan', 'tổng quan'],
                    url: '/index.php'
                },
                {
                    keys: ['nhân sự', 'nhan su', 'user', 'người dùng', 'nguoi dung', 'tài khoản', 'tai khoan'],
                    url: '/modules/admin/manage_users.php?search='
                },
                {
                    keys: ['sản phẩm', 'san pham', 'vật tư', 'vat tu', 'thuốc', 'thuoc'],
                    url: '/modules/catalog/products.php?search='
                },
                {
                    keys: ['danh mục', 'danh muc', 'loại sản phẩm', 'loai san pham', 'nhóm sản phẩm', 'nhom san pham'],
                    url: '/modules/catalog/categories.php?search='
                },
                {
                    keys: ['đơn vị', 'don vi', 'đơn vị tính', 'don vi tinh'],
                    url: '/modules/catalog/units.php?search='
                },
                {
                    keys: ['địa điểm', 'dia diem', 'tỉnh', 'tinh', 'phường', 'phuong', 'xã', 'xa'],
                    url: '/modules/catalog/locations.php?search='
                },
                {
                    keys: ['nhà cung cấp', 'nha cung cap', 'supplier', 'ncc'],
                    url: '/modules/partners/suppliers.php?search='
                },
                {
                    keys: ['khách hàng', 'khach hang', 'bệnh viện', 'benh vien', 'hospital'],
                    url: '/modules/partners/hospitals.php?search='
                },
                {
                    keys: ['nhập kho', 'nhap kho', 'đơn nhập', 'don nhap'],
                    url: '/modules/warehouse/import_order.php?search='
                },
                {
                    keys: ['xuất kho', 'xuat kho', 'đơn xuất', 'don xuat'],
                    url: '/modules/warehouse/export_order.php?search='
                },
                {
                    keys: ['tồn kho', 'ton kho', 'kiểm kê', 'kiem ke', 'current stock'],
                    url: '/modules/warehouse/inventory.php?search='
                },
                {
                    keys: ['cận date', 'can date', 'hết hạn', 'het han', 'cảnh báo', 'canh bao'],
                    url: '/modules/warehouse/alerts.php?search='
                },
                {
                    keys: ['doanh thu', 'chi phí', 'chi phi', 'thuế', 'thue', 'lợi nhuận', 'loi nhuan'],
                    url: '/modules/reports/revenue_costs.php'
                },
                {
                    keys: ['công nợ', 'cong no', 'debt'],
                    url: '/modules/reports/debts.php?search='
                }
            ];

            function normalize(value) {
                return String(value || '')
                    .toLowerCase()
                    .normalize('NFD')
                    .replace(/[\u0300-\u036f]/g, '')
                    .replace(/đ/g, 'd')
                    .trim();
            }

            if (form && input) {
                form.addEventListener('submit', function(event) {
                    event.preventDefault();

                    const raw = input.value.trim();
                    if (!raw) {
                        input.focus();
                        return;
                    }

                    const query = encodeURIComponent(raw);
                    const normalized = normalize(raw);
                    let target = '/modules/catalog/products.php?search=' + query;

                    for (const route of routes) {
                        if (route.keys.some(key => normalized.includes(normalize(key)))) {
                            target = route.url.endsWith('=') ? route.url + query : route.url;
                            break;
                        }
                    }

                    window.location.href = baseUrl + target;
                });
            }
        })();

        (function() {
            const toggle = document.getElementById('themeToggle');
            const icon = document.getElementById('themeToggleIcon');

            function applyTheme(theme) {
                document.documentElement.setAttribute('data-theme', theme);
                localStorage.setItem('app-theme', theme);
                if (icon) icon.textContent = theme === 'dark' ? '☀️' : '🌙';
            }

            applyTheme(localStorage.getItem('app-theme') || 'light');

            if (toggle) {
                toggle.addEventListener('click', function() {
                    const current = document.documentElement.getAttribute('data-theme') || 'light';
                    applyTheme(current === 'dark' ? 'light' : 'dark');
                });
            }
        })();
    </script>

    <div class="container">