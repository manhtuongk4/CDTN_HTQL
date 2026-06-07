<?php
$pageTitle = 'Quản lý nhân sự - Hệ thống quản lý vật tư y tế';
require_once $_SERVER['DOCUMENT_ROOT'] . '/quan_ly_vat_tu/config/database.php';
session_start();
requireLogin();

if (!isAdmin()) {
    header('Location: ' . getBaseUrl() . '/index.php');
    exit();
}

$success = '';
$error = '';
$action = $_GET['action'] ?? 'list';
$searchQuery = trim($_GET['search'] ?? '');
$filterRole = $_GET['role'] ?? '';
$filterStatus = $_GET['status'] ?? '';

function e($value)
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function columnExists(PDO $pdo, $table, $column)
{
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
        $stmt->execute([$column]);
        return (bool)$stmt->fetch();
    } catch (PDOException $e) {
        return false;
    }
}

function normalizeAvatarPath($path)
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
    return '/quan_ly_vat_tu/' . ltrim($path, '/');
}

function initials($name)
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

function avatarColorClass($id)
{
    $classes = ['primary', 'success', 'warning', 'danger', 'info'];
    return $classes[((int)$id) % count($classes)];
}

function uploadAvatar($inputName = 'avatar')
{
    if (empty($_FILES[$inputName]['name']) || $_FILES[$inputName]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($_FILES[$inputName]['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Không thể tải ảnh đại diện lên. Vui lòng thử lại.');
    }

    $maxSize = 3 * 1024 * 1024;
    if ($_FILES[$inputName]['size'] > $maxSize) {
        throw new RuntimeException('Ảnh đại diện không được vượt quá 3MB.');
    }

    $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
    $originalName = $_FILES[$inputName]['name'];
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    if (!in_array($ext, $allowedExts, true)) {
        throw new RuntimeException('Chỉ hỗ trợ ảnh .jpg, .jpeg, .png hoặc .webp.');
    }

    $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/quan_ly_vat_tu/assets/images/users/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }

    $fileName = 'user_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $targetPath = $uploadDir . $fileName;

    if (!move_uploaded_file($_FILES[$inputName]['tmp_name'], $targetPath)) {
        throw new RuntimeException('Không thể lưu ảnh đại diện vào thư mục assets/images/users.');
    }

    return 'assets/images/users/' . $fileName;
}

$hasAvatarColumn = columnExists($pdo, 'nhanvien', 'Avatar');

