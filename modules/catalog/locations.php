<?php
$pageTitle = 'Quản lý địa điểm - Hệ thống quản lý vật tư y tế';
require_once $_SERVER['DOCUMENT_ROOT'] . '/quan_ly_vat_tu/config/database.php';
session_start();
requireLogin();

$success = '';
$error = '';
$action = $_GET['action'] ?? 'list';
$searchQuery = trim($_GET['search'] ?? '');
$filterProvince = $_GET['province'] ?? '';

function e($value)
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function initialsLocation($name)
{
    $name = trim((string)$name);
    if ($name === '') {
        return 'ĐĐ';
    }

    $parts = preg_split('/\s+/u', $name);
    $first = mb_substr($parts[0], 0, 1, 'UTF-8');
    $last = count($parts) > 1 ? mb_substr($parts[count($parts) - 1], 0, 1, 'UTF-8') : '';

    return mb_strtoupper($first . $last, 'UTF-8');
}

function locationColorClass($key)
{
    $classes = ['primary', 'success', 'warning', 'danger', 'info'];
    $index = abs(crc32((string)$key)) % count($classes);
    return $classes[$index];
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

function columnExists(PDO $pdo, $table, $column)
{
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
        $stmt->execute([$column]);
        return (bool)$stmt->fetch();
    } catch (PDOException $e) {
        return false;
    }
}

function countReferences(PDO $pdo, $table, $column, $value)
{
    if (!tableExists($pdo, $table) || !columnExists($pdo, $table, $column)) {
        return 0;
    }

    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM `$table` WHERE `$column` = ?");
        $stmt->execute([$value]);
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

function provinceUsageCount(PDO $pdo, $maTinh)
{
    $count = 0;
    foreach (['khachhang', 'nhacc', 'nhanvien'] as $table) {
        $count += countReferences($pdo, $table, 'MaTinh', $maTinh);
    }
    return $count;
}

function wardUsageCount(PDO $pdo, $maXP)
{
    $count = 0;
    foreach (['khachhang', 'nhacc', 'nhanvien'] as $table) {
        $count += countReferences($pdo, $table, 'MaXP', $maXP);
    }
    return $count;
}

// Xử lý thêm/sửa/xóa tỉnh và phường/xã
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_tinh') {
        try {
            $maTinh = trim($_POST['ma_tinh'] ?? '');
            $tenTinh = trim($_POST['ten_tinh'] ?? '');

            if ($maTinh === '' || $tenTinh === '') {
                $error = 'Vui lòng điền đầy đủ mã tỉnh/thành phố và tên tỉnh/thành phố.';
            } else {
                $check = $pdo->prepare('SELECT COUNT(*) FROM tinh WHERE MaTinh = ? OR TenTinh = ?');
                $check->execute([$maTinh, $tenTinh]);

                if ((int)$check->fetchColumn() > 0) {
                    $error = 'Mã hoặc tên tỉnh/thành phố đã tồn tại.';
                } else {
                    $stmt = $pdo->prepare('INSERT INTO tinh (MaTinh, TenTinh) VALUES (?, ?)');
                    $stmt->execute([$maTinh, $tenTinh]);
                    $success = 'Thêm tỉnh/thành phố thành công!';
                }
            }
        } catch (PDOException $e) {
            $error = 'Lỗi: ' . $e->getMessage();
        }
    } elseif ($_POST['action'] === 'edit_tinh') {
        try {
            $maTinh = trim($_POST['ma_tinh'] ?? '');
            $tenTinh = trim($_POST['ten_tinh'] ?? '');

            if ($maTinh === '' || $tenTinh === '') {
                $error = 'Vui lòng nhập tên tỉnh/thành phố.';
            } else {
                $check = $pdo->prepare('SELECT COUNT(*) FROM tinh WHERE TenTinh = ? AND MaTinh <> ?');
                $check->execute([$tenTinh, $maTinh]);

                if ((int)$check->fetchColumn() > 0) {
                    $error = 'Tên tỉnh/thành phố đã tồn tại.';
                } else {
                    $stmt = $pdo->prepare('UPDATE tinh SET TenTinh = ? WHERE MaTinh = ?');
                    $stmt->execute([$tenTinh, $maTinh]);
                    $success = 'Cập nhật tỉnh/thành phố thành công!';
                    $action = 'list';
                }
            }
        } catch (PDOException $e) {
            $error = 'Lỗi: ' . $e->getMessage();
        }
    } elseif ($_POST['action'] === 'add_xaphuong') {
        try {
            $maXP = trim($_POST['ma_xp'] ?? '');
            $tenXP = trim($_POST['ten_xp'] ?? '');
            $maTinh = trim($_POST['ma_tinh_xp'] ?? '');

            if ($maXP === '' || $tenXP === '' || $maTinh === '') {
                $error = 'Vui lòng điền đầy đủ mã, tên phường/xã và tỉnh/thành phố.';
            } else {
                $check = $pdo->prepare('SELECT COUNT(*) FROM xaphuong WHERE MaXP = ?');
                $check->execute([$maXP]);

                if ((int)$check->fetchColumn() > 0) {
                    $error = 'Mã phường/xã đã tồn tại.';
                } else {
                    $stmt = $pdo->prepare('INSERT INTO xaphuong (MaXP, TenXP, MaTinh) VALUES (?, ?, ?)');
                    $stmt->execute([$maXP, $tenXP, $maTinh]);
                    $success = 'Thêm phường/xã thành công!';
                }
            }
        } catch (PDOException $e) {
            $error = 'Lỗi: ' . $e->getMessage();
        }
    } elseif ($_POST['action'] === 'edit_xaphuong') {
        try {
            $maXP = trim($_POST['ma_xp'] ?? '');
            $tenXP = trim($_POST['ten_xp'] ?? '');
            $maTinh = trim($_POST['ma_tinh_xp'] ?? '');

            if ($maXP === '' || $tenXP === '' || $maTinh === '') {
                $error = 'Vui lòng điền đầy đủ thông tin phường/xã.';
            } else {
                $stmt = $pdo->prepare('UPDATE xaphuong SET TenXP = ?, MaTinh = ? WHERE MaXP = ?');
                $stmt->execute([$tenXP, $maTinh, $maXP]);
                $success = 'Cập nhật phường/xã thành công!';
                $action = 'list';
            }
        } catch (PDOException $e) {
            $error = 'Lỗi: ' . $e->getMessage();
        }
    } elseif ($_POST['action'] === 'delete_tinh') {
        try {
            $maTinh = trim($_POST['ma_tinh'] ?? '');

            $wardCountStmt = $pdo->prepare('SELECT COUNT(*) FROM xaphuong WHERE MaTinh = ?');
            $wardCountStmt->execute([$maTinh]);
            $wardCount = (int)$wardCountStmt->fetchColumn();
            $usageCount = provinceUsageCount($pdo, $maTinh);

            if ($wardCount > 0) {
                $error = 'Không thể xóa tỉnh/thành phố vì vẫn còn phường/xã trực thuộc.';
            } elseif ($usageCount > 0) {
                $error = 'Không thể xóa tỉnh/thành phố vì đang được dữ liệu khác sử dụng.';
            } else {
                $stmt = $pdo->prepare('DELETE FROM tinh WHERE MaTinh = ?');
                $stmt->execute([$maTinh]);
                $success = 'Xóa tỉnh/thành phố thành công!';
            }
        } catch (PDOException $e) {
            $error = 'Lỗi: ' . $e->getMessage();
        }
    } elseif ($_POST['action'] === 'delete_xaphuong') {
        try {
            $maXP = trim($_POST['ma_xp'] ?? '');
            $usageCount = wardUsageCount($pdo, $maXP);

            if ($usageCount > 0) {
                $error = 'Không thể xóa phường/xã vì đang được dữ liệu khác sử dụng.';
            } else {
                $stmt = $pdo->prepare('DELETE FROM xaphuong WHERE MaXP = ?');
                $stmt->execute([$maXP]);
                $success = 'Xóa phường/xã thành công!';
            }
        } catch (PDOException $e) {
            $error = 'Lỗi: ' . $e->getMessage();
        }
    }
}

