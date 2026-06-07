<?php
$pageTitle = 'Báo cáo doanh thu & chi phí - Hệ thống quản lý vật tư y tế';
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
    if ($name === '') return 'R';
    $parts = preg_split('/\s+/u', $name);
    $first = mb_substr($parts[0], 0, 1, 'UTF-8');
    $last = count($parts) > 1 ? mb_substr($parts[count($parts) - 1], 0, 1, 'UTF-8') : '';
    return mb_strtoupper($first . $last, 'UTF-8');
}

$dateFrom = $_GET['from'] ?? date('Y-m-01');
$dateTo = $_GET['to'] ?? date('Y-m-d');
$vatRate = isset($_GET['vat_rate']) ? (float)$_GET['vat_rate'] : 8.0;
$citRate = isset($_GET['cit_rate']) ? (float)$_GET['cit_rate'] : 20.0;
$priceMode = $_GET['price_mode'] ?? 'exclusive';

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) $dateFrom = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) $dateTo = date('Y-m-d');
if ($dateFrom > $dateTo) {
    [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
}
$vatRate = max(0, min(20, $vatRate));
$citRate = max(0, min(35, $citRate));
$priceMode = in_array($priceMode, ['exclusive', 'inclusive'], true) ? $priceMode : 'exclusive';

// Lấy dữ liệu doanh thu
try {
    $stmt = $pdo->prepare('
        SELECT 
            COALESCE(SUM(ctb.SLBH * ctb.DGBH), 0) as tong_doanh_thu,
            COUNT(DISTINCT db.MaDBH) as so_don_xuat
        FROM donbh db
        JOIN chitietbanhang ctb ON db.MaDBH = ctb.MaDBH
        WHERE DATE(db.NgayDat) BETWEEN ? AND ?
    ');
    $stmt->execute([$dateFrom, $dateTo]);
    $revenueData = $stmt->fetch();
} catch (PDOException $e) {
    $revenueData = ['tong_doanh_thu' => 0, 'so_don_xuat' => 0];
}

// Lấy dữ liệu chi phí
try {
    $stmt = $pdo->prepare('
        SELECT 
            COALESCE(SUM(ctm.SLMH * ctm.DGMH), 0) as tong_chi_phi,
            COUNT(DISTINCT dm.MaDMH) as so_don_nhap
        FROM donmh dm
        JOIN chitietmuahang ctm ON dm.MaDMH = ctm.MaDMH
        WHERE DATE(dm.NgayDat) BETWEEN ? AND ?
    ');
    $stmt->execute([$dateFrom, $dateTo]);
    $costData = $stmt->fetch();
} catch (PDOException $e) {
    $costData = ['tong_chi_phi' => 0, 'so_don_nhap' => 0];
}

// Lấy dữ liệu doanh thu theo sản phẩm
try {
    $stmt = $pdo->prepare('
        SELECT 
            sp.MaSP,
            sp.TenSP, 
            SUM(ctb.SLBH) as tong_sl,
            SUM(ctb.SLBH * ctb.DGBH) as tong_doanh_thu
        FROM donbh db
        JOIN chitietbanhang ctb ON db.MaDBH = ctb.MaDBH
        JOIN sanpham sp ON ctb.MaSP = sp.MaSP
        WHERE DATE(db.NgayDat) BETWEEN ? AND ?
        GROUP BY sp.MaSP, sp.TenSP
        ORDER BY tong_doanh_thu DESC
        LIMIT 10
    ');
    $stmt->execute([$dateFrom, $dateTo]);
    $revenueByProduct = $stmt->fetchAll();
} catch (PDOException $e) {
    $revenueByProduct = [];
}

// Lấy dữ liệu chi phí theo nhà cung cấp
try {
    $stmt = $pdo->prepare('
        SELECT 
            nc.MaNCC,
            nc.TenNCC, 
            SUM(ctm.SLMH) as tong_sl,
            SUM(ctm.SLMH * ctm.DGMH) as tong_chi_phi
        FROM donmh dm
        JOIN chitietmuahang ctm ON dm.MaDMH = ctm.MaDMH
        JOIN nhacc nc ON dm.MaNCC = nc.MaNCC
        WHERE DATE(dm.NgayDat) BETWEEN ? AND ?
        GROUP BY nc.MaNCC, nc.TenNCC
        ORDER BY tong_chi_phi DESC
        LIMIT 10
    ');
    $stmt->execute([$dateFrom, $dateTo]);
    $costBySupplier = $stmt->fetchAll();
} catch (PDOException $e) {
    $costBySupplier = [];
}

// Dữ liệu theo ngày
try {
    $stmt = $pdo->prepare('
        SELECT DATE(db.NgayDat) as ngay, SUM(ctb.SLBH * ctb.DGBH) as doanh_thu
        FROM donbh db
        JOIN chitietbanhang ctb ON db.MaDBH = ctb.MaDBH
        WHERE DATE(db.NgayDat) BETWEEN ? AND ?
        GROUP BY DATE(db.NgayDat)
        ORDER BY DATE(db.NgayDat)
    ');
    $stmt->execute([$dateFrom, $dateTo]);
    $dailyRevenue = $stmt->fetchAll();
} catch (PDOException $e) {
    $dailyRevenue = [];
}

try {
    $stmt = $pdo->prepare('
        SELECT DATE(dm.NgayDat) as ngay, SUM(ctm.SLMH * ctm.DGMH) as chi_phi
        FROM donmh dm
        JOIN chitietmuahang ctm ON dm.MaDMH = ctm.MaDMH
        WHERE DATE(dm.NgayDat) BETWEEN ? AND ?
        GROUP BY DATE(dm.NgayDat)
        ORDER BY DATE(dm.NgayDat)
    ');
    $stmt->execute([$dateFrom, $dateTo]);
    $dailyCost = $stmt->fetchAll();
} catch (PDOException $e) {
    $dailyCost = [];
}

$chartDates = [];
$chartRevenue = [];
$chartCost = [];
$chartProfit = [];
$startDate = strtotime($dateFrom);
$endDate = strtotime($dateTo);
$maxDays = 62;
$daysAdded = 0;

for ($i = $startDate; $i <= $endDate && $daysAdded < $maxDays; $i += 86400, $daysAdded++) {
    $date = date('Y-m-d', $i);
    $chartDates[] = date('d/m', $i);

    $revenue = 0;
    foreach ($dailyRevenue as $item) {
        if ($item['ngay'] == $date) {
            $revenue = (float)$item['doanh_thu'];
            break;
        }
    }

    $cost = 0;
    foreach ($dailyCost as $item) {
        if ($item['ngay'] == $date) {
            $cost = (float)$item['chi_phi'];
            break;
        }
    }

    $chartRevenue[] = (int)$revenue;
    $chartCost[] = (int)$cost;
    $chartProfit[] = (int)($revenue - $cost);
}

$totalRevenue = (float)($revenueData['tong_doanh_thu'] ?? 0);
$totalCost = (float)($costData['tong_chi_phi'] ?? 0);

$vatMultiplier = $vatRate / 100;
if ($priceMode === 'inclusive') {
    $revenueBase = $vatRate > 0 ? $totalRevenue / (1 + $vatMultiplier) : $totalRevenue;
    $costBase = $vatRate > 0 ? $totalCost / (1 + $vatMultiplier) : $totalCost;
    $outputVat = $totalRevenue - $revenueBase;
    $inputVat = $totalCost - $costBase;
} else {
    $revenueBase = $totalRevenue;
    $costBase = $totalCost;
    $outputVat = $revenueBase * $vatMultiplier;
    $inputVat = $costBase * $vatMultiplier;
}

$estimatedVatPayable = max(0, $outputVat - $inputVat);
$vatCredit = max(0, $inputVat - $outputVat);
$profitBeforeTax = $revenueBase - $costBase;
$estimatedCit = $profitBeforeTax > 0 ? $profitBeforeTax * ($citRate / 100) : 0;
$profitAfterTax = $profitBeforeTax - $estimatedCit;
$profitMargin = $revenueBase > 0 ? ($profitAfterTax / $revenueBase * 100) : 0;
$totalOrders = (int)($revenueData['so_don_xuat'] ?? 0) + (int)($costData['so_don_nhap'] ?? 0);
$activeRevenueDays = count(array_filter($dailyRevenue, fn($item) => (float)$item['doanh_thu'] > 0));
$activeCostDays = count(array_filter($dailyCost, fn($item) => (float)$item['chi_phi'] > 0));

include $_SERVER['DOCUMENT_ROOT'] . '/quan_ly_vat_tu/includes/header.php';
include $_SERVER['DOCUMENT_ROOT'] . '/quan_ly_vat_tu/includes/sidebar.php';
?>

<style>
    .finance-page {
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

    .finance-toolbar {
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

    .finance-toolbar h1 {
        font-size: 28px;
        line-height: 1.25;
        margin: 0 0 8px;
        font-weight: 700;
    }

    .finance-toolbar p {
        margin: 0;
        font-size: 14px;
        opacity: .9;
    }

    .finance-actions {
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

    .finance-card {
        background: white;
        border-radius: 8px;
        box-shadow: var(--shadow);
        margin-bottom: 30px;
        overflow: hidden;
    }

    .finance-card-header {
        padding: 20px 24px;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        flex-wrap: wrap;
    }

    .finance-card-header h2,
    .finance-card-header h3 {
        margin: 0;
        font-size: 18px;
        font-weight: 600;
        color: var(--text-dark);
    }

    .finance-card-header p {
        margin: 5px 0 0;
        font-size: 13px;
        color: var(--text-light);
    }

    .finance-card-body {
        padding: 24px;
    }

    .finance-filter {
        display: grid;
        grid-template-columns: repeat(5, minmax(130px, 1fr)) auto auto;
        gap: 12px;
        align-items: end;
    }

    .finance-field label {
        display: block;
        margin-bottom: 7px;
        color: var(--text-dark);
        font-size: 13px;
        font-weight: 600;
    }

    .finance-input,
    .finance-select {
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

    .finance-input:focus,
    .finance-select:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(102, 126, 234, .12);
    }

    .finance-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .finance-stat-card {
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

    .finance-stat-card::before {
        content: '';
        position: absolute;
        inset: 0 0 auto 0;
        height: 4px;
        background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
    }

    .finance-stat-card.success::before {
        background: var(--success-color);
    }

    .finance-stat-card.warning::before {
        background: var(--warning-color);
    }

    .finance-stat-card.danger::before {
        background: var(--danger-color);
    }

    .finance-stat-card.info::before {
        background: var(--info-color);
    }

    .finance-stat-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--shadow-lg);
        border-color: var(--primary-color);
    }

    .finance-stat-icon {
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

    .finance-stat-card.success .finance-stat-icon {
        background: rgba(72, 187, 120, .15);
        color: var(--success-color);
    }

    .finance-stat-card.warning .finance-stat-icon {
        background: rgba(237, 137, 54, .15);
        color: var(--warning-color);
    }

    .finance-stat-card.danger .finance-stat-icon {
        background: rgba(245, 101, 101, .15);
        color: var(--danger-color);
    }

    .finance-stat-card.info .finance-stat-icon {
        background: rgba(66, 153, 225, .15);
        color: var(--info-color);
    }

    .finance-stat-content h3 {
        margin: 0 0 7px;
        color: var(--text-light);
        font-size: 12px;
        text-transform: uppercase;
        font-weight: 600;
        letter-spacing: .5px;
    }

    .finance-stat-content .value {
        color: var(--text-dark);
        font-size: 23px;
        font-weight: 700;
        line-height: 1.15;
    }

    .finance-stat-content .hint {
        margin-top: 7px;
        color: var(--text-light);
        font-size: 12px;
    }

    .finance-grid-2 {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .finance-chart {
        height: 340px;
    }

    .finance-table-wrap {
        overflow-x: auto;
    }

    .finance-table {
        width: 100%;
        border-collapse: collapse;
    }

    .finance-table th {
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

    .finance-table td {
        padding: 16px;
        border-bottom: 1px solid var(--border-color);
        color: var(--text-dark);
        vertical-align: middle;
        font-size: 14px;
    }

    .finance-table tr:hover td {
        background: var(--light-bg);
    }

    .entity-cell {
        display: flex;
        align-items: center;
        gap: 12px;
        min-width: 240px;
    }

    .entity-avatar {
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

    .entity-name {
        font-size: 14px;
        font-weight: 600;
        color: var(--text-dark);
        margin-bottom: 3px;
    }

    .entity-sub {
        font-size: 12px;
        color: var(--text-light);
    }

    .finance-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 11px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        white-space: nowrap;
    }

    .finance-badge.success {
        background: rgba(72, 187, 120, .14);
        color: var(--success-color);
    }

    .finance-badge.warning {
        background: rgba(237, 137, 54, .14);
        color: var(--warning-color);
    }

    .finance-badge.danger {
        background: rgba(245, 101, 101, .12);
        color: var(--danger-color);
    }

    .finance-badge.info {
        background: rgba(102, 126, 234, .12);
        color: var(--primary-color);
    }

    .tax-panel {
        border-left: 4px solid var(--primary-color);
        background: linear-gradient(135deg, rgba(102, 126, 234, .08), rgba(118, 75, 162, .08));
    }

    .tax-note {
        color: var(--text-light);
        font-size: 12px;
        line-height: 1.6;
        margin-top: 12px;
    }

    .finance-empty {
        text-align: center;
        padding: 45px 20px;
        color: var(--text-light);
        font-size: 14px;
    }

    @media print {

        .navbar,
        .sidebar,
        .finance-toolbar,
        .finance-filter,
        .btn-dashboard {
            display: none !important;
        }

        .finance-card,
        .finance-stat-card {
            box-shadow: none;
            border: 1px solid #ddd;
        }
    }

    @media (max-width: 1200px) {
        .finance-filter {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 992px) {
        .finance-grid-2 {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 768px) {
        .finance-toolbar {
            padding: 25px;
        }

        .finance-toolbar h1 {
            font-size: 24px;
        }

        .finance-actions,
        .btn-dashboard {
            width: 100%;
        }

        .finance-filter {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="finance-page">
    <div class="finance-toolbar">
        <div>
            <h1>💰 Báo cáo doanh thu & chi phí</h1>
            <p>Dashboard tài chính, lợi nhuận và thuế ước tính theo kỳ báo cáo.</p>
        </div>
        <div class="finance-actions">
            <button type="button" class="btn-dashboard primary" onclick="window.print()">🖨️ In báo cáo</button>
            <a href="<?= e(getBaseUrl()) ?>/modules/reports/debts.php" class="btn-dashboard primary">💳 Xem công nợ</a>
        </div>
    </div>

    <div class="finance-card">
        <div class="finance-card-header">
            <div>
                <h2>Bộ lọc báo cáo</h2>
                <p>Chọn khoảng thời gian và cấu hình thuế để tính toán ước tính.</p>
            </div>
        </div>
        <div class="finance-card-body">
            <form method="GET" class="finance-filter">
                <div class="finance-field">
                    <label for="from">Từ ngày</label>
                    <input class="finance-input" type="date" id="from" name="from" value="<?= e($dateFrom) ?>">
                </div>
                <div class="finance-field">
                    <label for="to">Đến ngày</label>
                    <input class="finance-input" type="date" id="to" name="to" value="<?= e($dateTo) ?>">
                </div>
                <div class="finance-field">
                    <label for="vat_rate">Thuế GTGT</label>
                    <select class="finance-select" id="vat_rate" name="vat_rate">
                        <?php foreach ([0, 5, 8, 10] as $rate): ?>
                            <option value="<?= $rate ?>" <?= abs($vatRate - $rate) < 0.001 ? 'selected' : '' ?>><?= $rate ?>%</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="finance-field">
                    <label for="cit_rate">Thuế TNDN</label>
                    <input class="finance-input" type="number" id="cit_rate" name="cit_rate" value="<?= e($citRate) ?>" min="0" max="35" step="0.1">
                </div>
                <div class="finance-field">
                    <label for="price_mode">Cách hiểu đơn giá</label>
                    <select class="finance-select" id="price_mode" name="price_mode">
                        <option value="exclusive" <?= $priceMode === 'exclusive' ? 'selected' : '' ?>>Chưa gồm VAT</option>
                        <option value="inclusive" <?= $priceMode === 'inclusive' ? 'selected' : '' ?>>Đã gồm VAT</option>
                    </select>
                </div>
                <button type="submit" class="btn-dashboard solid">🔎 Lọc dữ liệu</button>
                <a href="<?= e(getBaseUrl()) ?>/modules/reports/revenue_costs.php" class="btn-dashboard light">Đặt lại</a>
            </form>
        </div>
    </div>

    <div class="finance-stats">
        <div class="finance-stat-card success">
            <div class="finance-stat-icon">📈</div>
            <div class="finance-stat-content">
                <h3>Doanh thu</h3>
                <div class="value"><?= e(moneyShort($revenueBase)) ?></div>
                <div class="hint"><?= number_format((int)($revenueData['so_don_xuat'] ?? 0)) ?> đơn xuất kho</div>
            </div>
        </div>
        <div class="finance-stat-card danger">
            <div class="finance-stat-icon">📉</div>
            <div class="finance-stat-content">
                <h3>Chi phí mua hàng</h3>
                <div class="value"><?= e(moneyShort($costBase)) ?></div>
                <div class="hint"><?= number_format((int)($costData['so_don_nhap'] ?? 0)) ?> đơn nhập kho</div>
            </div>
        </div>
        <div class="finance-stat-card <?= $profitAfterTax >= 0 ? 'success' : 'danger' ?>">
            <div class="finance-stat-icon">💵</div>
            <div class="finance-stat-content">
                <h3>Lợi nhuận sau thuế</h3>
                <div class="value" style="color: <?= $profitAfterTax >= 0 ? 'var(--success-color)' : 'var(--danger-color)' ?>"><?= e(moneyShort($profitAfterTax)) ?></div>
                <div class="hint">Biên lợi nhuận <?= number_format($profitMargin, 2, ',', '.') ?>%</div>
            </div>
        </div>
        <div class="finance-stat-card info">
            <div class="finance-stat-icon">📊</div>
            <div class="finance-stat-content">
                <h3>Tổng đơn hàng</h3>
                <div class="value"><?= number_format($totalOrders) ?></div>
                <div class="hint">Xuất: <?= number_format((int)($revenueData['so_don_xuat'] ?? 0)) ?> | Nhập: <?= number_format((int)($costData['so_don_nhap'] ?? 0)) ?></div>
            </div>
        </div>
    </div>

    <div class="finance-card tax-panel">
        <div class="finance-card-header">
            <div>
                <h2>🧾 Chi phí thuế ước tính</h2>
                <p>Ước tính theo doanh thu/chi phí trong kỳ, chưa thay thế tờ khai thuế chính thức.</p>
            </div>
            <span class="finance-badge info">VAT <?= number_format($vatRate, 1, ',', '.') ?>% • TNDN <?= number_format($citRate, 1, ',', '.') ?>%</span>
        </div>
        <div class="finance-card-body">
            <div class="finance-stats" style="margin-bottom:0">
                <div class="finance-stat-card">
                    <div class="finance-stat-icon">🧾</div>
                    <div class="finance-stat-content">
                        <h3>VAT đầu ra</h3>
                        <div class="value"><?= e(moneyShort($outputVat)) ?></div>
                        <div class="hint">Tính trên doanh thu chịu thuế</div>
                    </div>
                </div>
                <div class="finance-stat-card warning">
                    <div class="finance-stat-icon">📥</div>
                    <div class="finance-stat-content">
                        <h3>VAT đầu vào</h3>
                        <div class="value"><?= e(moneyShort($inputVat)) ?></div>
                        <div class="hint">Tính trên chi phí mua hàng</div>
                    </div>
                </div>
                <div class="finance-stat-card <?= $estimatedVatPayable > 0 ? 'danger' : 'success' ?>">
                    <div class="finance-stat-icon">⚖️</div>
                    <div class="finance-stat-content">
                        <h3>VAT tạm nộp</h3>
                        <div class="value"><?= e(moneyShort($estimatedVatPayable)) ?></div>
                        <div class="hint"><?= $vatCredit > 0 ? 'VAT đầu vào còn được khấu trừ: ' . e(money($vatCredit)) : 'Sau khi khấu trừ đầu vào' ?></div>
                    </div>
                </div>
                <div class="finance-stat-card danger">
                    <div class="finance-stat-icon">🏛️</div>
                    <div class="finance-stat-content">
                        <h3>Thuế TNDN tạm tính</h3>
                        <div class="value"><?= e(moneyShort($estimatedCit)) ?></div>
                        <div class="hint">20% là thuế suất phổ thông</div>
                    </div>
                </div>
            </div>
            <div class="tax-note">
                Ghi chú: Nghị định 174/2025/NĐ-CP quy định chính sách giảm thuế GTGT theo Nghị quyết 204/2025/QH15, có hiệu lực từ 01/07/2025; hướng dẫn của cơ quan thuế nêu mức giảm từ 10% xuống 8% áp dụng đến hết 31/12/2026 cho nhóm hàng hóa/dịch vụ thuộc diện được giảm. Thuế TNDN (thu nhập doanh nghiệp) phổ thông hiện được cơ quan thuế công bố ở mức 20%. Hàng hóa y tế có thể có mức VAT khác nhau theo từng loại hàng/hóa đơn, nên phần này để chọn thuế suất khi lọc báo cáo.
            </div>
        </div>
    </div>

    <div class="finance-card">
        <div class="finance-card-header">
            <div>
                <h2>📈 Xu hướng doanh thu, chi phí và lợi nhuận</h2>
                <p>Hiển thị tối đa 62 ngày trong khoảng lọc.</p>
            </div>
        </div>
        <div class="finance-card-body">
            <div class="finance-chart">
                <canvas id="revenueChart"></canvas>
            </div>
        </div>
    </div>

    <div class="finance-grid-2">
        <div class="finance-card">
            <div class="finance-card-header">
                <div>
                    <h3>🏆 Top sản phẩm theo doanh thu</h3>
                    <p>10 sản phẩm có doanh thu cao nhất trong kỳ.</p>
                </div>
            </div>
            <div class="finance-table-wrap">
                <?php if (!empty($revenueByProduct)): ?>
                    <table class="finance-table">
                        <thead>
                            <tr>
                                <th>Sản phẩm</th>
                                <th>Số lượng</th>
                                <th style="text-align:right">Doanh thu</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($revenueByProduct as $product): ?>
                                <tr>
                                    <td>
                                        <div class="entity-cell">
                                            <div class="entity-avatar"><?= e(initials($product['TenSP'])) ?></div>
                                            <div>
                                                <div class="entity-name"><?= e($product['TenSP']) ?></div>
                                                <div class="entity-sub">Mã SP #<?= e($product['MaSP']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><span class="finance-badge info"><?= number_format((float)$product['tong_sl']) ?></span></td>
                                    <td style="text-align:right"><strong><?= e(money($product['tong_doanh_thu'])) ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="finance-empty">📭 Chưa có dữ liệu doanh thu theo sản phẩm.</div>
                <?php endif; ?>
            </div>
        </div>

        <div class="finance-card">
            <div class="finance-card-header">
                <div>
                    <h3>🏭 Top nhà cung cấp theo chi phí</h3>
                    <p>10 nhà cung cấp có chi phí nhập hàng cao nhất.</p>
                </div>
            </div>
            <div class="finance-table-wrap">
                <?php if (!empty($costBySupplier)): ?>
                    <table class="finance-table">
                        <thead>
                            <tr>
                                <th>Nhà cung cấp</th>
                                <th>Số lượng</th>
                                <th style="text-align:right">Chi phí</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($costBySupplier as $supplier): ?>
                                <tr>
                                    <td>
                                        <div class="entity-cell">
                                            <div class="entity-avatar"><?= e(initials($supplier['TenNCC'])) ?></div>
                                            <div>
                                                <div class="entity-name"><?= e($supplier['TenNCC']) ?></div>
                                                <div class="entity-sub">Mã NCC #<?= e($supplier['MaNCC']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><span class="finance-badge warning"><?= number_format((float)$supplier['tong_sl']) ?></span></td>
                                    <td style="text-align:right"><strong><?= e(money($supplier['tong_chi_phi'])) ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="finance-empty">📭 Chưa có dữ liệu chi phí theo nhà cung cấp.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="finance-card">
        <div class="finance-card-header">
            <div>
                <h2>📋 Tóm tắt tài chính</h2>
                <p>Bảng kiểm tra nhanh các chỉ tiêu chính trong kỳ.</p>
            </div>
        </div>
        <div class="finance-table-wrap">
            <table class="finance-table">
                <thead>
                    <tr>
                        <th>Chỉ tiêu</th>
                        <th style="text-align:right">Giá trị</th>
                        <th style="text-align:right">Ghi chú</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Doanh thu tính thuế</strong></td>
                        <td style="text-align:right;color:var(--success-color)"><strong><?= e(money($revenueBase)) ?></strong></td>
                        <td style="text-align:right"><span class="finance-badge success"><?= number_format($activeRevenueDays) ?> ngày có bán</span></td>
                    </tr>
                    <tr>
                        <td><strong>Chi phí mua hàng</strong></td>
                        <td style="text-align:right;color:var(--danger-color)"><strong><?= e(money($costBase)) ?></strong></td>
                        <td style="text-align:right"><span class="finance-badge warning"><?= number_format($activeCostDays) ?> ngày có nhập</span></td>
                    </tr>
                    <tr>
                        <td><strong>Lợi nhuận trước thuế TNDN</strong></td>
                        <td style="text-align:right"><strong><?= e(money($profitBeforeTax)) ?></strong></td>
                        <td style="text-align:right">Doanh thu - chi phí</td>
                    </tr>
                    <tr>
                        <td><strong>VAT tạm nộp</strong></td>
                        <td style="text-align:right"><strong><?= e(money($estimatedVatPayable)) ?></strong></td>
                        <td style="text-align:right">VAT đầu ra - VAT đầu vào</td>
                    </tr>
                    <tr>
                        <td><strong>Thuế TNDN tạm tính</strong></td>
                        <td style="text-align:right;color:var(--danger-color)"><strong><?= e(money($estimatedCit)) ?></strong></td>
                        <td style="text-align:right"><?= number_format($citRate, 1, ',', '.') ?>% lợi nhuận dương</td>
                    </tr>
                    <tr style="background:var(--light-bg);font-weight:700">
                        <td>Lợi nhuận sau thuế TNDN</td>
                        <td style="text-align:right;color:<?= $profitAfterTax >= 0 ? 'var(--success-color)' : 'var(--danger-color)' ?>"><?= e(money($profitAfterTax)) ?></td>
                        <td style="text-align:right"><span class="finance-badge <?= $profitAfterTax >= 0 ? 'success' : 'danger' ?>"><?= number_format($profitMargin, 2, ',', '.') ?>%</span></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    const revenueCtx = document.getElementById('revenueChart');
    if (revenueCtx) {
        new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode($chartDates, JSON_UNESCAPED_UNICODE) ?>,
                datasets: [{
                    label: 'Doanh thu',
                    data: <?= json_encode($chartRevenue) ?>,
                    borderColor: '#48bb78',
                    backgroundColor: 'rgba(72,187,120,.10)',
                    borderWidth: 3,
                    fill: true,
                    tension: .35,
                    pointRadius: 3
                }, {
                    label: 'Chi phí',
                    data: <?= json_encode($chartCost) ?>,
                    borderColor: '#f56565',
                    backgroundColor: 'rgba(245,101,101,.10)',
                    borderWidth: 3,
                    fill: true,
                    tension: .35,
                    pointRadius: 3
                }, {
                    label: 'Lợi nhuận',
                    data: <?= json_encode($chartProfit) ?>,
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102,126,234,.08)',
                    borderWidth: 2,
                    borderDash: [6, 4],
                    fill: false,
                    tension: .35,
                    pointRadius: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            padding: 18
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + Number(context.parsed.y || 0).toLocaleString('vi-VN') + ' ₫';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return Number(value).toLocaleString('vi-VN') + ' ₫';
                            }
                        },
                        grid: {
                            color: 'rgba(0,0,0,.05)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    }
</script>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/quan_ly_vat_tu/includes/footer.php'; ?>