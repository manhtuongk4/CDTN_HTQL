<?php
$pageTitle = 'Kiểm kê tồn kho - Hệ thống quản lý vật tư y tế';
require_once $_SERVER['DOCUMENT_ROOT'] . '/quan_ly_vat_tu/config/database.php';
session_start();
requireLogin();

$searchQuery = trim($_GET['search'] ?? '');
$filterStatus = $_GET['status'] ?? '';
$filterCategory = $_GET['category'] ?? '';

function e($value)
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function money($value)
{
    return number_format((float)$value, 0, ',', '.') . ' ₫';
}

function dateVN($date)
{
    if (!$date) {
        return 'N/A';
    }
    $timestamp = strtotime($date);
    return $timestamp ? date('d/m/Y', $timestamp) : 'N/A';
}

function shortName($name)
{
    $name = trim((string)$name);
    if ($name === '') {
        return 'SP';
    }

    if (function_exists('mb_substr') && function_exists('mb_strtoupper')) {
        $parts = preg_split('/\s+/u', $name);
        $first = mb_substr($parts[0], 0, 1, 'UTF-8');
        $last = count($parts) > 1 ? mb_substr($parts[count($parts) - 1], 0, 1, 'UTF-8') : '';
        return mb_strtoupper($first . $last, 'UTF-8');
    }

    return strtoupper(substr($name, 0, 2));
}

function productDisplayCode($maSP)
{
    $maSP = trim((string)$maSP);
    if ($maSP === '') {
        return '#SP';
    }

    if (preg_match('/^SP/i', $maSP)) {
        return '#' . strtoupper($maSP);
    }

    return '#SP' . $maSP;
}

function lotProductSuffix($maSP)
{
    $maSP = trim((string)$maSP);

    if (preg_match('/(\d+)$/', $maSP, $matches)) {
        return str_pad($matches[1], 2, '0', STR_PAD_LEFT);
    }

    $clean = preg_replace('/[^A-Za-z0-9]/', '', $maSP);
    $clean = strtoupper($clean ?: 'SP');
    return substr($clean, -4);
}

function displayLotCode($maLo, $maSP, $ngaySanXuat = null)
{
    $raw = trim((string)$maLo);
    if ($raw === '') {
        return 'N/A';
    }

    if (preg_match('/^L\d{4}_[A-Za-z0-9]+(?:_\d{2})?$/', $raw)) {
        return $raw;
    }

    if (preg_match('/^LO_(\d{4})(\d{2})\d{8,}_.+/i', $raw, $matches)) {
        return 'L' . substr($matches[1], -2) . $matches[2] . '_' . lotProductSuffix($maSP);
    }

    if (preg_match('/^RECOVER_/i', $raw)) {
        $timestamp = strtotime((string)$ngaySanXuat) ?: time();
        return 'R' . date('ym', $timestamp) . '_' . lotProductSuffix($maSP);
    }

    if (mb_strlen($raw, 'UTF-8') > 18) {
        return mb_substr($raw, 0, 15, 'UTF-8') . '...';
    }

    return $raw;
}

function statusInfo($expiryDate)
{
    $timestamp = strtotime((string)$expiryDate);
    if (!$timestamp) {
        return [
            'key' => 'unknown',
            'label' => 'Chưa có HSD',
            'badge' => 'muted',
            'icon' => '❔',
            'days' => null,
        ];
    }

    $today = strtotime(date('Y-m-d'));
    $days = (int)floor(($timestamp - $today) / 86400);

    if ($days < 0) {
        return [
            'key' => 'expired',
            'label' => 'Hết hạn',
            'badge' => 'danger',
            'icon' => '❌',
            'days' => $days,
        ];
    }

    if ($days <= 30) {
        return [
            'key' => 'critical',
            'label' => 'Rất cận date',
            'badge' => 'danger',
            'icon' => '🔴',
            'days' => $days,
        ];
    }

    if ($days <= 180) {
        return [
            'key' => 'expiring',
            'label' => 'Sắp hết hạn',
            'badge' => 'warning',
            'icon' => '⚠️',
            'days' => $days,
        ];
    }

    return [
        'key' => 'valid',
        'label' => 'Còn hạn',
        'badge' => 'success',
        'icon' => '✅',
        'days' => $days,
    ];
}

// Lấy danh mục để lọc
try {
    $categoryStmt = $pdo->query('SELECT MaLSP, TenLSP FROM loaisp ORDER BY TenLSP');
    $categories = $categoryStmt->fetchAll();
} catch (PDOException $e) {
    $categories = [];
}

