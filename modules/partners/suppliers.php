<?php
$pageTitle = 'Quản lý nhà cung cấp - Hệ thống quản lý vật tư y tế';
require_once $_SERVER['DOCUMENT_ROOT'] . '/quan_ly_vat_tu/config/database.php';
session_start();
requireLogin();

$success = '';
$error = '';
$action = $_GET['action'] ?? 'list';
$searchQuery = trim($_GET['search'] ?? '');
$filterWard = $_GET['ward'] ?? '';

function e($value)
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function partnerInitial($name)
{
    $name = trim((string)$name);
    if ($name === '') return 'N';
    if (function_exists('mb_substr') && function_exists('mb_strtoupper')) {
        return mb_strtoupper(mb_substr($name, 0, 1, 'UTF-8'), 'UTF-8');
    }
    return strtoupper(substr($name, 0, 1));
}

function partnerColorClass($id)
{
    $classes = ['primary', 'success', 'warning', 'danger', 'info'];
    return $classes[((int)$id) % count($classes)];
}

function nullableValue($value)
{
    $value = trim((string)($value ?? ''));
    return $value === '' ? null : $value;
}

function tableExists(PDO $pdo, $table)
{
    try {
        $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
        $stmt->execute([$table]);
        return (bool)$stmt->fetchColumn();
    } catch (PDOException $e) {
        return false;
    }
}

function countRowsByColumn(PDO $pdo, $table, $column, $value)
{
    if (!tableExists($pdo, $table)) return 0;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `$table` WHERE `$column` = ?");
    $stmt->execute([$value]);
    return (int)$stmt->fetchColumn();
}

function fetchSupplierById(PDO $pdo, $id)
{
    $stmt = $pdo->prepare('SELECT * FROM nhacc WHERE MaNCC = ?');
    $stmt->execute([$id]);
    return $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'add' || $_POST['action'] === 'edit') {
            $tenNCC = trim($_POST['ten_ncc'] ?? '');
            $dienThoai = trim($_POST['dien_thoai'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $diaChi = trim($_POST['dia_chi'] ?? '');
            $giayPhepGPP = trim($_POST['giay_phep'] ?? '');
            $maXP = nullableValue($_POST['ma_xp'] ?? '');

            if ($tenNCC === '') {
                $error = 'Vui lòng nhập tên nhà cung cấp!';
            } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Email nhà cung cấp không hợp lệ!';
            } else {
                if ($_POST['action'] === 'add') {
                    $stmt = $pdo->prepare('INSERT INTO nhacc (TenNCC, DienThoai, Email, DiaChi, GiayPhepGPP, MaXP) VALUES (?, ?, ?, ?, ?, ?)');
                    $stmt->execute([$tenNCC, $dienThoai, $email, $diaChi, $giayPhepGPP, $maXP]);
                    $success = 'Thêm nhà cung cấp thành công!';
                } else {
                    $maNCC = $_POST['ma_ncc'] ?? '';
                    $stmt = $pdo->prepare('UPDATE nhacc SET TenNCC = ?, DienThoai = ?, Email = ?, DiaChi = ?, GiayPhepGPP = ?, MaXP = ? WHERE MaNCC = ?');
                    $stmt->execute([$tenNCC, $dienThoai, $email, $diaChi, $giayPhepGPP, $maXP, $maNCC]);
                    $success = 'Cập nhật nhà cung cấp thành công!';
                }
                $action = 'list';
            }
        } elseif ($_POST['action'] === 'delete') {
            $maNCC = $_POST['ma_ncc'] ?? '';
            $orderCount = countRowsByColumn($pdo, 'donmh', 'MaNCC', $maNCC);
            if ($orderCount > 0) {
                $error = 'Không thể xóa nhà cung cấp này vì đã phát sinh đơn mua hàng.';
            } else {
                $stmt = $pdo->prepare('DELETE FROM nhacc WHERE MaNCC = ?');
                $stmt->execute([$maNCC]);
                $success = 'Xóa nhà cung cấp thành công!';
            }
        }
    } catch (PDOException $e) {
        $error = 'Lỗi: ' . $e->getMessage();
    }
}

