<?php
$pageTitle = 'Quản lý sản phẩm - Hệ thống quản lý vật tư y tế';
require_once $_SERVER['DOCUMENT_ROOT'] . '/quan_ly_vat_tu/config/database.php';
session_start();
requireLogin();

$success = '';
$error = '';
$action = $_GET['action'] ?? 'list';
$itemsPerPage = 10;
$currentPage = max(1, (int)($_GET['page'] ?? 1));

function e($value)
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function productImage($path)
{
    $path = trim((string)($path ?? ''));
    $fallback = 'data:image/svg+xml;utf8,' . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="320" height="240" viewBox="0 0 320 240"><rect width="320" height="240" rx="28" fill="#f1f5f9"/><path d="M116 108h88v58h-88z" fill="#dbe4f0"/><path d="M132 93h56l16 15h-88z" fill="#cbd5e1"/><circle cx="144" cy="137" r="10" fill="#94a3b8"/><path d="M161 150l18-20 25 36h-63z" fill="#94a3b8"/><text x="160" y="198" text-anchor="middle" font-family="Arial" font-size="16" fill="#64748b">No image</text></svg>');

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


function normalizeStoredImagePath($path)
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

    $path = ltrim($path, '/');
    $path = preg_replace('#^(quan_ly_vat_tu|Quan_ly_vat_tu)/#i', '', $path);

    return $path;
}

function handleProductImageUpload(&$error)
{
    if (
        !isset($_FILES['hinh_anh_file']) ||
        !is_array($_FILES['hinh_anh_file']) ||
        ($_FILES['hinh_anh_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE
    ) {
        return '';
    }

    if ($_FILES['hinh_anh_file']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Không thể tải ảnh lên. Vui lòng chọn lại ảnh hợp lệ.';
        return '';
    }

    $allowedExtensions = ['webp', 'png', 'jpg', 'jpeg'];
    $originalName = $_FILES['hinh_anh_file']['name'] ?? '';
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    if (!in_array($extension, $allowedExtensions, true)) {
        $error = 'Ảnh sản phẩm chỉ hỗ trợ định dạng .webp, .png, .jpg, .jpeg.';
        return '';
    }

    $maxSize = 5 * 1024 * 1024;
    if (($_FILES['hinh_anh_file']['size'] ?? 0) > $maxSize) {
        $error = 'Dung lượng ảnh không được vượt quá 5MB.';
        return '';
    }

    $mime = @mime_content_type($_FILES['hinh_anh_file']['tmp_name']);
    $allowedMimes = ['image/webp', 'image/png', 'image/jpeg'];
    if ($mime && !in_array($mime, $allowedMimes, true)) {
        $error = 'File tải lên không phải là ảnh hợp lệ.';
        return '';
    }

    $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/quan_ly_vat_tu/assets/images/products/';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true)) {
        $error = 'Không thể tạo thư mục lưu ảnh sản phẩm.';
        return '';
    }

    $baseName = pathinfo($originalName, PATHINFO_FILENAME);
    $baseName = strtolower(trim($baseName));
    $baseName = preg_replace('/[^a-z0-9\-_]+/i', '-', $baseName);
    $baseName = trim($baseName, '-');
    if ($baseName === '') {
        $baseName = 'san-pham';
    }

    $fileName = $baseName . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(3)) . '.' . $extension;
    $targetPath = $uploadDir . $fileName;

    if (!move_uploaded_file($_FILES['hinh_anh_file']['tmp_name'], $targetPath)) {
        $error = 'Không thể lưu ảnh sản phẩm vào thư mục assets/images/products.';
        return '';
    }

    return 'assets/images/products/' . $fileName;
}

function keepQuery(array $merge = [])
{
    $query = array_merge($_GET, $merge);
    return '?' . http_build_query($query);
}

