<?php
$pageTitle = 'Quản lý đơn vị tính - Hệ thống quản lý vật tư y tế';
require_once $_SERVER['DOCUMENT_ROOT'] . '/quan_ly_vat_tu/config/database.php';
session_start();
requireLogin();

$success = '';
$error = '';
$action = $_GET['action'] ?? 'list';
$searchQuery = trim($_GET['search'] ?? '');

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

function unitInitial($name)
{
    $name = trim((string)$name);
    if ($name === '') {
        return 'ĐV';
    }
    return mb_strtoupper(mb_substr($name, 0, 1, 'UTF-8'), 'UTF-8');
}

function unitColorClass($id)
{
    $classes = ['primary', 'success', 'warning', 'danger', 'info'];
    return $classes[((int)$id) % count($classes)];
}

$sanPhamHasUnitColumn = columnExists($pdo, 'sanpham', 'MaDVT');

// Xử lý thêm/sửa/xóa đơn vị tính
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        try {
            $tenDVT = trim($_POST['ten_dvt'] ?? '');

            if ($tenDVT === '') {
                $error = 'Vui lòng nhập tên đơn vị tính!';
            } else {
                $stmt = $pdo->prepare('SELECT COUNT(*) AS total FROM donvitinh WHERE LOWER(TenDVT) = LOWER(?)');
                $stmt->execute([$tenDVT]);

                if ((int)$stmt->fetch()['total'] > 0) {
                    $error = 'Đơn vị tính này đã tồn tại!';
                } else {
                    $stmt = $pdo->prepare('INSERT INTO donvitinh (TenDVT) VALUES (?)');
                    $stmt->execute([$tenDVT]);
                    $success = 'Thêm đơn vị tính thành công!';
                    $action = 'list';
                }
            }
        } catch (PDOException $e) {
            $error = 'Lỗi: ' . $e->getMessage();
        }
    } elseif ($_POST['action'] === 'edit') {
        try {
            $maDVT = (int)($_POST['ma_dvt'] ?? 0);
            $tenDVT = trim($_POST['ten_dvt'] ?? '');

            if ($maDVT <= 0 || $tenDVT === '') {
                $error = 'Vui lòng nhập đầy đủ thông tin đơn vị tính!';
            } else {
                $stmt = $pdo->prepare('SELECT COUNT(*) AS total FROM donvitinh WHERE LOWER(TenDVT) = LOWER(?) AND MaDVT <> ?');
                $stmt->execute([$tenDVT, $maDVT]);

                if ((int)$stmt->fetch()['total'] > 0) {
                    $error = 'Tên đơn vị tính này đã được sử dụng!';
                } else {
                    $stmt = $pdo->prepare('UPDATE donvitinh SET TenDVT = ? WHERE MaDVT = ?');
                    $stmt->execute([$tenDVT, $maDVT]);
                    $success = 'Cập nhật đơn vị tính thành công!';
                    $action = 'list';
                }
            }
        } catch (PDOException $e) {
            $error = 'Lỗi: ' . $e->getMessage();
        }
    } elseif ($_POST['action'] === 'delete') {
        try {
            $maDVT = (int)($_POST['ma_dvt'] ?? 0);

            if ($maDVT <= 0) {
                $error = 'Đơn vị tính không hợp lệ!';
            } else {
                $usedProducts = 0;
                if ($sanPhamHasUnitColumn) {
                    $stmt = $pdo->prepare('SELECT COUNT(*) AS total FROM sanpham WHERE MaDVT = ?');
                    $stmt->execute([$maDVT]);
                    $usedProducts = (int)$stmt->fetch()['total'];
                }

                if ($usedProducts > 0) {
                    $error = 'Không thể xóa đơn vị tính vì đang có ' . $usedProducts . ' sản phẩm sử dụng.';
                } else {
                    $stmt = $pdo->prepare('DELETE FROM donvitinh WHERE MaDVT = ?');
                    $stmt->execute([$maDVT]);
                    $success = 'Xóa đơn vị tính thành công!';
                }
            }
        } catch (PDOException $e) {
            $error = 'Lỗi: ' . $e->getMessage();
        }
    }
}

// Lấy đơn vị tính cần sửa
$editUnit = null;
if ($action === 'edit' && isset($_GET['id'])) {
    try {
        $stmt = $pdo->prepare('SELECT * FROM donvitinh WHERE MaDVT = ?');
        $stmt->execute([(int)$_GET['id']]);
        $editUnit = $stmt->fetch();

        if (!$editUnit) {
            $error = 'Không tìm thấy đơn vị tính cần chỉnh sửa.';
            $action = 'list';
        }
    } catch (PDOException $e) {
        $error = 'Lỗi: ' . $e->getMessage();
        $action = 'list';
    }
}

