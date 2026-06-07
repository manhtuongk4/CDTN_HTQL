<?php
$pageTitle = 'Quản lý danh mục sản phẩm - Hệ thống quản lý vật tư y tế';
require_once $_SERVER['DOCUMENT_ROOT'] . '/quan_ly_vat_tu/config/database.php';
session_start();
requireLogin();

$success = '';
$error = '';
$action = $_GET['action'] ?? 'list';
$searchQuery = trim($_GET['search'] ?? '');
$filterGroup = $_GET['group'] ?? '';

function e($value)
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function keepCategoryQuery(array $merge = [])
{
    $query = array_merge($_GET, $merge);
    return '?' . http_build_query($query);
}

function categoryInitial($name)
{
    $name = trim((string)$name);
    if ($name === '') {
        return 'C';
    }

    if (function_exists('mb_substr') && function_exists('mb_strtoupper')) {
        return mb_strtoupper(mb_substr($name, 0, 1, 'UTF-8'), 'UTF-8');
    }

    return strtoupper(substr($name, 0, 1));
}

function categoryColorClass($id)
{
    $classes = ['primary', 'success', 'warning', 'danger', 'info'];
    return $classes[((int)$id) % count($classes)];
}

function fetchGroupById(PDO $pdo, $id)
{
    $stmt = $pdo->prepare('SELECT * FROM nhomsp WHERE MaNSP = ?');
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function fetchCategoryById(PDO $pdo, $id)
{
    $stmt = $pdo->prepare('
        SELECT ls.*, ns.TenNSP
        FROM loaisp ls
        LEFT JOIN nhomsp ns ON ls.MaNSP = ns.MaNSP
        WHERE ls.MaLSP = ?
    ');
    $stmt->execute([$id]);
    return $stmt->fetch();
}

// Xử lý thêm/sửa/xóa nhóm và loại sản phẩm
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'add_group') {
            $tenNSP = trim($_POST['ten_nhom'] ?? '');
            $moTa = trim($_POST['mo_ta'] ?? '');

            if ($tenNSP === '') {
                $error = 'Vui lòng nhập tên nhóm sản phẩm!';
            } else {
                $stmt = $pdo->prepare('INSERT INTO nhomsp (TenNSP, MoTaNhom) VALUES (?, ?)');
                $stmt->execute([$tenNSP, $moTa]);
                $success = 'Thêm nhóm sản phẩm thành công!';
            }
        } elseif ($_POST['action'] === 'edit_group') {
            $maNSP = $_POST['ma_nhom'] ?? '';
            $tenNSP = trim($_POST['ten_nhom'] ?? '');
            $moTa = trim($_POST['mo_ta'] ?? '');

            if ($maNSP === '' || $tenNSP === '') {
                $error = 'Vui lòng nhập đầy đủ thông tin nhóm sản phẩm!';
            } else {
                $stmt = $pdo->prepare('UPDATE nhomsp SET TenNSP = ?, MoTaNhom = ? WHERE MaNSP = ?');
                $stmt->execute([$tenNSP, $moTa, $maNSP]);
                $success = 'Cập nhật nhóm sản phẩm thành công!';
                $action = 'list';
            }
        } elseif ($_POST['action'] === 'delete_group') {
            $maNSP = $_POST['ma_nhom'] ?? '';

            $checkStmt = $pdo->prepare('SELECT COUNT(*) AS total FROM loaisp WHERE MaNSP = ?');
            $checkStmt->execute([$maNSP]);
            $categoryCount = (int)$checkStmt->fetch()['total'];

            if ($categoryCount > 0) {
                $error = 'Không thể xóa nhóm sản phẩm đang có loại sản phẩm. Vui lòng xóa/chuyển các loại sản phẩm trước.';
            } else {
                $stmt = $pdo->prepare('DELETE FROM nhomsp WHERE MaNSP = ?');
                $stmt->execute([$maNSP]);
                $success = 'Xóa nhóm sản phẩm thành công!';
            }
        } elseif ($_POST['action'] === 'add_category') {
            $tenLSP = trim($_POST['ten_loai'] ?? '');
            $maNSP = $_POST['ma_nhom'] ?? '';

            if ($tenLSP === '' || $maNSP === '') {
                $error = 'Vui lòng điền đầy đủ tên loại và nhóm sản phẩm!';
            } else {
                $stmt = $pdo->prepare('INSERT INTO loaisp (TenLSP, MaNSP) VALUES (?, ?)');
                $stmt->execute([$tenLSP, $maNSP]);
                $success = 'Thêm loại sản phẩm thành công!';
            }
        } elseif ($_POST['action'] === 'edit_category') {
            $maLSP = $_POST['ma_loai'] ?? '';
            $tenLSP = trim($_POST['ten_loai'] ?? '');
            $maNSP = $_POST['ma_nhom'] ?? '';

            if ($maLSP === '' || $tenLSP === '' || $maNSP === '') {
                $error = 'Vui lòng điền đầy đủ thông tin loại sản phẩm!';
            } else {
                $stmt = $pdo->prepare('UPDATE loaisp SET TenLSP = ?, MaNSP = ? WHERE MaLSP = ?');
                $stmt->execute([$tenLSP, $maNSP, $maLSP]);
                $success = 'Cập nhật loại sản phẩm thành công!';
                $action = 'list';
            }
        } elseif ($_POST['action'] === 'delete_category') {
            $maLSP = $_POST['ma_loai'] ?? '';

            $checkStmt = $pdo->prepare('SELECT COUNT(*) AS total FROM sanpham WHERE MaLSP = ?');
            $checkStmt->execute([$maLSP]);
            $productCount = (int)$checkStmt->fetch()['total'];

            if ($productCount > 0) {
                $error = 'Không thể xóa loại sản phẩm đang được sử dụng bởi sản phẩm. Vui lòng chuyển sản phẩm sang loại khác trước.';
            } else {
                $stmt = $pdo->prepare('DELETE FROM loaisp WHERE MaLSP = ?');
                $stmt->execute([$maLSP]);
                $success = 'Xóa loại sản phẩm thành công!';
            }
        }
    } catch (PDOException $e) {
        $error = 'Lỗi: ' . $e->getMessage();
    }
}