// Xử lý thêm/sửa/xóa sản phẩm
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add' || $_POST['action'] === 'edit') {
        try {
            $tenSP = trim($_POST['ten_sp'] ?? '');
            $donGia = (float)($_POST['don_gia'] ?? 0);
            $hoatChatChinh = trim($_POST['hoat_chat'] ?? '');
            $hamLuong = trim($_POST['ham_luong'] ?? '');
            $moTaCT = trim($_POST['mo_ta'] ?? '');
            $soDangKy = trim($_POST['so_dang_ky'] ?? '');
            $laHangKiemSoat = isset($_POST['hang_kiem_soat']) ? 1 : 0;
            $dieuKienBaoQuan = trim($_POST['dieu_kien'] ?? '');
            $xuatXu = trim($_POST['xuat_xu'] ?? '');
            $congTySX = trim($_POST['cong_ty_sx'] ?? '');
            $hinhAnh = normalizeStoredImagePath($_POST['hinh_anh'] ?? '');
            $uploadedImage = handleProductImageUpload($error);
            if ($uploadedImage !== '') {
                $hinhAnh = $uploadedImage;
            }
            $maLSP = ($_POST['ma_loai'] ?? '') !== '' ? $_POST['ma_loai'] : null;

            if ($error === '' && ($tenSP === '' || $donGia <= 0)) {
                $error = 'Vui lòng điền đầy đủ tên sản phẩm và đơn giá hợp lệ!';
            } elseif ($error === '') {
                if ($_POST['action'] === 'add') {
                    $stmt = $pdo->prepare('INSERT INTO sanpham
                        (TenSP, DonGia, HoatChatChinh, HamLuong, MoTaCT, SoDangKy, LaHangKiemSoat, DieuKienBaoQuan, XuatXu, CongTySanXuat, HinhAnh, MaLSP)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                    $stmt->execute([$tenSP, $donGia, $hoatChatChinh, $hamLuong, $moTaCT, $soDangKy, $laHangKiemSoat, $dieuKienBaoQuan, $xuatXu, $congTySX, $hinhAnh, $maLSP]);
                    $success = 'Thêm sản phẩm thành công!';
                } else {
                    $maSP = $_POST['ma_sp'] ?? '';
                    $stmt = $pdo->prepare('UPDATE sanpham SET
                        TenSP = ?, DonGia = ?, HoatChatChinh = ?, HamLuong = ?, MoTaCT = ?, SoDangKy = ?, LaHangKiemSoat = ?, DieuKienBaoQuan = ?, XuatXu = ?, CongTySanXuat = ?, HinhAnh = ?, MaLSP = ?
                        WHERE MaSP = ?');
                    $stmt->execute([$tenSP, $donGia, $hoatChatChinh, $hamLuong, $moTaCT, $soDangKy, $laHangKiemSoat, $dieuKienBaoQuan, $xuatXu, $congTySX, $hinhAnh, $maLSP, $maSP]);
                    $success = 'Cập nhật sản phẩm thành công!';
                }
                $action = 'list';
            }
        } catch (PDOException $e) {
            $error = 'Lỗi: ' . $e->getMessage();
        }
    } elseif ($_POST['action'] === 'delete') {
        try {
            $maSP = $_POST['ma_sp'] ?? '';
            $stmt = $pdo->prepare('DELETE FROM sanpham WHERE MaSP = ?');
            $stmt->execute([$maSP]);
            $success = 'Xóa sản phẩm thành công!';
        } catch (PDOException $e) {
            $error = 'Lỗi: ' . $e->getMessage();
        }
    }
}

// Danh mục
try {
    $stmt = $pdo->query('SELECT * FROM loaisp ORDER BY TenLSP');
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    $categories = [];
}

// Sản phẩm cần sửa
$editProduct = null;
if ($action === 'edit' && isset($_GET['id'])) {
    try {
        $stmt = $pdo->prepare('SELECT * FROM sanpham WHERE MaSP = ?');
        $stmt->execute([$_GET['id']]);
        $editProduct = $stmt->fetch();
        if (!$editProduct) {
            $error = 'Không tìm thấy sản phẩm cần chỉnh sửa.';
            $action = 'list';
        }
    } catch (PDOException $e) {
        $error = 'Lỗi: ' . $e->getMessage();
        $action = 'list';
    }
}

$searchQuery = trim($_GET['search'] ?? '');
$filterCategory = $_GET['category'] ?? '';
$filterControlled = $_GET['controlled'] ?? '';

$whereConditions = [];
$params = [];

if ($searchQuery !== '') {
    $whereConditions[] = '(sp.TenSP LIKE ? OR sp.HoatChatChinh LIKE ? OR sp.SoDangKy LIKE ? OR sp.CongTySanXuat LIKE ?)';
    array_push($params, "%$searchQuery%", "%$searchQuery%", "%$searchQuery%", "%$searchQuery%");
}
if ($filterCategory !== '') {
    $whereConditions[] = 'sp.MaLSP = ?';
    $params[] = $filterCategory;
}
if ($filterControlled !== '') {
    $whereConditions[] = 'sp.LaHangKiemSoat = ?';
    $params[] = $filterControlled;
}
$whereClause = $whereConditions ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