// Xử lý thêm/sửa/xóa nhân viên
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        try {
            $hoten = trim($_POST['hoten'] ?? '');
            $tenDangNhap = trim($_POST['ten_dang_nhap'] ?? '');
            $matKhau = trim($_POST['mat_khau'] ?? '');
            $soDienThoai = trim($_POST['so_dien_thoai'] ?? '');
            $vaiTro = (int)($_POST['vai_tro'] ?? 2);
            $avatarPath = $hasAvatarColumn ? uploadAvatar('avatar') : null;

            if ($hoten === '' || $tenDangNhap === '' || $matKhau === '' || $soDienThoai === '') {
                $error = 'Vui lòng điền đầy đủ tất cả các trường bắt buộc!';
            } else {
                $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM nhanvien WHERE TenDangNhap = ?');
                $stmt->execute([$tenDangNhap]);

                if ((int)$stmt->fetch()['count'] > 0) {
                    $error = 'Tên đăng nhập đã tồn tại!';
                } else {
                    if ($hasAvatarColumn) {
                        $stmt = $pdo->prepare('INSERT INTO nhanvien (Hoten, TenDangNhap, MatKhau, SoDienThoai, VaiTro, TrangThai, Avatar) VALUES (?, ?, ?, ?, ?, 1, ?)');
                        $stmt->execute([$hoten, $tenDangNhap, $matKhau, $soDienThoai, $vaiTro, $avatarPath]);
                    } else {
                        $stmt = $pdo->prepare('INSERT INTO nhanvien (Hoten, TenDangNhap, MatKhau, SoDienThoai, VaiTro, TrangThai) VALUES (?, ?, ?, ?, ?, 1)');
                        $stmt->execute([$hoten, $tenDangNhap, $matKhau, $soDienThoai, $vaiTro]);
                    }

                    $success = 'Thêm nhân viên thành công!';
                    $action = 'list';
                }
            }
        } catch (Throwable $e) {
            $error = 'Lỗi: ' . $e->getMessage();
        }
    } elseif ($_POST['action'] === 'edit') {
        try {
            $maNV = $_POST['ma_nv'] ?? '';
            $hoten = trim($_POST['hoten'] ?? '');
            $soDienThoai = trim($_POST['so_dien_thoai'] ?? '');
            $vaiTro = (int)($_POST['vai_tro'] ?? 2);
            $trangThai = (int)($_POST['trang_thai'] ?? 1);
            $currentAvatar = trim($_POST['current_avatar'] ?? '');
            $uploadedAvatar = $hasAvatarColumn ? uploadAvatar('avatar') : null;
            $avatarPath = $uploadedAvatar ?: $currentAvatar;

            if ($hoten === '' || $soDienThoai === '') {
                $error = 'Vui lòng điền đầy đủ tất cả các trường bắt buộc!';
            } else {
                if ($hasAvatarColumn) {
                    $stmt = $pdo->prepare('UPDATE nhanvien SET Hoten = ?, SoDienThoai = ?, VaiTro = ?, TrangThai = ?, Avatar = ? WHERE MaNV = ?');
                    $stmt->execute([$hoten, $soDienThoai, $vaiTro, $trangThai, $avatarPath, $maNV]);
                } else {
                    $stmt = $pdo->prepare('UPDATE nhanvien SET Hoten = ?, SoDienThoai = ?, VaiTro = ?, TrangThai = ? WHERE MaNV = ?');
                    $stmt->execute([$hoten, $soDienThoai, $vaiTro, $trangThai, $maNV]);
                }

                if ((int)$maNV === (int)$_SESSION['MaNV']) {
                    $_SESSION['Hoten'] = $hoten;
                    $_SESSION['VaiTro'] = $vaiTro;
                    if ($hasAvatarColumn) {
                        $_SESSION['Avatar'] = $avatarPath;
                    }
                }

                $success = 'Cập nhật nhân viên thành công!';
                $action = 'list';
            }
        } catch (Throwable $e) {
            $error = 'Lỗi: ' . $e->getMessage();
        }
    } elseif ($_POST['action'] === 'delete') {
        try {
            $maNV = $_POST['ma_nv'] ?? '';

            if ($maNV == $_SESSION['MaNV']) {
                $error = 'Bạn không thể xóa chính mình!';
            } else {
                $stmt = $pdo->prepare('DELETE FROM nhanvien WHERE MaNV = ?');
                $stmt->execute([$maNV]);
                $success = 'Xóa nhân viên thành công!';
                $action = 'list';
            }
        } catch (PDOException $e) {
            $error = 'Lỗi: ' . $e->getMessage();
        }
    }
}

// Lấy nhân viên cần sửa
$editEmployee = null;
if ($action === 'edit' && isset($_GET['id'])) {
    try {
        $stmt = $pdo->prepare('SELECT * FROM nhanvien WHERE MaNV = ?');
        $stmt->execute([$_GET['id']]);
        $editEmployee = $stmt->fetch();

        if (!$editEmployee) {
            $error = 'Không tìm thấy nhân viên cần chỉnh sửa.';
            $action = 'list';
        }
    } catch (PDOException $e) {
        $error = 'Lỗi: ' . $e->getMessage();
        $action = 'list';
    }
}

// Lấy danh sách nhân viên + filter
$whereConditions = [];
$params = [];

if ($searchQuery !== '') {
    $whereConditions[] = '(Hoten LIKE ? OR TenDangNhap LIKE ? OR SoDienThoai LIKE ?)';
    $params[] = "%$searchQuery%";
    $params[] = "%$searchQuery%";
    $params[] = "%$searchQuery%";
}

if ($filterRole !== '') {
    $whereConditions[] = 'VaiTro = ?';
    $params[] = $filterRole;
}