// Lấy tỉnh/phường cần sửa
$editProvince = null;
$editWard = null;
if ($action === 'edit_tinh' && isset($_GET['id'])) {
    try {
        $stmt = $pdo->prepare('SELECT * FROM tinh WHERE MaTinh = ?');
        $stmt->execute([$_GET['id']]);
        $editProvince = $stmt->fetch();
        if (!$editProvince) {
            $error = 'Không tìm thấy tỉnh/thành phố cần chỉnh sửa.';
            $action = 'list';
        }
    } catch (PDOException $e) {
        $error = 'Lỗi: ' . $e->getMessage();
        $action = 'list';
    }
}

if ($action === 'edit_xaphuong' && isset($_GET['id'])) {
    try {
        $stmt = $pdo->prepare('SELECT * FROM xaphuong WHERE MaXP = ?');
        $stmt->execute([$_GET['id']]);
        $editWard = $stmt->fetch();
        if (!$editWard) {
            $error = 'Không tìm thấy phường/xã cần chỉnh sửa.';
            $action = 'list';
        }
    } catch (PDOException $e) {
        $error = 'Lỗi: ' . $e->getMessage();
        $action = 'list';
    }
}

// Lấy danh sách tỉnh
try {
    $stmt = $pdo->query('SELECT t.*, COUNT(xp.MaXP) AS WardCount FROM tinh t LEFT JOIN xaphuong xp ON t.MaTinh = xp.MaTinh GROUP BY t.MaTinh, t.TenTinh ORDER BY t.MaTinh');
    $provinces = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = 'Lỗi: ' . $e->getMessage();
    $provinces = [];
}

// Lấy danh sách xã phường + filter
$whereConditions = [];
$params = [];

if ($searchQuery !== '') {
    $whereConditions[] = '(xp.MaXP LIKE ? OR xp.TenXP LIKE ? OR t.MaTinh LIKE ? OR t.TenTinh LIKE ?)';
    array_push($params, "%$searchQuery%", "%$searchQuery%", "%$searchQuery%", "%$searchQuery%");
}

if ($filterProvince !== '') {
    $whereConditions[] = 'xp.MaTinh = ?';
    $params[] = $filterProvince;
}

$whereClause = $whereConditions ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

try {
    $stmt = $pdo->prepare("SELECT xp.*, t.TenTinh FROM xaphuong xp JOIN tinh t ON xp.MaTinh = t.MaTinh $whereClause ORDER BY xp.MaTinh, xp.MaXP");
    $stmt->execute($params);
    $wards = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = 'Lỗi: ' . $e->getMessage();
    $wards = [];
}

try {
    $stats = [
        'province_total' => (int)$pdo->query('SELECT COUNT(*) FROM tinh')->fetchColumn(),
        'ward_total' => (int)$pdo->query('SELECT COUNT(*) FROM xaphuong')->fetchColumn(),
        'ward_filtered' => count($wards),
        'province_with_wards' => (int)$pdo->query('SELECT COUNT(*) FROM (SELECT t.MaTinh FROM tinh t JOIN xaphuong xp ON t.MaTinh = xp.MaTinh GROUP BY t.MaTinh) tmp')->fetchColumn(),
    ];
} catch (PDOException $e) {
    $stats = ['province_total' => count($provinces), 'ward_total' => count($wards), 'ward_filtered' => count($wards), 'province_with_wards' => 0];
}


$vietmapApiKey = '';
if (defined('VIETMAP_API_KEY')) {
    $vietmapApiKey = trim((string)VIETMAP_API_KEY);
} elseif (getenv('VIETMAP_API_KEY')) {
    $vietmapApiKey = trim((string)getenv('VIETMAP_API_KEY'));
}

function vietmapProxyResponse($payload, $statusCode = 200)
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

function vietmapHttpGetPayload($url)
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'User-Agent: QuanLyVatTuYTe/1.0'
            ],
        ]);
        $body = curl_exec($ch);
        $curlError = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false || $body === '') {
            throw new Exception('Không kết nối được VietMap API. ' . ($curlError ?: 'Không có dữ liệu phản hồi.'));
        }
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 15,
                'header' => "Accept: application/json\r\nUser-Agent: QuanLyVatTuYTe/1.0\r\n"
            ]
        ]);
        $body = @file_get_contents($url, false, $context);
        $status = 200;
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
            $status = (int)$m[1];
        }
        if ($body === false || $body === '') {
            throw new Exception('Không kết nối được VietMap API bằng file_get_contents. Kiểm tra allow_url_fopen/cấu hình SSL/mạng máy chủ.');
        }
    }

    $json = json_decode($body, true);
    $isJson = !($json === null && json_last_error() !== JSON_ERROR_NONE);

    return [
        'status' => $status,
        'body' => (string)$body,
        'json' => $json,
        'is_json' => $isJson,
    ];
}

function vietmapErrorSnippet($body)
{
    $body = trim(strip_tags((string)$body));
    $body = preg_replace('/\s+/u', ' ', $body);
    if ($body === '') {
        return 'Không có nội dung phản hồi.';
    }
    if (function_exists('mb_substr')) {
        return mb_substr($body, 0, 220, 'UTF-8');
    }
    return substr($body, 0, 220);
}

