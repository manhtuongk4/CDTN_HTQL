<?php

/**
 * File kết nối CSDL MySQL
 * Sử dụng PDO để kết nối an toàn
 */

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'quanly_vattu_yte');
define('VIETMAP_API_KEY', 'd5924681d40964cbf5c8f16ab023e1051e49ca847dcc9f4a');

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        )
    );
} catch (PDOException $e) {
    die('Lỗi kết nối CSDL: ' . $e->getMessage());
}

// Hàm kiểm tra đăng nhập
function isLoggedIn()
{
    return isset($_SESSION['MaNV']) && isset($_SESSION['TenDangNhap']);
}

// Hàm redirect nếu chưa đăng nhập
function requireLogin()
{
    if (!isLoggedIn()) {
        header('Location: /quan_ly_vat_tu/login.php');
        exit();
    }
}

// Hàm kiểm tra quyền Admin
function isAdmin()
{
    return isset($_SESSION['VaiTro']) && $_SESSION['VaiTro'] == 1;
}

// Hàm lấy tên vai trò
function getRoleName($vaiTro)
{
    return $vaiTro == 1 ? 'Quản trị viên' : 'Nhân viên';
}



// Hàm lấy đường dẫn base
function getBaseUrl()
{
    return '/quan_ly_vat_tu';
}
