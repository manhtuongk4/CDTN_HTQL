<?php
$pageTitle = 'Chi tiết sản phẩm - Hệ thống quản lý vật tư y tế';
require_once $_SERVER['DOCUMENT_ROOT'] . '/quan_ly_vat_tu/config/database.php';
session_start();
requireLogin();

function e($value)
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}
function productImage($path)
{
    $path = trim((string)($path ?? ''));
    $fallback = 'data:image/svg+xml;utf8,' . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="720" height="520" viewBox="0 0 720 520"><rect width="720" height="520" rx="36" fill="#f1f5f9"/><path d="M250 224h220v146H250z" fill="#dbe4f0"/><path d="M290 184h140l40 40H250z" fill="#cbd5e1"/><circle cx="320" cy="294" r="26" fill="#94a3b8"/><path d="M362 330l54-62 72 102H300z" fill="#94a3b8"/><text x="360" y="430" text-anchor="middle" font-family="Arial" font-size="30" fill="#64748b">No image</text></svg>');

    if ($path === '') {
        return $fallback;
    }

    // Chuẩn hóa đường dẫn ảnh lưu trong DB:
    // - Quan_ly_vat_tu\assets\images\...  -> /quan_ly_vat_tu/assets/images/...
    // - assets/images/...                   -> /quan_ly_vat_tu/assets/images/...
    // - /quan_ly_vat_tu/assets/images/...   -> giữ nguyên
    // - https://... hoặc data:...            -> giữ nguyên
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

$maSP = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$product = null;
$error = '';

try {
    $stmt = $pdo->prepare("SELECT sp.*, ls.TenLSP, ns.TenNSP,
            COALESCE(nhap.TongNhap, 0) AS TongNhap,
            COALESCE(xuat.TongXuat, 0) AS TongXuat,
            COALESCE(nhap.TongNhap, 0) - COALESCE(xuat.TongXuat, 0) AS TonKho
        FROM sanpham sp
        LEFT JOIN loaisp ls ON sp.MaLSP = ls.MaLSP
        LEFT JOIN nhomsp ns ON ls.MaNSP = ns.MaNSP
        LEFT JOIN (SELECT MaSP, SUM(SLMH) AS TongNhap FROM chitietmuahang GROUP BY MaSP) nhap ON sp.MaSP = nhap.MaSP
        LEFT JOIN (SELECT MaSP, SUM(SLBH) AS TongXuat FROM chitietbanhang GROUP BY MaSP) xuat ON sp.MaSP = xuat.MaSP
        WHERE sp.MaSP = ?");
    $stmt->execute([$maSP]);
    $product = $stmt->fetch();
    if (!$product) $error = 'Không tìm thấy sản phẩm.';

    $related = [];
    if ($product && $product['MaLSP']) {
        $rel = $pdo->prepare('SELECT MaSP, TenSP, DonGia, HinhAnh FROM sanpham WHERE MaLSP = ? AND MaSP <> ? ORDER BY MaSP DESC LIMIT 4');
        $rel->execute([$product['MaLSP'], $product['MaSP']]);
        $related = $rel->fetchAll();
    }
} catch (PDOException $e) {
    $error = 'Lỗi: ' . $e->getMessage();
}

include $_SERVER['DOCUMENT_ROOT'] . '/quan_ly_vat_tu/includes/header.php';
include $_SERVER['DOCUMENT_ROOT'] . '/quan_ly_vat_tu/includes/sidebar.php';
?>

<style>
    :root {
        --kt-primary: #1b84ff;
        --kt-primary-light: #e9f3ff;
        --kt-success: #17c653;
        --kt-success-light: #e8fff3;
        --kt-warning: #f6c000;
        --kt-warning-light: #fff8dd;
        --kt-danger: #f8285a;
        --kt-danger-light: #ffeef3;
        --kt-dark: #252f4a;
        --kt-muted: #78829d;
        --kt-border: #f1f1f4;
        --kt-bg: #f5f8fa;
        --kt-radius: 18px
    }

    body {
        background: var(--kt-bg);
        font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        color: #4b5675
    }

    .kt-page {
        padding: 28px;
        max-width: 1480px;
        margin: 0 auto
    }

    .kt-toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 18px;
        margin-bottom: 22px;
        flex-wrap: wrap
    }

    .kt-breadcrumb {
        display: flex;
        gap: 8px;
        color: #99a1b7;
        font-size: 13px;
        margin-bottom: 8px
    }

    .kt-title h1 {
        font-size: 22px;
        margin: 0;
        color: var(--kt-dark);
        font-weight: 600;
        letter-spacing: -.02em
    }

    .kt-sub {
        margin-top: 7px;
        color: var(--kt-muted);
        font-size: 14px
    }

    .kt-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap
    }

    .kt-btn {
        border: 0;
        border-radius: 11px;
        padding: 11px 16px;
        font-weight: 500;
        font-size: 14px;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
        transition: .18s;
        white-space: nowrap
    }

    .kt-btn-primary {
        background: var(--kt-primary);
        color: #fff;
        box-shadow: 0 8px 18px rgba(27, 132, 255, .18)
    }

    .kt-btn-primary:hover {
        background: #056ee9;
        color: #fff
    }

    .kt-btn-light {
        background: #fff;
        color: #4b5675;
        border: 1px solid var(--kt-border)
    }

    .kt-btn-light:hover {
        color: var(--kt-primary)
    }

    .kt-grid {
        display: grid;
        grid-template-columns: minmax(420px, .98fr) 1.02fr;
        gap: 24px
    }

    .kt-card {
        background: #fff;
        border: 1px solid var(--kt-border);
        border-radius: var(--kt-radius);
        box-shadow: 0 8px 28px rgba(15, 23, 42, .04);
        overflow: hidden;
        margin-bottom: 20px
    }

    .kt-card-body {
        padding: 22px
    }

    .kt-card-head {
        padding: 20px 22px;
        border-bottom: 1px solid var(--kt-border);
        display: flex;
        justify-content: space-between;
        gap: 12px;
        align-items: center
    }

    .kt-card-title {
        margin: 0;
        color: var(--kt-dark);
        font-size: 16px;
        font-weight: 850
    }

    .kt-product-hero {
        padding: 20px
    }

    .kt-product-image-wrap {
        position: relative;
        width: 100%;
        min-height: 520px;
        border-radius: 22px;
        overflow: hidden;
        background: linear-gradient(135deg, #f8fafc 0%, #eef2f7 100%);
        border: 1px solid var(--kt-border);
        cursor: zoom-in;
        display: flex;
        align-items: center;
        justify-content: center
    }

    .kt-product-image-wrap::after {
        content: 'Di chuột để phóng to';
        position: absolute;
        right: 16px;
        bottom: 16px;
        padding: 7px 11px;
        border-radius: 999px;
        background: rgba(37, 47, 74, .72);
        color: #fff;
        font-size: 12px;
        font-weight: 600;
        opacity: .86;
        pointer-events: none;
        transition: .2s
    }

    .kt-product-image-wrap:hover::after {
        opacity: 0
    }

    .kt-product-hero img {
        width: 100%;
        height: 520px;
        object-fit: contain;
        border-radius: 20px;
        background: transparent;
        transition: transform .28s ease;
        will-change: transform;
        transform-origin: center center
    }

    .kt-product-image-wrap:hover img {
        transform: scale(1.55)
    }

    .kt-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        border-radius: 999px;
        padding: 7px 11px;
        font-size: 12px;
        font-weight: 600;
        white-space: nowrap
    }

    .kt-badge-primary {
        background: var(--kt-primary-light);
        color: var(--kt-primary)
    }

    .kt-badge-success {
        background: var(--kt-success-light);
        color: #047536
    }

    .kt-badge-warning {
        background: var(--kt-warning-light);
        color: #946200
    }

    .kt-badge-danger {
        background: var(--kt-danger-light);
        color: var(--kt-danger)
    }

    .kt-badge-muted {
        background: #f1f5f9;
        color: #64748b
    }

    .kt-price {
        font-size: 24px;
        font-weight: 600;
        color: var(--kt-dark);
        letter-spacing: -.03em
    }

    .kt-meta {
        font-size: 13px;
        color: #99a1b7
    }

    .kt-info-list {
        display: grid;
        gap: 0
    }

    .kt-info-row {
        display: grid;
        grid-template-columns: 190px 1fr;
        gap: 14px;
        padding: 14px 0;
        border-bottom: 1px dashed #e5e7eb
    }

    .kt-info-row:last-child {
        border-bottom: 0
    }

    .kt-info-label {
        font-size: 13px;
        font-weight: 600;
        color: #99a1b7
    }

    .kt-info-value {
        font-weight: 500;
        color: #252f4a
    }

    .kt-stats {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 14px
    }

    .kt-stat {
        border: 1px solid var(--kt-border);
        border-radius: 16px;
        padding: 18px;
        background: #fff
    }

    .kt-stat .label {
        color: var(--kt-muted);
        font-size: 13px;
        font-weight: 800
    }

    .kt-stat .value {
        font-size: 21px;
        font-weight: 600;
        color: var(--kt-dark);
        margin-top: 7px
    }

    .kt-desc {
        line-height: 1.75;
        color: #4b5675
    }

    .kt-related {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 14px
    }

    .kt-related-item {
        text-decoration: none;
        color: inherit;
        border: 1px solid var(--kt-border);
        border-radius: 16px;
        padding: 12px;
        transition: .18s
    }

    .kt-related-item:hover {
        border-color: var(--kt-primary);
        transform: translateY(-2px)
    }

    .kt-related-item img {
        width: 100%;
        height: 132px;
        object-fit: contain;
        border-radius: 13px;
        background: #f8fafc
    }

    .kt-related-name {
        font-weight: 600;
        color: var(--kt-dark);
        margin-top: 10px;
        font-size: 14px
    }

    .kt-empty {
        text-align: center;
        padding: 60px 20px;
        background: #fff;
        border: 1px solid var(--kt-border);
        border-radius: 18px;
        color: var(--kt-muted)
    }

    .kt-empty h2 {
        color: var(--kt-dark)
    }

    @media(max-width:1100px) {
        .kt-grid {
            grid-template-columns: 1fr
        }

        .kt-related {
            grid-template-columns: repeat(2, 1fr)
        }
    }


    @media(max-width:900px) {
        .kt-product-image-wrap {
            min-height: 420px
        }

        .kt-product-hero img {
            height: 420px
        }
    }

    @media(max-width:680px) {
        .kt-page {
            padding: 16px
        }

        .kt-actions,
        .kt-btn {
            width: 100%;
            justify-content: center
        }

        .kt-info-row {
            grid-template-columns: 1fr
        }

        .kt-stats,
        .kt-related {
            grid-template-columns: 1fr
        }

        .kt-price {
            font-size: 26px
        }

        .kt-product-image-wrap {
            min-height: 340px
        }

        .kt-product-hero img {
            height: 340px
        }

        .kt-product-image-wrap:hover img {
            transform: scale(1.25)
        }
    }
