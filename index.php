<?php
$pageTitle = 'Trang chủ - Hệ thống quản lý vật tư y tế';
require_once 'config/database.php';
session_start();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
requireLogin();

// Lấy thống kê
try {
    // Tổng số sản phẩm
    $stmt = $pdo->query('SELECT COUNT(*) as total FROM sanpham');
    $productCount = $stmt->fetch()['total'];

    // Tổng số nhà cung cấp
    $stmt = $pdo->query('SELECT COUNT(*) as total FROM nhacc');
    $supplierCount = $stmt->fetch()['total'];

    // Tổng số khách hàng
    $stmt = $pdo->query('SELECT COUNT(*) as total FROM khachhang');
    $customerCount = $stmt->fetch()['total'];

    // Tổng tồn kho (tính giá trị)
    $stmt = $pdo->query('SELECT SUM(kh.SoLuongTon * sp.DonGia) as total FROM khohang kh JOIN sanpham sp ON kh.MaSP = sp.MaSP');
    $stockValue = $stmt->fetch()['total'] ?? 0;

    // Tổng đơn nhập kho tháng này
    $stmt = $pdo->query('
        SELECT COUNT(DISTINCT MaDMH) as total FROM donmh 
        WHERE MONTH(NgayDat) = MONTH(CURDATE()) AND YEAR(NgayDat) = YEAR(CURDATE())
    ');
    $importOrdersThisMonth = $stmt->fetch()['total'];

    // Tổng đơn xuất kho tháng này
    $stmt = $pdo->query('
        SELECT COUNT(DISTINCT MaDBH) as total FROM donbh 
        WHERE MONTH(NgayDat) = MONTH(CURDATE()) AND YEAR(NgayDat) = YEAR(CURDATE())
    ');
    $exportOrdersThisMonth = $stmt->fetch()['total'];

    // Doanh thu tháng này
    $stmt = $pdo->query('
        SELECT COALESCE(SUM(ctb.SLBH * ctb.DGBH), 0) as total FROM chitietbanhang ctb
        JOIN donbh db ON ctb.MaDBH = db.MaDBH
        WHERE MONTH(db.NgayDat) = MONTH(CURDATE()) AND YEAR(db.NgayDat) = YEAR(CURDATE())
    ');
    $revenueThisMonth = $stmt->fetch()['total'];

    // Chi phí tháng này
    $stmt = $pdo->query('
        SELECT COALESCE(SUM(ctm.SLMH * ctm.DGMH), 0) as total FROM chitietmuahang ctm
        JOIN donmh dm ON ctm.MaDMH = dm.MaDMH
        WHERE MONTH(dm.NgayDat) = MONTH(CURDATE()) AND YEAR(dm.NgayDat) = YEAR(CURDATE())
    ');
    $costThisMonth = $stmt->fetch()['total'];

    // Lớp hàng cận date (6 tháng)
    $stmt = $pdo->query('
        SELECT COUNT(DISTINCT MaLo) as total FROM khohang
        WHERE HanSuDung <= DATE_ADD(CURDATE(), INTERVAL 6 MONTH)
        AND HanSuDung > CURDATE()
    ');
    $expiringCount = $stmt->fetch()['total'];

    // Hàng hết hạn
    $stmt = $pdo->query('
        SELECT COUNT(DISTINCT MaLo) as total FROM khohang
        WHERE HanSuDung <= CURDATE()
    ');
    $expiredCount = $stmt->fetch()['total'];

    // Lấy hoạt động gần đây (20 mục)
    $stmt = $pdo->query('
        SELECT \'Nhập kho\' as type, MaDMH as id, NgayDat as date, \'import\' as action FROM donmh
        UNION
        SELECT \'Xuất kho\' as type, MaDBH as id, NgayDat as date, \'export\' as action FROM donbh
        ORDER BY date DESC LIMIT 20
    ');
    $activities = $stmt->fetchAll();

    // Lấy sản phẩm bán chạy nhất (5 mục)
    $stmt = $pdo->query('
        SELECT sp.MaSP, sp.TenSP, SUM(ctbh.SLBH) as total_sold, SUM(ctbh.SLBH * ctbh.DGBH) as revenue
        FROM sanpham sp
        JOIN chitietbanhang ctbh ON sp.MaSP = ctbh.MaSP
        GROUP BY sp.MaSP
        ORDER BY total_sold DESC
        LIMIT 5
    ');
    $topProducts = $stmt->fetchAll();

    // Lấy hàng cận date (5 mục)
    $stmt = $pdo->query('
        SELECT sp.MaSP, sp.TenSP, kh.MaLo, kh.HanSuDung, kh.SoLuongTon,
               DATEDIFF(kh.HanSuDung, CURDATE()) as days_left
        FROM khohang kh
        JOIN sanpham sp ON kh.MaSP = sp.MaSP
        WHERE kh.HanSuDung <= DATE_ADD(CURDATE(), INTERVAL 6 MONTH)
        AND kh.HanSuDung > CURDATE()
        ORDER BY kh.HanSuDung ASC
        LIMIT 5
    ');
    $expiringProducts = $stmt->fetchAll();
} catch (PDOException $e) {
    die('Lỗi: ' . $e->getMessage());
}

$profit = $revenueThisMonth - $costThisMonth;
$profitMargin = $revenueThisMonth > 0 ? ($profit / $revenueThisMonth * 100) : 0;

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="dashboard-header">
    <h1>🎯 Chào mừng, <?php echo htmlspecialchars($_SESSION['Hoten']); ?></h1>
    <p>Xem tổng quan hệ thống quản lý vật tư y tế - <?php echo date('l, d/m/Y', time()); ?></p>
</div>

<!-- Thống kê chính (4 cột) -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom: 30px;">
    <!-- Tổng sản phẩm -->
    <div class="stat-card-large primary">
        <div class="stat-card-large-icon primary">📦</div>
        <div class="stat-card-large-content">
            <h3>Tổng sản phẩm</h3>
            <div class="value"><?php echo number_format($productCount); ?></div>
            <div class="trend">Đang quản lý</div>
        </div>
    </div>

    <!-- Giá trị tồn kho -->
    <div class="stat-card-large success">
        <div class="stat-card-large-icon success">💰</div>
        <div class="stat-card-large-content">
            <h3>Giá trị tồn kho</h3>
            <div class="value"><?php echo number_format($stockValue / 1000000, 1); ?>M ₫</div>
            <div class="trend">Tài sản hiện tại</div>
        </div>
    </div>

    <!-- Doanh thu tháng này -->
    <div class="stat-card-large warning">
        <div class="stat-card-large-icon warning">📈</div>
        <div class="stat-card-large-content">
            <h3>Doanh thu tháng này</h3>
            <div class="value"><?php echo number_format($revenueThisMonth / 1000000, 1); ?>M ₫</div>
            <div class="trend">Tháng <?php echo date('m/Y'); ?></div>
        </div>
    </div>

    <!-- Lợi nhuận tháng này -->
    <div class="stat-card-large <?php echo $profit >= 0 ? 'success' : 'danger'; ?>" style="border-top: 4px solid <?php echo $profit >= 0 ? 'var(--success-color)' : 'var(--danger-color)'; ?>">
        <div class="stat-card-large-icon <?php echo $profit >= 0 ? 'success' : 'danger'; ?>">💵</div>
        <div class="stat-card-large-content">
            <h3>Lợi nhuận</h3>
            <div class="value" style="color: <?php echo $profit >= 0 ? 'var(--success-color)' : 'var(--danger-color)'; ?>">
                <?php echo number_format($profit / 1000000, 1); ?>M ₫
            </div>
            <div class="trend <?php echo $profit >= 0 ? '' : 'negative'; ?>">
                <?php echo number_format($profitMargin, 1); ?>% tỷ suất
            </div>
        </div>
    </div>
</div>

<!-- Cảnh báo hàng cận date -->
<?php if ($expiringCount > 0 || $expiredCount > 0): ?>
    <div class="alert-banner">
        <div style="display: flex; align-items: center;">
            <span class="alert-banner-icon">⚠️</span>
            <div class="alert-banner-content">
                <h4>Cần chú ý - Hàng gần hết hạn</h4>
                <p>
                    Có <strong><?php echo $expiringCount; ?></strong> lô hàng sắp hết hạn trong 6 tháng tới
                    <?php if ($expiredCount > 0): ?>
                        và <strong><?php echo $expiredCount; ?></strong> lô đã hết hạn. Vui lòng xử lý ngay.
                    <?php endif; ?>
                    <a href="<?php echo getBaseUrl(); ?>/modules/warehouse/alerts.php" style="text-decoration: underline; font-weight: 600;">Chi tiết →</a>
                </p>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="grid grid-2" style="margin-bottom: 30px;">
    <!-- Đơn hàng tháng này -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
        <div class="stat-card-large info">
            <div class="stat-card-large-icon info">📥</div>
            <div class="stat-card-large-content">
                <h3>Đơn nhập tháng này</h3>
                <div class="value"><?php echo number_format($importOrdersThisMonth); ?></div>
                <div class="trend">Từ nhà cung cấp</div>
            </div>
        </div>

        <div class="stat-card-large info">
            <div class="stat-card-large-icon info">📤</div>
            <div class="stat-card-large-content">
                <h3>Đơn xuất tháng này</h3>
                <div class="value"><?php echo number_format($exportOrdersThisMonth); ?></div>
                <div class="trend">Cho khách hàng</div>
            </div>
        </div>
    </div>

    <!-- Quick Links -->
    <div class="card">
        <div class="card-header">
            <h2>🔗 Truy cập nhanh</h2>
        </div>
        <div class="card-body">
            <div class="quick-links">
                <a href="<?php echo getBaseUrl(); ?>/modules/warehouse/import_order.php" class="quick-link">
                    <div class="quick-link-icon">📥</div>
                    <div class="quick-link-text">
                        <div class="quick-link-title">Nhập kho</div>
                        <div class="quick-link-subtitle">Lập đơn nhập</div>
                    </div>
                    <div class="quick-link-arrow">→</div>
                </a>
                <a href="<?php echo getBaseUrl(); ?>/modules/warehouse/export_order.php" class="quick-link">
                    <div class="quick-link-icon">📤</div>
                    <div class="quick-link-text">
                        <div class="quick-link-title">Xuất kho</div>
                        <div class="quick-link-subtitle">Lập đơn xuất</div>
                    </div>
                    <div class="quick-link-arrow">→</div>
                </a>
                <a href="<?php echo getBaseUrl(); ?>/modules/warehouse/inventory.php" class="quick-link">
                    <div class="quick-link-icon">📋</div>
                    <div class="quick-link-text">
                        <div class="quick-link-title">Tồn kho</div>
                        <div class="quick-link-subtitle">Kiểm kê</div>
                    </div>
                    <div class="quick-link-arrow">→</div>
                </a>
                <a href="<?php echo getBaseUrl(); ?>/modules/reports/revenue_costs.php" class="quick-link">
                    <div class="quick-link-icon">💰</div>
                    <div class="quick-link-text">
                        <div class="quick-link-title">Báo cáo</div>
                        <div class="quick-link-subtitle">Doanh thu</div>
                    </div>
                    <div class="quick-link-arrow">→</div>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Nội dung chính (2 cột) -->
<div class="grid grid-2">
    <!-- Sản phẩm bán chạy -->
    <div class="card">
        <div class="card-header">
            <h2>🏆 Sản phẩm bán chạy nhất</h2>
            <a href="<?php echo getBaseUrl(); ?>/modules/reports/revenue_costs.php" style="font-size: 12px; color: var(--primary-color); text-decoration: none;">Xem tất cả →</a>
        </div>
        <div class="card-body">
            <?php if (!empty($topProducts)): ?>
                <?php foreach ($topProducts as $i => $product): ?>
                    <div class="product-item">
                        <div class="product-info">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <span style="background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); color: white; width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 600;">
                                    <?php echo $i + 1; ?>
                                </span>
                                <div class="product-name"><?php echo htmlspecialchars($product['TenSP']); ?></div>
                            </div>
                            <div class="product-stats">
                                <span>Bán: <strong><?php echo number_format($product['total_sold']); ?></strong></span>
                            </div>
                        </div>
                        <div class="product-revenue">
                            <div class="product-revenue-value"><?php echo number_format($product['revenue'] / 1000000, 1); ?>M ₫</div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📦</div>
                    <div class="empty-state-title">Chưa có dữ liệu</div>
                    <div class="empty-state-text">Chưa có đơn bán hàng nào</div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Hàng cận date -->
    <div class="card">
        <div class="card-header">
            <h2>⚠️ Hàng sắp hết hạn</h2>
            <a href="<?php echo getBaseUrl(); ?>/modules/warehouse/alerts.php" style="font-size: 12px; color: var(--danger-color); text-decoration: none;">Xem tất cả →</a>
        </div>
        <div class="card-body">
            <?php if (!empty($expiringProducts)): ?>
                <?php foreach ($expiringProducts as $product):
                    $daysleft = $product['days_left'];
                    $urgency = $daysleft <= 30 ? 'danger' : ($daysleft <= 90 ? 'warning' : 'info');
                ?>
                    <div class="product-item">
                        <div class="product-info">
                            <div class="product-name"><?php echo htmlspecialchars($product['TenSP']); ?></div>
                            <div class="product-stats">
                                <span>Mã lô: <strong><?php echo htmlspecialchars($product['MaLo']); ?></strong></span>
                                <span>SL: <strong><?php echo number_format($product['SoLuongTon']); ?></strong></span>
                            </div>
                        </div>
                        <div style="text-align: right;">
                            <div style="font-size: 14px; font-weight: 600; color: var(--text-dark);">
                                <?php echo date('d/m', strtotime($product['HanSuDung'])); ?>
                            </div>
                            <span class="badge badge-<?php echo $urgency; ?>">
                                <?php echo $daysleft; ?> ngày
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">✅</div>
                    <div class="empty-state-title">Tất cả bình thường</div>
                    <div class="empty-state-text">Không có hàng sắp hết hạn</div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Hoạt động gần đây -->
<div class="card">
    <div class="card-header">
        <h2>🔔 Hoạt động gần đây</h2>
    </div>
    <div class="card-body">
        <?php if (!empty($activities)): ?>
            <div class="activity-timeline">
                <?php foreach ($activities as $activity):
                    $isImport = $activity['action'] == 'import';
                    $itemClass = $isImport ? 'success' : 'warning';
                    $icon = $isImport ? '📥' : '📤';
                    $actionText = $isImport ? 'Nhập kho' : 'Xuất kho';
                ?>
                    <div class="timeline-item <?php echo $itemClass; ?>">
                        <div class="timeline-item-title">
                            <?php echo $icon; ?> <?php echo $actionText; ?> - <strong><?php echo htmlspecialchars($activity['id']); ?></strong>
                        </div>
                        <div class="timeline-item-time">
                            <?php echo date('d/m/Y H:i', strtotime($activity['date'])); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon">📭</div>
                <div class="empty-state-title">Chưa có hoạt động</div>
                <div class="empty-state-text">Bắt đầu bằng cách lập đơn hàng đầu tiên</div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Thống kê tổng quan -->
<div class="grid grid-2">
    <div class="card">
        <div class="card-header">
            <h2>👥 Đối tác</h2>
        </div>
        <div class="card-body">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div style="text-align: center; padding: 20px; background: var(--light-bg); border-radius: 8px;">
                    <div style="font-size: 28px; font-weight: 700; color: var(--primary-color);">
                        <?php echo number_format($supplierCount); ?>
                    </div>
                    <div style="font-size: 13px; color: var(--text-light); margin-top: 8px;">Nhà cung cấp</div>
                    <a href="<?php echo getBaseUrl(); ?>/modules/partners/suppliers.php" style="display: inline-block; margin-top: 12px; padding: 6px 12px; background: var(--primary-color); color: white; border-radius: 4px; text-decoration: none; font-size: 12px;">Quản lý</a>
                </div>
                <div style="text-align: center; padding: 20px; background: var(--light-bg); border-radius: 8px;">
                    <div style="font-size: 28px; font-weight: 700; color: var(--success-color);">
                        <?php echo number_format($customerCount); ?>
                    </div>
                    <div style="font-size: 13px; color: var(--text-light); margin-top: 8px;">Khách hàng</div>
                    <a href="<?php echo getBaseUrl(); ?>/modules/partners/hospitals.php" style="display: inline-block; margin-top: 12px; padding: 6px 12px; background: var(--success-color); color: white; border-radius: 4px; text-decoration: none; font-size: 12px;">Quản lý</a>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h2>📊 Tài chính tháng này</h2>
        </div>
        <div class="card-body">
            <div style="display: space-between; gap: 15px;">
                <div style="padding: 15px; background: rgba(102, 126, 234, 0.1); border-radius: 8px; margin-bottom: 15px;">
                    <div style="font-size: 12px; color: var(--text-light); margin-bottom: 5px;">Doanh thu</div>
                    <div style="font-size: 20px; font-weight: 700; color: var(--primary-color);">
                        <?php echo number_format($revenueThisMonth / 1000000, 1); ?>M ₫
                    </div>
                </div>
                <div style="padding: 15px; background: rgba(245, 101, 101, 0.1); border-radius: 8px; margin-bottom: 15px;">
                    <div style="font-size: 12px; color: var(--text-light); margin-bottom: 5px;">Chi phí</div>
                    <div style="font-size: 20px; font-weight: 700; color: var(--danger-color);">
                        <?php echo number_format($costThisMonth / 1000000, 1); ?>M ₫
                    </div>
                </div>
                <div style="padding: 15px; background: rgba(72, 187, 120, 0.1); border-radius: 8px;">
                    <div style="font-size: 12px; color: var(--text-light); margin-bottom: 5px;">Lợi nhuận</div>
                    <div style="font-size: 20px; font-weight: 700; color: var(--success-color);">
                        <?php echo number_format($profit / 1000000, 1); ?>M ₫
                    </div>
                </div>
            </div>
            <a href="<?php echo getBaseUrl(); ?>/modules/reports/revenue_costs.php" class="btn btn-primary" style="width: 100%; margin-top: 15px; text-align: center;">Xem báo cáo chi tiết</a>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>