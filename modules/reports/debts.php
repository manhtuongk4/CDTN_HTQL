<?php
$pageTitle = 'Báo cáo công nợ - Hệ thống quản lý vật tư y tế';
require_once $_SERVER['DOCUMENT_ROOT'] . '/quan_ly_vat_tu/config/database.php';
session_start();
requireLogin();

function e($value)
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function money($value)
{
    return number_format((float)$value, 0, ',', '.') . ' ₫';
}

function moneyShort($value)
{
    $value = (float)$value;
    if (abs($value) >= 1000000000) {
        return number_format($value / 1000000000, 1, ',', '.') . 'B ₫';
    }
    if (abs($value) >= 1000000) {
        return number_format($value / 1000000, 1, ',', '.') . 'M ₫';
    }
    return money($value);
}

function initials($name)
{
    $name = trim((string)$name);
    if ($name === '') return 'CN';
    $parts = preg_split('/\s+/u', $name);
    $first = mb_substr($parts[0], 0, 1, 'UTF-8');
    $last = count($parts) > 1 ? mb_substr($parts[count($parts) - 1], 0, 1, 'UTF-8') : '';
    return mb_strtoupper($first . $last, 'UTF-8');
}

$searchQuery = trim($_GET['search'] ?? '');
$debtType = $_GET['type'] ?? 'all';
$statusFilter = $_GET['status'] ?? 'all';