try {
    $wardStmt = $pdo->query('SELECT xp.*, t.TenTinh FROM xaphuong xp LEFT JOIN tinh t ON xp.MaTinh = t.MaTinh ORDER BY t.TenTinh, xp.TenXP');
    $wards = $wardStmt->fetchAll();
} catch (PDOException $e) {
    $wards = [];
}

$editSupplier = null;
if ($action === 'edit' && isset($_GET['id'])) {
    try {
        $editSupplier = fetchSupplierById($pdo, $_GET['id']);
        if (!$editSupplier) {
            $error = 'Không tìm thấy nhà cung cấp cần chỉnh sửa.';
            $action = 'list';
        }
    } catch (PDOException $e) {
        $error = 'Lỗi: ' . $e->getMessage();
        $action = 'list';
    }
}

$whereConditions = [];
$params = [];
if ($searchQuery !== '') {
    $whereConditions[] = '(nc.TenNCC LIKE ? OR nc.DienThoai LIKE ? OR nc.Email LIKE ? OR nc.GiayPhepGPP LIKE ? OR xp.TenXP LIKE ? OR t.TenTinh LIKE ?)';
    array_push($params, "%$searchQuery%", "%$searchQuery%", "%$searchQuery%", "%$searchQuery%", "%$searchQuery%", "%$searchQuery%");
}
if ($filterWard !== '') {
    $whereConditions[] = 'nc.MaXP = ?';
    $params[] = $filterWard;
}
$whereClause = $whereConditions ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

try {
    $sql = "SELECT nc.*, xp.TenXP, t.TenTinh, COALESCE(dm.SoDonNhap, 0) AS SoDonNhap
        FROM nhacc nc
        LEFT JOIN xaphuong xp ON nc.MaXP = xp.MaXP
        LEFT JOIN tinh t ON xp.MaTinh = t.MaTinh
        LEFT JOIN (SELECT MaNCC, COUNT(*) AS SoDonNhap FROM donmh GROUP BY MaNCC) dm ON nc.MaNCC = dm.MaNCC
        $whereClause
        ORDER BY nc.MaNCC DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $suppliers = $stmt->fetchAll();

    $statsStmt = $pdo->query('SELECT COUNT(*) AS total, SUM(CASE WHEN GiayPhepGPP IS NOT NULL AND GiayPhepGPP <> "" THEN 1 ELSE 0 END) AS licensed, SUM(CASE WHEN Email IS NOT NULL AND Email <> "" THEN 1 ELSE 0 END) AS has_email FROM nhacc');
    $stats = $statsStmt->fetch();
    $orderSuppliers = tableExists($pdo, 'donmh') ? (int)$pdo->query('SELECT COUNT(DISTINCT MaNCC) FROM donmh')->fetchColumn() : 0;
} catch (PDOException $e) {
    $error = 'Lỗi: ' . $e->getMessage();
    $suppliers = [];
    $stats = ['total' => 0, 'licensed' => 0, 'has_email' => 0];
    $orderSuppliers = 0;
}

include $_SERVER['DOCUMENT_ROOT'] . '/quan_ly_vat_tu/includes/header.php';
include $_SERVER['DOCUMENT_ROOT'] . '/quan_ly_vat_tu/includes/sidebar.php';
?>