function vietmapFirstAvailableJson(array $requests)
{
    $errors = [];

    foreach ($requests as $request) {
        $label = $request['label'] ?? 'VietMap API';
        $url = $request['url'] ?? '';
        if ($url === '') {
            continue;
        }

        try {
            $result = vietmapHttpGetPayload($url);
            $status = (int)$result['status'];

            if ($status >= 200 && $status < 300 && $result['is_json']) {
                return $result['json'];
            }

            if (!$result['is_json']) {
                $errors[] = $label . ' trả về HTTP ' . $status . ' nhưng không phải JSON: ' . vietmapErrorSnippet($result['body']);
            } else {
                $json = $result['json'];
                $message = is_array($json) ? ($json['message'] ?? $json['error'] ?? json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) : vietmapErrorSnippet($result['body']);
                $errors[] = $label . ' lỗi HTTP ' . $status . ': ' . $message;
            }
        } catch (Exception $e) {
            $errors[] = $label . ': ' . $e->getMessage();
        }
    }

    throw new Exception(implode(' | ', $errors));
}

if (isset($_GET['vietmap_proxy'])) {
    try {
        if ($vietmapApiKey === '') {
            throw new Exception('Chưa cấu hình VIETMAP_API_KEY trong config/database.php.');
        }

        $proxyAction = trim((string)$_GET['vietmap_proxy']);

        if ($proxyAction === 'autocomplete') {
            $text = trim((string)($_GET['text'] ?? ''));
            if (mb_strlen($text, 'UTF-8') < 2) {
                vietmapProxyResponse(['success' => true, 'data' => []]);
            }

            $queryV4 = http_build_query([
                'apikey' => $vietmapApiKey,
                'text' => $text,
                'focus' => '16.0471,106.6297',
                'display_type' => 5,
                'layers' => 'POI,ADDRESS,VILLAGE,WARD,DIST,CITY,STREET',
            ]);

            $queryV3 = http_build_query([
                'apikey' => $vietmapApiKey,
                'text' => $text,
                'focus' => '16.0471,106.6297',
                'layers' => 'POI,ADDRESS,VILLAGE,WARD,DIST,CITY,STREET',
            ]);

            // Một số API key VietMap chỉ được mở Autocomplete v3. Nếu v4 trả HTTP 423,
            // hệ thống sẽ tự fallback sang v3 để đúng với tài liệu bạn đang dùng.
            $data = vietmapFirstAvailableJson([
                ['label' => 'Autocomplete v4', 'url' => 'https://maps.vietmap.vn/api/autocomplete/v4?' . $queryV4],
                ['label' => 'Autocomplete v3', 'url' => 'https://maps.vietmap.vn/api/autocomplete/v3?' . $queryV3],
            ]);
            vietmapProxyResponse(['success' => true, 'data' => is_array($data) ? $data : []]);
        }

        if ($proxyAction === 'place') {
            $refId = trim((string)($_GET['refid'] ?? ''));
            if ($refId === '') {
                throw new Exception('Thiếu refid để gọi VietMap Place API.');
            }

            $query = http_build_query([
                'apikey' => $vietmapApiKey,
                'refid' => $refId,
            ]);

            $preferV4First = str_starts_with($refId, 'auto:');
            $requests = $preferV4First
                ? [
                    ['label' => 'Place v4', 'url' => 'https://maps.vietmap.vn/api/place/v4?' . $query],
                    ['label' => 'Place v3', 'url' => 'https://maps.vietmap.vn/api/place/v3?' . $query],
                ]
                : [
                    ['label' => 'Place v3', 'url' => 'https://maps.vietmap.vn/api/place/v3?' . $query],
                    ['label' => 'Place v4', 'url' => 'https://maps.vietmap.vn/api/place/v4?' . $query],
                ];

            $data = vietmapFirstAvailableJson($requests);
            vietmapProxyResponse(['success' => true, 'data' => $data]);
        }

        throw new Exception('vietmap_proxy không hợp lệ.');
    } catch (Exception $e) {
        vietmapProxyResponse([
            'success' => false,
            'message' => $e->getMessage(),
        ], 500);
    }
}

include $_SERVER['DOCUMENT_ROOT'] . '/quan_ly_vat_tu/includes/header.php';
include $_SERVER['DOCUMENT_ROOT'] . '/quan_ly_vat_tu/includes/sidebar.php';
?>


<link href="https://unpkg.com/@vietmap/vietmap-gl-js@6.0.1/dist/vietmap-gl.css" rel="stylesheet">
<script src="https://unpkg.com/@vietmap/vietmap-gl-js@6.0.1/dist/vietmap-gl.js"></script>