try {
    $countStmt = $pdo->prepare("SELECT COUNT(*) AS total FROM sanpham sp $whereClause");
    $countStmt->execute($params);
    $totalProducts = (int)$countStmt->fetch()['total'];
    $totalPages = max(1, (int)ceil($totalProducts / $itemsPerPage));
    $currentPage = min($currentPage, $totalPages);
    $offset = ($currentPage - 1) * $itemsPerPage;

    $sql = "SELECT sp.*, ls.TenLSP, ns.TenNSP,
            COALESCE(nhap.TongNhap, 0) AS TongNhap,
            COALESCE(xuat.TongXuat, 0) AS TongXuat,
            COALESCE(nhap.TongNhap, 0) - COALESCE(xuat.TongXuat, 0) AS TonKho
        FROM sanpham sp
        LEFT JOIN loaisp ls ON sp.MaLSP = ls.MaLSP
        LEFT JOIN nhomsp ns ON ls.MaNSP = ns.MaNSP
        LEFT JOIN (SELECT MaSP, SUM(SLMH) AS TongNhap FROM chitietmuahang GROUP BY MaSP) nhap ON sp.MaSP = nhap.MaSP
        LEFT JOIN (SELECT MaSP, SUM(SLBH) AS TongXuat FROM chitietbanhang GROUP BY MaSP) xuat ON sp.MaSP = xuat.MaSP
        $whereClause
        ORDER BY sp.MaSP DESC
        LIMIT " . intval($itemsPerPage) . " OFFSET " . intval($offset);

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = 'Lỗi: ' . $e->getMessage();
    $products = [];
    $totalProducts = 0;
    $totalPages = 1;
}
try {
    $controlledStmt = $pdo->query("
        SELECT COUNT(*) AS total
        FROM sanpham
        WHERE LaHangKiemSoat = 1
    ");
    $totalControlledProducts = (int)$controlledStmt->fetch()['total'];
} catch (PDOException $e) {
    $totalControlledProducts = 0;
}

include $_SERVER['DOCUMENT_ROOT'] . '/quan_ly_vat_tu/includes/header.php';
include $_SERVER['DOCUMENT_ROOT'] . '/quan_ly_vat_tu/includes/sidebar.php';
?>

<style>
    :root {
        --product-primary-light: rgba(102, 126, 234, 0.12);
        --product-success-light: rgba(72, 187, 120, 0.14);
        --product-warning-light: rgba(237, 137, 54, 0.14);
        --product-danger-light: rgba(245, 101, 101, 0.14);
    }

    .kt-page {
        padding: 0;
    }

    .kt-toolbar {
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
        color: #fff;
        padding: 32px 36px;
        border-radius: 8px;
        margin-bottom: 30px;
        box-shadow: var(--shadow-lg);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 18px;
        flex-wrap: wrap;
    }

    .kt-breadcrumb {
        display: flex;
        align-items: center;
        gap: 8px;
        color: rgba(255, 255, 255, 0.78);
        font-size: 12px;
        margin-bottom: 8px;
    }

    .kt-title h1 {
        font-size: 26px;
        line-height: 1.25;
        margin: 0;
        color: #fff;
        font-weight: 600;
    }

    .kt-title .sub {
        margin-top: 8px;
        color: rgba(255, 255, 255, 0.9);
        font-size: 14px;
        font-weight: 400;
    }

    .kt-actions {
        display: flex;
        gap: 10px;
        align-items: center;
        flex-wrap: wrap;
    }

    .kt-btn {
        border: 1px solid transparent;
        border-radius: 6px;
        padding: 9px 14px;
        font-weight: 600;
        font-size: 13px;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        cursor: pointer;
        transition: all 0.3s;
        white-space: nowrap;
        line-height: 1.3;
    }

    .kt-btn-primary {
        background: var(--primary-color);
        color: #fff;
        box-shadow: var(--shadow);
    }

    .kt-btn-primary:hover {
        background: var(--secondary-color);
        color: #fff;
        transform: translateY(-1px);
        box-shadow: var(--shadow-lg);
    }

    .kt-btn-light {
        background: #fff;
        color: var(--text-dark);
        border-color: var(--border-color);
    }

    .kt-toolbar .kt-btn-light {
        border-color: rgba(255, 255, 255, 0.35);
    }

    .kt-btn-light:hover {
        background: var(--light-bg);
        color: var(--primary-color);
        border-color: var(--primary-color);
    }

    .kt-btn-danger {
        background: var(--product-danger-light);
        color: var(--danger-color);
        border-color: rgba(245, 101, 101, 0.18);
    }

    .kt-btn-danger:hover {
        background: var(--danger-color);
        color: #fff;
    }

    .kt-btn-info {
        background: var(--product-primary-light);
        color: var(--primary-color);
        border-color: rgba(102, 126, 234, 0.18);
    }

    .kt-btn-info:hover {
        background: var(--primary-color);
        color: #fff;
    }

    .kt-btn-sm {
        padding: 7px 10px;
        border-radius: 5px;
        font-size: 12px;
    }

    .kt-card {
        background: #fff;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        box-shadow: var(--shadow);
        margin-bottom: 30px;
        overflow: hidden;
    }

    .kt-card-head {
        padding: 18px 22px;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        flex-wrap: wrap;
    }

    .kt-card-title {
        font-weight: 600;
        color: var(--text-dark);
        font-size: 18px;
        margin: 0;
    }

    .kt-card-subtitle {
        font-size: 13px;
        color: var(--text-light);
        margin-top: 4px;
    }

    .kt-card-body {
        padding: 22px;
    }

    .kt-card-footer {
        padding: 18px 22px;
        border-top: 1px solid var(--border-color);
        background: #fff;
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    .kt-alert {
        border-radius: 8px;
        padding: 14px 16px;
        margin-bottom: 18px;
        display: flex;
        justify-content: space-between;
        gap: 12px;
        align-items: center;
        font-weight: 500;
        font-size: 14px;
    }

    .kt-alert-success {
        background: #d4edda;
        color: #155724;
        border-left: 4px solid var(--success-color);
    }

    .kt-alert-danger {
        background: #f8d7da;
        color: #721c24;
        border-left: 4px solid var(--danger-color);
    }

    .kt-alert button {
        border: 0;
        background: transparent;
        font-size: 22px;
        cursor: pointer;
        color: inherit;
    }

    .kt-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .kt-stat {
        background: #fff;
        border-radius: 8px;
        box-shadow: var(--shadow);
        padding: 22px;
        border: 2px solid transparent;
        position: relative;
        overflow: hidden;
        transition: all 0.3s;
        display: flex;
        align-items: center;
        gap: 16px;
    }

    .kt-stat:hover {
        transform: translateY(-4px);
        box-shadow: var(--shadow-lg);
        border-color: var(--primary-color);
    }

    .kt-stat::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
    }

    .kt-stat.success::before {
        background: var(--success-color);
    }

    .kt-stat.warning::before {
        background: var(--warning-color);
    }

    .kt-stat.info::before {
        background: var(--info-color);
    }

    .kt-stat.danger::before {
        background: var(--danger-color);
    }

    .kt-stat-icon {
        width: 58px;
        height: 58px;
        border-radius: 12px;
        display: flex;
        justify-content: center;
        align-items: center;
        flex-shrink: 0;
        font-size: 26px;
        background: rgba(102, 126, 234, 0.15);
        color: var(--primary-color);
    }

    .kt-stat.success .kt-stat-icon {
        background: rgba(72, 187, 120, 0.15);
        color: var(--success-color);
    }

    .kt-stat.warning .kt-stat-icon {
        background: rgba(237, 137, 54, 0.15);
        color: var(--warning-color);
    }

    .kt-stat.info .kt-stat-icon {
        background: rgba(66, 153, 225, 0.15);
        color: var(--info-color);
    }

    .kt-stat.danger .kt-stat-icon {
        background: rgba(245, 101, 101, 0.15);
        color: var(--danger-color);
    }

    .kt-stat-content {
        flex: 1;
        min-width: 0;
    }

    .kt-stat .label {
        color: var(--text-light);
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .kt-stat .value {
        font-size: 24px;
        font-weight: 700;
        color: var(--text-dark);
        margin-top: 8px;
        line-height: 1;
    }

    .kt-stat .hint {
        font-size: 12px;
        color: var(--text-light);
        margin-top: 7px;
    }

    .kt-filter {
        display: grid;
        grid-template-columns: 1.6fr 1fr 1fr auto auto;
        gap: 12px;
        align-items: end;
    }

    .kt-field label {
        font-size: 13px;
        color: var(--text-dark);
        font-weight: 600;
        margin-bottom: 7px;
        display: block;
    }

    .kt-input,
    .kt-select,
    .kt-textarea {
        width: 100%;
        border: 1px solid var(--border-color);
        border-radius: 6px;
        padding: 10px 12px;
        color: var(--text-dark);
        background: #fff;
        font-size: 13px;
        outline: none;
        transition: all 0.3s;
        font-family: inherit;
    }

    .kt-input:focus,
    .kt-select:focus,
    .kt-textarea:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.12);
    }

    .kt-textarea {
        min-height: 120px;
        resize: vertical;
    }

    .required:after {
        content: ' *';
        color: var(--danger-color);
    }

    .kt-table-wrap {
        overflow-x: auto;
    }

    .kt-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
    }

    .kt-table th {
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        color: var(--text-light);
        font-weight: 600;
        text-align: left;
        padding: 14px 16px;
        border-bottom: 1px solid var(--border-color);
        background: var(--light-bg);
    }

    .kt-table td {
        padding: 15px 16px;
        border-bottom: 1px solid var(--border-color);
        vertical-align: middle;
    }

    .kt-table tr:hover td {
        background: var(--light-bg);
    }

    .kt-product-cell {
        display: flex;
        align-items: center;
        gap: 14px;
        min-width: 320px;
    }

    .kt-product-img {
        width: 54px;
        height: 54px;
        border-radius: 8px;
        object-fit: cover;
        background: var(--light-bg);
        border: 1px solid var(--border-color);
        flex-shrink: 0;
    }

    .kt-product-name {
        font-weight: 600;
        color: var(--text-dark);
        text-decoration: none;
        display: block;
        margin-bottom: 4px;
        font-size: 14px;
    }

    .kt-product-name:hover {
        color: var(--primary-color);
    }

    .kt-meta {
        font-size: 12px;
        color: var(--text-light);
        line-height: 1.5;
    }

    .kt-price {
        font-weight: 600;
        color: var(--text-dark);
        white-space: nowrap;
    }

    .kt-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        border-radius: 20px;
        padding: 6px 10px;
        font-size: 12px;
        font-weight: 600;
        white-space: nowrap;
    }

    .kt-badge-primary {
        background: var(--product-primary-light);
        color: var(--primary-color);
    }

    .kt-badge-success {
        background: #d4edda;
        color: #155724;
    }

    .kt-badge-warning {
        background: #fff3cd;
        color: #856404;
    }

    .kt-badge-muted {
        background: var(--light-bg);
        color: var(--text-light);
    }

    .kt-badge-danger {
        background: #f8d7da;
        color: #721c24;
    }

    .kt-action-group {
        display: flex;
        gap: 8px;
        align-items: center;
    }

    .kt-pagination {
        display: flex;
        gap: 7px;
        align-items: center;
        justify-content: flex-end;
        padding: 18px 22px;
        flex-wrap: wrap;
    }

    .kt-page-link {
        min-width: 34px;
        height: 34px;
        border-radius: 6px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        color: var(--text-dark);
        background: #fff;
        border: 1px solid var(--border-color);
        font-weight: 600;
        font-size: 12px;
    }

    .kt-page-link:hover,
    .kt-page-link.active {
        background: var(--primary-color);
        border-color: var(--primary-color);
        color: #fff;
    }

    .kt-page-link.disabled {
        opacity: .45;
        pointer-events: none;
    }

    .kt-empty {
        text-align: center;
        padding: 50px 20px;
        color: var(--text-light);
    }

    .kt-empty .icon {
        font-size: 48px;
        margin-bottom: 15px;
    }

    .kt-empty h3 {
        color: var(--text-dark);
        margin: 0 0 8px;
        font-size: 16px;
        font-weight: 600;
    }

    .kt-form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 18px;
    }

    .kt-form-grid-3 {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 18px;
    }

    .kt-image-preview {
        display: flex;
        gap: 16px;
        align-items: center;
        background: var(--light-bg);
        border: 1px dashed var(--border-color);
        border-radius: 8px;
        padding: 14px;
    }

    .kt-image-preview img {
        width: 96px;
        height: 96px;
        border-radius: 8px;
        object-fit: cover;
        border: 1px solid var(--border-color);
        background: #fff;
        flex-shrink: 0;
    }

    .kt-upload-row {
        display: flex;
        gap: 10px;
        align-items: center;
        flex-wrap: wrap;
        margin-top: 10px;
    }

    .kt-file-input {
        display: none;
    }

    .kt-upload-hint {
        color: var(--text-light);
        font-size: 12px;
        margin-top: 6px;
        line-height: 1.5;
    }

    .kt-check {
        display: flex;
        align-items: center;
        gap: 10px;
        font-weight: 500;
        color: var(--text-dark);
    }

    .kt-check input {
        width: 18px;
        height: 18px;
        accent-color: var(--primary-color);
    }

    @media(max-width:1100px) {
        .kt-filter {
            grid-template-columns: 1fr 1fr;
        }

        .kt-form-grid,
        .kt-form-grid-3 {
            grid-template-columns: 1fr;
        }
    }

    @media(max-width:700px) {
        .kt-toolbar {
            padding: 25px;
            align-items: stretch;
        }

        .kt-title h1 {
            font-size: 22px;
        }

        .kt-filter {
            grid-template-columns: 1fr;
        }

        .kt-actions,
        .kt-btn {
            width: 100%;
            justify-content: center;
        }

        .kt-card-head {
            align-items: flex-start;
        }

        .kt-pagination {
            justify-content: center;
        }

        .kt-image-preview {
            align-items: flex-start;
        }

        .kt-stat {
            align-items: flex-start;
        }
    }