</style>

<main class="kt-page">
    <?php if ($error): ?>
        <div class="kt-empty">
            <h2>Không thể hiển thị sản phẩm</h2>
            <p><?= e($error) ?></p><a class="kt-btn kt-btn-primary" href="products.php">← Quay lại danh sách</a>
        </div>
    <?php else: ?>
        <div class="kt-toolbar">
            <div class="kt-title">
                <div class="kt-breadcrumb">Trang chủ <span>/</span> Kho vật tư <span>/</span> Chi tiết sản phẩm</div>
                <h1><?= e($product['TenSP']) ?></h1>
                <div class="kt-sub">Mã sản phẩm #SP<?= e($product['MaSP']) ?> • <?= e($product['TenLSP'] ?: 'Chưa phân loại') ?></div>
            </div>
            <div class="kt-actions"><a class="kt-btn kt-btn-light" href="products.php">← Danh sách</a><a class="kt-btn kt-btn-primary" href="products.php?action=edit&id=<?= e($product['MaSP']) ?>">✎ Chỉnh sửa</a></div>
        </div>

        <div class="kt-grid">
            <section>
                <div class="kt-card kt-product-hero">
                    <div class="kt-product-image-wrap">
                        <img class="kt-product-main-image" src="<?= e(productImage($product['HinhAnh'])) ?>" alt="<?= e($product['TenSP']) ?>">
                    </div>
                </div>
                <div class="kt-card">
                    <div class="kt-card-head">
                        <h2 class="kt-card-title">Trạng thái</h2>
                    </div>
                    <div class="kt-card-body" style="display:flex;gap:10px;flex-wrap:wrap"><?= (int)$product['LaHangKiemSoat'] === 1 ? '<span class="kt-badge kt-badge-warning">⚠ Hàng kiểm soát</span>' : '<span class="kt-badge kt-badge-success">✓ Hàng thông thường</span>' ?><?= (int)$product['TonKho'] > 0 ? '<span class="kt-badge kt-badge-success">Còn hàng</span>' : '<span class="kt-badge kt-badge-danger">Hết hàng</span>' ?><span class="kt-badge kt-badge-primary"><?= e($product['TenLSP'] ?: 'Chưa phân loại') ?></span></div>
                </div>
            </section>

            <section>
                <div class="kt-card">
                    <div class="kt-card-body">
                        <div class="kt-meta">Giá bán hiện tại</div>
                        <div class="kt-price"><?= number_format((float)$product['DonGia'], 0, ',', '.') ?> ₫</div>
                        <p class="kt-desc" style="margin:16px 0 0"><?= e($product['MoTaCT'] ?: 'Chưa có mô tả chi tiết cho sản phẩm này.') ?></p>
                    </div>
                </div>
                <div class="kt-stats">
                    <div class="kt-stat">
                        <div class="label">Tổng nhập</div>
                        <div class="value"><?= number_format((int)$product['TongNhap']) ?></div>
                    </div>
                    <div class="kt-stat">
                        <div class="label">Tổng bán</div>
                        <div class="value"><?= number_format((int)$product['TongXuat']) ?></div>
                    </div>
                    <div class="kt-stat">
                        <div class="label">Tồn kho</div>
                        <div class="value"><?= number_format((int)$product['TonKho']) ?></div>
                    </div>
                </div>
                <div class="kt-card" style="margin-top:20px">
                    <div class="kt-card-head">
                        <h2 class="kt-card-title">Thông tin chi tiết</h2>
                    </div>
                    <div class="kt-card-body">
                        <div class="kt-info-list">
                            <div class="kt-info-row">
                                <div class="kt-info-label">Tên sản phẩm</div>
                                <div class="kt-info-value"><?= e($product['TenSP']) ?></div>
                            </div>
                            <div class="kt-info-row">
                                <div class="kt-info-label">Nhóm / Loại</div>
                                <div class="kt-info-value"><?= e(($product['TenNSP'] ?: 'Chưa có nhóm') . ' / ' . ($product['TenLSP'] ?: 'Chưa có loại')) ?></div>
                            </div>
                            <div class="kt-info-row">
                                <div class="kt-info-label">Hoạt chất chính</div>
                                <div class="kt-info-value"><?= e($product['HoatChatChinh'] ?: '-') ?></div>
                            </div>
                            <div class="kt-info-row">
                                <div class="kt-info-label">Hàm lượng</div>
                                <div class="kt-info-value"><?= e($product['HamLuong'] ?: '-') ?></div>
                            </div>
                            <div class="kt-info-row">
                                <div class="kt-info-label">Số đăng ký</div>
                                <div class="kt-info-value"><?= e($product['SoDangKy'] ?: '-') ?></div>
                            </div>
                            <div class="kt-info-row">
                                <div class="kt-info-label">Điều kiện bảo quản</div>
                                <div class="kt-info-value"><?= e($product['DieuKienBaoQuan'] ?: '-') ?></div>
                            </div>
                            <div class="kt-info-row">
                                <div class="kt-info-label">Xuất xứ</div>
                                <div class="kt-info-value"><?= e($product['XuatXu'] ?: '-') ?></div>
                            </div>
                            <div class="kt-info-row">
                                <div class="kt-info-label">Công ty sản xuất</div>
                                <div class="kt-info-value"><?= e($product['CongTySanXuat'] ?: '-') ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <?php if (!empty($related)): ?>
            <div class="kt-card">
                <div class="kt-card-head">
                    <h2 class="kt-card-title">Sản phẩm cùng loại</h2>
                </div>
                <div class="kt-card-body">
                    <div class="kt-related">
                        <?php foreach ($related as $item): ?><a class="kt-related-item" href="product_details.php?id=<?= e($item['MaSP']) ?>"><img src="<?= e(productImage($item['HinhAnh'])) ?>" alt="<?= e($item['TenSP']) ?>">
                                <div class="kt-related-name"><?= e($item['TenSP']) ?></div>
                                <div class="kt-meta"><?= number_format((float)$item['DonGia'], 0, ',', '.') ?> ₫</div>
                            </a><?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</main>

