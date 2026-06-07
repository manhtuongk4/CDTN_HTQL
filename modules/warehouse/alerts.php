<?php
$pageTitle = 'Cảnh báo cận date - Hệ thống quản lý vật tư y tế';
require_once $_SERVER['DOCUMENT_ROOT'] . '/quan_ly_vat_tu/config/database.php';
session_start();
requireLogin();

$searchQuery = trim($_GET['search'] ?? '');
$filterLevel = $_GET['level'] ?? '';

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

function expiringLevel($daysLeft)
{
    $daysLeft = (int)$daysLeft;

    if ($daysLeft <= 30) {
        return [
            'key' => 'critical',
            'label' => 'Rất gấp',
            'badge' => 'danger',
            'icon' => '🔴',
        ];
    }

    if ($daysLeft <= 90) {
        return [
            'key' => 'urgent',
            'label' => 'Gấp',
            'badge' => 'warning',
            'icon' => '🟠',
        ];
    }

    return [
        'key' => 'notice',
        'label' => 'Cần chú ý',
        'badge' => 'info',
        'icon' => '🟡',
    ];
}

function rowMatchesSearch($item, $searchQuery)
{
    if ($searchQuery === '') {
        return true;
    }

    $haystack = strtolower(
        ($item['MaLo'] ?? '') . ' ' .
            ($item['MaSP'] ?? '') . ' ' .
            ($item['TenSP'] ?? '') . ' ' .
            ($item['TenLSP'] ?? '')
    );

    return strpos($haystack, strtolower($searchQuery)) !== false;
}