</style>

<main class="kt-page">
    <div class="kt-toolbar">
        <div>
            <h1><?= $action === 'add' ? 'Thêm sản phẩm mới' : ($action === 'edit' ? 'Cập nhật sản phẩm' : 'Danh sách sản phẩm') ?></h1>
            <div class="sub"><?= $action === 'list' ? 'Quản lý thuốc, thiết bị y tế, hình ảnh, giá bán và trạng thái kiểm soát.' : 'Cập nhật đầy đủ thông tin sản phẩm và đường dẫn hình ảnh.' ?></div>
        </div>
        <div class="kt-actions">
            <?php if ($action === 'list'): ?>
                <a href="?action=add" class="kt-btn kt-btn-primary">＋ Thêm sản phẩm</a>
            <?php else: ?>
                <a href="?action=list" class="kt-btn kt-btn-light">← Quay lại danh sách</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($success): ?><div class="kt-alert kt-alert-success"><span>✅ <?= e($success) ?></span><button onclick="this.parentElement.remove()">×</button></div><?php endif; ?>
    <?php if ($error): ?><div class="kt-alert kt-alert-danger"><span>❌ <?= e($error) ?></span><button onclick="this.parentElement.remove()">×</button></div><?php endif; ?>

    <?php if ($action === 'add' || ($action === 'edit' && $editProduct)): ?>
        <form method="POST" enctype="multipart/form-data" class="kt-card">
            <input type="hidden" name="action" value="<?= e($action) ?>">
            <?php if ($action === 'edit'): ?><input type="hidden" name="ma_sp" value="<?= e($editProduct['MaSP']) ?>"><?php endif; ?>

            <div class="kt-card-head">
                <div>
                    <h2 class="kt-card-title">Thông tin sản phẩm</h2>
                    <div class="kt-card-subtitle">Các trường có dấu * là bắt buộc.</div>
                </div>
                <?php if ($action === 'edit'): ?><a class="kt-btn kt-btn-info" href="product_details.php?id=<?= e($editProduct['MaSP']) ?>">Xem chi tiết</a><?php endif; ?>
            </div>

            <div class="kt-card-body">
                <div class="kt-form-grid">
                    <div class="kt-field"><label class="required" for="ten_sp">Tên sản phẩm</label><input class="kt-input" type="text" id="ten_sp" name="ten_sp" value="<?= e($editProduct['TenSP'] ?? '') ?>" placeholder="VD: Paracetamol 500mg" required></div>
                    <div class="kt-field"><label class="required" for="don_gia">Đơn giá (₫)</label><input class="kt-input" type="number" id="don_gia" name="don_gia" value="<?= e($editProduct['DonGia'] ?? 0) ?>" min="0" step="0.01" required></div>
                </div>

                <div class="kt-form-grid" style="margin-top:18px">
                    <div class="kt-field"><label for="hoat_chat">Hoạt chất chính</label><input class="kt-input" type="text" id="hoat_chat" name="hoat_chat" value="<?= e($editProduct['HoatChatChinh'] ?? '') ?>" placeholder="VD: Paracetamol, Amoxicillin"></div>
                    <div class="kt-field"><label for="ham_luong">Hàm lượng</label><input class="kt-input" type="text" id="ham_luong" name="ham_luong" value="<?= e($editProduct['HamLuong'] ?? '') ?>" placeholder="VD: 500mg, 100ml"></div>
                </div>

                <div class="kt-form-grid-3" style="margin-top:18px">
                    <div class="kt-field"><label for="so_dang_ky">Số đăng ký</label><input class="kt-input" type="text" id="so_dang_ky" name="so_dang_ky" value="<?= e($editProduct['SoDangKy'] ?? '') ?>" placeholder="VD: VD-52487-25"></div>
                    <div class="kt-field"><label for="ma_loai">Loại sản phẩm</label><select class="kt-select" id="ma_loai" name="ma_loai">
                            <option value="">-- Chọn loại --</option><?php foreach ($categories as $cat): ?><option value="<?= e($cat['MaLSP']) ?>" <?= (($editProduct['MaLSP'] ?? null) == $cat['MaLSP']) ? 'selected' : '' ?>><?= e($cat['TenLSP']) ?></option><?php endforeach; ?>
                        </select></div>
                    <div class="kt-field"><label for="xuat_xu">Xuất xứ</label><input class="kt-input" type="text" id="xuat_xu" name="xuat_xu" value="<?= e($editProduct['XuatXu'] ?? '') ?>" placeholder="VD: Việt Nam"></div>
                </div>

                <div class="kt-form-grid" style="margin-top:18px">
                    <div class="kt-field"><label for="dieu_kien">Điều kiện bảo quản</label><input class="kt-input" type="text" id="dieu_kien" name="dieu_kien" value="<?= e($editProduct['DieuKienBaoQuan'] ?? '') ?>" placeholder="VD: Nhiệt độ phòng, nơi khô ráo"></div>
                    <div class="kt-field"><label for="cong_ty_sx">Công ty sản xuất</label><input class="kt-input" type="text" id="cong_ty_sx" name="cong_ty_sx" value="<?= e($editProduct['CongTySanXuat'] ?? '') ?>" placeholder="Nhập công ty sản xuất"></div>
                </div>

                <div class="kt-form-grid" style="margin-top:18px;align-items:end">
                    <div class="kt-field">
                        <label for="hinh_anh">Hình ảnh sản phẩm</label>
                        <input class="kt-input" type="text" id="hinh_anh" name="hinh_anh" value="<?= e(normalizeStoredImagePath($editProduct['HinhAnh'] ?? '')) ?>" placeholder="assets/images/products/ten-anh.webp">
                        <div class="kt-upload-row">
                            <input class="kt-file-input" type="file" id="hinh_anh_file" name="hinh_anh_file" accept=".webp,.png,.jpg,.jpeg,image/webp,image/png,image/jpeg">
                            <label class="kt-btn kt-btn-info" for="hinh_anh_file">📁 Chọn ảnh từ máy</label>
                            <button class="kt-btn kt-btn-light" type="button" id="clearImageBtn">Xóa ảnh chọn</button>
                        </div>
                        <div class="kt-upload-hint">Có thể nhập đường dẫn có sẵn hoặc chọn file ảnh. File chọn từ máy sẽ được lưu vào <strong>assets/images/products/</strong>.</div>
                    </div>
                    <div class="kt-image-preview">
                        <img id="previewImage" src="<?= e(productImage($editProduct['HinhAnh'] ?? '')) ?>" alt="Ảnh sản phẩm">
                        <div>
                            <strong style="color:var(--text-dark);font-weight:600">Xem trước hình ảnh</strong>
                            <div class="kt-meta" style="margin-top:6px">Hỗ trợ .webp, .png, .jpg, .jpeg. Dung lượng tối đa 5MB.</div>
                        </div>
                    </div>
                </div>

                <div class="kt-field" style="margin-top:18px"><label for="mo_ta">Mô tả chi tiết</label><textarea class="kt-textarea" id="mo_ta" name="mo_ta" placeholder="Nhập mô tả chi tiết về sản phẩm"><?= e($editProduct['MoTaCT'] ?? '') ?></textarea></div>
                <label class="kt-check" style="margin-top:18px"><input type="checkbox" name="hang_kiem_soat" value="1" <?= (($editProduct['LaHangKiemSoat'] ?? 0) == 1) ? 'checked' : '' ?>> Là hàng kiểm soát</label>
            </div>
            <div class="kt-card-footer"><button type="submit" class="kt-btn kt-btn-primary">💾 Lưu sản phẩm</button><a href="?action=list" class="kt-btn kt-btn-light">Hủy</a></div>
        </form>
    <?php else: ?>
        <div class="kt-stats">
            <div class="kt-stat">
                <div class="kt-stat-icon">📦</div>
                <div class="kt-stat-content">
                    <div class="label">Tổng sản phẩm</div>
                    <div class="value"><?= number_format($totalProducts) ?></div>
                    <div class="hint">Theo bộ lọc hiện tại</div>
                </div>
            </div>
            <div class="kt-stat success">
                <div class="kt-stat-icon">✅</div>
                <div class="kt-stat-content">
                    <div class="label">Đang hiển thị</div>
                    <div class="value"><?= count($products) ?></div>
                    <div class="hint">Trang <?= $currentPage ?>/<?= $totalPages ?></div>
                </div>
            </div>
            <div class="kt-stat warning">
                <div class="kt-stat-icon">⚠️</div>
                <div class="kt-stat-content">
                    <div class="label">Có kiểm soát</div>
                    <div class="value"><?= number_format($totalControlledProducts) ?></div>
                    <div class="hint">Trong trang hiện tại</div>
                </div>
            </div>
            <div class="kt-stat info">
                <div class="kt-stat-icon">🏷️</div>
                <div class="kt-stat-content">
                    <div class="label">Danh mục</div>
                    <div class="value"><?= count($categories) ?></div>
                    <div class="hint">Loại sản phẩm</div>
                </div>
            </div>
        </div>

        <div class="kt-card">
            <div class="kt-card-head">
                <div>
                    <h2 class="kt-card-title">📋 Danh sách sản phẩm</h2>
                    <div class="kt-card-subtitle">Quản lý sản phẩm, hình ảnh, giá bán, tồn kho và trạng thái kiểm soát.</div>
                </div>
            </div>
            <div class="kt-card-body">
                <form method="GET" class="kt-filter">
                    <input type="hidden" name="action" value="list">
                    <div class="kt-field"><label>Tìm kiếm</label><input class="kt-input" type="text" name="search" value="<?= e($searchQuery) ?>" placeholder="Tìm theo tên, hoạt chất, SĐK, nhà sản xuất..."></div>
                    <div class="kt-field"><label>Loại sản phẩm</label><select class="kt-select" name="category">
                            <option value="">Tất cả loại</option><?php foreach ($categories as $cat): ?><option value="<?= e($cat['MaLSP']) ?>" <?= $filterCategory == $cat['MaLSP'] ? 'selected' : '' ?>><?= e($cat['TenLSP']) ?></option><?php endforeach; ?>
                        </select></div>
                    <div class="kt-field"><label>Kiểm soát</label><select class="kt-select" name="controlled">
                            <option value="">Tất cả</option>
                            <option value="1" <?= $filterControlled === '1' ? 'selected' : '' ?>>Có kiểm soát</option>
                            <option value="0" <?= $filterControlled === '0' ? 'selected' : '' ?>>Không kiểm soát</option>
                        </select></div>
                    <button class="kt-btn kt-btn-primary" type="submit">🔎 Lọc</button>
                    <a class="kt-btn kt-btn-light" href="?action=list">Đặt lại</a>
                </form>
            </div>
            <?php if ($products): ?>
                <div class="kt-table-wrap">
                    <table class="kt-table">
                        <thead>
                            <tr>
                                <th>Sản phẩm</th>
                                <th>Danh mục</th>
                                <th>Giá bán</th>
                                <th>Tồn kho</th>
                                <th>Hoạt chất</th>
                                <th>Trạng thái</th>
                                <th style="text-align:right">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $prod): ?>
                                <tr>
                                    <td>
                                        <div class="kt-product-cell"><a href="product_details.php?id=<?= e($prod['MaSP']) ?>"><img class="kt-product-img" src="<?= e(productImage($prod['HinhAnh'])) ?>" alt="<?= e($prod['TenSP']) ?>"></a>
                                            <div><a class="kt-product-name" href="product_details.php?id=<?= e($prod['MaSP']) ?>"><?= e($prod['TenSP']) ?></a>
                                                <div class="kt-meta">#SP<?= e($prod['MaSP']) ?> • SĐK: <?= e($prod['SoDangKy'] ?: 'Chưa có') ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?= $prod['TenLSP'] ? '<span class="kt-badge kt-badge-primary">' . e($prod['TenLSP']) . '</span>' : '<span class="kt-badge kt-badge-muted">Chưa phân loại</span>' ?><div class="kt-meta" style="margin-top:6px"><?= e($prod['TenNSP'] ?? '') ?></div>
                                    </td>
                                    <td><span class="kt-price"><?= number_format((float)$prod['DonGia'], 0, ',', '.') ?> ₫</span></td>
                                    <td><span class="kt-badge <?= (int)$prod['TonKho'] > 0 ? 'kt-badge-success' : 'kt-badge-danger' ?>"><?= number_format((int)$prod['TonKho']) ?></span></td>
                                    <td><?= e($prod['HoatChatChinh'] ?: '-') ?><div class="kt-meta"><?= e($prod['HamLuong'] ?: '') ?></div>
                                    </td>
                                    <td><?= (int)$prod['LaHangKiemSoat'] === 1 ? '<span class="kt-badge kt-badge-warning">⚠ Có kiểm soát</span>' : '<span class="kt-badge kt-badge-success">✓ Bình thường</span>' ?></td>
                                    <td style="text-align:right">
                                        <div class="kt-action-group" style="justify-content:flex-end"><a class="kt-btn kt-btn-info kt-btn-sm" href="product_details.php?id=<?= e($prod['MaSP']) ?>">Xem</a><a class="kt-btn kt-btn-light kt-btn-sm" href="?action=edit&id=<?= e($prod['MaSP']) ?>">Sửa</a>
                                            <form method="POST" onsubmit="return confirm('Bạn chắc chắn muốn xóa sản phẩm này?');"><input type="hidden" name="action" value="delete"><input type="hidden" name="ma_sp" value="<?= e($prod['MaSP']) ?>"><button class="kt-btn kt-btn-danger kt-btn-sm" type="submit">Xóa</button></form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($totalPages > 1): ?><div class="kt-pagination">
                        <a class="kt-page-link <?= $currentPage <= 1 ? 'disabled' : '' ?>" href="<?= e(keepQuery(['page' => $currentPage - 1])) ?>">‹</a>
                        <?php for ($i = max(1, $currentPage - 2); $i <= min($totalPages, $currentPage + 2); $i++): ?><a class="kt-page-link <?= $i === $currentPage ? 'active' : '' ?>" href="<?= e(keepQuery(['page' => $i])) ?>"><?= $i ?></a><?php endfor; ?>
                        <a class="kt-page-link <?= $currentPage >= $totalPages ? 'disabled' : '' ?>" href="<?= e(keepQuery(['page' => $currentPage + 1])) ?>">›</a>
                    </div><?php endif; ?>
            <?php else: ?>
                <div class="kt-empty">
                    <div class="icon">📦</div>
                    <h3>Không tìm thấy sản phẩm</h3>
                    <p>Thử thay đổi bộ lọc hoặc thêm sản phẩm mới vào hệ thống.</p><a href="?action=add" class="kt-btn kt-btn-primary" style="margin-top:12px">＋ Thêm sản phẩm</a>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</main>