<script>
    const productImageFallback = 'data:image/svg+xml;utf8,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20width%3D%22720%22%20height%3D%22520%22%20viewBox%3D%220%200%20720%20520%22%3E%3Crect%20width%3D%22720%22%20height%3D%22520%22%20rx%3D%2236%22%20fill%3D%22%23f1f5f9%22/%3E%3Cpath%20d%3D%22M250%20224h220v146H250z%22%20fill%3D%22%23dbe4f0%22/%3E%3Cpath%20d%3D%22M290%20184h140l40%2040H250z%22%20fill%3D%22%23cbd5e1%22/%3E%3Ccircle%20cx%3D%22320%22%20cy%3D%22294%22%20r%3D%2226%22%20fill%3D%22%2394a3b8%22/%3E%3Cpath%20d%3D%22M362%20330l54-62%2072%20102H300z%22%20fill%3D%22%2394a3b8%22/%3E%3Ctext%20x%3D%22360%22%20y%3D%22430%22%20text-anchor%3D%22middle%22%20font-family%3D%22Arial%22%20font-size%3D%2230%22%20fill%3D%22%2364748b%22%3ENo%20image%3C/text%3E%3C/svg%3E';
    document.querySelectorAll('img').forEach(img => {
        img.onerror = function() {
            this.onerror = null;
            this.src = productImageFallback;
        };
    });

    const mainImageWrap = document.querySelector('.kt-product-image-wrap');
    const mainImage = document.querySelector('.kt-product-main-image');
    if (mainImageWrap && mainImage) {
        mainImageWrap.addEventListener('mousemove', function(event) {
            const rect = mainImageWrap.getBoundingClientRect();
            const x = ((event.clientX - rect.left) / rect.width) * 100;
            const y = ((event.clientY - rect.top) / rect.height) * 100;
            mainImage.style.transformOrigin = x.toFixed(2) + '% ' + y.toFixed(2) + '%';
        });

        mainImageWrap.addEventListener('mouseleave', function() {
            mainImage.style.transformOrigin = 'center center';
        });
    }
</script>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/quan_ly_vat_tu/includes/footer.php'; ?>