// Lấy danh sách nhóm sản phẩm
try {
    $stmt = $pdo->query('
        SELECT ns.*,
            COUNT(DISTINCT ls.MaLSP) AS TongLoai,
            COUNT(DISTINCT sp.MaSP) AS TongSanPham
        FROM nhomsp ns
        LEFT JOIN loaisp ls ON ns.MaNSP = ls.MaNSP
        LEFT JOIN sanpham sp ON ls.MaLSP = sp.MaLSP
        GROUP BY ns.MaNSP
        ORDER BY ns.MaNSP DESC
    ');
    $groups = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = 'Lỗi: ' . $e->getMessage();
    $groups = [];
}

// Lấy thông tin đang sửa
$editGroup = null;
$editCategory = null;
if ($action === 'edit_group' && isset($_GET['id'])) {
    try {
        $editGroup = fetchGroupById($pdo, $_GET['id']);
        if (!$editGroup) {
            $error = 'Không tìm thấy nhóm sản phẩm cần chỉnh sửa.';
            $action = 'list';
        }
    } catch (PDOException $e) {
        $error = 'Lỗi: ' . $e->getMessage();
        $action = 'list';
    }
}

if ($action === 'edit_category' && isset($_GET['id'])) {
    try {
        $editCategory = fetchCategoryById($pdo, $_GET['id']);
        if (!$editCategory) {
            $error = 'Không tìm thấy loại sản phẩm cần chỉnh sửa.';
            $action = 'list';
        }
    } catch (PDOException $e) {
        $error = 'Lỗi: ' . $e->getMessage();
        $action = 'list';
    }
}

// Lấy danh sách loại sản phẩm + filter
$whereConditions = [];
$params = [];

if ($searchQuery !== '') {
    $whereConditions[] = '(ls.TenLSP LIKE ? OR ns.TenNSP LIKE ?)';
    $params[] = "%$searchQuery%";
    $params[] = "%$searchQuery%";
}

if ($filterGroup !== '') {
    $whereConditions[] = 'ls.MaNSP = ?';
    $params[] = $filterGroup;
}

$whereClause = $whereConditions ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

try {
    $stmt = $pdo->prepare("
        SELECT ls.*, ns.TenNSP,
            COUNT(sp.MaSP) AS TongSanPham
        FROM loaisp ls
        LEFT JOIN nhomsp ns ON ls.MaNSP = ns.MaNSP
        LEFT JOIN sanpham sp ON ls.MaLSP = sp.MaLSP
        $whereClause
        GROUP BY ls.MaLSP
        ORDER BY ls.MaNSP DESC, ls.MaLSP DESC
    ");
    $stmt->execute($params);
    $categories = $stmt->fetchAll();

    $statsStmt = $pdo->query('
        SELECT
            (SELECT COUNT(*) FROM nhomsp) AS total_groups,
            (SELECT COUNT(*) FROM loaisp) AS total_categories,
            (SELECT COUNT(*) FROM sanpham WHERE MaLSP IS NOT NULL) AS assigned_products,
            (SELECT COUNT(*) FROM sanpham WHERE MaLSP IS NULL) AS unassigned_products
    ');
    $stats = $statsStmt->fetch();
} catch (PDOException $e) {
    $error = 'Lỗi: ' . $e->getMessage();
    $categories = [];
    $stats = ['total_groups' => 0, 'total_categories' => 0, 'assigned_products' => 0, 'unassigned_products' => 0];
}

include $_SERVER['DOCUMENT_ROOT'] . '/quan_ly_vat_tu/includes/header.php';
include $_SERVER['DOCUMENT_ROOT'] . '/quan_ly_vat_tu/includes/sidebar.php';
?>

<style>
    .categories-page {
        animation: categoryFadeIn .25s ease;
    }

    @keyframes categoryFadeIn {
        from {
            opacity: 0;
            transform: translateY(6px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .categories-toolbar {
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

    .categories-toolbar h1 {
        font-size: 28px;
        line-height: 1.25;
        margin: 0 0 8px;
        font-weight: 700;
    }

    .categories-toolbar p {
        margin: 0;
        font-size: 14px;
        opacity: .9;
    }

    .categories-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    .btn-category {
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

    .btn-category.primary {
        background: white;
        color: var(--primary-color);
    }

    .btn-category.primary:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow);
    }

    .btn-category.solid {
        background: var(--primary-color);
        color: white;
    }

    .btn-category.solid:hover {
        background: var(--secondary-color);
        color: white;
    }

    .btn-category.light {
        background: white;
        color: var(--text-dark);
        border: 1px solid var(--border-color);
    }

    .btn-category.light:hover {
        background: var(--light-bg);
        color: var(--primary-color);
    }

    .btn-category.danger {
        background: rgba(245, 101, 101, .12);
        color: var(--danger-color);
    }

    .btn-category.danger:hover {
        background: var(--danger-color);
        color: white;
    }

    .btn-category.info {
        background: rgba(102, 126, 234, .12);
        color: var(--primary-color);
    }

    .btn-category.info:hover {
        background: var(--primary-color);
        color: white;
    }

    .btn-category.sm {
        padding: 7px 11px;
        font-size: 12px;
    }

    .categories-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .category-stat-card {
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

    .category-stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
    }

    .category-stat-card.success::before {
        background: var(--success-color);
    }

    .category-stat-card.warning::before {
        background: var(--warning-color);
    }

    .category-stat-card.danger::before {
        background: var(--danger-color);
    }

    .category-stat-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--shadow-lg);
        border-color: var(--primary-color);
    }

    .category-stat-icon {
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

    .category-stat-card.success .category-stat-icon {
        background: rgba(72, 187, 120, .15);
        color: var(--success-color);
    }

    .category-stat-card.warning .category-stat-icon {
        background: rgba(237, 137, 54, .15);
        color: var(--warning-color);
    }

    .category-stat-card.danger .category-stat-icon {
        background: rgba(245, 101, 101, .15);
        color: var(--danger-color);
    }

    .category-stat-content h3 {
        margin: 0 0 7px;
        color: var(--text-light);
        font-size: 12px;
        text-transform: uppercase;
        font-weight: 600;
        letter-spacing: .5px;
    }

    .category-stat-content .value {
        color: var(--text-dark);
        font-size: 24px;
        font-weight: 700;
        line-height: 1;
    }

    .category-stat-content .hint {
        margin-top: 7px;
        color: var(--text-light);
        font-size: 12px;
    }

    .categories-grid {
        display: grid;
        grid-template-columns: minmax(320px, .9fr) 1.5fr;
        gap: 22px;
        align-items: start;
    }

    .category-card {
        background: white;
        border-radius: 8px;
        box-shadow: var(--shadow);
        margin-bottom: 30px;
        overflow: hidden;
    }

    .category-card-header {
        padding: 20px 24px;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        flex-wrap: wrap;
    }

    .category-card-header h2 {
        margin: 0;
        font-size: 18px;
        font-weight: 600;
        color: var(--text-dark);
    }

    .category-card-header p {
        margin: 5px 0 0;
        font-size: 13px;
        color: var(--text-light);
    }

    .category-card-body {
        padding: 24px;
    }

    .category-form-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 18px;
    }

    .category-form-grid.one {
        grid-template-columns: 1fr;
    }

    .category-field label {
        display: block;
        margin-bottom: 7px;
        color: var(--text-dark);
        font-size: 13px;
        font-weight: 600;
    }

    .category-field label.required::after {
        content: ' *';
        color: var(--danger-color);
    }

    .category-input,
    .category-select,
    .category-textarea {
        width: 100%;
        border: 1px solid var(--border-color);
        border-radius: 6px;
        padding: 10px 12px;
        font-size: 14px;
        color: var(--text-dark);
        background: white;
        outline: none;
        transition: all .3s;
        font-family: inherit;
    }

    .category-input,
    .category-select {
        height: 42px;
    }

    .category-textarea {
        min-height: 96px;
        resize: vertical;
        line-height: 1.6;
    }

    .category-input:focus,
    .category-select:focus,
    .category-textarea:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(102, 126, 234, .12);
    }

    .category-filter {
        display: grid;
        grid-template-columns: 1.5fr 1fr auto auto;
        gap: 12px;
        align-items: end;
    }

    .category-table-wrap {
        overflow-x: auto;
    }

    .category-table {
        width: 100%;
        border-collapse: collapse;
    }

    .category-table th {
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

    .category-table td {
        padding: 16px;
        border-bottom: 1px solid var(--border-color);
        color: var(--text-dark);
        vertical-align: middle;
        font-size: 14px;
    }

    .category-table tr:hover td {
        background: var(--light-bg);
    }

    .category-cell {
        display: flex;
        align-items: center;
        gap: 12px;
        min-width: 250px;
    }

    .category-avatar {
        width: 46px;
        height: 46px;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        color: white;
        font-size: 16px;
        font-weight: 700;
        flex-shrink: 0;
        box-shadow: inset 0 0 0 1px rgba(255, 255, 255, .18);
    }

    .category-avatar.primary {
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    }

    .category-avatar.success {
        background: linear-gradient(135deg, var(--success-color), #38a169);
    }

    .category-avatar.warning {
        background: linear-gradient(135deg, var(--warning-color), #dd6b20);
    }

    .category-avatar.danger {
        background: linear-gradient(135deg, var(--danger-color), #c53030);
    }

    .category-avatar.info {
        background: linear-gradient(135deg, var(--info-color), #3182ce);
    }

    .category-name {
        color: var(--text-dark);
        font-size: 14px;
        font-weight: 600;
        margin-bottom: 3px;
    }

    .category-sub {
        color: var(--text-light);
        font-size: 12px;
        line-height: 1.5;
    }

    .category-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 11px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        white-space: nowrap;
    }

    .category-badge.primary {
        background: rgba(102, 126, 234, .12);
        color: var(--primary-color);
    }

    .category-badge.success {
        background: rgba(72, 187, 120, .14);
        color: var(--success-color);
    }

    .category-badge.warning {
        background: rgba(237, 137, 54, .14);
        color: var(--warning-color);
    }

    .category-badge.danger {
        background: rgba(245, 101, 101, .12);
        color: var(--danger-color);
    }

    .category-actions-inline {
        display: flex;
        justify-content: flex-end;
        align-items: center;
        gap: 8px;
    }

    .group-list {
        display: grid;
        gap: 12px;
        max-height: 520px;
        overflow-y: auto;
        padding-right: 4px;
    }

    .group-item {
        background: var(--light-bg);
        border: 1px solid var(--border-color);
        border-radius: 8px;
        padding: 14px;
        display: flex;
        gap: 12px;
        align-items: flex-start;
        transition: all .3s;
    }

    .group-item:hover {
        border-color: var(--primary-color);
        background: white;
        box-shadow: var(--shadow);
    }

    .group-item-main {
        flex: 1;
        min-width: 0;
    }

    .group-title {
        font-size: 14px;
        font-weight: 600;
        color: var(--text-dark);
        margin-bottom: 4px;
    }

    .group-desc {
        color: var(--text-light);
        font-size: 12px;
        line-height: 1.5;
        margin-bottom: 9px;
    }

    .group-actions {
        display: flex;
        gap: 7px;
        flex-wrap: wrap;
    }

    .category-alert {
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

    .category-alert.success {
        background: rgba(72, 187, 120, .14);
        color: #276749;
        border-left: 4px solid var(--success-color);
    }

    .category-alert.danger {
        background: rgba(245, 101, 101, .13);
        color: #9b2c2c;
        border-left: 4px solid var(--danger-color);
    }

    .category-alert button {
        border: 0;
        background: transparent;
        color: inherit;
        font-size: 20px;
        cursor: pointer;
    }

    .category-empty {
        text-align: center;
        padding: 46px 20px;
    }

    .category-empty .icon {
        font-size: 48px;
        margin-bottom: 15px;
    }

    .category-empty h3 {
        color: var(--text-dark);
        margin: 0 0 8px;
        font-size: 16px;
        font-weight: 600;
    }

    .category-empty p {
        color: var(--text-light);
        margin: 0;
        font-size: 14px;
    }

    .category-card-footer {
        border-top: 1px solid var(--border-color);
        padding: 18px 24px;
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        background: #fafafa;
    }

    @media (max-width: 1100px) {
        .categories-grid {
            grid-template-columns: 1fr;
        }

        .category-filter,
        .category-form-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 768px) {
        .categories-toolbar {
            padding: 25px;
        }

        .categories-toolbar h1 {
            font-size: 24px;
        }

        .categories-actions,
        .btn-category {
            width: 100%;
        }

        .category-card-header {
            align-items: flex-start;
        }

        .category-actions-inline {
            justify-content: flex-start;
        }
    }
</style>

<div class="categories-page">
    <div class="categories-toolbar">
        <div>
            <h1><?= $action === 'edit_group' ? 'Cập nhật nhóm sản phẩm' : ($action === 'edit_category' ? 'Cập nhật loại sản phẩm' : 'Quản lý danh mục sản phẩm') ?></h1>
            <p><?= ($action === 'edit_group' || $action === 'edit_category') ? 'Chỉnh sửa thông tin danh mục và đồng bộ phân loại sản phẩm.' : 'Quản lý nhóm sản phẩm, loại sản phẩm và số lượng sản phẩm theo từng danh mục.' ?></p>
        </div>
        <div class="categories-actions">
            <?php if ($action === 'list'): ?>
                <a href="#category-create" class="btn-category primary">＋ Thêm danh mục</a>
            <?php else: ?>
                <a href="<?= e(getBaseUrl()) ?>/modules/products/categories.php" class="btn-category primary">← Quay lại danh sách</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="category-alert success">
            <span>✅ <?= e($success) ?></span>
            <button type="button" onclick="this.parentElement.remove()">×</button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="category-alert danger">
            <span>❌ <?= e($error) ?></span>
            <button type="button" onclick="this.parentElement.remove()">×</button>
        </div>
    <?php endif; ?>

    <?php if ($action === 'edit_group' && $editGroup): ?>
        <div class="category-card">
            <div class="category-card-header">
                <div>
                    <h2>Thông tin nhóm sản phẩm</h2>
                    <p>Cập nhật tên nhóm và mô tả hiển thị.</p>
                </div>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="edit_group">
                <input type="hidden" name="ma_nhom" value="<?= e($editGroup['MaNSP']) ?>">
                <div class="category-card-body">
                    <div class="category-form-grid">
                        <div class="category-field">
                            <label class="required" for="ten_nhom">Tên nhóm</label>
                            <input class="category-input" type="text" id="ten_nhom" name="ten_nhom" value="<?= e($editGroup['TenNSP']) ?>" required>
                        </div>
                        <div class="category-field">
                            <label>Mã nhóm</label>
                            <input class="category-input" type="text" value="#NSP<?= e($editGroup['MaNSP']) ?>" disabled>
                        </div>
                    </div>
                    <div class="category-field" style="margin-top:18px">
                        <label for="mo_ta">Mô tả</label>
                        <textarea class="category-textarea" id="mo_ta" name="mo_ta" placeholder="Mô tả ngắn về nhóm sản phẩm"><?= e($editGroup['MoTaNhom']) ?></textarea>
                    </div>
                </div>
                <div class="category-card-footer">
                    <button class="btn-category solid" type="submit">💾 Lưu nhóm</button>
                    <a class="btn-category light" href="<?= e(getBaseUrl()) ?>/modules/products/categories.php">Hủy</a>
                </div>
            </form>
        </div>
    <?php elseif ($action === 'edit_category' && $editCategory): ?>
        <div class="category-card">
            <div class="category-card-header">
                <div>
                    <h2>Thông tin loại sản phẩm</h2>
                    <p>Cập nhật tên loại và nhóm sản phẩm trực thuộc.</p>
                </div>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="edit_category">
                <input type="hidden" name="ma_loai" value="<?= e($editCategory['MaLSP']) ?>">
                <div class="category-card-body">
                    <div class="category-form-grid">
                        <div class="category-field">
                            <label class="required" for="ten_loai">Tên loại</label>
                            <input class="category-input" type="text" id="ten_loai" name="ten_loai" value="<?= e($editCategory['TenLSP']) ?>" required>
                        </div>
                        <div class="category-field">
                            <label class="required" for="ma_nhom">Nhóm sản phẩm</label>
                            <select class="category-select" id="ma_nhom" name="ma_nhom" required>
                                <option value="">-- Chọn nhóm --</option>
                                <?php foreach ($groups as $group): ?>
                                    <option value="<?= e($group['MaNSP']) ?>" <?= (int)$editCategory['MaNSP'] === (int)$group['MaNSP'] ? 'selected' : '' ?>>
                                        <?= e($group['TenNSP']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="category-card-footer">
                    <button class="btn-category solid" type="submit">💾 Lưu loại</button>
                    <a class="btn-category light" href="<?= e(getBaseUrl()) ?>/modules/products/categories.php">Hủy</a>
                </div>
            </form>
        </div>
    <?php else: ?>
        <div class="categories-stats">
            <div class="category-stat-card">
                <div class="category-stat-icon">🗂️</div>
                <div class="category-stat-content">
                    <h3>Tổng nhóm</h3>
                    <div class="value"><?= number_format((int)$stats['total_groups']) ?></div>
                    <div class="hint">Nhóm sản phẩm</div>
                </div>
            </div>
            <div class="category-stat-card success">
                <div class="category-stat-icon">🏷️</div>
                <div class="category-stat-content">
                    <h3>Tổng loại</h3>
                    <div class="value"><?= number_format((int)$stats['total_categories']) ?></div>
                    <div class="hint">Loại sản phẩm</div>
                </div>
            </div>
            <div class="category-stat-card warning">
                <div class="category-stat-icon">📦</div>
                <div class="category-stat-content">
                    <h3>Đã phân loại</h3>
                    <div class="value"><?= number_format((int)$stats['assigned_products']) ?></div>
                    <div class="hint">Sản phẩm có loại</div>
                </div>
            </div>
            <div class="category-stat-card danger">
                <div class="category-stat-icon">⚠️</div>
                <div class="category-stat-content">
                    <h3>Chưa phân loại</h3>
                    <div class="value"><?= number_format((int)$stats['unassigned_products']) ?></div>
                    <div class="hint">Cần bổ sung danh mục</div>
                </div>
            </div>
        </div>

        <div class="categories-grid" id="category-create">
            <section>
                <div class="category-card">
                    <div class="category-card-header">
                        <div>
                            <h2>Thêm nhóm sản phẩm</h2>
                            <p>Tạo cấp danh mục lớn để gom các loại sản phẩm.</p>
                        </div>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="action" value="add_group">
                        <div class="category-card-body">
                            <div class="category-field">
                                <label class="required" for="ten_nhom">Tên nhóm</label>
                                <input class="category-input" type="text" id="ten_nhom" name="ten_nhom" placeholder="VD: Thuốc điều trị" required>
                            </div>
                            <div class="category-field" style="margin-top:16px">
                                <label for="mo_ta">Mô tả</label>
                                <textarea class="category-textarea" id="mo_ta" name="mo_ta" placeholder="Mô tả ngắn về nhóm sản phẩm"></textarea>
                            </div>
                        </div>
                        <div class="category-card-footer">
                            <button class="btn-category solid" type="submit">＋ Thêm nhóm</button>
                        </div>
                    </form>
                </div>

                <div class="category-card">
                    <div class="category-card-header">
                        <div>
                            <h2>Danh sách nhóm</h2>
                            <p>Theo dõi số loại và sản phẩm trong từng nhóm.</p>
                        </div>
                    </div>
                    <div class="category-card-body">
                        <?php if (!empty($groups)): ?>
                            <div class="group-list">
                                <?php foreach ($groups as $group): ?>
                                    <div class="group-item">
                                        <div class="category-avatar <?= e(categoryColorClass($group['MaNSP'])) ?>">
                                            <?= e(categoryInitial($group['TenNSP'])) ?>
                                        </div>
                                        <div class="group-item-main">
                                            <div class="group-title"><?= e($group['TenNSP']) ?></div>
                                            <div class="group-desc"><?= e($group['MoTaNhom'] ?: 'Chưa có mô tả cho nhóm này.') ?></div>
                                            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px">
                                                <span class="category-badge primary">🏷️ <?= number_format((int)$group['TongLoai']) ?> loại</span>
                                                <span class="category-badge success">📦 <?= number_format((int)$group['TongSanPham']) ?> sản phẩm</span>
                                            </div>
                                            <div class="group-actions">
                                                <a class="btn-category info sm" href="?action=edit_group&id=<?= e($group['MaNSP']) ?>">Sửa</a>
                                                <form method="POST" style="display:inline" onsubmit="return confirm('Bạn chắc chắn muốn xóa nhóm sản phẩm này?');">
                                                    <input type="hidden" name="action" value="delete_group">
                                                    <input type="hidden" name="ma_nhom" value="<?= e($group['MaNSP']) ?>">
                                                    <button class="btn-category danger sm" type="submit">Xóa</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="category-empty">
                                <div class="icon">🗂️</div>
                                <h3>Chưa có nhóm sản phẩm</h3>
                                <p>Hãy tạo nhóm sản phẩm đầu tiên để phân loại dữ liệu.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <section>
                <div class="category-card">
                    <div class="category-card-header">
                        <div>
                            <h2>Thêm loại sản phẩm</h2>
                            <p>Loại sản phẩm được gán vào một nhóm cụ thể.</p>
                        </div>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="action" value="add_category">
                        <div class="category-card-body">
                            <div class="category-form-grid">
                                <div class="category-field">
                                    <label class="required" for="ten_loai">Tên loại</label>
                                    <input class="category-input" type="text" id="ten_loai" name="ten_loai" placeholder="VD: Thuốc giảm đau" required>
                                </div>
                                <div class="category-field">
                                    <label class="required" for="ma_nhom">Nhóm sản phẩm</label>
                                    <select class="category-select" id="ma_nhom" name="ma_nhom" required>
                                        <option value="">-- Chọn nhóm --</option>
                                        <?php foreach ($groups as $group): ?>
                                            <option value="<?= e($group['MaNSP']) ?>"><?= e($group['TenNSP']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="category-card-footer">
                            <button class="btn-category solid" type="submit">＋ Thêm loại</button>
                        </div>
                    </form>
                </div>

                <div class="category-card">
                    <div class="category-card-header">
                        <div>
                            <h2>Danh sách loại sản phẩm</h2>
                            <!-- <p>Giao diện category-list đồng bộ với dashboard và product-list.</p> -->
                        </div>
                    </div>
                    <div class="category-card-body">
                        <form method="GET" class="category-filter">
                            <input type="hidden" name="action" value="list">
                            <div class="category-field">
                                <label>Tìm kiếm</label>
                                <input class="category-input" type="text" name="search" value="<?= e($searchQuery) ?>" placeholder="Tìm theo loại hoặc nhóm sản phẩm...">
                            </div>
                            <div class="category-field">
                                <label>Nhóm sản phẩm</label>
                                <select class="category-select" name="group">
                                    <option value="">Tất cả nhóm</option>
                                    <?php foreach ($groups as $group): ?>
                                        <option value="<?= e($group['MaNSP']) ?>" <?= $filterGroup == $group['MaNSP'] ? 'selected' : '' ?>>
                                            <?= e($group['TenNSP']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button class="btn-category solid" type="submit">🔎 Lọc</button>
                            <a class="btn-category light" href="<?= e(getBaseUrl()) ?>/modules/products/categories.php">Đặt lại</a>
                        </form>
                    </div>

                    <?php if (!empty($categories)): ?>
                        <div class="category-table-wrap">
                            <table class="category-table">
                                <thead>
                                    <tr>
                                        <th>Loại sản phẩm</th>
                                        <th>Nhóm</th>
                                        <th>Sản phẩm</th>
                                        <th>Mã loại</th>
                                        <th style="text-align:right">Hành động</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($categories as $cat): ?>
                                        <tr>
                                            <td>
                                                <div class="category-cell">
                                                    <div class="category-avatar <?= e(categoryColorClass($cat['MaLSP'])) ?>">
                                                        <?= e(categoryInitial($cat['TenLSP'])) ?>
                                                    </div>
                                                    <div>
                                                        <div class="category-name"><?= e($cat['TenLSP']) ?></div>
                                                        <div class="category-sub">Thuộc nhóm: <?= e($cat['TenNSP'] ?: 'Chưa có nhóm') ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><span class="category-badge primary">🗂️ <?= e($cat['TenNSP'] ?: 'Chưa có nhóm') ?></span></td>
                                            <td><span class="category-badge success">📦 <?= number_format((int)$cat['TongSanPham']) ?></span></td>
                                            <td><span class="category-sub">#LSP<?= e($cat['MaLSP']) ?></span></td>
                                            <td>
                                                <div class="category-actions-inline">
                                                    <a class="btn-category info sm" href="?action=edit_category&id=<?= e($cat['MaLSP']) ?>">Sửa</a>
                                                    <form method="POST" style="display:inline" onsubmit="return confirm('Bạn chắc chắn muốn xóa loại sản phẩm này?');">
                                                        <input type="hidden" name="action" value="delete_category">
                                                        <input type="hidden" name="ma_loai" value="<?= e($cat['MaLSP']) ?>">
                                                        <button class="btn-category danger sm" type="submit">Xóa</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="category-empty">
                            <div class="icon">🏷️</div>
                            <h3>Không tìm thấy loại sản phẩm</h3>
                            <p>Thử đổi bộ lọc hoặc thêm loại sản phẩm mới.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    <?php endif; ?>
</div>

<script>
    document.querySelectorAll('.category-alert').forEach(function(alert) {
        setTimeout(function() {
            if (alert && alert.parentElement) alert.remove();
        }, 5000);
    });
</script>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/quan_ly_vat_tu/includes/footer.php'; ?>