<style>
    .partner-page {
        animation: partnerFadeIn .25s ease;
    }

    @keyframes partnerFadeIn {
        from {
            opacity: 0;
            transform: translateY(6px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .partner-toolbar {
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

    .partner-toolbar h1 {
        font-size: 28px;
        line-height: 1.25;
        margin: 0 0 8px;
        font-weight: 700;
    }

    .partner-toolbar p {
        margin: 0;
        font-size: 14px;
        opacity: .9;
    }

    .partner-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    .partner-btn {
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
        line-height: 1.3;
        font-family: inherit;
    }

    .partner-btn.primary {
        background: #fff;
        color: var(--primary-color);
    }

    .partner-btn.primary:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow);
        color: var(--primary-color);
    }

    .partner-btn.solid {
        background: var(--primary-color);
        color: #fff;
    }

    .partner-btn.solid:hover {
        background: var(--secondary-color);
        color: #fff;
    }

    .partner-btn.light {
        background: #fff;
        color: var(--text-dark);
        border: 1px solid var(--border-color);
    }

    .partner-btn.light:hover {
        background: var(--light-bg);
        color: var(--primary-color);
    }

    .partner-btn.info {
        background: rgba(102, 126, 234, .12);
        color: var(--primary-color);
    }

    .partner-btn.info:hover {
        background: var(--primary-color);
        color: #fff;
    }

    .partner-btn.danger {
        background: rgba(245, 101, 101, .12);
        color: var(--danger-color);
    }

    .partner-btn.danger:hover {
        background: var(--danger-color);
        color: #fff;
    }

    .partner-btn.sm {
        padding: 7px 11px;
        font-size: 12px;
    }

    .partner-alert {
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

    .partner-alert.success {
        background: rgba(72, 187, 120, .14);
        color: #276749;
        border-left: 4px solid var(--success-color);
    }

    .partner-alert.danger {
        background: rgba(245, 101, 101, .13);
        color: #9b2c2c;
        border-left: 4px solid var(--danger-color);
    }

    .partner-alert button {
        border: 0;
        background: transparent;
        color: inherit;
        font-size: 20px;
        cursor: pointer;
    }

    .partner-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .partner-stat-card {
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

    .partner-stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
    }

    .partner-stat-card.success::before {
        background: var(--success-color);
    }

    .partner-stat-card.warning::before {
        background: var(--warning-color);
    }

    .partner-stat-card.danger::before {
        background: var(--danger-color);
    }

    .partner-stat-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--shadow-lg);
        border-color: var(--primary-color);
    }

    .partner-stat-icon {
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

    .partner-stat-card.success .partner-stat-icon {
        background: rgba(72, 187, 120, .15);
        color: var(--success-color);
    }

    .partner-stat-card.warning .partner-stat-icon {
        background: rgba(237, 137, 54, .15);
        color: var(--warning-color);
    }

    .partner-stat-card.danger .partner-stat-icon {
        background: rgba(245, 101, 101, .15);
        color: var(--danger-color);
    }

    .partner-stat-content h3 {
        margin: 0 0 7px;
        color: var(--text-light);
        font-size: 12px;
        text-transform: uppercase;
        font-weight: 600;
        letter-spacing: .5px;
    }

    .partner-stat-content .value {
        color: var(--text-dark);
        font-size: 24px;
        font-weight: 700;
        line-height: 1;
    }

    .partner-stat-content .hint {
        margin-top: 7px;
        color: var(--text-light);
        font-size: 12px;
    }

    .partner-card {
        background: #fff;
        border-radius: 8px;
        box-shadow: var(--shadow);
        margin-bottom: 30px;
        overflow: hidden;
    }

    .partner-card-header {
        padding: 20px 24px;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        flex-wrap: wrap;
    }

    .partner-card-header h2 {
        margin: 0;
        font-size: 18px;
        font-weight: 600;
        color: var(--text-dark);
    }

    .partner-card-header p {
        margin: 5px 0 0;
        font-size: 13px;
        color: var(--text-light);
    }

    .partner-card-body {
        padding: 24px;
    }

    .partner-filter {
        display: grid;
        grid-template-columns: 1.8fr 1fr auto auto;
        gap: 12px;
        align-items: end;
    }

    .partner-form-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 18px;
    }

    .partner-form-grid.one {
        grid-template-columns: 1fr;
    }

    .partner-field label {
        display: block;
        margin-bottom: 7px;
        color: var(--text-dark);
        font-size: 13px;
        font-weight: 600;
    }

    .partner-field label.required::after {
        content: ' *';
        color: var(--danger-color);
    }

    .partner-input,
    .partner-select,
    .partner-textarea {
        width: 100%;
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

    .partner-input,
    .partner-select {
        height: 42px;
    }

    .partner-textarea {
        min-height: 96px;
        resize: vertical;
    }

    .partner-input:focus,
    .partner-select:focus,
    .partner-textarea:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(102, 126, 234, .12);
    }

    .partner-table-wrap {
        overflow-x: auto;
    }

    .partner-table {
        width: 100%;
        border-collapse: collapse;
    }

    .partner-table th {
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

    .partner-table td {
        padding: 16px;
        border-bottom: 1px solid var(--border-color);
        color: var(--text-dark);
        vertical-align: middle;
        font-size: 14px;
    }

    .partner-table tr:hover td {
        background: var(--light-bg);
    }

    .partner-cell {
        display: flex;
        align-items: center;
        gap: 12px;
        min-width: 270px;
    }

    .partner-avatar {
        width: 46px;
        height: 46px;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        color: white;
        font-size: 15px;
        font-weight: 700;
        flex-shrink: 0;
        box-shadow: inset 0 0 0 1px rgba(255, 255, 255, .18);
    }

    .partner-avatar.primary {
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    }

    .partner-avatar.success {
        background: linear-gradient(135deg, var(--success-color), #38a169);
    }

    .partner-avatar.warning {
        background: linear-gradient(135deg, var(--warning-color), #dd6b20);
    }

    .partner-avatar.danger {
        background: linear-gradient(135deg, var(--danger-color), #c53030);
    }

    .partner-avatar.info {
        background: linear-gradient(135deg, var(--info-color), #3182ce);
    }

    .partner-name {
        color: var(--text-dark);
        font-size: 14px;
        font-weight: 600;
        margin-bottom: 3px;
    }

    .partner-sub {
        color: var(--text-light);
        font-size: 12px;
        line-height: 1.45;
    }

    .partner-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 11px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        white-space: nowrap;
    }

    .partner-badge.primary {
        background: rgba(102, 126, 234, .12);
        color: var(--primary-color);
    }

    .partner-badge.success {
        background: rgba(72, 187, 120, .14);
        color: var(--success-color);
    }

    .partner-badge.warning {
        background: rgba(237, 137, 54, .14);
        color: var(--warning-color);
    }

    .partner-badge.muted {
        background: var(--light-bg);
        color: var(--text-light);
    }

    .partner-badge.danger {
        background: rgba(245, 101, 101, .12);
        color: var(--danger-color);
    }

    .partner-actions-inline {
        display: flex;
        justify-content: flex-end;
        align-items: center;
        gap: 8px;
    }

    .partner-empty {
        text-align: center;
        padding: 50px 20px;
    }

    .partner-empty .icon {
        font-size: 48px;
        margin-bottom: 15px;
    }

    .partner-empty h3 {
        color: var(--text-dark);
        margin: 0 0 8px;
        font-size: 16px;
        font-weight: 600;
    }

    .partner-empty p {
        color: var(--text-light);
        margin: 0;
        font-size: 14px;
    }

    .partner-footer {
        border-top: 1px solid var(--border-color);
        padding: 18px 24px;
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        background: #fafafa;
    }

    @media (max-width: 992px) {

        .partner-filter,
        .partner-form-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 768px) {
        .partner-toolbar {
            padding: 25px;
        }

        .partner-toolbar h1 {
            font-size: 24px;
        }

        .partner-actions,
        .partner-btn {
            width: 100%;
        }

        .partner-card-header {
            align-items: flex-start;
        }

        .partner-actions-inline {
            justify-content: flex-start;
        }
    }
</style>


<div class="partner-page">
    <div class="partner-toolbar">
        <div>
            <h1><?= $action === 'add' ? 'Thêm nhà cung cấp mới' : ($action === 'edit' ? 'Cập nhật nhà cung cấp' : 'Quản lý nhà cung cấp') ?></h1>
            <p><?= $action === 'list' ? 'Quản lý hồ sơ nhà cung cấp, giấy phép, địa điểm và thông tin liên hệ.' : 'Cập nhật thông tin định danh, liên hệ và địa chỉ nhà cung cấp.' ?></p>
        </div>
        <div class="partner-actions">
            <?php if ($action === 'list'): ?>
                <a href="?action=add" class="partner-btn primary">＋ Thêm nhà cung cấp</a>
            <?php else: ?>
                <a href="<?= e(getBaseUrl()) ?>/modules/partners/suppliers.php" class="partner-btn primary">← Quay lại danh sách</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($success): ?><div class="partner-alert success"><span>✅ <?= e($success) ?></span><button type="button" onclick="this.parentElement.remove()">×</button></div><?php endif; ?>
    <?php if ($error): ?><div class="partner-alert danger"><span>❌ <?= e($error) ?></span><button type="button" onclick="this.parentElement.remove()">×</button></div><?php endif; ?>

    <?php if ($action === 'add' || ($action === 'edit' && $editSupplier)): ?>
        <div class="partner-card">
            <div class="partner-card-header">
                <div>
                    <h2><?= $action === 'add' ? 'Thông tin nhà cung cấp mới' : 'Thông tin nhà cung cấp' ?></h2>
                    <p>Các trường có dấu * là bắt buộc.</p>
                </div>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="<?= e($action) ?>">
                <?php if ($action === 'edit'): ?><input type="hidden" name="ma_ncc" value="<?= e($editSupplier['MaNCC']) ?>"><?php endif; ?>
                <div class="partner-card-body">
                    <div class="partner-form-grid">
                        <div class="partner-field"><label class="required" for="ten_ncc">Tên nhà cung cấp</label><input class="partner-input" type="text" id="ten_ncc" name="ten_ncc" value="<?= e($editSupplier['TenNCC'] ?? '') ?>" placeholder="VD: Công ty Dược phẩm ABC" required></div>
                        <div class="partner-field"><label for="giay_phep">Giấy phép GPP</label><input class="partner-input" type="text" id="giay_phep" name="giay_phep" value="<?= e($editSupplier['GiayPhepGPP'] ?? '') ?>" placeholder="VD: GPP-2026-001"></div>
                    </div>
                    <div class="partner-form-grid" style="margin-top:18px">
                        <div class="partner-field"><label for="dien_thoai">Điện thoại</label><input class="partner-input" type="tel" id="dien_thoai" name="dien_thoai" value="<?= e($editSupplier['DienThoai'] ?? '') ?>" placeholder="VD: 028 1234 5678"></div>
                        <div class="partner-field"><label for="email">Email</label><input class="partner-input" type="email" id="email" name="email" value="<?= e($editSupplier['Email'] ?? '') ?>" placeholder="VD: contact@example.com"></div>
                    </div>
                    <div class="partner-form-grid" style="margin-top:18px">
                        <div class="partner-field"><label for="ma_xp">Phường/Xã</label><select class="partner-select" id="ma_xp" name="ma_xp">
                                <option value="">-- Không có --</option><?php foreach ($wards as $ward): ?><option value="<?= e($ward['MaXP']) ?>" <?= (($editSupplier['MaXP'] ?? null) == $ward['MaXP']) ? 'selected' : '' ?>><?= e($ward['TenXP'] . ($ward['TenTinh'] ? ' - ' . $ward['TenTinh'] : '')) ?></option><?php endforeach; ?>
                            </select></div>
                        <div class="partner-field"><label for="dia_chi">Địa chỉ</label><textarea class="partner-textarea" id="dia_chi" name="dia_chi" placeholder="Nhập địa chỉ chi tiết"><?= e($editSupplier['DiaChi'] ?? '') ?></textarea></div>
                    </div>
                </div>
                <div class="partner-footer"><button type="submit" class="partner-btn solid">💾 Lưu thông tin</button><a href="<?= e(getBaseUrl()) ?>/modules/partners/suppliers.php" class="partner-btn light">Hủy</a></div>
            </form>
        </div>
    <?php else: ?>
        <div class="partner-stats">
            <div class="partner-stat-card">
                <div class="partner-stat-icon">🏭</div>
                <div class="partner-stat-content">
                    <h3>Tổng nhà cung cấp</h3>
                    <div class="value"><?= number_format((int)$stats['total']) ?></div>
                    <div class="hint">Hồ sơ trong hệ thống</div>
                </div>
            </div>
            <div class="partner-stat-card success">
                <div class="partner-stat-icon">✅</div>
                <div class="partner-stat-content">
                    <h3>Đang hiển thị</h3>
                    <div class="value"><?= number_format(count($suppliers)) ?></div>
                    <div class="hint">Theo bộ lọc hiện tại</div>
                </div>
            </div>
            <div class="partner-stat-card warning">
                <div class="partner-stat-icon">📄</div>
                <div class="partner-stat-content">
                    <h3>Có giấy phép</h3>
                    <div class="value"><?= number_format((int)$stats['licensed']) ?></div>
                    <div class="hint">Đã nhập giấy phép GPP</div>
                </div>
            </div>
            <div class="partner-stat-card danger">
                <div class="partner-stat-icon">📦</div>
                <div class="partner-stat-content">
                    <h3>Có đơn nhập</h3>
                    <div class="value"><?= number_format($orderSuppliers) ?></div>
                    <div class="hint">Đã phát sinh mua hàng</div>
                </div>
            </div>
        </div>

        <div class="partner-card">
            <div class="partner-card-header">
                <div>
                    <h2>Danh sách nhà cung cấp</h2>
                    <p>Giao diện danh sách đồng bộ với trang danh mục sản phẩm.</p>
                </div>
            </div>
            <div class="partner-card-body">
                <form method="GET" class="partner-filter">
                    <input type="hidden" name="action" value="list">
                    <div class="partner-field"><label>Tìm kiếm</label><input class="partner-input" type="text" name="search" value="<?= e($searchQuery) ?>" placeholder="Tìm theo tên, SĐT, email, giấy phép, địa điểm..."></div>
                    <div class="partner-field"><label>Phường/Xã</label><select class="partner-select" name="ward">
                            <option value="">Tất cả địa điểm</option><?php foreach ($wards as $ward): ?><option value="<?= e($ward['MaXP']) ?>" <?= $filterWard == $ward['MaXP'] ? 'selected' : '' ?>><?= e($ward['TenXP']) ?></option><?php endforeach; ?>
                        </select></div>
                    <button class="partner-btn solid" type="submit">🔎 Lọc</button>
                    <a class="partner-btn light" href="<?= e(getBaseUrl()) ?>/modules/partners/suppliers.php">Đặt lại</a>
                </form>
            </div>
            <?php if (!empty($suppliers)): ?>
                <div class="partner-table-wrap">
                    <table class="partner-table">
                        <thead>
                            <tr>
                                <th>Nhà cung cấp</th>
                                <th>Liên hệ</th>
                                <th>Giấy phép</th>
                                <th>Địa điểm</th>
                                <th>Đơn nhập</th>
                                <th style="text-align:right">Hành động</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($suppliers as $supplier): ?>
                                <tr>
                                    <td>
                                        <div class="partner-cell">
                                            <div class="partner-avatar <?= e(partnerColorClass($supplier['MaNCC'])) ?>"><?= e(partnerInitial($supplier['TenNCC'])) ?></div>
                                            <div>
                                                <div class="partner-name"><?= e($supplier['TenNCC']) ?></div>
                                                <div class="partner-sub">Mã NCC #<?= e($supplier['MaNCC']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div><?= e($supplier['DienThoai'] ?: 'Chưa có SĐT') ?></div>
                                        <div class="partner-sub"><?= e($supplier['Email'] ?: 'Chưa có email') ?></div>
                                    </td>
                                    <td><?= $supplier['GiayPhepGPP'] ? '<span class="partner-badge success">✅ ' . e($supplier['GiayPhepGPP']) . '</span>' : '<span class="partner-badge muted">Chưa có</span>' ?></td>
                                    <td><span class="partner-badge primary">📍 <?= e($supplier['TenXP'] ?: 'Chưa có') ?></span>
                                        <div class="partner-sub" style="margin-top:6px"><?= e($supplier['TenTinh'] ?: '') ?></div>
                                    </td>
                                    <td><span class="partner-badge <?= (int)$supplier['SoDonNhap'] > 0 ? 'warning' : 'muted' ?>"><?= number_format((int)$supplier['SoDonNhap']) ?> đơn</span></td>
                                    <td>
                                        <div class="partner-actions-inline"><a href="?action=edit&id=<?= e($supplier['MaNCC']) ?>" class="partner-btn info sm">Sửa</a>
                                            <form method="POST" style="display:inline" onsubmit="return confirm('Bạn chắc chắn muốn xóa nhà cung cấp này?');"><input type="hidden" name="action" value="delete"><input type="hidden" name="ma_ncc" value="<?= e($supplier['MaNCC']) ?>"><button type="submit" class="partner-btn danger sm">Xóa</button></form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="partner-empty">
                    <div class="icon">🏭</div>
                    <h3>Không tìm thấy nhà cung cấp</h3>
                    <p>Thử thay đổi bộ lọc hoặc thêm hồ sơ mới.</p><a href="?action=add" class="partner-btn solid" style="margin-top:16px">＋ Thêm nhà cung cấp</a>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
<script>
    document.querySelectorAll('.partner-alert').forEach(a => setTimeout(() => a && a.parentElement && a.remove(), 5000));
</script>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/quan_ly_vat_tu/includes/footer.php'; ?>