if ($filterStatus !== '') {
    $whereConditions[] = 'TrangThai = ?';
    $params[] = $filterStatus;
}

$whereClause = $whereConditions ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

try {
    $stmt = $pdo->prepare("SELECT * FROM nhanvien $whereClause ORDER BY MaNV DESC");
    $stmt->execute($params);
    $employees = $stmt->fetchAll();

    $statsStmt = $pdo->query('SELECT COUNT(*) AS total, SUM(CASE WHEN VaiTro = 1 THEN 1 ELSE 0 END) AS admins, SUM(CASE WHEN TrangThai = 1 THEN 1 ELSE 0 END) AS active, SUM(CASE WHEN TrangThai = 0 THEN 1 ELSE 0 END) AS locked FROM nhanvien');
    $stats = $statsStmt->fetch();
} catch (PDOException $e) {
    $error = 'Lỗi: ' . $e->getMessage();
    $employees = [];
    $stats = ['total' => 0, 'admins' => 0, 'active' => 0, 'locked' => 0];
}

include $_SERVER['DOCUMENT_ROOT'] . '/quan_ly_vat_tu/includes/header.php';
include $_SERVER['DOCUMENT_ROOT'] . '/quan_ly_vat_tu/includes/sidebar.php';
?>

<style>
    .users-page {
        animation: fadeIn .25s ease;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(6px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .users-toolbar {
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
        color: white;
        padding: 34px 38px;
        border-radius: 8px;
        margin-bottom: 30px;
        box-shadow: var(--shadow-lg);
        display: flex;
        justify-content: space-between;
        gap: 20px;
        align-items: center;
        flex-wrap: wrap;
    }

    .users-toolbar h1 {
        font-size: 28px;
        line-height: 1.25;
        margin: 0 0 8px;
        font-weight: 700;
    }

    .users-toolbar p {
        margin: 0;
        font-size: 14px;
        opacity: .9;
    }

    .users-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    .btn-dashboard {
        border: none;
        border-radius: 6px;
        padding: 10px 16px;
        font-size: 13px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        text-decoration: none;
        cursor: pointer;
        transition: all .3s;
        white-space: nowrap;
    }

    .btn-dashboard.primary {
        background: white;
        color: var(--primary-color);
    }

    .btn-dashboard.primary:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow);
    }

    .btn-dashboard.solid {
        background: var(--primary-color);
        color: white;
    }

    .btn-dashboard.solid:hover {
        background: var(--secondary-color);
        color: white;
    }

    .btn-dashboard.light {
        background: white;
        color: var(--text-dark);
        border: 1px solid var(--border-color);
    }

    .btn-dashboard.light:hover {
        background: var(--light-bg);
        color: var(--primary-color);
    }

    .btn-dashboard.danger {
        background: rgba(245, 101, 101, .12);
        color: var(--danger-color);
    }

    .btn-dashboard.danger:hover {
        background: var(--danger-color);
        color: white;
    }

    .btn-dashboard.info {
        background: rgba(102, 126, 234, .12);
        color: var(--primary-color);
    }

    .btn-dashboard.info:hover {
        background: var(--primary-color);
        color: white;
    }

    .btn-dashboard.sm {
        padding: 7px 11px;
        font-size: 12px;
    }

    .users-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .users-stat-card {
        background: white;
        border-radius: 8px;
        box-shadow: var(--shadow);
        padding: 22px;
        display: flex;
        align-items: center;
        gap: 16px;
        border: 2px solid transparent;
        position: relative;
        overflow: hidden;
        transition: all .3s;
    }

    .users-stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
    }

    .users-stat-card.success::before {
        background: var(--success-color);
    }

    .users-stat-card.warning::before {
        background: var(--warning-color);
    }

    .users-stat-card.danger::before {
        background: var(--danger-color);
    }

    .users-stat-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--shadow-lg);
        border-color: var(--primary-color);
    }

    .users-stat-icon {
        width: 58px;
        height: 58px;
        border-radius: 12px;
        display: flex;
        justify-content: center;
        align-items: center;
        flex-shrink: 0;
        font-size: 26px;
        background: rgba(102, 126, 234, .15);
        color: var(--primary-color);
    }

    .users-stat-card.success .users-stat-icon {
        background: rgba(72, 187, 120, .15);
        color: var(--success-color);
    }

    .users-stat-card.warning .users-stat-icon {
        background: rgba(237, 137, 54, .15);
        color: var(--warning-color);
    }

    .users-stat-card.danger .users-stat-icon {
        background: rgba(245, 101, 101, .15);
        color: var(--danger-color);
    }

    .users-stat-content h3 {
        margin: 0 0 7px;
        color: var(--text-light);
        font-size: 12px;
        text-transform: uppercase;
        font-weight: 600;
        letter-spacing: .5px;
    }

    .users-stat-content .value {
        color: var(--text-dark);
        font-size: 24px;
        font-weight: 700;
        line-height: 1;
    }

    .users-stat-content .hint {
        margin-top: 7px;
        color: var(--text-light);
        font-size: 12px;
    }

    .users-card {
        background: white;
        border-radius: 8px;
        box-shadow: var(--shadow);
        margin-bottom: 30px;
        overflow: hidden;
    }

    .users-card-header {
        padding: 20px 24px;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        flex-wrap: wrap;
    }

    .users-card-header h2 {
        margin: 0;
        font-size: 18px;
        font-weight: 600;
        color: var(--text-dark);
    }

    .users-card-header p {
        margin: 5px 0 0;
        font-size: 13px;
        color: var(--text-light);
    }

    .users-card-body {
        padding: 24px;
    }

    .users-filter {
        display: grid;
        grid-template-columns: 1.6fr 1fr 1fr auto auto;
        gap: 12px;
        align-items: end;
    }

    .users-form-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 18px;
    }

    .users-form-grid.one {
        grid-template-columns: 1fr;
    }

    .users-field label {
        display: block;
        margin-bottom: 7px;
        color: var(--text-dark);
        font-size: 13px;
        font-weight: 600;
    }

    .users-field label.required::after {
        content: ' *';
        color: var(--danger-color);
    }

    .users-input,
    .users-select {
        width: 100%;
        height: 42px;
        border: 1px solid var(--border-color);
        border-radius: 6px;
        padding: 9px 12px;
        font-size: 14px;
        color: var(--text-dark);
        background: white;
        outline: none;
        transition: all .3s;
    }

    .users-input:focus,
    .users-select:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(102, 126, 234, .12);
    }

    .users-avatar-editor {
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 16px;
        border: 1px dashed var(--border-color);
        border-radius: 8px;
        background: var(--light-bg);
    }

    .user-avatar {
        width: 46px;
        height: 46px;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        color: white;
        font-size: 14px;
        font-weight: 700;
        flex-shrink: 0;
        box-shadow: inset 0 0 0 1px rgba(255, 255, 255, .18);
    }

    .user-avatar.large {
        width: 72px;
        height: 72px;
        border-radius: 16px;
        font-size: 22px;
    }

    .user-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }

    .user-avatar.primary {
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    }

    .user-avatar.success {
        background: linear-gradient(135deg, var(--success-color), #38a169);
    }

    .user-avatar.warning {
        background: linear-gradient(135deg, var(--warning-color), #dd6b20);
    }

    .user-avatar.danger {
        background: linear-gradient(135deg, var(--danger-color), #c53030);
    }

    .user-avatar.info {
        background: linear-gradient(135deg, var(--info-color), #3182ce);
    }

    .avatar-upload-note {
        color: var(--text-light);
        font-size: 12px;
        margin-top: 6px;
        line-height: 1.5;
    }

    .users-table-wrap {
        overflow-x: auto;
    }

    .users-table {
        width: 100%;
        border-collapse: collapse;
    }

    .users-table th {
        text-align: left;
        color: var(--text-light);
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .4px;
        padding: 14px 16px;
        border-bottom: 1px solid var(--border-color);
        background: #fafafa;
        white-space: nowrap;
    }

    .users-table td {
        padding: 16px;
        border-bottom: 1px solid var(--border-color);
        color: var(--text-dark);
        vertical-align: middle;
        font-size: 14px;
    }

    .users-table tr:hover td {
        background: var(--light-bg);
    }

    .user-cell {
        display: flex;
        align-items: center;
        gap: 12px;
        min-width: 260px;
    }

    .user-name {
        color: var(--text-dark);
        font-size: 14px;
        font-weight: 600;
        margin-bottom: 3px;
    }

    .user-sub {
        color: var(--text-light);
        font-size: 12px;
    }

    .users-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 11px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        white-space: nowrap;
    }

    .users-badge.admin {
        background: rgba(245, 101, 101, .12);
        color: var(--danger-color);
    }

    .users-badge.staff {
        background: rgba(102, 126, 234, .12);
        color: var(--primary-color);
    }

    .users-badge.active {
        background: rgba(72, 187, 120, .14);
        color: var(--success-color);
    }

    .users-badge.locked {
        background: rgba(245, 101, 101, .12);
        color: var(--danger-color);
    }

    .users-actions-inline {
        display: flex;
        justify-content: flex-end;
        align-items: center;
        gap: 8px;
    }

    .users-alert {
        padding: 14px 16px;
        border-radius: 8px;
        margin-bottom: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        font-size: 14px;
        font-weight: 600;
    }

    .users-alert.success {
        background: rgba(72, 187, 120, .14);
        color: #276749;
        border-left: 4px solid var(--success-color);
    }

    .users-alert.danger {
        background: rgba(245, 101, 101, .13);
        color: #9b2c2c;
        border-left: 4px solid var(--danger-color);
    }

    .users-alert button {
        border: 0;
        background: transparent;
        color: inherit;
        font-size: 20px;
        cursor: pointer;
    }

    .users-empty {
        text-align: center;
        padding: 50px 20px;
    }

    .users-empty .icon {
        font-size: 48px;
        margin-bottom: 15px;
    }

    .users-empty h3 {
        color: var(--text-dark);
        margin: 0 0 8px;
        font-size: 16px;
        font-weight: 600;
    }

    .users-empty p {
        color: var(--text-light);
        margin: 0;
        font-size: 14px;
    }

    .card-footer.users-footer {
        border-top: 1px solid var(--border-color);
        padding: 18px 24px;
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        background: #fafafa;
    }

    @media (max-width: 992px) {

        .users-filter,
        .users-form-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 768px) {
        .users-toolbar {
            padding: 25px;
        }

        .users-toolbar h1 {
            font-size: 24px;
        }

        .users-actions,
        .btn-dashboard {
            width: 100%;
        }

        .users-card-header {
            align-items: flex-start;
        }

        .users-actions-inline {
            justify-content: flex-start;
        }
    }
</style>

<div class="users-page">
    <div class="users-toolbar">
        <div>
            <h1><?= $action === 'add' ? 'Thêm nhân sự mới' : ($action === 'edit' ? 'Cập nhật nhân sự' : 'Quản lý nhân sự') ?></h1>
            <p><?= $action === 'list' ? 'Quản lý tài khoản, vai trò, trạng thái hoạt động và quyền truy cập hệ thống.' : 'Cập nhật thông tin nhân viên và phân quyền tài khoản.' ?></p>
        </div>
        <div class="users-actions">
            <?php if ($action === 'list'): ?>
                <a href="?action=add" class="btn-dashboard primary">＋ Thêm nhân viên</a>
            <?php else: ?>
                <a href="<?= e(getBaseUrl()) ?>/modules/admin/manage_users.php" class="btn-dashboard primary">← Quay lại danh sách</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="users-alert success">
            <span>✅ <?= e($success) ?></span>
            <button type="button" onclick="this.parentElement.remove()">×</button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="users-alert danger">
            <span>❌ <?= e($error) ?></span>
            <button type="button" onclick="this.parentElement.remove()">×</button>
        </div>
    <?php endif; ?>

    <?php if ($action === 'add' || ($action === 'edit' && $editEmployee)): ?>
        <?php
        $currentAvatar = $hasAvatarColumn ? ($editEmployee['Avatar'] ?? '') : '';
        $avatarSrc = normalizeAvatarPath($currentAvatar);
        ?>
        <div class="users-card">
            <div class="users-card-header">
                <div>
                    <h2><?= $action === 'add' ? 'Thông tin nhân viên mới' : 'Thông tin nhân viên' ?></h2>
                    <p>Các trường có dấu * là bắt buộc.</p>
                </div>
            </div>

            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="<?= e($action) ?>">
                <?php if ($action === 'edit'): ?>
                    <input type="hidden" name="ma_nv" value="<?= e($editEmployee['MaNV']) ?>">
                    <input type="hidden" name="current_avatar" value="<?= e($currentAvatar) ?>">
                <?php endif; ?>

                <div class="users-card-body">
                    <div class="users-form-grid">
                        <div class="users-field">
                            <label for="hoten" class="required">Họ tên</label>
                            <input class="users-input" type="text" id="hoten" name="hoten" value="<?= e($editEmployee['Hoten'] ?? '') ?>" placeholder="VD: Nguyễn Văn A" required>
                        </div>

                        <?php if ($action === 'add'): ?>
                            <div class="users-field">
                                <label for="ten_dang_nhap" class="required">Tên đăng nhập</label>
                                <input class="users-input" type="text" id="ten_dang_nhap" name="ten_dang_nhap" placeholder="VD: nhanvien01" required>
                            </div>
                        <?php else: ?>
                            <div class="users-field">
                                <label>Tên đăng nhập</label>
                                <input class="users-input" type="text" value="<?= e($editEmployee['TenDangNhap'] ?? '') ?>" disabled>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($action === 'add'): ?>
                        <div class="users-form-grid one" style="margin-top:18px">
                            <div class="users-field">
                                <label for="mat_khau" class="required">Mật khẩu</label>
                                <input class="users-input" type="password" id="mat_khau" name="mat_khau" required>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="users-form-grid" style="margin-top:18px">
                        <div class="users-field">
                            <label for="so_dien_thoai" class="required">Số điện thoại</label>
                            <input class="users-input" type="tel" id="so_dien_thoai" name="so_dien_thoai" value="<?= e($editEmployee['SoDienThoai'] ?? '') ?>" placeholder="VD: 0987654321" required>
                        </div>

                        <div class="users-field">
                            <label for="vai_tro" class="required">Vai trò</label>
                            <select class="users-select" id="vai_tro" name="vai_tro" required>
                                <option value="1" <?= (($editEmployee['VaiTro'] ?? 2) == 1) ? 'selected' : '' ?>>Quản trị viên</option>
                                <option value="2" <?= (($editEmployee['VaiTro'] ?? 2) == 2) ? 'selected' : '' ?>>Nhân viên</option>
                            </select>
                        </div>
                    </div>

                    <?php if ($action === 'edit'): ?>
                        <div class="users-form-grid one" style="margin-top:18px">
                            <div class="users-field">
                                <label for="trang_thai" class="required">Trạng thái</label>

                                <?php if ((int)$editEmployee['VaiTro'] === 1): ?>
                                    <input type="hidden" name="trang_thai" value="1">
                                    <select class="users-select" id="trang_thai" disabled>
                                        <option value="1" selected>Hoạt động</option>
                                    </select>
                                    <div class="avatar-upload-note">
                                        Tài khoản quản trị viên không thể bị khóa.
                                    </div>
                                <?php else: ?>
                                    <select class="users-select" id="trang_thai" name="trang_thai" required>
                                        <option value="1" <?= (int)$editEmployee['TrangThai'] === 1 ? 'selected' : '' ?>>Hoạt động</option>
                                        <option value="0" <?= (int)$editEmployee['TrangThai'] === 0 ? 'selected' : '' ?>>Khóa</option>
                                    </select>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="users-field" style="margin-top:18px">
                        <label>Ảnh đại diện</label>
                        <div class="users-avatar-editor">
                            <div class="user-avatar large <?= e(avatarColorClass($editEmployee['MaNV'] ?? 1)) ?>" id="avatarPreviewBox">
                                <?php if ($avatarSrc): ?>
                                    <img id="avatarPreviewImage" src="<?= e($avatarSrc) ?>" alt="Avatar">
                                <?php else: ?>
                                    <span id="avatarPreviewText"><?= e(initials($editEmployee['Hoten'] ?? 'User')) ?></span>
                                <?php endif; ?>
                            </div>
                            <div style="flex:1">
                                <?php if ($hasAvatarColumn): ?>
                                    <input class="users-input" type="file" id="avatar" name="avatar" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                                    <div class="avatar-upload-note">Hỗ trợ .jpg, .jpeg, .png, .webp, tối đa 3MB. Ảnh được lưu vào <strong>assets/images/users/</strong>.</div>
                                <?php else: ?>
                                    <div class="avatar-upload-note">
                                        Avatar đang hiển thị theo chữ cái đầu của họ tên. Nếu muốn upload ảnh thật, hãy thêm cột <strong>Avatar VARCHAR(255) NULL</strong> vào bảng <strong>nhanvien</strong>; file này sẽ tự nhận diện và mở chức năng tải ảnh.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-footer users-footer">
                    <button type="submit" class="btn-dashboard solid">💾 Lưu thông tin</button>
                    <a href="<?= e(getBaseUrl()) ?>/modules/admin/manage_users.php" class="btn-dashboard light">Hủy</a>
                </div>
            </form>
        </div>
    <?php else: ?>
        <div class="users-stats">
            <div class="users-stat-card">
                <div class="users-stat-icon">👥</div>
                <div class="users-stat-content">
                    <h3>Tổng nhân sự</h3>
                    <div class="value"><?= number_format((int)$stats['total']) ?></div>
                    <div class="hint">Tài khoản trong hệ thống</div>
                </div>
            </div>
            <div class="users-stat-card success">
                <div class="users-stat-icon">✅</div>
                <div class="users-stat-content">
                    <h3>Đang hoạt động</h3>
                    <div class="value"><?= number_format((int)$stats['active']) ?></div>
                    <div class="hint">Có thể đăng nhập</div>
                </div>
            </div>
            <div class="users-stat-card warning">
                <div class="users-stat-icon">🛡️</div>
                <div class="users-stat-content">
                    <h3>Quản trị viên</h3>
                    <div class="value"><?= number_format((int)$stats['admins']) ?></div>
                    <div class="hint">Có toàn quyền quản trị</div>
                </div>
            </div>
            <div class="users-stat-card danger">
                <div class="users-stat-icon">🔒</div>
                <div class="users-stat-content">
                    <h3>Tài khoản khóa</h3>
                    <div class="value"><?= number_format((int)$stats['locked']) ?></div>
                    <div class="hint">Không thể truy cập</div>
                </div>
            </div>
        </div>

        <div class="users-card">
            <div class="users-card-header">
                <div>
                    <h2>Danh sách nhân sự</h2>
                    <!-- <p>Giao diện dạng customer-list, đồng bộ với dashboard trang chủ.</p> -->
                </div>
            </div>

            <div class="users-card-body">
                <form method="GET" class="users-filter">
                    <input type="hidden" name="action" value="list">
                    <div class="users-field">
                        <label>Tìm kiếm</label>
                        <input class="users-input" type="text" name="search" value="<?= e($searchQuery) ?>" placeholder="Tìm theo họ tên, username, số điện thoại...">
                    </div>
                    <div class="users-field">
                        <label>Vai trò</label>
                        <select class="users-select" name="role">
                            <option value="">Tất cả vai trò</option>
                            <option value="1" <?= $filterRole === '1' ? 'selected' : '' ?>>Quản trị viên</option>
                            <option value="2" <?= $filterRole === '2' ? 'selected' : '' ?>>Nhân viên</option>
                        </select>
                    </div>
                    <div class="users-field">
                        <label>Trạng thái</label>
                        <select class="users-select" name="status">
                            <option value="">Tất cả trạng thái</option>
                            <option value="1" <?= $filterStatus === '1' ? 'selected' : '' ?>>Hoạt động</option>
                            <option value="0" <?= $filterStatus === '0' ? 'selected' : '' ?>>Khóa</option>
                        </select>
                    </div>
                    <button class="btn-dashboard solid" type="submit">🔎 Lọc</button>
                    <a class="btn-dashboard light" href="<?= e(getBaseUrl()) ?>/modules/admin/manage_users.php">Đặt lại</a>
                </form>
            </div>

            <?php if (!empty($employees)): ?>
                <div class="users-table-wrap">
                    <table class="users-table">
                        <thead>
                            <tr>
                                <th>Nhân viên</th>
                                <th>Tên đăng nhập</th>
                                <th>Số điện thoại</th>
                                <th>Vai trò</th>
                                <th>Trạng thái</th>
                                <th style="text-align:right">Hành động</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($employees as $emp): ?>
                                <?php
                                $avatarPath = $hasAvatarColumn ? normalizeAvatarPath($emp['Avatar'] ?? '') : '';
                                $isCurrentUser = (int)$emp['MaNV'] === (int)$_SESSION['MaNV'];
                                ?>
                                <tr>
                                    <td>
                                        <div class="user-cell">
                                            <div class="user-avatar <?= e(avatarColorClass($emp['MaNV'])) ?>">
                                                <?php if ($avatarPath): ?>
                                                    <img src="<?= e($avatarPath) ?>" alt="<?= e($emp['Hoten']) ?>">
                                                <?php else: ?>
                                                    <?= e(initials($emp['Hoten'])) ?>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <div class="user-name"><?= e($emp['Hoten']) ?><?= $isCurrentUser ? ' <span class="user-sub">(Bạn)</span>' : '' ?></div>
                                                <div class="user-sub">Mã nhân viên #NV<?= e($emp['MaNV']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?= e($emp['TenDangNhap']) ?></td>
                                    <td><?= e($emp['SoDienThoai']) ?></td>
                                    <td>
                                        <span class="users-badge <?= (int)$emp['VaiTro'] === 1 ? 'admin' : 'staff' ?>">
                                            <?= (int)$emp['VaiTro'] === 1 ? '🛡️ Quản trị viên' : '👤 Nhân viên' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="users-badge <?= (int)$emp['TrangThai'] === 1 ? 'active' : 'locked' ?>">
                                            <?= (int)$emp['TrangThai'] === 1 ? '✅ Hoạt động' : '🔒 Khóa' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="users-actions-inline">
                                            <a href="?action=edit&id=<?= e($emp['MaNV']) ?>" class="btn-dashboard info sm">Sửa</a>
                                            <?php if (!$isCurrentUser): ?>
                                                <form method="POST" style="display:inline" onsubmit="return confirm('Bạn chắc chắn muốn xóa nhân viên này?');">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="ma_nv" value="<?= e($emp['MaNV']) ?>">
                                                    <button type="submit" class="btn-dashboard danger sm">Xóa</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="users-empty">
                    <div class="icon">👥</div>
                    <h3>Không tìm thấy nhân viên</h3>
                    <p>Thử thay đổi bộ lọc hoặc thêm nhân viên mới.</p>
                    <a href="?action=add" class="btn-dashboard solid" style="margin-top:16px">＋ Thêm nhân viên</a>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<script>
    document.querySelectorAll('.users-alert').forEach(function(alert) {
        setTimeout(function() {
            if (alert && alert.parentElement) alert.remove();
        }, 5000);
    });

    const avatarInput = document.getElementById('avatar');
    const avatarBox = document.getElementById('avatarPreviewBox');

    if (avatarInput && avatarBox) {
        avatarInput.addEventListener('change', function() {
            const file = this.files && this.files[0] ? this.files[0] : null;
            if (!file) return;

            const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
            if (!allowedTypes.includes(file.type)) {
                alert('Chỉ hỗ trợ ảnh JPG, PNG hoặc WEBP.');
                this.value = '';
                return;
            }

            if (file.size > 3 * 1024 * 1024) {
                alert('Ảnh đại diện không được vượt quá 3MB.');
                this.value = '';
                return;
            }

            const reader = new FileReader();
            reader.onload = function(e) {
                avatarBox.innerHTML = '<img src="' + e.target.result + '" alt="Avatar preview">';
            };
            reader.readAsDataURL(file);
        });
    }
</script>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/quan_ly_vat_tu/includes/footer.php'; ?>