<script>
    const productImageFallback = 'data:image/svg+xml;utf8,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20width%3D%22320%22%20height%3D%22240%22%20viewBox%3D%220%200%20320%20240%22%3E%3Crect%20width%3D%22320%22%20height%3D%22240%22%20rx%3D%2228%22%20fill%3D%22%23f1f5f9%22/%3E%3Cpath%20d%3D%22M116%20108h88v58h-88z%22%20fill%3D%22%23dbe4f0%22/%3E%3Cpath%20d%3D%22M132%2093h56l16%2015h-88z%22%20fill%3D%22%23cbd5e1%22/%3E%3Ccircle%20cx%3D%22144%22%20cy%3D%22137%22%20r%3D%2210%22%20fill%3D%22%2394a3b8%22/%3E%3Cpath%20d%3D%22M161%20150l18-20%2025%2036h-63z%22%20fill%3D%22%2394a3b8%22/%3E%3Ctext%20x%3D%22160%22%20y%3D%22198%22%20text-anchor%3D%22middle%22%20font-family%3D%22Arial%22%20font-size%3D%2216%22%20fill%3D%22%2364748b%22%3ENo%20image%3C/text%3E%3C/svg%3E';
    document.querySelectorAll('img').forEach(img => {
        img.onerror = function() {
            this.onerror = null;
            this.src = productImageFallback;
        };
    });
    document.querySelectorAll('.kt-alert').forEach(alert => setTimeout(() => alert.remove(), 5000));
    const imageInput = document.getElementById('hinh_anh');
    const imageFileInput = document.getElementById('hinh_anh_file');
    const clearImageBtn = document.getElementById('clearImageBtn');
    const preview = document.getElementById('previewImage');

    function toProductImageUrl(value) {
        value = (value || '').trim().replaceAll('\\\\', '/').replaceAll('\\', '/');
        if (!value) return productImageFallback;
        if (/^https?:\/\//i.test(value) || value.startsWith('/') || value.startsWith('data:')) return value;
        value = value.replace(/^\.\//, '').replace(/^(quan_ly_vat_tu|Quan_ly_vat_tu)\//i, '');
        return '/quan_ly_vat_tu/' + value.replace(/^\/+/, '');
    }

    if (imageInput && preview) {
        imageInput.addEventListener('input', function() {
            if (imageFileInput) imageFileInput.value = '';
            preview.src = toProductImageUrl(this.value);
        });
    }

    if (imageFileInput && preview) {
        imageFileInput.addEventListener('change', function() {
            const file = this.files && this.files[0] ? this.files[0] : null;
            if (!file) {
                preview.src = toProductImageUrl(imageInput ? imageInput.value : '');
                return;
            }

            const validTypes = ['image/webp', 'image/png', 'image/jpeg'];
            if (!validTypes.includes(file.type)) {
                alert('Chỉ hỗ trợ ảnh .webp, .png, .jpg, .jpeg');
                this.value = '';
                return;
            }

            if (file.size > 5 * 1024 * 1024) {
                alert('Dung lượng ảnh không được vượt quá 5MB');
                this.value = '';
                return;
            }

            preview.src = URL.createObjectURL(file);
        });
    }

    if (clearImageBtn && imageFileInput && preview) {
        clearImageBtn.addEventListener('click', function() {
            imageFileInput.value = '';
            preview.src = toProductImageUrl(imageInput ? imageInput.value : '');
        });
    }
</script>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/quan_ly_vat_tu/includes/footer.php'; ?>