<style>
    .locations-page {
        animation: locationFadeIn .25s ease;
    }

    @keyframes locationFadeIn {
        from {
            opacity: 0;
            transform: translateY(6px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .locations-toolbar {
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

    .locations-toolbar h1 {
        font-size: 28px;
        line-height: 1.25;
        margin: 0 0 8px;
        font-weight: 700;
    }

    .locations-toolbar p {
        margin: 0;
        font-size: 14px;
        opacity: .9;
    }

    .locations-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    .location-btn {
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
    }

    .location-btn.primary {
        background: white;
        color: var(--primary-color);
    }

    .location-btn.primary:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow);
    }

    .location-btn.solid {
        background: var(--primary-color);
        color: white;
    }

    .location-btn.solid:hover {
        background: var(--secondary-color);
        color: white;
    }

    .location-btn.light {
        background: white;
        color: var(--text-dark);
        border: 1px solid var(--border-color);
    }

    .location-btn.light:hover {
        background: var(--light-bg);
        color: var(--primary-color);
    }

    .location-btn.danger {
        background: rgba(245, 101, 101, .12);
        color: var(--danger-color);
    }

    .location-btn.danger:hover {
        background: var(--danger-color);
        color: white;
    }

    .location-btn.info {
        background: rgba(102, 126, 234, .12);
        color: var(--primary-color);
    }

    .location-btn.info:hover {
        background: var(--primary-color);
        color: white;
    }

    .location-btn.sm {
        padding: 7px 11px;
        font-size: 12px;
    }

    .locations-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .locations-stat-card {
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

    .locations-stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
    }

    .locations-stat-card.success::before {
        background: var(--success-color);
    }

    .locations-stat-card.warning::before {
        background: var(--warning-color);
    }

    .locations-stat-card.danger::before {
        background: var(--danger-color);
    }

    .locations-stat-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--shadow-lg);
        border-color: var(--primary-color);
    }

    .locations-stat-icon {
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

    .locations-stat-card.success .locations-stat-icon {
        background: rgba(72, 187, 120, .15);
        color: var(--success-color);
    }

    .locations-stat-card.warning .locations-stat-icon {
        background: rgba(237, 137, 54, .15);
        color: var(--warning-color);
    }

    .locations-stat-card.danger .locations-stat-icon {
        background: rgba(245, 101, 101, .15);
        color: var(--danger-color);
    }

    .locations-stat-content h3 {
        margin: 0 0 7px;
        color: var(--text-light);
        font-size: 12px;
        text-transform: uppercase;
        font-weight: 600;
        letter-spacing: .5px;
    }

    .locations-stat-content .value {
        color: var(--text-dark);
        font-size: 24px;
        font-weight: 700;
        line-height: 1;
    }

    .locations-stat-content .hint {
        margin-top: 7px;
        color: var(--text-light);
        font-size: 12px;
    }

    .locations-grid {
        display: grid;
        grid-template-columns: minmax(320px, .85fr) 1.15fr;
        gap: 24px;
        margin-bottom: 30px;
    }

    .locations-card {
        background: white;
        border-radius: 8px;
        box-shadow: var(--shadow);
        margin-bottom: 30px;
        overflow: hidden;
    }

    .locations-card-header {
        padding: 20px 24px;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        flex-wrap: wrap;
    }

    .locations-card-header h2 {
        margin: 0;
        font-size: 18px;
        font-weight: 600;
        color: var(--text-dark);
    }

    .locations-card-header p {
        margin: 5px 0 0;
        font-size: 13px;
        color: var(--text-light);
    }

    .locations-card-body {
        padding: 24px;
    }

    .locations-form-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 18px;
    }

    .locations-form-grid.one {
        grid-template-columns: 1fr;
    }

    .locations-field label {
        display: block;
        margin-bottom: 7px;
        color: var(--text-dark);
        font-size: 13px;
        font-weight: 600;
    }

    .locations-field label.required::after {
        content: ' *';
        color: var(--danger-color);
    }

    .locations-input,
    .locations-select {
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

    .locations-input:focus,
    .locations-select:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(102, 126, 234, .12);
    }

    .location-form-note {
        color: var(--text-light);
        font-size: 12px;
        margin-top: 8px;
        line-height: 1.5;
    }

    .locations-filter {
        display: grid;
        grid-template-columns: 1.4fr 1fr auto auto;
        gap: 12px;
        align-items: end;
    }

    .location-avatar {
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
        box-shadow: inset 0 0 0 1px rgba(255, 255, 255, .18);
    }

    .location-avatar.primary {
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    }

    .location-avatar.success {
        background: linear-gradient(135deg, var(--success-color), #38a169);
    }

    .location-avatar.warning {
        background: linear-gradient(135deg, var(--warning-color), #dd6b20);
    }

    .location-avatar.danger {
        background: linear-gradient(135deg, var(--danger-color), #c53030);
    }

    .location-avatar.info {
        background: linear-gradient(135deg, var(--info-color), #3182ce);
    }

    .province-list {
        display: grid;
        gap: 12px;
        max-height: 520px;
        overflow-y: auto;
        padding-right: 4px;
    }

    .province-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 14px;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        background: #fff;
        transition: all .3s;
    }

    .province-item:hover {
        background: var(--light-bg);
        border-color: var(--primary-color);
    }

    .province-main {
        display: flex;
        align-items: center;
        gap: 12px;
        min-width: 0;
    }

    .province-name {
        color: var(--text-dark);
        font-size: 14px;
        font-weight: 600;
        margin-bottom: 3px;
    }

    .location-sub {
        color: var(--text-light);
        font-size: 12px;
        line-height: 1.5;
    }

    .locations-table-wrap {
        overflow-x: auto;
    }

    .locations-table {
        width: 100%;
        border-collapse: collapse;
    }

    .locations-table th {
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

    .locations-table td {
        padding: 16px;
        border-bottom: 1px solid var(--border-color);
        color: var(--text-dark);
        vertical-align: middle;
        font-size: 14px;
    }

    .locations-table tr:hover td {
        background: var(--light-bg);
    }

    .location-cell {
        display: flex;
        align-items: center;
        gap: 12px;
        min-width: 260px;
    }

    .location-name {
        color: var(--text-dark);
        font-size: 14px;
        font-weight: 600;
        margin-bottom: 3px;
    }

    .locations-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 11px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        white-space: nowrap;
    }

    .locations-badge.primary {
        background: rgba(102, 126, 234, .12);
        color: var(--primary-color);
    }

    .locations-badge.success {
        background: rgba(72, 187, 120, .14);
        color: var(--success-color);
    }

    .locations-actions-inline {
        display: flex;
        justify-content: flex-end;
        align-items: center;
        gap: 8px;
    }

    .locations-alert {
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

    .locations-alert.success {
        background: rgba(72, 187, 120, .14);
        color: #276749;
        border-left: 4px solid var(--success-color);
    }

    .locations-alert.danger {
        background: rgba(245, 101, 101, .13);
        color: #9b2c2c;
        border-left: 4px solid var(--danger-color);
    }

    .locations-alert button {
        border: 0;
        background: transparent;
        color: inherit;
        font-size: 20px;
        cursor: pointer;
    }

    .locations-empty {
        text-align: center;
        padding: 50px 20px;
    }

    .locations-empty .icon {
        font-size: 48px;
        margin-bottom: 15px;
    }

    .locations-empty h3 {
        color: var(--text-dark);
        margin: 0 0 8px;
        font-size: 16px;
        font-weight: 600;
    }

    .locations-empty p {
        color: var(--text-light);
        margin: 0;
        font-size: 14px;
    }

    .locations-footer {
        border-top: 1px solid var(--border-color);
        padding: 18px 24px;
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        background: #fafafa;
    }

    @media (max-width: 1100px) {
        .locations-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 900px) {

        .locations-filter,
        .locations-form-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 768px) {
        .locations-toolbar {
            padding: 25px;
        }

        .locations-toolbar h1 {
            font-size: 24px;
        }

        .locations-actions,
        .location-btn {
            width: 100%;
        }

        .locations-actions-inline {
            justify-content: flex-start;
        }

        .province-item {
            align-items: flex-start;
            flex-direction: column;
        }
    }


    .vietmap-card {
        background: white;
        border-radius: 8px;
        box-shadow: var(--shadow);
        margin-bottom: 30px;
        overflow: hidden;
    }

    .vietmap-card-header {
        padding: 20px 24px;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        flex-wrap: wrap;
    }

    .vietmap-card-header h2 {
        margin: 0;
        font-size: 18px;
        font-weight: 600;
        color: var(--text-dark);
    }

    .vietmap-card-header p {
        margin: 5px 0 0;
        font-size: 13px;
        color: var(--text-light);
    }

    .vietmap-body {
        display: grid;
        grid-template-columns: minmax(0, 1.45fr) minmax(320px, .55fr);
        gap: 0;
        min-height: 520px;
    }

    .vietmap-canvas-wrap {
        position: relative;
        min-height: 520px;
        background: var(--light-bg);
    }

    #vietmapLocationMap {
        width: 100%;
        height: 100%;
        min-height: 520px;
    }

    .vietmap-panel {
        border-left: 1px solid var(--border-color);
        padding: 22px;
        background: #fff;
    }

    .vietmap-search-box {
        position: relative;
        margin-bottom: 16px;
    }

    .vietmap-search-box input {
        width: 100%;
        height: 44px;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        padding: 10px 13px;
        font-size: 14px;
        font-family: inherit;
        outline: none;
        background: white;
        color: var(--text-dark);
        transition: all .25s;
    }

    .vietmap-search-box input:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(102, 126, 234, .12);
    }

    .vietmap-suggestions {
        position: absolute;
        left: 0;
        right: 0;
        top: calc(100% + 6px);
        z-index: 20;
        background: white;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        box-shadow: var(--shadow-lg);
        overflow: hidden;
        display: none;
        max-height: 300px;
        overflow-y: auto;
    }

    .vietmap-suggestion {
        padding: 11px 13px;
        border-bottom: 1px solid var(--border-color);
        cursor: pointer;
        transition: background .2s;
    }

    .vietmap-suggestion:last-child {
        border-bottom: 0;
    }

    .vietmap-suggestion:hover {
        background: var(--light-bg);
    }

    .vietmap-suggestion-name {
        font-size: 13px;
        font-weight: 600;
        color: var(--text-dark);
        margin-bottom: 3px;
    }

    .vietmap-suggestion-address {
        font-size: 12px;
        color: var(--text-light);
        line-height: 1.45;
    }

    .vietmap-note {
        padding: 13px 14px;
        border-radius: 8px;
        background: rgba(102, 126, 234, .1);
        color: var(--text-dark);
        font-size: 12px;
        line-height: 1.6;
        margin-bottom: 16px;
        border-left: 4px solid var(--primary-color);
    }

    .vietmap-note.warning {
        background: rgba(237, 137, 54, .12);
        border-left-color: var(--warning-color);
    }

    .vietmap-place-card {
        border: 1px solid var(--border-color);
        border-radius: 8px;
        padding: 14px;
        margin-top: 14px;
        background: #fff;
    }

    .vietmap-place-card h3 {
        margin: 0 0 8px;
        font-size: 15px;
        color: var(--text-dark);
    }

    .vietmap-place-row {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        padding: 7px 0;
        border-bottom: 1px dashed var(--border-color);
        font-size: 12px;
    }

    .vietmap-place-row:last-child {
        border-bottom: 0;
    }

    .vietmap-place-row span:first-child {
        color: var(--text-light);
    }

    .vietmap-place-row span:last-child {
        color: var(--text-dark);
        font-weight: 600;
        text-align: right;
    }

    .vietmap-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        margin-top: 14px;
    }

    .vietmap-island-label {
        position: absolute;
        right: 18px;
        bottom: 18px;
        z-index: 5;
        display: grid;
        gap: 8px;
        pointer-events: none;
    }

    .vietmap-island-label span {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 7px 10px;
        border-radius: 999px;
        background: rgba(255, 255, 255, .92);
        color: var(--text-dark);
        border: 1px solid var(--border-color);
        font-size: 12px;
        font-weight: 700;
        box-shadow: var(--shadow);
    }

    :root[data-theme="dark"] .vietmap-card,
    :root[data-theme="dark"] .vietmap-panel,
    :root[data-theme="dark"] .vietmap-suggestions,
    :root[data-theme="dark"] .vietmap-place-card {
        background: #111827 !important;
        border-color: #263244 !important;
    }

    :root[data-theme="dark"] .vietmap-island-label span {
        background: rgba(17, 24, 39, .92);
        border-color: #263244;
    }
</style>

<div class="locations-page">
    <div class="locations-toolbar">
        <div>
            <h1><?= $action === 'edit_tinh' ? 'Cập nhật tỉnh/thành phố' : ($action === 'edit_xaphuong' ? 'Cập nhật phường/xã' : 'Quản lý địa điểm') ?></h1>
            <p><?= ($action === 'edit_tinh' || $action === 'edit_xaphuong') ? 'Chỉnh sửa dữ liệu địa giới hành chính dùng cho khách hàng, nhà cung cấp và giao dịch.' : 'Quản lý tỉnh/thành phố, phường/xã và dữ liệu địa chỉ dùng trong toàn hệ thống.' ?></p>
        </div>
        <div class="locations-actions">
            <?php if ($action !== 'list'): ?>
                <a href="<?= e(getBaseUrl()) ?>/modules/admin/locations.php" class="location-btn primary">← Quay lại danh sách</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="locations-alert success">
            <span>✅ <?= e($success) ?></span>
            <button type="button" onclick="this.parentElement.remove()">×</button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="locations-alert danger">
            <span>❌ <?= e($error) ?></span>
            <button type="button" onclick="this.parentElement.remove()">×</button>
        </div>
    <?php endif; ?>

    <?php if ($action === 'edit_tinh' && $editProvince): ?>
        <div class="locations-card">
            <div class="locations-card-header">
                <div>
                    <h2>Thông tin tỉnh/thành phố</h2>
                    <p>Mã tỉnh/thành phố là khóa chính nên không chỉnh sửa trực tiếp.</p>
                </div>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="edit_tinh">
                <input type="hidden" name="ma_tinh" value="<?= e($editProvince['MaTinh']) ?>">
                <div class="locations-card-body">
                    <div class="locations-form-grid">
                        <div class="locations-field">
                            <label>Mã tỉnh/thành phố</label>
                            <input class="locations-input" type="text" value="<?= e($editProvince['MaTinh']) ?>" disabled>
                        </div>
                        <div class="locations-field">
                            <label for="ten_tinh" class="required">Tên tỉnh/thành phố</label>
                            <input class="locations-input" type="text" id="ten_tinh" name="ten_tinh" value="<?= e($editProvince['TenTinh']) ?>" required>
                        </div>
                    </div>
                </div>
                <div class="locations-footer">
                    <button type="submit" class="location-btn solid">💾 Lưu thay đổi</button>
                    <a href="<?= e(getBaseUrl()) ?>/modules/admin/locations.php" class="location-btn light">Hủy</a>
                </div>
            </form>
        </div>
    <?php elseif ($action === 'edit_xaphuong' && $editWard): ?>
        <div class="locations-card">
            <div class="locations-card-header">
                <div>
                    <h2>Thông tin phường/xã</h2>
                    <p>Cập nhật tên phường/xã và tỉnh/thành phố trực thuộc.</p>
                </div>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="edit_xaphuong">
                <input type="hidden" name="ma_xp" value="<?= e($editWard['MaXP']) ?>">
                <div class="locations-card-body">
                    <div class="locations-form-grid">
                        <div class="locations-field">
                            <label>Mã phường/xã</label>
                            <input class="locations-input" type="text" value="<?= e($editWard['MaXP']) ?>" disabled>
                        </div>
                        <div class="locations-field">
                            <label for="ten_xp" class="required">Tên phường/xã</label>
                            <input class="locations-input" type="text" id="ten_xp" name="ten_xp" value="<?= e($editWard['TenXP']) ?>" required>
                        </div>
                    </div>
                    <div class="locations-form-grid one" style="margin-top:18px">
                        <div class="locations-field">
                            <label for="ma_tinh_xp" class="required">Tỉnh/Thành phố</label>
                            <select class="locations-select" id="ma_tinh_xp" name="ma_tinh_xp" required>
                                <option value="">-- Chọn tỉnh/thành phố --</option>
                                <?php foreach ($provinces as $prov): ?>
                                    <option value="<?= e($prov['MaTinh']) ?>" <?= $editWard['MaTinh'] == $prov['MaTinh'] ? 'selected' : '' ?>><?= e($prov['TenTinh']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="locations-footer">
                    <button type="submit" class="location-btn solid">💾 Lưu thay đổi</button>
                    <a href="<?= e(getBaseUrl()) ?>/modules/admin/locations.php" class="location-btn light">Hủy</a>
                </div>
            </form>
        </div>
    <?php else: ?>
        <div class="locations-stats">
            <div class="locations-stat-card">
                <div class="locations-stat-icon">🏙️</div>
                <div class="locations-stat-content">
                    <h3>Tổng tỉnh/thành</h3>
                    <div class="value"><?= number_format((int)$stats['province_total']) ?></div>
                    <div class="hint">Đơn vị cấp tỉnh</div>
                </div>
            </div>
            <div class="locations-stat-card success">
                <div class="locations-stat-icon">📍</div>
                <div class="locations-stat-content">
                    <h3>Tổng phường/xã</h3>
                    <div class="value"><?= number_format((int)$stats['ward_total']) ?></div>
                    <div class="hint">Toàn bộ dữ liệu địa điểm</div>
                </div>
            </div>
            <div class="locations-stat-card warning">
                <div class="locations-stat-icon">🔎</div>
                <div class="locations-stat-content">
                    <h3>Đang hiển thị</h3>
                    <div class="value"><?= number_format((int)$stats['ward_filtered']) ?></div>
                    <div class="hint">Theo bộ lọc hiện tại</div>
                </div>
            </div>
            <div class="locations-stat-card danger">
                <div class="locations-stat-icon">🗺️</div>
                <div class="locations-stat-content">
                    <h3>Tỉnh có phường/xã</h3>
                    <div class="value"><?= number_format((int)$stats['province_with_wards']) ?></div>
                    <div class="hint">Đã có dữ liệu trực thuộc</div>
                </div>
            </div>
        </div>

        <div class="vietmap-card">
            <div class="vietmap-card-header">
                <div>
                    <h2>Bản đồ & tìm kiếm địa chỉ Việt Nam bằng VietMap</h2>
                    <p>Tra cứu địa chỉ bằng Autocomplete, xem vị trí trên bản đồ VietMap và lấy phường/xã, quận/huyện, tỉnh/thành theo dữ liệu API.</p>
                </div>
                <span class="locations-badge primary">🇻🇳 VietMap API</span>
            </div>
            <div class="vietmap-body">
                <div class="vietmap-canvas-wrap">
                    <div id="vietmapLocationMap"></div>
                    <div class="vietmap-island-label" aria-label="Chú thích chủ quyền biển đảo Việt Nam">
                        <span>🇻🇳 Hoàng Sa - Việt Nam</span>
                        <span>🇻🇳 Trường Sa - Việt Nam</span>
                    </div>
                </div>
                <div class="vietmap-panel">
                    <?php if ($vietmapApiKey === ''): ?>
                        <div class="vietmap-note warning">
                            Chưa cấu hình <strong>VIETMAP_API_KEY</strong>. Thêm key vào <code>config/database.php</code>:<br>
                            <code>define('VIETMAP_API_KEY', 'API_KEY_CUA_BAN');</code>
                        </div>
                    <?php else: ?>
                        <div class="vietmap-note">
                            Đang dùng VietMap GL JS để hiển thị bản đồ. Autocomplete/Place v4 được gọi qua PHP proxy nội bộ. Đây chỉ là demo vì API key miễn phí nên có giới hạn truy cập và tốc độ phản hồi.
                        </div>
                    <?php endif; ?>

                    <div class="vietmap-search-box">
                        <input type="text" id="vietmapAddressSearch" placeholder="Nhập địa chỉ, bệnh viện, phường/xã..." autocomplete="off">
                        <div class="vietmap-suggestions" id="vietmapSuggestions"></div>
                    </div>

                    <div class="vietmap-place-card" id="vietmapSelectedPlace">
                        <h3>Chưa chọn địa điểm</h3>
                        <div class="location-sub">Nhập từ khóa vào ô tìm kiếm, chọn một kết quả để xem tọa độ và địa giới hành chính.</div>
                    </div>

                    <div class="vietmap-actions">
                        <button type="button" class="location-btn solid" id="vietmapZoomVietnam">Zoom Việt Nam</button>
                        <button type="button" class="location-btn light" id="vietmapClearMarker">Xóa marker</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="locations-grid">
            <div>
                <div class="locations-card">
                    <div class="locations-card-header">
                        <div>
                            <h2>Thêm tỉnh/thành phố</h2>
                            <p>Tạo mới đơn vị hành chính cấp tỉnh.</p>
                        </div>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="action" value="add_tinh">
                        <div class="locations-card-body">
                            <div class="locations-form-grid one">
                                <div class="locations-field">
                                    <label for="ma_tinh" class="required">Mã tỉnh/thành phố</label>
                                    <input class="locations-input" type="text" id="ma_tinh" name="ma_tinh" placeholder="Ví dụ: 01" maxlength="10" required>
                                    <div class="location-form-note">Mã này dùng làm khóa liên kết cho phường/xã và dữ liệu địa chỉ.</div>
                                </div>
                                <div class="locations-field">
                                    <label for="ten_tinh" class="required">Tên tỉnh/thành phố</label>
                                    <input class="locations-input" type="text" id="ten_tinh" name="ten_tinh" placeholder="Ví dụ: Hà Nội" required>
                                </div>
                            </div>
                        </div>
                        <div class="locations-footer">
                            <button type="submit" class="location-btn solid">＋ Thêm tỉnh/thành phố</button>
                        </div>
                    </form>
                </div>

                <div class="locations-card">
                    <div class="locations-card-header">
                        <div>
                            <h2>Danh sách tỉnh/thành phố</h2>
                            <p>Theo dõi số phường/xã trực thuộc từng tỉnh.</p>
                        </div>
                    </div>
                    <div class="locations-card-body">
                        <?php if (!empty($provinces)): ?>
                            <div class="province-list">
                                <?php foreach ($provinces as $prov): ?>
                                    <div class="province-item">
                                        <div class="province-main">
                                            <div class="location-avatar <?= e(locationColorClass($prov['MaTinh'])) ?>"><?= e(initialsLocation($prov['TenTinh'])) ?></div>
                                            <div>
                                                <div class="province-name"><?= e($prov['TenTinh']) ?></div>
                                                <div class="location-sub">Mã tỉnh: <?= e($prov['MaTinh']) ?> • <?= number_format((int)$prov['WardCount']) ?> phường/xã</div>
                                            </div>
                                        </div>
                                        <div class="locations-actions-inline">
                                            <a class="location-btn info sm" href="?action=edit_tinh&id=<?= urlencode($prov['MaTinh']) ?>">Sửa</a>
                                            <form method="POST" style="display:inline" onsubmit="return confirm('Bạn chắc chắn muốn xóa tỉnh/thành phố này?');">
                                                <input type="hidden" name="action" value="delete_tinh">
                                                <input type="hidden" name="ma_tinh" value="<?= e($prov['MaTinh']) ?>">
                                                <button type="submit" class="location-btn danger sm">Xóa</button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="locations-empty">
                                <div class="icon">🏙️</div>
                                <h3>Chưa có tỉnh/thành phố</h3>
                                <p>Thêm tỉnh/thành phố đầu tiên để bắt đầu quản lý địa điểm.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div>
                <div class="locations-card">
                    <div class="locations-card-header">
                        <div>
                            <h2>Thêm phường/xã</h2>
                            <p>Tạo mới phường/xã và liên kết với tỉnh/thành phố.</p>
                        </div>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="action" value="add_xaphuong">
                        <div class="locations-card-body">
                            <div class="locations-form-grid">
                                <div class="locations-field">
                                    <label for="ma_xp" class="required">Mã phường/xã</label>
                                    <input class="locations-input" type="text" id="ma_xp" name="ma_xp" placeholder="Ví dụ: 01001" maxlength="20" required>
                                </div>
                                <div class="locations-field">
                                    <label for="ten_xp" class="required">Tên phường/xã</label>
                                    <input class="locations-input" type="text" id="ten_xp" name="ten_xp" placeholder="Ví dụ: Phường Hoàn Kiếm" required>
                                </div>
                            </div>
                            <div class="locations-form-grid one" style="margin-top:18px">
                                <div class="locations-field">
                                    <label for="ma_tinh_xp" class="required">Tỉnh/Thành phố</label>
                                    <select class="locations-select" id="ma_tinh_xp" name="ma_tinh_xp" required>
                                        <option value="">-- Chọn tỉnh/thành phố --</option>
                                        <?php foreach ($provinces as $prov): ?>
                                            <option value="<?= e($prov['MaTinh']) ?>"><?= e($prov['TenTinh']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="locations-footer">
                            <button type="submit" class="location-btn solid">＋ Thêm phường/xã</button>
                        </div>
                    </form>
                </div>

                <div class="locations-card">
                    <div class="locations-card-header">
                        <div>
                            <h2>Danh sách phường/xã</h2>
                            <p>Lọc nhanh theo tên, mã hoặc tỉnh/thành phố.</p>
                        </div>
                    </div>
                    <div class="locations-card-body">
                        <form method="GET" class="locations-filter">
                            <input type="hidden" name="action" value="list">
                            <div class="locations-field">
                                <label>Tìm kiếm</label>
                                <input class="locations-input" type="text" name="search" value="<?= e($searchQuery) ?>" placeholder="Tìm mã, phường/xã hoặc tỉnh...">
                            </div>
                            <div class="locations-field">
                                <label>Tỉnh/Thành phố</label>
                                <select class="locations-select" name="province">
                                    <option value="">Tất cả tỉnh/thành</option>
                                    <?php foreach ($provinces as $prov): ?>
                                        <option value="<?= e($prov['MaTinh']) ?>" <?= $filterProvince === $prov['MaTinh'] ? 'selected' : '' ?>><?= e($prov['TenTinh']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" class="location-btn solid">🔎 Lọc</button>
                            <a href="<?= e(getBaseUrl()) ?>/modules/admin/locations.php" class="location-btn light">Đặt lại</a>
                        </form>
                    </div>

                    <?php if (!empty($wards)): ?>
                        <div class="locations-table-wrap">
                            <table class="locations-table">
                                <thead>
                                    <tr>
                                        <th>Phường/Xã</th>
                                        <th>Tỉnh/Thành phố</th>
                                        <th style="text-align:right">Hành động</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($wards as $ward): ?>
                                        <tr>
                                            <td>
                                                <div class="location-cell">
                                                    <div class="location-avatar <?= e(locationColorClass($ward['MaXP'])) ?>"><?= e(initialsLocation($ward['TenXP'])) ?></div>
                                                    <div>
                                                        <div class="location-name"><?= e($ward['TenXP']) ?></div>
                                                        <div class="location-sub">Mã phường/xã: <?= e($ward['MaXP']) ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="locations-badge primary">🏙️ <?= e($ward['TenTinh']) ?></span>
                                                <div class="location-sub" style="margin-top:6px">Mã tỉnh: <?= e($ward['MaTinh']) ?></div>
                                            </td>
                                            <td>
                                                <div class="locations-actions-inline">
                                                    <a class="location-btn info sm" href="?action=edit_xaphuong&id=<?= urlencode($ward['MaXP']) ?>">Sửa</a>
                                                    <form method="POST" style="display:inline" onsubmit="return confirm('Bạn chắc chắn muốn xóa phường/xã này?');">
                                                        <input type="hidden" name="action" value="delete_xaphuong">
                                                        <input type="hidden" name="ma_xp" value="<?= e($ward['MaXP']) ?>">
                                                        <button type="submit" class="location-btn danger sm">Xóa</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="locations-empty">
                            <div class="icon">📍</div>
                            <h3>Không tìm thấy phường/xã</h3>
                            <p>Thử thay đổi bộ lọc hoặc thêm phường/xã mới.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>


<script>
    const VIETMAP_API_KEY = <?= json_encode($vietmapApiKey) ?>;
    let vietmapMap = null;
    let vietmapMarker = null;
    let vietmapDebounce = null;

    function escapeHtmlClient(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function initVietmapLocationMap() {
        const mapEl = document.getElementById('vietmapLocationMap');
        if (!mapEl || !window.vietmapgl) return;

        if (!VIETMAP_API_KEY) {
            mapEl.innerHTML = '<div style="display:flex;height:100%;align-items:center;justify-content:center;padding:24px;text-align:center;color:var(--text-light)">Chưa có VIETMAP_API_KEY nên bản đồ chưa thể tải.</div>';
            return;
        }

        vietmapMap = new vietmapgl.Map({
            container: 'vietmapLocationMap',
            style: 'https://maps.vietmap.vn/maps/styles/tm/style.json?apikey=' + encodeURIComponent(VIETMAP_API_KEY),
            center: [106.6297, 16.0471],
            zoom: 4.7,
            maxZoom: 18,
            minZoom: 4,
            attributionControl: true,
            vietmapLogo: true
        });

        vietmapMap.addControl(new vietmapgl.NavigationControl(), 'top-right');
        vietmapMap.addControl(new vietmapgl.ScaleControl({
            unit: 'metric'
        }), 'bottom-left');
    }

    function renderVietmapSuggestions(items) {
        const box = document.getElementById('vietmapSuggestions');
        if (!box) return;

        if (!items || !items.length) {
            box.style.display = 'none';
            box.innerHTML = '';
            return;
        }

        box.innerHTML = items.map(item => `
            <div class="vietmap-suggestion" data-ref-id="${escapeHtmlClient(item.ref_id)}">
                <div class="vietmap-suggestion-name">${escapeHtmlClient(item.name || item.display)}</div>
                <div class="vietmap-suggestion-address">${escapeHtmlClient(item.address || item.display || '')}</div>
            </div>
        `).join('');
        box.style.display = 'block';

        box.querySelectorAll('.vietmap-suggestion').forEach((node, idx) => {
            node.addEventListener('click', function() {
                selectVietmapPlace(items[idx]);
                box.style.display = 'none';
            });
        });
    }

    async function searchVietmapAutocomplete(text) {
        if (text.trim().length < 2) {
            renderVietmapSuggestions([]);
            return;
        }

        const url = window.location.pathname + '?vietmap_proxy=autocomplete&text=' + encodeURIComponent(text.trim());

        try {
            const response = await fetch(url, {
                headers: {
                    'Accept': 'application/json'
                },
                credentials: 'same-origin'
            });
            const payload = await response.json().catch(() => null);
            if (!response.ok || !payload || payload.success !== true) {
                throw new Error((payload && payload.message) ? payload.message : 'Autocomplete proxy failed');
            }
            renderVietmapSuggestions(Array.isArray(payload.data) ? payload.data : []);
        } catch (err) {
            console.error('VietMap Autocomplete error:', err);
            const box = document.getElementById('vietmapSuggestions');
            if (box) {
                box.innerHTML = '<div class="vietmap-suggestion"><div class="vietmap-suggestion-name">Không gọi được VietMap Autocomplete</div><div class="vietmap-suggestion-address">' + escapeHtmlClient(err.message || 'Kiểm tra API key, domain restriction hoặc kết nối mạng máy chủ.') + '</div></div>';
                box.style.display = 'block';
            }
        }
    }

    async function selectVietmapPlace(item) {
        const placeBox = document.getElementById('vietmapSelectedPlace');
        const input = document.getElementById('vietmapAddressSearch');
        if (input) input.value = item.display || item.name || '';
        if (!placeBox) return;

        placeBox.innerHTML = '<h3>Đang lấy chi tiết...</h3><div class="location-sub">Vui lòng đợi VietMap Place API phản hồi.</div>';

        if (!item.ref_id) {
            placeBox.innerHTML = '<h3>Không có ref_id</h3><div class="location-sub">Kết quả này không đủ dữ liệu để lấy tọa độ.</div>';
            return;
        }

        try {
            const url = window.location.pathname + '?vietmap_proxy=place&refid=' + encodeURIComponent(item.ref_id);
            const response = await fetch(url, {
                headers: {
                    'Accept': 'application/json'
                },
                credentials: 'same-origin'
            });
            const payload = await response.json().catch(() => null);
            if (!response.ok || !payload || payload.success !== true) {
                throw new Error((payload && payload.message) ? payload.message : 'Place proxy failed');
            }
            const place = payload.data || {};

            const lat = Number(place.lat);
            const lng = Number(place.lng);
            const hasCoord = Number.isFinite(lat) && Number.isFinite(lng);

            placeBox.innerHTML = `
                <h3>${escapeHtmlClient(place.name || item.name || 'Địa điểm')}</h3>
                <div class="vietmap-place-row"><span>Địa chỉ</span><span>${escapeHtmlClient(place.display || item.display || '')}</span></div>
                <div class="vietmap-place-row"><span>Tỉnh/Thành</span><span>${escapeHtmlClient(place.city || '')}</span></div>
                <div class="vietmap-place-row"><span>Quận/Huyện</span><span>${escapeHtmlClient(place.district || '')}</span></div>
                <div class="vietmap-place-row"><span>Phường/Xã</span><span>${escapeHtmlClient(place.ward || '')}</span></div>
                <div class="vietmap-place-row"><span>Tọa độ</span><span>${hasCoord ? lat.toFixed(6) + ', ' + lng.toFixed(6) : 'Không có'}</span></div>
            `;

            if (hasCoord && vietmapMap) {
                const lngLat = [lng, lat];
                if (vietmapMarker) vietmapMarker.remove();
                vietmapMarker = new vietmapgl.Marker({
                    color: '#3e97ff'
                }).setLngLat(lngLat).addTo(vietmapMap);
                new vietmapgl.Popup({
                        offset: 24
                    })
                    .setLngLat(lngLat)
                    .setHTML('<strong>' + escapeHtmlClient(place.name || item.name || 'Địa điểm') + '</strong><br>' + escapeHtmlClient(place.display || item.display || ''))
                    .addTo(vietmapMap);
                vietmapMap.flyTo({
                    center: lngLat,
                    zoom: 15,
                    speed: 1.2
                });
            }
        } catch (err) {
            placeBox.innerHTML = '<h3>Lỗi lấy chi tiết</h3><div class="location-sub">Không gọi được VietMap Place API. Kiểm tra API key/domain hoặc thử lại.</div>';
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        initVietmapLocationMap();

        const input = document.getElementById('vietmapAddressSearch');
        if (input) {
            input.addEventListener('input', function() {
                clearTimeout(vietmapDebounce);
                vietmapDebounce = setTimeout(() => searchVietmapAutocomplete(input.value), 350);
            });
        }

        const zoomBtn = document.getElementById('vietmapZoomVietnam');
        if (zoomBtn) {
            zoomBtn.addEventListener('click', function() {
                if (vietmapMap) vietmapMap.flyTo({
                    center: [106.6297, 16.0471],
                    zoom: 4.7,
                    speed: 1.1
                });
            });
        }

        const clearBtn = document.getElementById('vietmapClearMarker');
        if (clearBtn) {
            clearBtn.addEventListener('click', function() {
                if (vietmapMarker) {
                    vietmapMarker.remove();
                    vietmapMarker = null;
                }
                const placeBox = document.getElementById('vietmapSelectedPlace');
                if (placeBox) placeBox.innerHTML = '<h3>Chưa chọn địa điểm</h3><div class="location-sub">Nhập từ khóa vào ô tìm kiếm, chọn một kết quả để xem tọa độ và địa giới hành chính.</div>';
            });
        }

        document.addEventListener('click', function(event) {
            const box = document.getElementById('vietmapSuggestions');
            const search = document.getElementById('vietmapAddressSearch');
            if (box && search && !box.contains(event.target) && event.target !== search) {
                box.style.display = 'none';
            }
        });
    });
</script>

<script>
    document.querySelectorAll('.locations-alert').forEach(function(alert) {
        setTimeout(function() {
            if (alert && alert.parentElement) alert.remove();
        }, 5000);
    });
</script>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/quan_ly_vat_tu/includes/footer.php'; ?>