// Lấy danh sách hàng cận date (trong 6 tháng)
try {
    $stmt = $pdo->query('
        SELECT kh.MaLo, sp.MaSP, sp.TenSP, kh.SoLuongTon, sp.DonGia,
               (kh.SoLuongTon * sp.DonGia) AS gia_tri_ton,
               kh.NgaySanXuat, kh.HanSuDung, ls.TenLSP,
               DATEDIFF(kh.HanSuDung, CURDATE()) AS ngay_con_lai
        FROM khohang kh
        JOIN sanpham sp ON kh.MaSP = sp.MaSP
        LEFT JOIN loaisp ls ON sp.MaLSP = ls.MaLSP
        WHERE kh.HanSuDung <= DATE_ADD(CURDATE(), INTERVAL 6 MONTH)
          AND kh.HanSuDung > CURDATE()
        ORDER BY kh.HanSuDung ASC
    ');
    $allExpiringInventory = $stmt->fetchAll();
} catch (PDOException $e) {
    $allExpiringInventory = [];
}

// Lấy danh sách hàng đã hết hạn
try {
    $stmt = $pdo->query('
        SELECT kh.MaLo, sp.MaSP, sp.TenSP, kh.SoLuongTon, sp.DonGia,
               (kh.SoLuongTon * sp.DonGia) AS gia_tri_ton,
               kh.NgaySanXuat, kh.HanSuDung, ls.TenLSP,
               DATEDIFF(CURDATE(), kh.HanSuDung) AS ngay_qua_han
        FROM khohang kh
        JOIN sanpham sp ON kh.MaSP = sp.MaSP
        LEFT JOIN loaisp ls ON sp.MaLSP = ls.MaLSP
        WHERE kh.HanSuDung <= CURDATE()
        ORDER BY kh.HanSuDung DESC
    ');
    $allExpiredInventory = $stmt->fetchAll();
} catch (PDOException $e) {
    $allExpiredInventory = [];
}

$expiringInventory = array_values(array_filter($allExpiringInventory, function ($item) use ($searchQuery, $filterLevel) {
    if (!rowMatchesSearch($item, $searchQuery)) {
        return false;
    }

    $level = expiringLevel($item['ngay_con_lai'] ?? 0);
    if ($filterLevel !== '' && $level['key'] !== $filterLevel) {
        return false;
    }

    return true;
}));

$expiredInventory = array_values(array_filter($allExpiredInventory, function ($item) use ($searchQuery, $filterLevel) {
    if (!rowMatchesSearch($item, $searchQuery)) {
        return false;
    }

    if ($filterLevel !== '' && $filterLevel !== 'expired') {
        return false;
    }

    return true;
}));

$totalExpiringQty = array_sum(array_column($expiringInventory, 'SoLuongTon'));
$totalExpiringValue = array_sum(array_column($expiringInventory, 'gia_tri_ton'));
$totalExpiredQty = array_sum(array_column($expiredInventory, 'SoLuongTon'));
$totalExpiredValue = array_sum(array_column($expiredInventory, 'gia_tri_ton'));

$criticalCount = count(array_filter($allExpiringInventory, fn($item) => (int)($item['ngay_con_lai'] ?? 0) <= 30));
$urgentCount = count(array_filter($allExpiringInventory, fn($item) => (int)($item['ngay_con_lai'] ?? 0) > 30 && (int)($item['ngay_con_lai'] ?? 0) <= 90));
$noticeCount = count($allExpiringInventory) - $criticalCount - $urgentCount;

include $_SERVER['DOCUMENT_ROOT'] . '/quan_ly_vat_tu/includes/header.php';
include $_SERVER['DOCUMENT_ROOT'] . '/quan_ly_vat_tu/includes/sidebar.php';
?>

<style>
    .alert-stock-page {
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

    .alert-stock-toolbar {
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

    .alert-stock-toolbar h1 {
        font-size: 28px;
        line-height: 1.25;
        margin: 0 0 8px;
        font-weight: 700;
    }

    .alert-stock-toolbar p {
        margin: 0;
        font-size: 14px;
        opacity: .9;
    }

    .alert-stock-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    .alert-stock-btn {
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

    .alert-stock-btn.primary {
        background: white;
        color: var(--primary-color);
    }

    .alert-stock-btn.primary:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow);
    }

    .alert-stock-btn.solid {
        background: var(--primary-color);
        color: white;
    }

    .alert-stock-btn.solid:hover {
        background: var(--secondary-color);
        color: white;
    }

    .alert-stock-btn.light {
        background: #fff;
        color: var(--text-dark);
        border: 1px solid var(--border-color);
    }

    .alert-stock-btn.light:hover {
        background: var(--light-bg);
        color: var(--primary-color);
    }

    .alert-stock-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .alert-stock-stat-card {
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

    .alert-stock-stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
    }

    .alert-stock-stat-card.warning::before {
        background: var(--warning-color);
    }

    .alert-stock-stat-card.danger::before {
        background: var(--danger-color);
    }

    .alert-stock-stat-card.success::before {
        background: var(--success-color);
    }

    .alert-stock-stat-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--shadow-lg);
        border-color: var(--primary-color);
    }

    .alert-stock-stat-icon {
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

    .alert-stock-stat-card.warning .alert-stock-stat-icon {
        background: rgba(237, 137, 54, .15);
        color: var(--warning-color);
    }

    .alert-stock-stat-card.danger .alert-stock-stat-icon {
        background: rgba(245, 101, 101, .15);
        color: var(--danger-color);
    }

    .alert-stock-stat-card.success .alert-stock-stat-icon {
        background: rgba(72, 187, 120, .15);
        color: var(--success-color);
    }

    .alert-stock-stat-content h3 {
        margin: 0 0 7px;
        color: var(--text-light);
        font-size: 12px;
        text-transform: uppercase;
        font-weight: 600;
        letter-spacing: .5px;
    }

    .alert-stock-stat-content .value {
        color: var(--text-dark);
        font-size: 24px;
        font-weight: 700;
        line-height: 1.15;
    }

    .alert-stock-stat-content .hint {
        margin-top: 7px;
        color: var(--text-light);
        font-size: 12px;
    }

    .alert-stock-card {
        background: white;
        border-radius: 8px;
        box-shadow: var(--shadow);
        margin-bottom: 30px;
        overflow: hidden;
    }

    .alert-stock-card-header {
        padding: 20px 24px;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        flex-wrap: wrap;
    }

    .alert-stock-card-header h2 {
        margin: 0;
        font-size: 18px;
        font-weight: 600;
        color: var(--text-dark);
    }

    .alert-stock-card-header p {
        margin: 5px 0 0;
        font-size: 13px;
        color: var(--text-light);
    }

    .alert-stock-card-body {
        padding: 24px;
    }

    .alert-stock-filter {
        display: grid;
        grid-template-columns: 1.8fr 1fr auto auto;
        gap: 12px;
        align-items: end;
    }

    .alert-stock-field label {
        display: block;
        margin-bottom: 7px;
        color: var(--text-dark);
        font-size: 13px;
        font-weight: 600;
    }

    .alert-stock-input,
    .alert-stock-select {
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

    .alert-stock-input:focus,
    .alert-stock-select:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(102, 126, 234, .12);
    }

    .alert-message {
        border-radius: 8px;
        padding: 14px 16px;
        margin-bottom: 18px;
        display: flex;
        align-items: flex-start;
        gap: 12px;
        font-size: 14px;
        line-height: 1.5;
        border-left: 4px solid;
    }

    .alert-message.warning {
        background: rgba(237, 137, 54, .12);
        color: #8a4b12;
        border-left-color: var(--warning-color);
    }

    .alert-message.danger {
        background: rgba(245, 101, 101, .12);
        color: #9b2c2c;
        border-left-color: var(--danger-color);
    }

    .alert-message.success {
        background: rgba(72, 187, 120, .13);
        color: #276749;
        border-left-color: var(--success-color);
    }

    .alert-stock-table-wrap {
        overflow-x: auto;
    }

    .alert-stock-table {
        width: 100%;
        border-collapse: collapse;
    }

    .alert-stock-table th {
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

    .alert-stock-table td {
        padding: 16px;
        border-bottom: 1px solid var(--border-color);
        color: var(--text-dark);
        vertical-align: middle;
        font-size: 14px;
    }

    .alert-stock-table tr:hover td {
        background: var(--light-bg);
    }

    .alert-stock-table tr.expired td {
        background: rgba(245, 101, 101, .045);
    }

    .alert-stock-item-cell {
        display: flex;
        align-items: center;
        gap: 12px;
        min-width: 260px;
    }

    .alert-stock-avatar {
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

    .alert-stock-avatar.danger {
        background: linear-gradient(135deg, var(--danger-color), #c53030);
    }

    .alert-stock-name {
        color: var(--text-dark);
        font-size: 14px;
        font-weight: 600;
        margin-bottom: 3px;
    }

    .alert-stock-sub {
        color: var(--text-light);
        font-size: 12px;
    }

    .alert-stock-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 11px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        white-space: nowrap;
    }

    .alert-stock-badge.success {
        background: rgba(72, 187, 120, .14);
        color: var(--success-color);
    }

    .alert-stock-badge.info {
        background: rgba(66, 153, 225, .14);
        color: var(--info-color);
    }

    .alert-stock-badge.warning {
        background: rgba(237, 137, 54, .14);
        color: var(--warning-color);
    }

    .alert-stock-badge.danger {
        background: rgba(245, 101, 101, .13);
        color: var(--danger-color);
    }

    .alert-stock-badge.muted {
        background: var(--light-bg);
        color: var(--text-light);
    }

    .alert-stock-empty {
        text-align: center;
        padding: 50px 20px;
    }

    .alert-stock-empty .icon {
        font-size: 48px;
        margin-bottom: 15px;
    }

    .alert-stock-empty h3 {
        color: var(--text-dark);
        margin: 0 0 8px;
        font-size: 16px;
        font-weight: 600;
    }

    .alert-stock-empty p {
        color: var(--text-light);
        margin: 0;
        font-size: 14px;
    }

    @media (max-width: 992px) {
        .alert-stock-filter {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 768px) {
        .alert-stock-toolbar {
            padding: 25px;
        }

        .alert-stock-toolbar h1 {
            font-size: 24px;
        }

        .alert-stock-actions,
        .alert-stock-btn {
            width: 100%;
        }

        .alert-stock-card-header {
            align-items: flex-start;
        }
    }
</style>

<div class="alert-stock-page">
    <div class="alert-stock-toolbar">
        <div>
            <h1>Cảnh báo hàng cận date</h1>
            <p>Theo dõi lô hàng sắp hết hạn trong 6 tháng tới và lô đã hết hạn.</p>
        </div>
        <div class="alert-stock-actions">
            <a href="<?= e(getBaseUrl()) ?>/modules/warehouse/inventory.php" class="alert-stock-btn primary">📋 Kiểm kê tồn kho</a>
        </div>
    </div>

    <div class="alert-stock-stats">
        <div class="alert-stock-stat-card warning">
            <div class="alert-stock-stat-icon">📦</div>
            <div class="alert-stock-stat-content">
                <h3>Lô cận date</h3>
                <div class="value"><?= number_format(count($allExpiringInventory)) ?></div>
                <div class="hint"><?= number_format($criticalCount) ?> rất gấp, <?= number_format($urgentCount) ?> gấp</div>
            </div>
        </div>
        <div class="alert-stock-stat-card warning">
            <div class="alert-stock-stat-icon">🔢</div>
            <div class="alert-stock-stat-content">
                <h3>SL cận date</h3>
                <div class="value"><?= number_format($totalExpiringQty) ?></div>
                <div class="hint">Theo bộ lọc hiện tại</div>
            </div>
        </div>
        <div class="alert-stock-stat-card danger">
            <div class="alert-stock-stat-icon">❌</div>
            <div class="alert-stock-stat-content">
                <h3>Lô hết hạn</h3>
                <div class="value"><?= number_format(count($allExpiredInventory)) ?></div>
                <div class="hint"><?= number_format($totalExpiredQty) ?> sản phẩm tồn</div>
            </div>
        </div>
        <div class="alert-stock-stat-card danger">
            <div class="alert-stock-stat-icon">💰</div>
            <div class="alert-stock-stat-content">
                <h3>Giá trị hết hạn</h3>
                <div class="value"><?= money($totalExpiredValue) ?></div>
                <div class="hint">Cần xử lý / tiêu hủy</div>
            </div>
        </div>
    </div>

    <div class="alert-stock-card">
        <div class="alert-stock-card-header">
            <div>
                <h2>Bộ lọc cảnh báo</h2>
                <p>Lọc nhanh theo mã lô, sản phẩm hoặc mức độ ưu tiên.</p>
            </div>
        </div>
        <div class="alert-stock-card-body">
            <form method="GET" class="alert-stock-filter">
                <div class="alert-stock-field">
                    <label>Tìm kiếm</label>
                    <input class="alert-stock-input" type="text" name="search" value="<?= e($searchQuery) ?>" placeholder="Tìm mã lô, sản phẩm, loại sản phẩm...">
                </div>
                <div class="alert-stock-field">
                    <label>Mức độ</label>
                    <select class="alert-stock-select" name="level">
                        <option value="">Tất cả</option>
                        <option value="critical" <?= $filterLevel === 'critical' ? 'selected' : '' ?>>Rất gấp ≤ 30 ngày</option>
                        <option value="urgent" <?= $filterLevel === 'urgent' ? 'selected' : '' ?>>Gấp ≤ 90 ngày</option>
                        <option value="notice" <?= $filterLevel === 'notice' ? 'selected' : '' ?>>Cần chú ý ≤ 180 ngày</option>
                        <option value="expired" <?= $filterLevel === 'expired' ? 'selected' : '' ?>>Đã hết hạn</option>
                    </select>
                </div>
                <button class="alert-stock-btn solid" type="submit">🔎 Lọc</button>
                <a class="alert-stock-btn light" href="<?= e(getBaseUrl()) ?>/modules/warehouse/alerts.php">Đặt lại</a>
            </form>
        </div>
    </div>

    <div class="alert-stock-card">
        <div class="alert-stock-card-header">
            <div>
                <h2>Hàng sắp hết hạn</h2>
                <p>Các lô hàng còn hạn nhưng cần ưu tiên xử lý theo nguyên tắc FEFO.</p>
            </div>
        </div>
        <div class="alert-stock-card-body">
            <?php if (!empty($expiringInventory)): ?>
                <div class="alert-message warning">
                    <span>⚠️</span>
                    <div>Có <strong><?= number_format(count($expiringInventory)) ?></strong> lô hàng sắp hết hạn với tổng giá trị <strong><?= money($totalExpiringValue) ?></strong>. Nên ưu tiên xuất trước các lô có hạn dùng gần nhất.</div>
                </div>
            <?php else: ?>
                <div class="alert-message success">
                    <span>✅</span>
                    <div>Không có hàng sắp hết hạn phù hợp với bộ lọc hiện tại.</div>
                </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($expiringInventory)): ?>
            <div class="alert-stock-table-wrap">
                <table class="alert-stock-table">
                    <thead>
                        <tr>
                            <th>Lô / Sản phẩm</th>
                            <th>Loại</th>
                            <th>Số lượng</th>
                            <th>Hạn dùng</th>
                            <th>Còn lại</th>
                            <th>Giá trị</th>
                            <th>Mức độ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($expiringInventory as $item): ?>
                            <?php $level = expiringLevel($item['ngay_con_lai'] ?? 0); ?>
                            <tr>
                                <td>
                                    <div class="alert-stock-item-cell">
                                        <div class="alert-stock-avatar"><?= e(shortName($item['TenSP'])) ?></div>
                                        <div>
                                            <div class="alert-stock-name"><?= e($item['TenSP']) ?></div>
                                            <div class="alert-stock-sub">Lô <?= e($item['MaLo']) ?> • #SP<?= e($item['MaSP']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><?= $item['TenLSP'] ? '<span class="alert-stock-badge muted">' . e($item['TenLSP']) . '</span>' : '<span class="alert-stock-badge muted">Chưa phân loại</span>' ?></td>
                                <td><strong><?= number_format((int)$item['SoLuongTon']) ?></strong></td>
                                <td><?= e(dateVN($item['HanSuDung'])) ?></td>
                                <td><?= number_format((int)$item['ngay_con_lai']) ?> ngày</td>
                                <td><strong><?= money($item['gia_tri_ton']) ?></strong></td>
                                <td><span class="alert-stock-badge <?= e($level['badge']) ?>"><?= e($level['icon'] . ' ' . $level['label']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="alert-stock-card">
        <div class="alert-stock-card-header">
            <div>
                <h2>Hàng đã hết hạn</h2>
                <p>Các lô đã quá hạn sử dụng, cần tách riêng và xử lý theo quy trình.</p>
            </div>
        </div>
        <div class="alert-stock-card-body">
            <?php if (!empty($expiredInventory)): ?>
                <div class="alert-message danger">
                    <span>❌</span>
                    <div>Có <strong><?= number_format(count($expiredInventory)) ?></strong> lô hàng đã hết hạn với tổng giá trị <strong><?= money($totalExpiredValue) ?></strong>. Cần kiểm tra, cô lập và xử lý theo quy định.</div>
                </div>
            <?php else: ?>
                <div class="alert-message success">
                    <span>✅</span>
                    <div>Không có hàng hết hạn phù hợp với bộ lọc hiện tại.</div>
                </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($expiredInventory)): ?>
            <div class="alert-stock-table-wrap">
                <table class="alert-stock-table">
                    <thead>
                        <tr>
                            <th>Lô / Sản phẩm</th>
                            <th>Loại</th>
                            <th>Số lượng</th>
                            <th>Hạn dùng</th>
                            <th>Quá hạn</th>
                            <th>Giá trị</th>
                            <th>Trạng thái</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($expiredInventory as $item): ?>
                            <tr class="expired">
                                <td>
                                    <div class="alert-stock-item-cell">
                                        <div class="alert-stock-avatar danger"><?= e(shortName($item['TenSP'])) ?></div>
                                        <div>
                                            <div class="alert-stock-name"><?= e($item['TenSP']) ?></div>
                                            <div class="alert-stock-sub">Lô <?= e($item['MaLo']) ?> • #SP<?= e($item['MaSP']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><?= $item['TenLSP'] ? '<span class="alert-stock-badge muted">' . e($item['TenLSP']) . '</span>' : '<span class="alert-stock-badge muted">Chưa phân loại</span>' ?></td>
                                <td><strong><?= number_format((int)$item['SoLuongTon']) ?></strong></td>
                                <td><?= e(dateVN($item['HanSuDung'])) ?></td>
                                <td><?= number_format((int)$item['ngay_qua_han']) ?> ngày</td>
                                <td><strong><?= money($item['gia_tri_ton']) ?></strong></td>
                                <td><span class="alert-stock-badge danger">❌ Hết hạn</span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/quan_ly_vat_tu/includes/footer.php'; ?>