// Lấy danh sách công nợ khách hàng
try {
    $stmt = $pdo->query('
        SELECT kh.MaKH, kh.TenKH, kh.DienThoai, kh.Email,
               COUNT(DISTINCT db.MaDBH) as so_don,
               COALESCE(SUM(ctb.SLBH * ctb.DGBH), 0) as tong_tien,
               COALESCE(SUM(CASE WHEN db.NgayGiao IS NOT NULL THEN ctb.SLBH * ctb.DGBH ELSE 0 END), 0) as da_giao,
               COALESCE(SUM(CASE WHEN db.NgayGiao IS NULL THEN ctb.SLBH * ctb.DGBH ELSE 0 END), 0) as chua_giao
        FROM khachhang kh
        LEFT JOIN donbh db ON kh.MaKH = db.MaKH
        LEFT JOIN chitietbanhang ctb ON db.MaDBH = ctb.MaDBH
        GROUP BY kh.MaKH, kh.TenKH, kh.DienThoai, kh.Email
        ORDER BY chua_giao DESC, tong_tien DESC
    ');
    $customerDebts = $stmt->fetchAll();
} catch (PDOException $e) {
    $customerDebts = [];
}

// Lấy danh sách công nợ nhà cung cấp
try {
    $stmt = $pdo->query('
        SELECT nc.MaNCC, nc.TenNCC, nc.DienThoai, nc.Email,
               COUNT(DISTINCT dm.MaDMH) as so_don,
               COALESCE(SUM(ctm.SLMH * ctm.DGMH), 0) as tong_tien,
               COALESCE(SUM(CASE WHEN dm.NgayGiao IS NOT NULL THEN ctm.SLMH * ctm.DGMH ELSE 0 END), 0) as da_giao,
               COALESCE(SUM(CASE WHEN dm.NgayGiao IS NULL THEN ctm.SLMH * ctm.DGMH ELSE 0 END), 0) as chua_giao
        FROM nhacc nc
        LEFT JOIN donmh dm ON nc.MaNCC = dm.MaNCC
        LEFT JOIN chitietmuahang ctm ON dm.MaDMH = ctm.MaDMH
        GROUP BY nc.MaNCC, nc.TenNCC, nc.DienThoai, nc.Email
        ORDER BY chua_giao DESC, tong_tien DESC
    ');
    $supplierDebts = $stmt->fetchAll();
} catch (PDOException $e) {
    $supplierDebts = [];
}

$filterDebtRows = function (array $rows, string $kind) use ($searchQuery, $statusFilter) {
    return array_values(array_filter($rows, function ($row) use ($searchQuery, $statusFilter, $kind) {
        $name = $kind === 'customer' ? ($row['TenKH'] ?? '') : ($row['TenNCC'] ?? '');
        $needle = mb_strtolower($searchQuery, 'UTF-8');
        if ($needle !== '') {
            $haystack = mb_strtolower($name . ' ' . ($row['DienThoai'] ?? '') . ' ' . ($row['Email'] ?? ''), 'UTF-8');
            if (mb_strpos($haystack, $needle, 0, 'UTF-8') === false) {
                return false;
            }
        }

        $unpaid = (float)($row['chua_giao'] ?? 0);
        if ($statusFilter === 'debt' && $unpaid <= 0) return false;
        if ($statusFilter === 'clear' && $unpaid > 0) return false;

        return true;
    }));
};

$customerRows = in_array($debtType, ['all', 'customer'], true) ? $filterDebtRows($customerDebts, 'customer') : [];
$supplierRows = in_array($debtType, ['all', 'supplier'], true) ? $filterDebtRows($supplierDebts, 'supplier') : [];

$totalCustomerDebt = array_sum(array_column($customerDebts, 'tong_tien'));
$totalCustomerUnpaid = array_sum(array_column($customerDebts, 'chua_giao'));
$totalSupplierDebt = array_sum(array_column($supplierDebts, 'tong_tien'));
$totalSupplierUnpaid = array_sum(array_column($supplierDebts, 'chua_giao'));
$totalUnpaid = $totalCustomerUnpaid + $totalSupplierUnpaid;
$totalDebt = $totalCustomerDebt + $totalSupplierDebt;
$customerDebtRatio = $totalCustomerDebt > 0 ? ($totalCustomerUnpaid / $totalCustomerDebt * 100) : 0;
$supplierDebtRatio = $totalSupplierDebt > 0 ? ($totalSupplierUnpaid / $totalSupplierDebt * 100) : 0;
$overallDebtRatio = $totalDebt > 0 ? ($totalUnpaid / $totalDebt * 100) : 0;
$partnersWithDebt = count(array_filter($customerDebts, fn($r) => (float)$r['chua_giao'] > 0)) + count(array_filter($supplierDebts, fn($r) => (float)$r['chua_giao'] > 0));

include $_SERVER['DOCUMENT_ROOT'] . '/quan_ly_vat_tu/includes/header.php';
include $_SERVER['DOCUMENT_ROOT'] . '/quan_ly_vat_tu/includes/sidebar.php';
?>

<style>
    .debts-page {
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

    .debts-toolbar {
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

    .debts-toolbar h1 {
        font-size: 28px;
        line-height: 1.25;
        margin: 0 0 8px;
        font-weight: 700;
    }

    .debts-toolbar p {
        margin: 0;
        font-size: 14px;
        opacity: .9;
    }

    .debts-actions {
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

    .debts-card {
        background: white;
        border-radius: 8px;
        box-shadow: var(--shadow);
        margin-bottom: 30px;
        overflow: hidden;
    }

    .debts-card-header {
        padding: 20px 24px;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        flex-wrap: wrap;
    }

    .debts-card-header h2,
    .debts-card-header h3 {
        margin: 0;
        font-size: 18px;
        font-weight: 600;
        color: var(--text-dark);
    }

    .debts-card-header p {
        margin: 5px 0 0;
        font-size: 13px;
        color: var(--text-light);
    }

    .debts-card-body {
        padding: 24px;
    }

    .debts-filter {
        display: grid;
        grid-template-columns: 1.6fr 1fr 1fr auto auto;
        gap: 12px;
        align-items: end;
    }

    .debts-field label {
        display: block;
        margin-bottom: 7px;
        color: var(--text-dark);
        font-size: 13px;
        font-weight: 600;
    }

    .debts-input,
    .debts-select {
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

    .debts-input:focus,
    .debts-select:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(102, 126, 234, .12);
    }

    .debts-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .debts-stat-card {
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

    .debts-stat-card::before {
        content: '';
        position: absolute;
        inset: 0 0 auto 0;
        height: 4px;
        background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
    }

    .debts-stat-card.success::before {
        background: var(--success-color);
    }

    .debts-stat-card.warning::before {
        background: var(--warning-color);
    }

    .debts-stat-card.danger::before {
        background: var(--danger-color);
    }

    .debts-stat-card.info::before {
        background: var(--info-color);
    }

    .debts-stat-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--shadow-lg);
        border-color: var(--primary-color);
    }

    .debts-stat-icon {
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

    .debts-stat-card.success .debts-stat-icon {
        background: rgba(72, 187, 120, .15);
        color: var(--success-color);
    }

    .debts-stat-card.warning .debts-stat-icon {
        background: rgba(237, 137, 54, .15);
        color: var(--warning-color);
    }

    .debts-stat-card.danger .debts-stat-icon {
        background: rgba(245, 101, 101, .15);
        color: var(--danger-color);
    }

    .debts-stat-card.info .debts-stat-icon {
        background: rgba(66, 153, 225, .15);
        color: var(--info-color);
    }

    .debts-stat-content h3 {
        margin: 0 0 7px;
        color: var(--text-light);
        font-size: 12px;
        text-transform: uppercase;
        font-weight: 600;
        letter-spacing: .5px;
    }

    .debts-stat-content .value {
        color: var(--text-dark);
        font-size: 23px;
        font-weight: 700;
        line-height: 1.15;
    }

    .debts-stat-content .hint {
        margin-top: 7px;
        color: var(--text-light);
        font-size: 12px;
    }

    .debts-grid-2 {
        display: grid;
        grid-template-columns: 1fr;
        gap: 30px;
        margin-bottom: 30px;
    }

    .debts-table-wrap {
        overflow-x: visible;
    }

    .debts-table {
        width: 100%;
        border-collapse: collapse;
    }

    .debts-table th {
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

    .debts-table td {
        padding: 16px;
        border-bottom: 1px solid var(--border-color);
        color: var(--text-dark);
        vertical-align: middle;
        font-size: 14px;
    }

    .debts-table tr:hover td {
        background: var(--light-bg);
    }

    .partner-cell {
        display: flex;
        align-items: center;
        gap: 12px;
        min-width: 250px;
    }

    .partner-avatar {
        width: 42px;
        height: 42px;
        border-radius: 12px;
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        color: white;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 13px;
        flex-shrink: 0;
    }

    .partner-name {
        font-size: 14px;
        font-weight: 600;
        color: var(--text-dark);
        margin-bottom: 3px;
    }

    .partner-sub {
        font-size: 12px;
        color: var(--text-light);
    }

    .debts-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 11px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        white-space: nowrap;
    }

    .debts-badge.success {
        background: rgba(72, 187, 120, .14);
        color: var(--success-color);
    }

    .debts-badge.warning {
        background: rgba(237, 137, 54, .14);
        color: var(--warning-color);
    }

    .debts-badge.danger {
        background: rgba(245, 101, 101, .12);
        color: var(--danger-color);
    }

    .debts-badge.info {
        background: rgba(102, 126, 234, .12);
        color: var(--primary-color);
    }

    .debt-progress {
        width: 110px;
        height: 8px;
        background: #edf2f7;
        border-radius: 99px;
        overflow: hidden;
    }

    .debt-progress span {
        display: block;
        height: 100%;
        border-radius: 99px;
        background: var(--warning-color);
    }

    .debt-progress.danger span {
        background: var(--danger-color);
    }

    .debt-progress.success span {
        background: var(--success-color);
    }

    .debts-empty {
        text-align: center;
        padding: 45px 20px;
        color: var(--text-light);
        font-size: 14px;
    }

    @media print {

        .navbar,
        .sidebar,
        .debts-toolbar,
        .debts-filter,
        .btn-dashboard {
            display: none !important;
        }

        .debts-card,
        .debts-stat-card {
            box-shadow: none;
            border: 1px solid #ddd;
        }
    }

    @media (max-width: 1200px) {
        .debts-filter {
            grid-template-columns: 1fr 1fr;
        }
    }

    @media (max-width: 900px) {
        .debts-table-wrap {
            overflow-x: auto;
        }

        .debts-table {
            min-width: 820px;
        }
    }

    @media (max-width: 768px) {
        .debts-toolbar {
            padding: 25px;
        }

        .debts-toolbar h1 {
            font-size: 24px;
        }

        .debts-actions,
        .btn-dashboard {
            width: 100%;
        }

        .debts-filter {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="debts-page">
    <div class="debts-toolbar">
        <div>
            <h1>💳 Báo cáo công nợ</h1>
            <p>Theo dõi công nợ khách hàng, nhà cung cấp và tỷ lệ chưa giao/chưa thanh toán.</p>
        </div>
        <div class="debts-actions">
            <button type="button" class="btn-dashboard primary" onclick="window.print()">🖨️ In báo cáo</button>
            <a href="<?= e(getBaseUrl()) ?>/modules/reports/revenue_costs.php" class="btn-dashboard primary">💰 Doanh thu & chi phí</a>
        </div>
    </div>

    <div class="debts-card">
        <div class="debts-card-header">
            <div>
                <h2>Bộ lọc công nợ</h2>
                <p>Tìm theo tên đối tác, số điện thoại hoặc email.</p>
            </div>
        </div>
        <div class="debts-card-body">
            <form method="GET" class="debts-filter">
                <div class="debts-field">
                    <label for="search">Tìm kiếm</label>
                    <input class="debts-input" type="text" id="search" name="search" value="<?= e($searchQuery) ?>" placeholder="Tên, SĐT, email...">
                </div>
                <div class="debts-field">
                    <label for="type">Loại đối tác</label>
                    <select class="debts-select" id="type" name="type">
                        <option value="all" <?= $debtType === 'all' ? 'selected' : '' ?>>Tất cả</option>
                        <option value="customer" <?= $debtType === 'customer' ? 'selected' : '' ?>>Khách hàng</option>
                        <option value="supplier" <?= $debtType === 'supplier' ? 'selected' : '' ?>>Nhà cung cấp</option>
                    </select>
                </div>
                <div class="debts-field">
                    <label for="status">Trạng thái</label>
                    <select class="debts-select" id="status" name="status">
                        <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>Tất cả</option>
                        <option value="debt" <?= $statusFilter === 'debt' ? 'selected' : '' ?>>Có công nợ</option>
                        <option value="clear" <?= $statusFilter === 'clear' ? 'selected' : '' ?>>Không công nợ</option>
                    </select>
                </div>
                <button type="submit" class="btn-dashboard solid">🔎 Lọc</button>
                <a href="<?= e(getBaseUrl()) ?>/modules/reports/debts.php" class="btn-dashboard light">Đặt lại</a>
            </form>
        </div>
    </div>

    <div class="debts-stats">
        <div class="debts-stat-card info">
            <div class="debts-stat-icon">🏥</div>
            <div class="debts-stat-content">
                <h3>Công nợ khách hàng</h3>
                <div class="value"><?= e(moneyShort($totalCustomerDebt)) ?></div>
                <div class="hint">Chưa giao/chưa thu: <?= e(moneyShort($totalCustomerUnpaid)) ?></div>
            </div>
        </div>
        <div class="debts-stat-card warning">
            <div class="debts-stat-icon">🏭</div>
            <div class="debts-stat-content">
                <h3>Công nợ nhà cung cấp</h3>
                <div class="value"><?= e(moneyShort($totalSupplierDebt)) ?></div>
                <div class="hint">Chưa giao/chưa trả: <?= e(moneyShort($totalSupplierUnpaid)) ?></div>
            </div>
        </div>
        <div class="debts-stat-card danger">
            <div class="debts-stat-icon">⚠️</div>
            <div class="debts-stat-content">
                <h3>Tổng chưa xử lý</h3>
                <div class="value"><?= e(moneyShort($totalUnpaid)) ?></div>
                <div class="hint"><?= number_format($partnersWithDebt) ?> đối tác còn công nợ</div>
            </div>
        </div>
        <div class="debts-stat-card success">
            <div class="debts-stat-icon">📊</div>
            <div class="debts-stat-content">
                <h3>Tỷ lệ công nợ</h3>
                <div class="value"><?= number_format($overallDebtRatio, 1, ',', '.') ?>%</div>
                <div class="hint">Trên tổng phát sinh hai chiều</div>
            </div>
        </div>
    </div>

    <div class="debts-grid-2">
        <div class="debts-card">
            <div class="debts-card-header">
                <div>
                    <h3>🏥 Công nợ khách hàng</h3>
                    <p><?= number_format(count($customerRows)) ?> khách hàng theo bộ lọc hiện tại.</p>
                </div>
                <span class="debts-badge info">Tỷ lệ <?= number_format($customerDebtRatio, 1, ',', '.') ?>%</span>
            </div>
            <div class="debts-table-wrap">
                <?php if (!empty($customerRows)): ?>
                    <table class="debts-table">
                        <thead>
                            <tr>
                                <th>Khách hàng</th>
                                <th>Đơn</th>
                                <th>Tổng tiền</th>
                                <th>Chưa xử lý</th>
                                <th>Tỷ lệ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($customerRows as $debt):
                                $unpaidRatio = (float)$debt['tong_tien'] > 0 ? ((float)$debt['chua_giao'] / (float)$debt['tong_tien'] * 100) : 0;
                                $progressClass = $unpaidRatio <= 0 ? 'success' : ($unpaidRatio > 50 ? 'danger' : '');
                            ?>
                                <tr>
                                    <td>
                                        <div class="partner-cell">
                                            <div class="partner-avatar"><?= e(initials($debt['TenKH'])) ?></div>
                                            <div>
                                                <div class="partner-name"><?= e($debt['TenKH']) ?></div>
                                                <div class="partner-sub"><?= e($debt['DienThoai'] ?: 'Chưa có SĐT') ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><span class="debts-badge info"><?= number_format((int)$debt['so_don']) ?></span></td>
                                    <td><?= e(money($debt['tong_tien'])) ?></td>
                                    <td><strong style="color:<?= (float)$debt['chua_giao'] > 0 ? 'var(--danger-color)' : 'var(--success-color)' ?>"><?= e(money($debt['chua_giao'])) ?></strong></td>
                                    <td>
                                        <div style="display:flex;align-items:center;gap:8px">
                                            <div class="debt-progress <?= $progressClass ?>"><span style="width:<?= min(100, $unpaidRatio) ?>%"></span></div>
                                            <span style="font-size:12px;color:var(--text-light)"><?= number_format($unpaidRatio, 1, ',', '.') ?>%</span>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="debts-empty">📭 Không có khách hàng phù hợp bộ lọc.</div>
                <?php endif; ?>
            </div>
        </div>

        <div class="debts-card">
            <div class="debts-card-header">
                <div>
                    <h3>🏭 Công nợ nhà cung cấp</h3>
                    <p><?= number_format(count($supplierRows)) ?> nhà cung cấp theo bộ lọc hiện tại.</p>
                </div>
                <span class="debts-badge warning">Tỷ lệ <?= number_format($supplierDebtRatio, 1, ',', '.') ?>%</span>
            </div>
            <div class="debts-table-wrap">
                <?php if (!empty($supplierRows)): ?>
                    <table class="debts-table">
                        <thead>
                            <tr>
                                <th>Nhà cung cấp</th>
                                <th>Đơn</th>
                                <th>Tổng tiền</th>
                                <th>Chưa xử lý</th>
                                <th>Tỷ lệ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($supplierRows as $debt):
                                $unpaidRatio = (float)$debt['tong_tien'] > 0 ? ((float)$debt['chua_giao'] / (float)$debt['tong_tien'] * 100) : 0;
                                $progressClass = $unpaidRatio <= 0 ? 'success' : ($unpaidRatio > 50 ? 'danger' : '');
                            ?>
                                <tr>
                                    <td>
                                        <div class="partner-cell">
                                            <div class="partner-avatar"><?= e(initials($debt['TenNCC'])) ?></div>
                                            <div>
                                                <div class="partner-name"><?= e($debt['TenNCC']) ?></div>
                                                <div class="partner-sub"><?= e($debt['DienThoai'] ?: 'Chưa có SĐT') ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><span class="debts-badge info"><?= number_format((int)$debt['so_don']) ?></span></td>
                                    <td><?= e(money($debt['tong_tien'])) ?></td>
                                    <td><strong style="color:<?= (float)$debt['chua_giao'] > 0 ? 'var(--danger-color)' : 'var(--success-color)' ?>"><?= e(money($debt['chua_giao'])) ?></strong></td>
                                    <td>
                                        <div style="display:flex;align-items:center;gap:8px">
                                            <div class="debt-progress <?= $progressClass ?>"><span style="width:<?= min(100, $unpaidRatio) ?>%"></span></div>
                                            <span style="font-size:12px;color:var(--text-light)"><?= number_format($unpaidRatio, 1, ',', '.') ?>%</span>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="debts-empty">📭 Không có nhà cung cấp phù hợp bộ lọc.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="debts-card">
        <div class="debts-card-header">
            <div>
                <h2>📊 Tóm tắt công nợ</h2>
                <p>So sánh phát sinh và khoản chưa xử lý giữa khách hàng và nhà cung cấp.</p>
            </div>
        </div>
        <div class="debts-table-wrap">
            <table class="debts-table">
                <thead>
                    <tr>
                        <th>Chỉ tiêu</th>
                        <th style="text-align:right">Khách hàng</th>
                        <th style="text-align:right">Nhà cung cấp</th>
                        <th style="text-align:right">Tổng cộng</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Số đối tác</strong></td>
                        <td style="text-align:right"><?= number_format(count($customerDebts)) ?></td>
                        <td style="text-align:right"><?= number_format(count($supplierDebts)) ?></td>
                        <td style="text-align:right"><?= number_format(count($customerDebts) + count($supplierDebts)) ?></td>
                    </tr>
                    <tr>
                        <td><strong>Tổng phát sinh</strong></td>
                        <td style="text-align:right"><?= e(money($totalCustomerDebt)) ?></td>
                        <td style="text-align:right"><?= e(money($totalSupplierDebt)) ?></td>
                        <td style="text-align:right"><strong><?= e(money($totalDebt)) ?></strong></td>
                    </tr>
                    <tr>
                        <td><strong>Chưa xử lý</strong></td>
                        <td style="text-align:right;color:var(--danger-color)"><?= e(money($totalCustomerUnpaid)) ?></td>
                        <td style="text-align:right;color:var(--danger-color)"><?= e(money($totalSupplierUnpaid)) ?></td>
                        <td style="text-align:right;color:var(--danger-color)"><strong><?= e(money($totalUnpaid)) ?></strong></td>
                    </tr>
                    <tr style="background:var(--light-bg);font-weight:700">
                        <td>Tỷ lệ công nợ</td>
                        <td style="text-align:right"><span class="debts-badge <?= $customerDebtRatio > 50 ? 'danger' : ($customerDebtRatio > 0 ? 'warning' : 'success') ?>"><?= number_format($customerDebtRatio, 1, ',', '.') ?>%</span></td>
                        <td style="text-align:right"><span class="debts-badge <?= $supplierDebtRatio > 50 ? 'danger' : ($supplierDebtRatio > 0 ? 'warning' : 'success') ?>"><?= number_format($supplierDebtRatio, 1, ',', '.') ?>%</span></td>
                        <td style="text-align:right"><span class="debts-badge <?= $overallDebtRatio > 50 ? 'danger' : ($overallDebtRatio > 0 ? 'warning' : 'success') ?>"><?= number_format($overallDebtRatio, 1, ',', '.') ?>%</span></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/quan_ly_vat_tu/includes/footer.php'; ?>