// Lấy danh sách đơn vị tính + thống kê
$whereClause = '';
$params = [];

if ($searchQuery !== '') {
    $whereClause = 'WHERE dvt.TenDVT LIKE ? OR dvt.MaDVT LIKE ?';
    $params[] = "%$searchQuery%";
    $params[] = "%$searchQuery%";
}

try {
    if ($sanPhamHasUnitColumn) {
        $sql = "SELECT dvt.*, COALESCE(sp.SoSanPham, 0) AS SoSanPham
            FROM donvitinh dvt
            LEFT JOIN (SELECT MaDVT, COUNT(*) AS SoSanPham FROM sanpham GROUP BY MaDVT) sp ON dvt.MaDVT = sp.MaDVT
            $whereClause
            ORDER BY dvt.MaDVT DESC";
    } else {
        $sql = "SELECT dvt.*, 0 AS SoSanPham
            FROM donvitinh dvt
            $whereClause
            ORDER BY dvt.MaDVT DESC";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $units = $stmt->fetchAll();

    $statsStmt = $pdo->query('SELECT COUNT(*) AS total FROM donvitinh');
    $stats = $statsStmt->fetch();

    $usedUnits = 0;
    $usedProducts = 0;
    if ($sanPhamHasUnitColumn) {
        $usedUnitsStmt = $pdo->query('SELECT COUNT(DISTINCT MaDVT) AS total FROM sanpham WHERE MaDVT IS NOT NULL');
        $usedUnits = (int)$usedUnitsStmt->fetch()['total'];

        $usedProductsStmt = $pdo->query('SELECT COUNT(*) AS total FROM sanpham WHERE MaDVT IS NOT NULL');
        $usedProducts = (int)$usedProductsStmt->fetch()['total'];
    }
} catch (PDOException $e) {
    $error = 'Lỗi: ' . $e->getMessage();
    $units = [];
    $stats = ['total' => 0];
    $usedUnits = 0;
    $usedProducts = 0;
}

include $_SERVER['DOCUMENT_ROOT'] . '/quan_ly_vat_tu/includes/header.php';
include $_SERVER['DOCUMENT_ROOT'] . '/quan_ly_vat_tu/includes/sidebar.php';
?>

<style>
    .units-page {
        animation: unitsFadeIn .25s ease;
    }

    @keyframes unitsFadeIn {
        from {
            opacity: 0;
            transform: translateY(6px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .units-toolbar {
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
        color: #fff;
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

    .units-toolbar h1 {
        font-size: 28px;
        line-height: 1.25;
        margin: 0 0 8px;
        font-weight: 700;
    }

    .units-toolbar p {
        margin: 0;
        font-size: 14px;
        opacity: .9;
    }

    .units-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    .units-btn {
        border: 1px solid transparent;
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
        line-height: 1.3;
    }

    .units-btn.primary {
        background: #fff;
        color: var(--primary-color);
    }

    .units-btn.primary:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow);
    }

    .units-btn.solid {
        background: var(--primary-color);
        color: #fff;
        box-shadow: var(--shadow);
    }

    .units-btn.solid:hover {
        background: var(--secondary-color);
        color: #fff;
        transform: translateY(-1px);
        box-shadow: var(--shadow-lg);
    }

    .units-btn.light {
        background: #fff;
        color: var(--text-dark);
        border-color: var(--border-color);
    }

    .units-btn.light:hover {
        background: var(--light-bg);
        color: var(--primary-color);
        border-color: var(--primary-color);
    }

    .units-btn.danger {
        background: rgba(245, 101, 101, .12);
        color: var(--danger-color);
        border-color: rgba(245, 101, 101, .18);
    }

    .units-btn.danger:hover {
        background: var(--danger-color);
        color: #fff;
    }

    .units-btn.info {
        background: rgba(102, 126, 234, .12);
        color: var(--primary-color);
        border-color: rgba(102, 126, 234, .18);
    }

    .units-btn.info:hover {
        background: var(--primary-color);
        color: #fff;
    }

    .units-btn.sm {
        padding: 7px 11px;
        font-size: 12px;
    }

    .units-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .units-stat-card {
        background: #fff;
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

    .units-stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
    }

    .units-stat-card.success::before {
        background: var(--success-color);
    }

    .units-stat-card.warning::before {
        background: var(--warning-color);
    }

    .units-stat-card.danger::before {
        background: var(--danger-color);
    }

    .units-stat-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--shadow-lg);
        border-color: var(--primary-color);
    }

    .units-stat-icon {
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

    .units-stat-card.success .units-stat-icon {
        background: rgba(72, 187, 120, .15);
        color: var(--success-color);
    }

    .units-stat-card.warning .units-stat-icon {
        background: rgba(237, 137, 54, .15);
        color: var(--warning-color);
    }

    .units-stat-card.danger .units-stat-icon {
        background: rgba(245, 101, 101, .15);
        color: var(--danger-color);
    }

    .units-stat-content h3 {
        margin: 0 0 7px;
        color: var(--text-light);
        font-size: 12px;
        text-transform: uppercase;
        font-weight: 600;
        letter-spacing: .5px;
    }

    .units-stat-content .value {
        color: var(--text-dark);
        font-size: 24px;
        font-weight: 700;
        line-height: 1;
    }

    .units-stat-content .hint {
        margin-top: 7px;
        color: var(--text-light);
        font-size: 12px;
    }

    .units-grid {
        display: grid;
        grid-template-columns: minmax(320px, .9fr) minmax(0, 1.5fr);
        gap: 24px;
        align-items: start;
    }

    .units-card {
        background: #fff;
        border-radius: 8px;
        box-shadow: var(--shadow);
        margin-bottom: 30px;
        overflow: hidden;
    }

    .units-card-header {
        padding: 20px 24px;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        flex-wrap: wrap;
    }

    .units-card-header h2 {
        margin: 0;
        font-size: 18px;
        font-weight: 600;
        color: var(--text-dark);
    }

    .units-card-header p {
        margin: 5px 0 0;
        font-size: 13px;
        color: var(--text-light);
    }

    .units-card-body {
        padding: 24px;
    }

    .units-field label {
        display: block;
        margin-bottom: 7px;
        color: var(--text-dark);
        font-size: 13px;
        font-weight: 600;
    }

    .units-field label.required::after {
        content: ' *';
        color: var(--danger-color);
    }

    .units-input {
        width: 100%;
        height: 42px;
        border: 1px solid var(--border-color);
        border-radius: 6px;
        padding: 9px 12px;
        font-size: 14px;
        color: var(--text-dark);
        background: #fff;
        outline: none;
        transition: all .3s;
        font-family: inherit;
    }

    .units-input:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(102, 126, 234, .12);
    }

    .units-filter {
        display: grid;
        grid-template-columns: minmax(220px, 1fr) auto auto;
        gap: 12px;
        align-items: end;
    }

    .units-table-wrap {
        overflow-x: auto;
    }

    .units-table {
        width: 100%;
        border-collapse: collapse;
    }

    .units-table th {
        text-align: left;
        color: var(--text-light);
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .4px;
        padding: 14px 16px;
        border-bottom: 1px solid var(--border-color);
        background: var(--light-bg);
        white-space: nowrap;
    }

    .units-table td {
        padding: 16px;
        border-bottom: 1px solid var(--border-color);
        color: var(--text-dark);
        vertical-align: middle;
        font-size: 14px;
    }

    .units-table tr:hover td {
        background: var(--light-bg);
    }

    .unit-cell {
        display: flex;
        align-items: center;
        gap: 12px;
        min-width: 240px;
    }

    .unit-avatar {
        width: 46px;
        height: 46px;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-size: 17px;
        font-weight: 700;
        flex-shrink: 0;
        box-shadow: inset 0 0 0 1px rgba(255, 255, 255, .18);
    }

    .unit-avatar.primary {
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    }

    .unit-avatar.success {
        background: linear-gradient(135deg, var(--success-color), #38a169);
    }

    .unit-avatar.warning {
        background: linear-gradient(135deg, var(--warning-color), #dd6b20);
    }

    .unit-avatar.danger {
        background: linear-gradient(135deg, var(--danger-color), #c53030);
    }

    .unit-avatar.info {
        background: linear-gradient(135deg, var(--info-color), #3182ce);
    }

    .unit-name {
        color: var(--text-dark);
        font-size: 14px;
        font-weight: 600;
        margin-bottom: 3px;
    }

    .unit-sub {
        color: var(--text-light);
        font-size: 12px;
    }

    .units-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 11px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        white-space: nowrap;
    }

    .units-badge.primary {
        background: rgba(102, 126, 234, .12);
        color: var(--primary-color);
    }

    .units-badge.success {
        background: rgba(72, 187, 120, .14);
        color: var(--success-color);
    }

    .units-badge.muted {
        background: var(--light-bg);
        color: var(--text-light);
    }

    .units-actions-inline {
        display: flex;
        justify-content: flex-end;
        align-items: center;
        gap: 8px;
    }

    .units-alert {
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

    .units-alert.success {
        background: rgba(72, 187, 120, .14);
        color: #276749;
        border-left: 4px solid var(--success-color);
    }

    .units-alert.danger {
        background: rgba(245, 101, 101, .13);
        color: #9b2c2c;
        border-left: 4px solid var(--danger-color);
    }

    .units-alert button {
        border: 0;
        background: transparent;
        color: inherit;
        font-size: 20px;
        cursor: pointer;
    }

    .units-empty {
        text-align: center;
        padding: 50px 20px;
    }

    .units-empty .icon {
        font-size: 48px;
        margin-bottom: 15px;
    }

    .units-empty h3 {
        color: var(--text-dark);
        margin: 0 0 8px;
        font-size: 16px;
        font-weight: 600;
    }

    .units-empty p,
    .units-note {
        color: var(--text-light);
        margin: 0;
        font-size: 13px;
        line-height: 1.6;
    }

    .units-card-footer {
        border-top: 1px solid var(--border-color);
        padding: 18px 24px;
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        background: #fafafa;
    }

    @media (max-width: 992px) {

        .units-grid,
        .units-filter {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 768px) {
        .units-toolbar {
            padding: 25px;
        }

        .units-toolbar h1 {
            font-size: 24px;
        }

        .units-actions,
        .units-btn {
            width: 100%;
        }

        .units-card-header {
            align-items: flex-start;
        }

        .units-actions-inline {
            justify-content: flex-start;
        }
    }
</style>

<div class="units-page">
    <div class="units-toolbar">
        <div>
            <h1><?= $action === 'edit' ? 'Cập nhật đơn vị tính' : 'Quản lý đơn vị tính' ?></h1>
            <p>Quản lý các đơn vị đo lường sản phẩm như cái, hộp, viên, vỉ, lọ và các quy chuẩn nhập xuất kho.</p>
        </div>
        <div class="units-actions">
            <?php if ($action === 'edit'): ?>
                <a href="<?= e(getBaseUrl()) ?>/modules/products/units.php" class="units-btn primary">← Quay lại danh sách</a>
            <?php else: ?>
                <a href="#unitForm" class="units-btn primary">＋ Thêm đơn vị</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="units-alert success">
            <span>✅ <?= e($success) ?></span>
            <button type="button" onclick="this.parentElement.remove()">×</button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="units-alert danger">
            <span>❌ <?= e($error) ?></span>
            <button type="button" onclick="this.parentElement.remove()">×</button>
        </div>
    <?php endif; ?>

    <?php if ($action !== 'edit'): ?>
        <div class="units-stats">
            <div class="units-stat-card">
                <div class="units-stat-icon">📏</div>
                <div class="units-stat-content">
                    <h3>Tổng đơn vị</h3>
                    <div class="value"><?= number_format((int)$stats['total']) ?></div>
                    <div class="hint">Đơn vị tính trong hệ thống</div>
                </div>
            </div>
            <div class="units-stat-card success">
                <div class="units-stat-icon">✅</div>
                <div class="units-stat-content">
                    <h3>Đang hiển thị</h3>
                    <div class="value"><?= number_format(count($units)) ?></div>
                    <div class="hint">Theo bộ lọc hiện tại</div>
                </div>
            </div>
            <div class="units-stat-card warning">
                <div class="units-stat-icon">📦</div>
                <div class="units-stat-content">
                    <h3>Sản phẩm có đơn vị</h3>
                    <div class="value"><?= number_format($usedProducts) ?></div>
                    <div class="hint"><?= $sanPhamHasUnitColumn ? 'Đang liên kết sản phẩm' : 'Bảng sản phẩm chưa có MaDVT' ?></div>
                </div>
            </div>
            <div class="units-stat-card danger">
                <div class="units-stat-icon">🏷️</div>
                <div class="units-stat-content">
                    <h3>Đơn vị được dùng</h3>
                    <div class="value"><?= number_format($usedUnits) ?></div>
                    <div class="hint"><?= $sanPhamHasUnitColumn ? 'Đơn vị có phát sinh' : 'Chưa bật liên kết sản phẩm' ?></div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="units-grid">
        <div class="units-card" id="unitForm">
            <div class="units-card-header">
                <div>
                    <h2><?= $action === 'edit' ? 'Chỉnh sửa đơn vị tính' : 'Thêm đơn vị tính' ?></h2>
                    <p><?= $action === 'edit' ? 'Cập nhật tên đơn vị tính đang chọn.' : 'Tạo đơn vị đo lường mới cho sản phẩm.' ?></p>
                </div>
            </div>

            <form method="POST">
                <input type="hidden" name="action" value="<?= $action === 'edit' ? 'edit' : 'add' ?>">
                <?php if ($action === 'edit'): ?>
                    <input type="hidden" name="ma_dvt" value="<?= e($editUnit['MaDVT']) ?>">
                <?php endif; ?>

                <div class="units-card-body">
                    <div class="units-field">
                        <label class="required" for="ten_dvt">Tên đơn vị tính</label>
                        <input class="units-input" type="text" id="ten_dvt" name="ten_dvt" value="<?= e($editUnit['TenDVT'] ?? '') ?>" placeholder="Ví dụ: Cái, Hộp, Viên, Vỉ, Lọ..." required>
                    </div>
                    <p class="units-note" style="margin-top:12px">Nên đặt tên ngắn, rõ nghĩa để dễ sử dụng trong nhập kho, xuất kho và báo cáo.</p>
                </div>

                <div class="units-card-footer">
                    <button type="submit" class="units-btn solid">💾 <?= $action === 'edit' ? 'Lưu thay đổi' : 'Thêm đơn vị' ?></button>
                    <?php if ($action === 'edit'): ?>
                        <a href="<?= e(getBaseUrl()) ?>/modules/products/units.php" class="units-btn light">Hủy</a>
                    <?php else: ?>
                        <button type="reset" class="units-btn light">Làm mới</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="units-card">
            <div class="units-card-header">
                <div>
                    <h2>Danh sách đơn vị tính</h2>
                    <p>Quản lý, tìm kiếm và chỉnh sửa các đơn vị đo lường.</p>
                </div>
            </div>

            <div class="units-card-body">
                <form method="GET" class="units-filter">
                    <div class="units-field">
                        <label>Tìm kiếm</label>
                        <input class="units-input" type="text" name="search" value="<?= e($searchQuery) ?>" placeholder="Tìm theo tên đơn vị hoặc mã...">
                    </div>
                    <button class="units-btn solid" type="submit">🔎 Lọc</button>
                    <a class="units-btn light" href="<?= e(getBaseUrl()) ?>/modules/products/units.php">Đặt lại</a>
                </form>
            </div>

            <?php if (!empty($units)): ?>
                <div class="units-table-wrap">
                    <table class="units-table">
                        <thead>
                            <tr>
                                <th>Đơn vị tính</th>
                                <th>Sản phẩm dùng</th>
                                <th>Trạng thái</th>
                                <th style="text-align:right">Hành động</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($units as $unit): ?>
                                <?php $usage = (int)($unit['SoSanPham'] ?? 0); ?>
                                <tr>
                                    <td>
                                        <div class="unit-cell">
                                            <div class="unit-avatar <?= e(unitColorClass($unit['MaDVT'])) ?>"><?= e(unitInitial($unit['TenDVT'])) ?></div>
                                            <div>
                                                <div class="unit-name"><?= e($unit['TenDVT']) ?></div>
                                                <div class="unit-sub">Mã đơn vị #DVT<?= e($unit['MaDVT']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="units-badge <?= $usage > 0 ? 'success' : 'muted' ?>">
                                            <?= $usage > 0 ? '📦 ' . number_format($usage) . ' sản phẩm' : 'Chưa có sản phẩm' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="units-badge primary">✅ Sẵn sàng sử dụng</span>
                                    </td>
                                    <td>
                                        <div class="units-actions-inline">
                                            <a href="?action=edit&id=<?= e($unit['MaDVT']) ?>" class="units-btn info sm">Sửa</a>
                                            <form method="POST" style="display:inline" onsubmit="return confirm('Bạn chắc chắn muốn xóa đơn vị tính này?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="ma_dvt" value="<?= e($unit['MaDVT']) ?>">
                                                <button type="submit" class="units-btn danger sm">Xóa</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="units-empty">
                    <div class="icon">📏</div>
                    <h3>Không tìm thấy đơn vị tính</h3>
                    <p>Thử thay đổi từ khóa tìm kiếm hoặc thêm đơn vị tính mới.</p>
                    <a href="#unitForm" class="units-btn solid" style="margin-top:16px">＋ Thêm đơn vị</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    document.querySelectorAll('.units-alert').forEach(function(alert) {
        setTimeout(function() {
            if (alert && alert.parentElement) alert.remove();
        }, 5000);
    });
</script>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/quan_ly_vat_tu/includes/footer.php'; ?>