// Lấy danh sách tồn kho
try {
    $stmt = $pdo->query('
        SELECT kh.MaLo, sp.MaSP, sp.TenSP, kh.SoLuongTon, sp.DonGia,
               (kh.SoLuongTon * sp.DonGia) AS gia_tri_ton,
               kh.NgaySanXuat, kh.HanSuDung, ls.MaLSP, ls.TenLSP
        FROM khohang kh
        JOIN sanpham sp ON kh.MaSP = sp.MaSP
        LEFT JOIN loaisp ls ON sp.MaLSP = ls.MaLSP
        ORDER BY kh.HanSuDung ASC, sp.TenSP ASC
    ');
    $allInventory = $stmt->fetchAll();
} catch (PDOException $e) {
    $allInventory = [];
}

$inventory = array_values(array_filter($allInventory, function ($item) use ($searchQuery, $filterStatus, $filterCategory) {
    $status = statusInfo($item['HanSuDung'] ?? null);

    if ($searchQuery !== '') {
        $haystack = strtolower(
            ($item['MaLo'] ?? '') . ' ' .
                ($item['TenSP'] ?? '') . ' ' .
                ($item['TenLSP'] ?? '') . ' ' .
                ($item['MaSP'] ?? '')
        );
        if (strpos($haystack, strtolower($searchQuery)) === false) {
            return false;
        }
    }

    if ($filterCategory !== '' && (string)($item['MaLSP'] ?? '') !== (string)$filterCategory) {
        return false;
    }

    if ($filterStatus !== '' && $status['key'] !== $filterStatus) {
        return false;
    }

    return true;
}));

$totalLots = count($allInventory);
$totalItems = count($inventory);
$totalValue = array_sum(array_column($inventory, 'gia_tri_ton'));
$totalQuantity = array_sum(array_column($inventory, 'SoLuongTon'));
$expiredLots = count(array_filter($allInventory, fn($item) => statusInfo($item['HanSuDung'] ?? null)['key'] === 'expired'));
$expiringLots = count(array_filter($allInventory, fn($item) => in_array(statusInfo($item['HanSuDung'] ?? null)['key'], ['critical', 'expiring'], true)));

try {
    $stmt = $pdo->query('
        SELECT sp.MaSP, sp.TenSP, SUM(kh.SoLuongTon) AS tong_sl,
               SUM(kh.SoLuongTon * sp.DonGia) AS tong_gia_tri,
               COUNT(DISTINCT kh.MaLo) AS so_lo,
               MIN(kh.HanSuDung) AS han_som_nhat,
               ls.TenLSP
        FROM khohang kh
        JOIN sanpham sp ON kh.MaSP = sp.MaSP
        LEFT JOIN loaisp ls ON sp.MaLSP = ls.MaLSP
        GROUP BY sp.MaSP, sp.TenSP, ls.TenLSP
        ORDER BY tong_gia_tri DESC
    ');
    $byProduct = $stmt->fetchAll();
} catch (PDOException $e) {
    $byProduct = [];
}

include $_SERVER['DOCUMENT_ROOT'] . '/quan_ly_vat_tu/includes/header.php';
include $_SERVER['DOCUMENT_ROOT'] . '/quan_ly_vat_tu/includes/sidebar.php';
?>

<style>
    .stock-page {
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

    .stock-toolbar {
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

    .stock-toolbar h1 {
        font-size: 28px;
        line-height: 1.25;
        margin: 0 0 8px;
        font-weight: 700;
    }

    .stock-toolbar p {
        margin: 0;
        font-size: 14px;
        opacity: .9;
    }

    .stock-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    .stock-btn {
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

    .stock-btn.primary {
        background: white;
        color: var(--primary-color);
    }

    .stock-btn.primary:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow);
    }

    .stock-btn.solid {
        background: var(--primary-color);
        color: white;
    }

    .stock-btn.solid:hover {
        background: var(--secondary-color);
        color: white;
    }

    .stock-btn.light {
        background: #fff;
        color: var(--text-dark);
        border: 1px solid var(--border-color);
    }

    .stock-btn.light:hover {
        background: var(--light-bg);
        color: var(--primary-color);
    }

    .stock-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .stock-stat-card {
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

    .stock-stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
    }

    .stock-stat-card.success::before {
        background: var(--success-color);
    }

    .stock-stat-card.warning::before {
        background: var(--warning-color);
    }

    .stock-stat-card.danger::before {
        background: var(--danger-color);
    }

    .stock-stat-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--shadow-lg);
        border-color: var(--primary-color);
    }

    .stock-stat-icon {
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

    .stock-stat-card.success .stock-stat-icon {
        background: rgba(72, 187, 120, .15);
        color: var(--success-color);
    }

    .stock-stat-card.warning .stock-stat-icon {
        background: rgba(237, 137, 54, .15);
        color: var(--warning-color);
    }

    .stock-stat-card.danger .stock-stat-icon {
        background: rgba(245, 101, 101, .15);
        color: var(--danger-color);
    }

    .stock-stat-content h3 {
        margin: 0 0 7px;
        color: var(--text-light);
        font-size: 12px;
        text-transform: uppercase;
        font-weight: 600;
        letter-spacing: .5px;
    }

    .stock-stat-content .value {
        color: var(--text-dark);
        font-size: 24px;
        font-weight: 700;
        line-height: 1.15;
    }

    .stock-stat-content .hint {
        margin-top: 7px;
        color: var(--text-light);
        font-size: 12px;
    }

    .stock-card {
        background: white;
        border-radius: 8px;
        box-shadow: var(--shadow);
        margin-bottom: 30px;
        overflow: hidden;
    }

    .stock-card-header {
        padding: 20px 24px;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        flex-wrap: wrap;
    }

    .stock-card-header h2 {
        margin: 0;
        font-size: 18px;
        font-weight: 600;
        color: var(--text-dark);
    }

    .stock-card-header p {
        margin: 5px 0 0;
        font-size: 13px;
        color: var(--text-light);
    }

    .stock-card-body {
        padding: 24px;
    }

    .stock-filter {
        display: grid;
        grid-template-columns: 1.6fr 1fr 1fr auto auto;
        gap: 12px;
        align-items: end;
    }

    .stock-field label {
        display: block;
        margin-bottom: 7px;
        color: var(--text-dark);
        font-size: 13px;
        font-weight: 600;
    }

    .stock-input,
    .stock-select {
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
        font-family: inherit;
    }

    .stock-input:focus,
    .stock-select:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(102, 126, 234, .12);
    }

    .stock-table-wrap {
        overflow-x: auto;
    }

    .stock-table {
        width: 100%;
        border-collapse: collapse;
    }

    .stock-table th {
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

    .stock-table td {
        padding: 16px;
        border-bottom: 1px solid var(--border-color);
        color: var(--text-dark);
        vertical-align: middle;
        font-size: 14px;
    }

    .stock-table tr:hover td {
        background: var(--light-bg);
    }

    .stock-item-cell {
        display: flex;
        align-items: center;
        gap: 12px;
        min-width: 260px;
    }

    .stock-avatar {
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
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    }

    .stock-name {
        color: var(--text-dark);
        font-size: 14px;
        font-weight: 600;
        margin-bottom: 3px;
    }

    .stock-sub {
        color: var(--text-light);
        font-size: 12px;
        max-width: 260px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .stock-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 11px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        white-space: nowrap;
    }

    .stock-badge.success {
        background: rgba(72, 187, 120, .14);
        color: var(--success-color);
    }

    .stock-badge.warning {
        background: rgba(237, 137, 54, .14);
        color: var(--warning-color);
    }

    .stock-badge.danger {
        background: rgba(245, 101, 101, .13);
        color: var(--danger-color);
    }

    .stock-badge.muted {
        background: var(--light-bg);
        color: var(--text-light);
    }

    .stock-empty {
        text-align: center;
        padding: 50px 20px;
    }

    .stock-empty .icon {
        font-size: 48px;
        margin-bottom: 15px;
    }

    .stock-empty h3 {
        color: var(--text-dark);
        margin: 0 0 8px;
        font-size: 16px;
        font-weight: 600;
    }

    .stock-empty p {
        color: var(--text-light);
        margin: 0;
        font-size: 14px;
    }

    .stock-summary-row td {
        background: #fafafa;
        font-weight: 700;
    }

    @media (max-width: 992px) {
        .stock-filter {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 768px) {
        .stock-toolbar {
            padding: 25px;
        }

        .stock-toolbar h1 {
            font-size: 24px;
        }

        .stock-actions,
        .stock-btn {
            width: 100%;
        }

        .stock-card-header {
            align-items: flex-start;
        }
    }
</style>

<div class="stock-page">
    <div class="stock-toolbar">
        <div>
            <h1>Kiểm kê tồn kho</h1>
            <p>Tra cứu số lượng, giá trị tồn kho và hạn sử dụng theo từng lô hàng.</p>
        </div>
        <div class="stock-actions">
            <button type="button" onclick="exportTableToCSV('inventory.csv')" class="stock-btn primary">📥 Xuất CSV</button>
            <a href="<?= e(getBaseUrl()) ?>/modules/warehouse/alerts.php" class="stock-btn primary">⚠️ Xem cảnh báo</a>
        </div>
    </div>

    <div class="stock-stats">
        <div class="stock-stat-card">
            <div class="stock-stat-icon">📦</div>
            <div class="stock-stat-content">
                <h3>Số lô hàng</h3>
                <div class="value"><?= number_format($totalLots) ?></div>
                <div class="hint">Tổng số lô trong kho</div>
            </div>
        </div>
        <div class="stock-stat-card success">
            <div class="stock-stat-icon">🔢</div>
            <div class="stock-stat-content">
                <h3>Tổng số lượng</h3>
                <div class="value"><?= number_format($totalQuantity) ?></div>
                <div class="hint">Theo bộ lọc hiện tại</div>
            </div>
        </div>
        <div class="stock-stat-card warning">
            <div class="stock-stat-icon">💰</div>
            <div class="stock-stat-content">
                <h3>Giá trị tồn</h3>
                <div class="value"><?= money($totalValue) ?></div>
                <div class="hint">Tổng giá trị hiện thị</div>
            </div>
        </div>
        <div class="stock-stat-card danger">
            <div class="stock-stat-icon">⚠️</div>
            <div class="stock-stat-content">
                <h3>Lô cần chú ý</h3>
                <div class="value"><?= number_format($expiredLots + $expiringLots) ?></div>
                <div class="hint"><?= number_format($expiredLots) ?> hết hạn, <?= number_format($expiringLots) ?> cận date</div>
            </div>
        </div>
    </div>

    <div class="stock-card">
        <div class="stock-card-header">
            <div>
                <h2>Tồn kho hiện tại</h2>
                <p>Danh sách tồn kho theo lô, trạng thái hạn dùng và giá trị tồn.</p>
            </div>
        </div>

        <div class="stock-card-body">
            <form method="GET" class="stock-filter">
                <div class="stock-field">
                    <label>Tìm kiếm</label>
                    <input class="stock-input" type="text" name="search" value="<?= e($searchQuery) ?>" placeholder="Tìm mã lô, tên sản phẩm, loại sản phẩm...">
                </div>
                <div class="stock-field">
                    <label>Loại sản phẩm</label>
                    <select class="stock-select" name="category">
                        <option value="">Tất cả loại</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= e($cat['MaLSP']) ?>" <?= (string)$filterCategory === (string)$cat['MaLSP'] ? 'selected' : '' ?>>
                                <?= e($cat['TenLSP']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="stock-field">
                    <label>Trạng thái hạn dùng</label>
                    <select class="stock-select" name="status">
                        <option value="">Tất cả trạng thái</option>
                        <option value="valid" <?= $filterStatus === 'valid' ? 'selected' : '' ?>>Còn hạn</option>
                        <option value="expiring" <?= $filterStatus === 'expiring' ? 'selected' : '' ?>>Sắp hết hạn</option>
                        <option value="critical" <?= $filterStatus === 'critical' ? 'selected' : '' ?>>Rất cận date</option>
                        <option value="expired" <?= $filterStatus === 'expired' ? 'selected' : '' ?>>Hết hạn</option>
                    </select>
                </div>
                <button class="stock-btn solid" type="submit">🔎 Lọc</button>
                <a class="stock-btn light" href="<?= e(getBaseUrl()) ?>/modules/warehouse/inventory.php">Đặt lại</a>
            </form>
        </div>

        <?php if (!empty($inventory)): ?>
            <div class="stock-table-wrap">
                <table class="stock-table" id="inventoryTable">
                    <thead>
                        <tr>
                            <th>Lô / Sản phẩm</th>
                            <th>Loại</th>
                            <th>Số lượng</th>
                            <th>Đơn giá</th>
                            <th>Giá trị tồn</th>
                            <th>Ngày SX</th>
                            <th>Hạn dùng</th>
                            <th>Trạng thái</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($inventory as $item): ?>
                            <?php $status = statusInfo($item['HanSuDung'] ?? null); ?>
                            <tr>
                                <td>
                                    <div class="stock-item-cell">
                                        <div class="stock-avatar"><?= e(shortName($item['TenSP'])) ?></div>
                                        <div>
                                            <div class="stock-name"><?= e($item['TenSP']) ?></div>
                                            <div class="stock-sub" title="Mã lô gốc: <?= e($item['MaLo']) ?>">Lô <?= e(displayLotCode($item['MaLo'], $item['MaSP'], $item['NgaySanXuat'])) ?> • <?= e(productDisplayCode($item['MaSP'])) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><?= $item['TenLSP'] ? '<span class="stock-badge muted">' . e($item['TenLSP']) . '</span>' : '<span class="stock-badge muted">Chưa phân loại</span>' ?></td>
                                <td><strong><?= number_format((int)$item['SoLuongTon']) ?></strong></td>
                                <td><?= money($item['DonGia']) ?></td>
                                <td><strong><?= money($item['gia_tri_ton']) ?></strong></td>
                                <td><?= e(dateVN($item['NgaySanXuat'])) ?></td>
                                <td>
                                    <div><?= e(dateVN($item['HanSuDung'])) ?></div>
                                    <div class="stock-sub"><?= $status['days'] !== null ? (($status['days'] >= 0 ? 'Còn ' . number_format($status['days']) : 'Quá hạn ' . number_format(abs($status['days']))) . ' ngày') : 'Không xác định' ?></div>
                                </td>
                                <td><span class="stock-badge <?= e($status['badge']) ?>"><?= e($status['icon'] . ' ' . $status['label']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="stock-summary-row">
                            <td colspan="2" style="text-align:right">Tổng theo bộ lọc:</td>
                            <td><?= number_format($totalQuantity) ?></td>
                            <td></td>
                            <td><?= money($totalValue) ?></td>
                            <td colspan="3"></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="stock-empty">
                <div class="icon">📦</div>
                <h3>Không có dữ liệu tồn kho</h3>
                <p>Thử thay đổi bộ lọc hoặc nhập kho để tạo dữ liệu tồn.</p>
            </div>
        <?php endif; ?>
    </div>

    <div class="stock-card">
        <div class="stock-card-header">
            <div>
                <h2>Thống kê theo sản phẩm</h2>
                <p>Tổng hợp số lượng, số lô và giá trị tồn theo từng sản phẩm.</p>
            </div>
        </div>
        <?php if (!empty($byProduct)): ?>
            <div class="stock-table-wrap">
                <table class="stock-table">
                    <thead>
                        <tr>
                            <th>Sản phẩm</th>
                            <th>Loại</th>
                            <th>Tổng số lượng</th>
                            <th>Số lô</th>
                            <th>Hạn sớm nhất</th>
                            <th>Tổng giá trị</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($byProduct as $prod): ?>
                            <?php $status = statusInfo($prod['han_som_nhat'] ?? null); ?>
                            <tr>
                                <td>
                                    <div class="stock-item-cell">
                                        <div class="stock-avatar"><?= e(shortName($prod['TenSP'])) ?></div>
                                        <div>
                                            <div class="stock-name"><?= e($prod['TenSP']) ?></div>
                                            <div class="stock-sub">#SP<?= e($prod['MaSP']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><?= e($prod['TenLSP'] ?: 'Chưa phân loại') ?></td>
                                <td><strong><?= number_format((int)$prod['tong_sl']) ?></strong></td>
                                <td><?= number_format((int)$prod['so_lo']) ?></td>
                                <td><span class="stock-badge <?= e($status['badge']) ?>"><?= e(dateVN($prod['han_som_nhat'])) ?></span></td>
                                <td><strong><?= money($prod['tong_gia_tri']) ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="stock-empty">
                <div class="icon">📊</div>
                <h3>Không có dữ liệu</h3>
                <p>Chưa có sản phẩm nào trong kho.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    function exportTableToCSV(filename) {
        const table = document.getElementById('inventoryTable');
        if (!table) {
            alert('Không có dữ liệu để xuất.');
            return;
        }

        const rows = Array.from(table.querySelectorAll('tr'));
        const csv = rows.map(row => {
            const cells = Array.from(row.querySelectorAll('th,td'));
            return cells.map(cell => {
                const text = cell.innerText.replace(/\s+/g, ' ').trim().replace(/"/g, '""');
                return '"' + text + '"';
            }).join(',');
        }).join('\n');

        const blob = new Blob(['\uFEFF' + csv], {
            type: 'text/csv;charset=utf-8;'
        });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = filename;
        link.click();
        URL.revokeObjectURL(link.href);
    }
</script>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/quan_ly_vat_tu/includes/footer.php'; ?>