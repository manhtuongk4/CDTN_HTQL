<?php
$pageTitle = 'Xuất kho - Hệ thống quản lý vật tư y tế';
require_once $_SERVER['DOCUMENT_ROOT'] . '/quan_ly_vat_tu/config/database.php';
session_start();
requireLogin();

$success = '';
$error = '';
$action = $_GET['action'] ?? 'list';
$searchQuery = trim($_GET['search'] ?? '');
$filterStatus = $_GET['status'] ?? '';
$filterCustomer = $_GET['customer'] ?? '';


function e($value)
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function moneyVnd($value)
{
    return number_format((float)$value, 0, ',', '.') . ' ₫';
}

function orderStatusBadge($date)
{
    return $date ? '<span class="orders-badge success">✅ Đã giao</span>' : '<span class="orders-badge warning">⏳ Chờ giao</span>';
}

function dateVNShort($date)
{
    if (!$date) {
        return '';
    }

    $timestamp = strtotime((string)$date);
    return $timestamp ? date('d/m/Y', $timestamp) : '';
}

function productOptionLabel(array $product)
{
    $stock = (int)($product['SoLuongConHan'] ?? 0);
    $nearestExpiry = dateVNShort($product['HanGanNhat'] ?? '');
    $stockText = $stock > 0
        ? ' — còn ' . number_format($stock) . ($nearestExpiry ? ' • HSD gần nhất ' . $nearestExpiry : '')
        : ' — hết hàng còn hạn';

    return ($product['TenSP'] ?? '') . $stockText;
}


function ensureStockMovementTable(PDO $pdo)
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS stock_movements (
            ID INT AUTO_INCREMENT PRIMARY KEY,
            OrderType VARCHAR(10) NOT NULL,
            MaDon VARCHAR(50) NOT NULL,
            MaSP VARCHAR(50) NOT NULL,
            MaLo VARCHAR(100) NOT NULL,
            SoLuong INT NOT NULL,
            MovementType VARCHAR(10) NOT NULL,
            CreatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_order (OrderType, MaDon),
            INDEX idx_lot (MaLo, MaSP)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function isValidDateValue($date)
{
    $dt = DateTime::createFromFormat('Y-m-d', (string)$date);
    return $dt && $dt->format('Y-m-d') === $date;
}

function requirePositiveInt($value, $fieldName)
{
    $value = trim((string)$value);
    if (!preg_match('/^[1-9][0-9]*$/', $value)) {
        throw new Exception($fieldName . ' phải là số nguyên lớn hơn 0.');
    }
    return (int)$value;
}

function requirePositiveMoney($value, $fieldName)
{
    $value = trim((string)$value);
    if ($value === '' || !is_numeric($value) || (float)$value <= 0) {
        throw new Exception($fieldName . ' phải lớn hơn 0.');
    }
    return (float)$value;
}

function recordExists(PDO $pdo, $sql, array $params)
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (bool)$stmt->fetchColumn();
}

function assertAdminCanCancel()
{
    if (function_exists('isAdmin') && !isAdmin()) {
        throw new Exception('Chỉ quản trị viên mới được hủy đơn.');
    }
}

function productLotSuffix($maSP)
{
    $maSP = trim((string)$maSP);

    if (preg_match('/(\d+)$/', $maSP, $matches)) {
        return str_pad($matches[1], 2, '0', STR_PAD_LEFT);
    }

    $clean = preg_replace('/[^A-Za-z0-9]/', '', $maSP);
    $clean = strtoupper($clean ?: 'SP');
    return substr($clean, -4);
}

function generateRecoveryLotCode(PDO $pdo, $maSP)
{
    $baseCode = 'R' . date('ym') . '_' . productLotSuffix($maSP);

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM khohang WHERE MaLo = ?');
    $stmt->execute([$baseCode]);
    if ((int)$stmt->fetchColumn() === 0) {
        return $baseCode;
    }

    for ($i = 2; $i <= 99; $i++) {
        $candidate = $baseCode . '_' . str_pad((string)$i, 2, '0', STR_PAD_LEFT);
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM khohang WHERE MaLo = ?');
        $stmt->execute([$candidate]);
        if ((int)$stmt->fetchColumn() === 0) {
            return $candidate;
        }
    }

    return $baseCode . '_' . strtoupper(bin2hex(random_bytes(2)));
}



// Xử lý tạo đơn xuất kho / xác nhận giao / hủy đơn
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    ensureStockMovementTable($pdo);

    if ($_POST['action'] === 'create_order') {
        try {
            $pdo->beginTransaction();

            $ngayDat = date('Y-m-d H:i:s');
            $today = date('Y-m-d');
            $maKH = trim($_POST['ma_kh'] ?? '');
            $maHopDongThau = trim($_POST['ma_hop_dong'] ?? '');

            if ($maKH === '') {
                throw new Exception('Vui lòng chọn khách hàng!');
            }

            $customerStmt = $pdo->prepare('SELECT MaKH, TenKH, DiaChi, MaXP FROM khachhang WHERE MaKH = ? LIMIT 1');
            $customerStmt->execute([$maKH]);
            $selectedCustomer = $customerStmt->fetch();

            if (!$selectedCustomer) {
                throw new Exception('Khách hàng không tồn tại hoặc đã bị xóa.');
            }

            // Địa chỉ/phường xã giao hàng được khóa theo hồ sơ khách hàng để tránh chọn nhầm địa điểm không liên quan.
            $dcGH = trim((string)($selectedCustomer['DiaChi'] ?? ''));
            $maXP = trim((string)($selectedCustomer['MaXP'] ?? ''));

            if (mb_strlen($dcGH, 'UTF-8') > 255) {
                throw new Exception('Địa chỉ giao hàng mặc định của khách hàng không được vượt quá 255 ký tự.');
            }

            if (mb_strlen($maHopDongThau, 'UTF-8') > 100) {
                throw new Exception('Mã hợp đồng thầu không được vượt quá 100 ký tự.');
            }

            $items = $_POST['items'] ?? [];
            if (!is_array($items) || empty($items)) {
                throw new Exception('Vui lòng thêm ít nhất 1 sản phẩm vào đơn xuất.');
            }

            $validItems = [];
            $requestedByProduct = [];

            foreach ($items as $index => $item) {
                $rowNo = (int)$index + 1;
                $maSP = trim($item['ma_sp'] ?? '');
                $qtyRaw = $item['sl_bh'] ?? '';
                $priceRaw = $item['dg_bh'] ?? '';

                if ($maSP === '' && trim((string)$qtyRaw) === '' && trim((string)$priceRaw) === '') {
                    continue;
                }

                if ($maSP === '') {
                    throw new Exception("Dòng #$rowNo: Vui lòng chọn sản phẩm.");
                }

                if (!recordExists($pdo, 'SELECT COUNT(*) FROM sanpham WHERE MaSP = ?', [$maSP])) {
                    throw new Exception("Dòng #$rowNo: Sản phẩm không tồn tại hoặc đã bị xóa.");
                }

                $slBH = requirePositiveInt($qtyRaw, "Dòng #$rowNo: Số lượng xuất");
                $dgBH = requirePositiveMoney($priceRaw, "Dòng #$rowNo: Đơn giá bán");

                $validItems[] = [
                    'ma_sp' => $maSP,
                    'so_luong' => $slBH,
                    'don_gia' => $dgBH
                ];

                $requestedByProduct[$maSP] = ($requestedByProduct[$maSP] ?? 0) + $slBH;
            }

            if (empty($validItems)) {
                throw new Exception('Vui lòng thêm ít nhất 1 sản phẩm hợp lệ vào đơn xuất.');
            }

            foreach ($requestedByProduct as $maSP => $requiredQty) {
                $stmt = $pdo->prepare('
                    SELECT COALESCE(SUM(SoLuongTon), 0)
                    FROM khohang
                    WHERE MaSP = ? AND SoLuongTon > 0 AND HanSuDung > CURDATE()
                ');
                $stmt->execute([$maSP]);
                $availableQty = (int)$stmt->fetchColumn();

                if ($availableQty < $requiredQty) {
                    $nameStmt = $pdo->prepare('SELECT TenSP FROM sanpham WHERE MaSP = ? LIMIT 1');
                    $nameStmt->execute([$maSP]);
                    $productName = $nameStmt->fetchColumn() ?: ('ID ' . $maSP);
                    throw new Exception('Không đủ tồn kho còn hạn cho sản phẩm "' . $productName . '". Cần ' . number_format($requiredQty) . ', hiện có ' . number_format($availableQty) . '.');
                }
            }

            $stmt = $pdo->query('SELECT MaDBH FROM donbh ORDER BY MaDBH DESC LIMIT 1');
            $lastCode = (string)($stmt->fetchColumn() ?: '');
            $nextNumber = ctype_digit($lastCode) ? ((int)$lastCode + 1) : 1;

            do {
                $maDBH = str_pad($nextNumber, 9, '0', STR_PAD_LEFT);
                $nextNumber++;
                $existsStmt = $pdo->prepare('SELECT COUNT(*) FROM donbh WHERE MaDBH = ?');
                $existsStmt->execute([$maDBH]);
            } while ((int)$existsStmt->fetchColumn() > 0);

            $stmt = $pdo->prepare('INSERT INTO donbh (MaDBH, NgayDat, DCGH, MaHopDongThau, MaXP, MaKH) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([$maDBH, $ngayDat, $dcGH, $maHopDongThau, $maXP !== '' ? $maXP : null, $maKH]);

            foreach ($validItems as $item) {
                $maSP = $item['ma_sp'];
                $slBH = $item['so_luong'];
                $dgBH = $item['don_gia'];

                $stmt = $pdo->prepare('INSERT INTO chitietbanhang (MaDBH, MaSP, SLBH, DGBH) VALUES (?, ?, ?, ?)');
                $stmt->execute([$maDBH, $maSP, $slBH, $dgBH]);

                $stmt = $pdo->prepare('
                    SELECT MaLo, SoLuongTon, HanSuDung
                    FROM khohang
                    WHERE MaSP = ? AND SoLuongTon > 0 AND HanSuDung > CURDATE()
                    ORDER BY HanSuDung ASC, NgaySanXuat ASC, MaLo ASC
                    FOR UPDATE
                ');
                $stmt->execute([$maSP]);
                $stockLots = $stmt->fetchAll();

                $remainingQty = $slBH;
                foreach ($stockLots as $lot) {
                    if ($remainingQty <= 0) {
                        break;
                    }

                    $qtyToTake = min($remainingQty, (int)$lot['SoLuongTon']);
                    if ($qtyToTake <= 0) {
                        continue;
                    }

                    $stmt = $pdo->prepare('UPDATE khohang SET SoLuongTon = SoLuongTon - ? WHERE MaLo = ? AND MaSP = ? AND SoLuongTon >= ?');
                    $stmt->execute([$qtyToTake, $lot['MaLo'], $maSP, $qtyToTake]);

                    if ($stmt->rowCount() !== 1) {
                        throw new Exception('Tồn kho lô ' . $lot['MaLo'] . ' vừa thay đổi. Vui lòng thử lại.');
                    }

                    $stmt = $pdo->prepare('INSERT INTO stock_movements (OrderType, MaDon, MaSP, MaLo, SoLuong, MovementType) VALUES (?, ?, ?, ?, ?, ?)');
                    $stmt->execute(['EXPORT', $maDBH, $maSP, $lot['MaLo'], $qtyToTake, 'OUT']);

                    $remainingQty -= $qtyToTake;
                }

                if ($remainingQty > 0) {
                    throw new Exception("Không đủ hàng trong kho cho sản phẩm ID: $maSP");
                }
            }

            $pdo->commit();
            $success = 'Tạo đơn xuất kho thành công! Mã đơn: ' . $maDBH;
            $action = 'list';
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = 'Lỗi: ' . $e->getMessage();
        }
    } elseif ($_POST['action'] === 'confirm_delivery') {
        try {
            $maDBH = trim($_POST['ma_dbh'] ?? '');
            $ngayGiao = trim($_POST['ngay_giao'] ?? date('Y-m-d'));

            if ($maDBH === '') {
                throw new Exception('Thiếu mã đơn xuất.');
            }

            if (!isValidDateValue($ngayGiao)) {
                throw new Exception('Ngày giao không hợp lệ.');
            }

            $stmt = $pdo->prepare('SELECT NgayDat, NgayGiao FROM donbh WHERE MaDBH = ? LIMIT 1');
            $stmt->execute([$maDBH]);
            $order = $stmt->fetch();

            if (!$order) {
                throw new Exception('Không tìm thấy đơn xuất cần xác nhận.');
            }

            if (!empty($order['NgayGiao'])) {
                throw new Exception('Đơn xuất này đã được xác nhận giao trước đó.');
            }

            if ($ngayGiao < date('Y-m-d', strtotime($order['NgayDat']))) {
                throw new Exception('Ngày giao không được trước ngày đặt đơn.');
            }

            if ($ngayGiao > date('Y-m-d')) {
                throw new Exception('Ngày giao không được lớn hơn ngày hiện tại.');
            }

            $stmt = $pdo->prepare('UPDATE donbh SET NgayGiao = ? WHERE MaDBH = ? AND NgayGiao IS NULL');
            $stmt->execute([$ngayGiao, $maDBH]);
            $success = 'Xác nhận giao hàng thành công!';
        } catch (Exception $e) {
            $error = 'Lỗi: ' . $e->getMessage();
        }
    } elseif ($_POST['action'] === 'cancel_order') {
        try {
            assertAdminCanCancel();
            $pdo->beginTransaction();

            $maDBH = trim($_POST['ma_dbh'] ?? '');
            if ($maDBH === '') {
                throw new Exception('Thiếu mã đơn xuất cần hủy.');
            }

            $stmt = $pdo->prepare('SELECT MaDBH, NgayGiao FROM donbh WHERE MaDBH = ? LIMIT 1 FOR UPDATE');
            $stmt->execute([$maDBH]);
            $order = $stmt->fetch();

            if (!$order) {
                throw new Exception('Không tìm thấy đơn xuất cần hủy.');
            }

            if (!empty($order['NgayGiao'])) {
                throw new Exception('Đơn xuất đã được xác nhận giao thành công nên không thể hủy.');
            }

            $stmt = $pdo->prepare('SELECT * FROM stock_movements WHERE OrderType = ? AND MaDon = ? AND MovementType = ? ORDER BY ID ASC');
            $stmt->execute(['EXPORT', $maDBH, 'OUT']);
            $movements = $stmt->fetchAll();

            $usedLegacyRollback = false;

            if (!empty($movements)) {
                foreach ($movements as $movement) {
                    $stmt = $pdo->prepare('SELECT COUNT(*) FROM khohang WHERE MaLo = ? AND MaSP = ? FOR UPDATE');
                    $stmt->execute([$movement['MaLo'], $movement['MaSP']]);
                    if ((int)$stmt->fetchColumn() === 0) {
                        throw new Exception('Không tìm thấy lô ' . $movement['MaLo'] . ' để hoàn kho đơn xuất.');
                    }
                }

                foreach ($movements as $movement) {
                    $stmt = $pdo->prepare('UPDATE khohang SET SoLuongTon = SoLuongTon + ? WHERE MaLo = ? AND MaSP = ?');
                    $stmt->execute([(int)$movement['SoLuong'], $movement['MaLo'], $movement['MaSP']]);
                }

                $stmt = $pdo->prepare('DELETE FROM stock_movements WHERE OrderType = ? AND MaDon = ?');
                $stmt->execute(['EXPORT', $maDBH]);
            } else {
                // Đơn cũ được tạo trước khi có stock_movements nên không biết chính xác lô nào đã bị trừ.
                // Hệ thống hoàn tồn ở mức sản phẩm: cộng lại vào lô hiện có gần hạn nhất; nếu không còn lô thì tạo lô phục hồi.
                $usedLegacyRollback = true;

                $stmt = $pdo->prepare('
                    SELECT MaSP, SUM(SLBH) AS SoLuongCanHoan
                    FROM chitietbanhang
                    WHERE MaDBH = ?
                    GROUP BY MaSP
                ');
                $stmt->execute([$maDBH]);
                $legacyItems = $stmt->fetchAll();

                if (empty($legacyItems)) {
                    throw new Exception('Đơn xuất không có chi tiết sản phẩm để hủy.');
                }

                foreach ($legacyItems as $item) {
                    $maSP = $item['MaSP'];
                    $qtyToReturn = (int)$item['SoLuongCanHoan'];

                    $stmt = $pdo->prepare('
                        SELECT MaLo
                        FROM khohang
                        WHERE MaSP = ?
                        ORDER BY HanSuDung ASC, NgaySanXuat ASC, MaLo ASC
                        LIMIT 1
                        FOR UPDATE
                    ');
                    $stmt->execute([$maSP]);
                    $maLo = $stmt->fetchColumn();

                    if ($maLo !== false && $maLo !== null && $maLo !== '') {
                        $stmt = $pdo->prepare('UPDATE khohang SET SoLuongTon = SoLuongTon + ? WHERE MaLo = ? AND MaSP = ?');
                        $stmt->execute([$qtyToReturn, $maLo, $maSP]);
                    } else {
                        $recoveryLot = generateRecoveryLotCode($pdo, $maSP);
                        $ngaySanXuat = date('Y-m-d');
                        $hanSuDung = date('Y-m-d', strtotime('+1 year'));

                        $stmt = $pdo->prepare('
                            INSERT INTO khohang (MaLo, MaSP, SoLuongTon, NgaySanXuat, HanSuDung)
                            VALUES (?, ?, ?, ?, ?)
                        ');
                        $stmt->execute([$recoveryLot, $maSP, $qtyToReturn, $ngaySanXuat, $hanSuDung]);
                    }
                }
            }

            $stmt = $pdo->prepare('DELETE FROM chitietbanhang WHERE MaDBH = ?');
            $stmt->execute([$maDBH]);

            $stmt = $pdo->prepare('DELETE FROM donbh WHERE MaDBH = ?');
            $stmt->execute([$maDBH]);

            $pdo->commit();
            $success = $usedLegacyRollback
                ? 'Đã hủy đơn xuất kho cũ và hoàn tồn kho theo chi tiết sản phẩm.'
                : 'Đã hủy đơn xuất kho và hoàn lại tồn kho thành công!';
            $action = 'list';
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = 'Lỗi: ' . $e->getMessage();
        }
    }
}

try {
    $stmt = $pdo->query('SELECT kh.*, xp.TenXP FROM khachhang kh LEFT JOIN xaphuong xp ON kh.MaXP = xp.MaXP ORDER BY kh.TenKH');
    $customers = $stmt->fetchAll();
} catch (PDOException $e) {
    $customers = [];
}
try {
    $stmt = $pdo->query('
        SELECT 
            sp.*,
            COALESCE(SUM(CASE 
                WHEN kh.SoLuongTon > 0 AND kh.HanSuDung > CURDATE() 
                THEN kh.SoLuongTon ELSE 0 
            END), 0) AS SoLuongConHan,
            COALESCE(SUM(CASE 
                WHEN kh.SoLuongTon > 0 
                THEN kh.SoLuongTon ELSE 0 
            END), 0) AS TongSoLuongTon,
            MIN(CASE 
                WHEN kh.SoLuongTon > 0 AND kh.HanSuDung > CURDATE() 
                THEN kh.HanSuDung ELSE NULL 
            END) AS HanGanNhat
        FROM sanpham sp
        LEFT JOIN khohang kh ON sp.MaSP = kh.MaSP
        GROUP BY sp.MaSP
        ORDER BY sp.TenSP
    ');
    $products = $stmt->fetchAll();
} catch (PDOException $e) {
    $products = [];
}
try {
    $stmt = $pdo->query('SELECT * FROM xaphuong ORDER BY TenXP');
    $wards = $stmt->fetchAll();
} catch (PDOException $e) {
    $wards = [];
}

$whereConditions = [];
$params = [];
if ($searchQuery !== '') {
    $whereConditions[] = '(db.MaDBH LIKE ? OR kh.TenKH LIKE ? OR db.MaHopDongThau LIKE ?)';
    $params[] = "%$searchQuery%";
    $params[] = "%$searchQuery%";
    $params[] = "%$searchQuery%";
}
if ($filterCustomer !== '') {
    $whereConditions[] = 'db.MaKH = ?';
    $params[] = $filterCustomer;
}
if ($filterStatus === 'delivered') {
    $whereConditions[] = 'db.NgayGiao IS NOT NULL';
} elseif ($filterStatus === 'pending') {
    $whereConditions[] = 'db.NgayGiao IS NULL';
}
$whereClause = $whereConditions ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

try {
    $stmt = $pdo->prepare("SELECT db.*, kh.TenKH,
            COUNT(ctb.MaSP) AS so_mat_hang,
            COALESCE(SUM(ctb.SLBH * ctb.DGBH), 0) AS tong_tien
        FROM donbh db
        JOIN khachhang kh ON db.MaKH = kh.MaKH
        LEFT JOIN chitietbanhang ctb ON db.MaDBH = ctb.MaDBH
        $whereClause
        GROUP BY db.MaDBH
        ORDER BY db.NgayDat DESC");
    $stmt->execute($params);
    $orders = $stmt->fetchAll();

    $statsStmt = $pdo->query("SELECT COUNT(*) AS total, SUM(CASE WHEN NgayGiao IS NULL THEN 1 ELSE 0 END) AS pending, SUM(CASE WHEN NgayGiao IS NOT NULL THEN 1 ELSE 0 END) AS delivered FROM donbh");
    $stats = $statsStmt->fetch();
    $valueStmt = $pdo->query('SELECT COALESCE(SUM(SLBH * DGBH), 0) AS total_value FROM chitietbanhang');
    $totalValue = (float)$valueStmt->fetch()['total_value'];
} catch (PDOException $e) {
    $error = 'Lỗi: ' . $e->getMessage();
    $orders = [];
    $stats = ['total' => 0, 'pending' => 0, 'delivered' => 0];
    $totalValue = 0;
}

$orderDetails = [];
$orderInfo = null;
if ($action === 'detail' && isset($_GET['id'])) {
    try {
        $stmt = $pdo->prepare('SELECT db.*, kh.TenKH FROM donbh db JOIN khachhang kh ON db.MaKH = kh.MaKH WHERE db.MaDBH = ?');
        $stmt->execute([$_GET['id']]);
        $orderInfo = $stmt->fetch();
        $stmt = $pdo->prepare('SELECT ctb.*, sp.TenSP FROM chitietbanhang ctb JOIN sanpham sp ON ctb.MaSP = sp.MaSP WHERE ctb.MaDBH = ?');
        $stmt->execute([$_GET['id']]);
        $orderDetails = $stmt->fetchAll();
    } catch (PDOException $e) {
        $error = 'Lỗi: ' . $e->getMessage();
    }
}

include $_SERVER['DOCUMENT_ROOT'] . '/quan_ly_vat_tu/includes/header.php';
include $_SERVER['DOCUMENT_ROOT'] . '/quan_ly_vat_tu/includes/sidebar.php';
?>


<style>
    .orders-page {
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

    .orders-toolbar {
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

    .orders-toolbar h1 {
        font-size: 28px;
        line-height: 1.25;
        margin: 0 0 8px;
        font-weight: 700;
    }

    .orders-toolbar p {
        margin: 0;
        font-size: 14px;
        opacity: .9;
    }

    .orders-actions {
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

    .btn-dashboard.danger {
        background: rgba(245, 101, 101, .12);
        color: var(--danger-color);
    }

    .btn-dashboard.danger:hover {
        background: var(--danger-color);
        color: white;
    }

    .btn-dashboard.success {
        background: rgba(72, 187, 120, .14);
        color: var(--success-color);
    }

    .btn-dashboard.success:hover {
        background: var(--success-color);
        color: white;
    }

    .btn-dashboard.info {
        background: rgba(102, 126, 234, .12);
        color: var(--primary-color);
    }

    .btn-dashboard.info:hover {
        background: var(--primary-color);
        color: white;
    }

    .btn-dashboard.sm {
        padding: 7px 11px;
        font-size: 12px;
    }

    .orders-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .orders-stat-card {
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

    .orders-stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
    }

    .orders-stat-card.success::before {
        background: var(--success-color);
    }

    .orders-stat-card.warning::before {
        background: var(--warning-color);
    }

    .orders-stat-card.danger::before {
        background: var(--danger-color);
    }

    .orders-stat-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--shadow-lg);
        border-color: var(--primary-color);
    }

    .orders-stat-icon {
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

    .orders-stat-card.success .orders-stat-icon {
        background: rgba(72, 187, 120, .15);
        color: var(--success-color);
    }

    .orders-stat-card.warning .orders-stat-icon {
        background: rgba(237, 137, 54, .15);
        color: var(--warning-color);
    }

    .orders-stat-card.danger .orders-stat-icon {
        background: rgba(245, 101, 101, .15);
        color: var(--danger-color);
    }

    .orders-stat-content h3 {
        margin: 0 0 7px;
        color: var(--text-light);
        font-size: 12px;
        text-transform: uppercase;
        font-weight: 600;
        letter-spacing: .5px;
    }

    .orders-stat-content .value {
        color: var(--text-dark);
        font-size: 24px;
        font-weight: 700;
        line-height: 1;
    }

    .orders-stat-content .hint {
        margin-top: 7px;
        color: var(--text-light);
        font-size: 12px;
    }

    .orders-card {
        background: white;
        border-radius: 8px;
        box-shadow: var(--shadow);
        margin-bottom: 30px;
        overflow: hidden;
    }

    .orders-card-header {
        padding: 20px 24px;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        flex-wrap: wrap;
    }

    .orders-card-header h2 {
        margin: 0;
        font-size: 18px;
        font-weight: 600;
        color: var(--text-dark);
    }

    .orders-card-header p {
        margin: 5px 0 0;
        font-size: 13px;
        color: var(--text-light);
    }

    .orders-card-body {
        padding: 24px;
    }

    .orders-filter {
        display: grid;
        grid-template-columns: 1.6fr 1fr 1fr auto auto;
        gap: 12px;
        align-items: end;
    }

    .orders-form-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 18px;
    }

    .orders-form-grid.three {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .orders-form-grid.one {
        grid-template-columns: 1fr;
    }

    .orders-field label {
        display: block;
        margin-bottom: 7px;
        color: var(--text-dark);
        font-size: 13px;
        font-weight: 600;
    }

    .orders-field label.required::after {
        content: ' *';
        color: var(--danger-color);
    }

    .orders-input,
    .orders-select {
        width: 100%;
        min-height: 42px;
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

    .orders-input:focus,
    .orders-select:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(102, 126, 234, .12);
    }

    .orders-table-wrap {
        overflow-x: auto;
    }

    .orders-table {
        width: 100%;
        border-collapse: collapse;
    }

    .orders-table th {
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

    .orders-table td {
        padding: 16px;
        border-bottom: 1px solid var(--border-color);
        color: var(--text-dark);
        vertical-align: middle;
        font-size: 14px;
    }

    .orders-table tr:hover td {
        background: var(--light-bg);
    }

    .order-cell {
        display: flex;
        align-items: center;
        gap: 12px;
        min-width: 230px;
    }

    .order-avatar {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        color: #fff;
        font-size: 20px;
        font-weight: 700;
        flex-shrink: 0;
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    }

    .order-avatar.success {
        background: linear-gradient(135deg, var(--success-color), #38a169);
    }

    .order-avatar.warning {
        background: linear-gradient(135deg, var(--warning-color), #dd6b20);
    }

    .order-name {
        color: var(--text-dark);
        font-size: 14px;
        font-weight: 600;
        margin-bottom: 3px;
    }

    .order-sub {
        color: var(--text-light);
        font-size: 12px;
    }

    .orders-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 11px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        white-space: nowrap;
    }

    .orders-badge.success {
        background: rgba(72, 187, 120, .14);
        color: var(--success-color);
    }

    .orders-badge.warning {
        background: rgba(237, 137, 54, .14);
        color: var(--warning-color);
    }

    .orders-badge.info {
        background: rgba(102, 126, 234, .12);
        color: var(--primary-color);
    }

    .orders-badge.muted {
        background: var(--light-bg);
        color: var(--text-light);
    }

    .orders-actions-inline {
        display: flex;
        justify-content: flex-end;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    .orders-alert {
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

    .orders-alert.success {
        background: rgba(72, 187, 120, .14);
        color: #276749;
        border-left: 4px solid var(--success-color);
    }

    .orders-alert.danger {
        background: rgba(245, 101, 101, .13);
        color: #9b2c2c;
        border-left: 4px solid var(--danger-color);
    }

    .orders-alert button {
        border: 0;
        background: transparent;
        color: inherit;
        font-size: 20px;
        cursor: pointer;
    }

    .orders-empty {
        text-align: center;
        padding: 50px 20px;
    }

    .orders-empty .icon {
        font-size: 48px;
        margin-bottom: 15px;
    }

    .orders-empty h3 {
        color: var(--text-dark);
        margin: 0 0 8px;
        font-size: 16px;
        font-weight: 600;
    }

    .orders-empty p {
        color: var(--text-light);
        margin: 0;
        font-size: 14px;
    }

    .order-item-card {
        border: 1px solid var(--border-color);
        border-radius: 8px;
        padding: 18px;
        background: #fff;
        margin-bottom: 14px;
        position: relative;
    }

    .order-item-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        margin-bottom: 16px;
    }

    .order-item-title {
        font-size: 14px;
        color: var(--text-dark);
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .order-summary {
        background: var(--light-bg);
        border: 1px solid var(--border-color);
        border-radius: 8px;
        padding: 18px;
        display: flex;
        justify-content: space-between;
        gap: 16px;
        align-items: center;
        flex-wrap: wrap;
        margin-top: 18px;
    }

    .order-summary-label {
        color: var(--text-light);
        font-size: 12px;
        text-transform: uppercase;
        font-weight: 600;
        letter-spacing: .4px;
    }

    .order-summary-value {
        color: var(--text-dark);
        font-size: 22px;
        font-weight: 700;
        margin-top: 4px;
    }

    .card-footer.orders-footer {
        border-top: 1px solid var(--border-color);
        padding: 18px 24px;
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        background: #fafafa;
    }


    .orders-help {
        margin-top: 6px;
        color: var(--text-light);
        font-size: 12px;
        line-height: 1.45;
    }

    .product-stock-note.ok {
        color: var(--success-color);
        font-weight: 600;
    }

    .product-stock-note.low {
        color: var(--warning-color);
        font-weight: 600;
    }

    .product-stock-note.out {
        color: var(--danger-color);
        font-weight: 600;
    }

    @media (max-width: 1100px) {

        .orders-filter,
        .orders-form-grid,
        .orders-form-grid.three {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 768px) {
        .orders-toolbar {
            padding: 25px;
        }

        .orders-toolbar h1 {
            font-size: 24px;
        }

        .orders-actions,
        .btn-dashboard {
            width: 100%;
        }

        .orders-card-header {
            align-items: flex-start;
        }

        .orders-actions-inline {
            justify-content: flex-start;
        }
    }
</style>


<div class="orders-page">
    <div class="orders-toolbar">
        <div>
            <h1><?= $action === 'create' ? 'Tạo đơn xuất kho' : ($action === 'detail' ? 'Chi tiết đơn xuất kho' : 'Xuất kho') ?></h1>
            <p><?= $action === 'list' ? 'Theo dõi danh sách đơn xuất, khách hàng, trạng thái giao hàng và tổng giá trị bán hàng.' : 'Quản lý thông tin đơn xuất và chi tiết sản phẩm giao cho khách hàng.' ?></p>
        </div>
        <div class="orders-actions"><?php if ($action === 'list'): ?><a href="?action=create" class="btn-dashboard primary">＋ Tạo đơn xuất kho</a><?php else: ?><a href="<?= e(getBaseUrl()) ?>/modules/warehouse/export_order.php" class="btn-dashboard primary">← Quay lại danh sách</a><?php endif; ?></div>
    </div>
    <?php if ($success): ?><div class="orders-alert success"><span>✅ <?= e($success) ?></span><button type="button" onclick="this.parentElement.remove()">×</button></div><?php endif; ?>
    <?php if ($error): ?><div class="orders-alert danger"><span>❌ <?= e($error) ?></span><button type="button" onclick="this.parentElement.remove()">×</button></div><?php endif; ?>

    <?php if ($action === 'create'): ?>
        <div class="orders-card">
            <div class="orders-card-header">
                <div>
                    <h2>Thông tin đơn xuất</h2>
                    <p>Chọn khách hàng, địa chỉ giao và thêm sản phẩm cần xuất kho.</p>
                </div>
            </div>
            <form method="POST" id="exportForm"><input type="hidden" name="action" value="create_order">
                <div class="orders-card-body">
                    <div class="orders-form-grid">
                        <div class="orders-field"><label class="required" for="ma_kh">Khách hàng</label><select class="orders-select" id="ma_kh" name="ma_kh" required>
                                <option value="">-- Chọn khách hàng --</option><?php foreach ($customers as $customer): ?><option value="<?= e($customer['MaKH']) ?>" data-address="<?= e($customer['DiaChi'] ?? '') ?>" data-ma-xp="<?= e($customer['MaXP'] ?? '') ?>" data-ward="<?= e($customer['TenXP'] ?? '') ?>"><?= e($customer['TenKH']) ?></option><?php endforeach; ?>
                            </select></div>
                        <div class="orders-field"><label for="ma_xp_display">Phường/Xã giao hàng</label><input class="orders-input" type="text" id="ma_xp_display" value="Chọn khách hàng để tự lấy địa điểm" readonly><input type="hidden" id="ma_xp" name="ma_xp"></div>
                    </div>
                    <div class="orders-form-grid" style="margin-top:18px">
                        <div class="orders-field"><label for="dc_giao_hang">Địa chỉ giao hàng mặc định</label><input class="orders-input" type="text" id="dc_giao_hang" name="dc_giao_hang" value="Chọn khách hàng để tự lấy địa chỉ" readonly>
                            <div class="orders-help">Địa chỉ và phường/xã được khóa theo hồ sơ khách hàng, không chọn thủ công để tránh giao sai địa điểm.</div>
                        </div>
                        <div class="orders-field"><label for="ma_hop_dong">Mã hợp đồng thầu</label><input class="orders-input" type="text" id="ma_hop_dong" name="ma_hop_dong"></div>
                    </div>
                    <div class="orders-card-header" style="padding:22px 0 14px;border-bottom:0">
                        <div>
                            <h2>Chi tiết xuất kho</h2>
                            <p>Hệ thống sẽ trừ tồn kho theo lô gần hết hạn trước.</p>
                        </div><button type="button" class="btn-dashboard info" onclick="addItem()">＋ Thêm sản phẩm</button>
                    </div>
                    <div id="itemsContainer">
                        <div class="order-item-card export-item">
                            <div class="order-item-head">
                                <div class="order-item-title"><span class="order-avatar warning" style="width:34px;height:34px;font-size:16px">📦</span> Sản phẩm #1</div><button type="button" class="btn-dashboard danger sm" onclick="removeItem(this)">Xóa</button>
                            </div>
                            <div class="orders-form-grid three">
                                <div class="orders-field"><label class="required">Sản phẩm</label><select class="orders-select product-select" name="items[0][ma_sp]" required>
                                        <option value="">-- Chọn sản phẩm --</option><?php foreach ($products as $product): ?><?php $stockQty = (int)($product['SoLuongConHan'] ?? 0); ?><option value="<?= e($product['MaSP']) ?>" data-price="<?= e($product['DonGia'] ?? 0) ?>" data-stock="<?= e($stockQty) ?>" data-total-stock="<?= e((int)($product['TongSoLuongTon'] ?? 0)) ?>" data-expiry="<?= e(dateVNShort($product['HanGanNhat'] ?? '')) ?>" <?= $stockQty <= 0 ? 'disabled' : '' ?>><?= e(productOptionLabel($product)) ?></option><?php endforeach; ?>
                                    </select>
                                    <div class="orders-help product-stock-note">Chỉ hiển thị để chọn các sản phẩm còn hàng còn hạn sử dụng.</div>
                                </div>
                                <div class="orders-field"><label class="required">Số lượng</label><input class="orders-input qty-input" type="number" name="items[0][sl_bh]" min="1" required></div>
                                <div class="orders-field"><label class="required">Đơn giá bán</label><input class="orders-input price-input" type="number" name="items[0][dg_bh]" step="0.01" min="0" required>
                                    <div class="orders-help product-price-note">Chọn sản phẩm để tự lấy đơn giá bán hiện tại.</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="order-summary">
                        <div>
                            <div class="order-summary-label">Tạm tính đơn xuất</div>
                            <div class="order-summary-value" id="orderTotal">0 ₫</div>
                        </div><button type="button" class="btn-dashboard info" onclick="addItem()">＋ Thêm dòng</button>
                    </div>
                </div>
                <div class="card-footer orders-footer"><button type="submit" class="btn-dashboard solid">💾 Tạo đơn xuất</button><a href="<?= e(getBaseUrl()) ?>/modules/warehouse/export_order.php" class="btn-dashboard light">Hủy</a></div>
            </form>
        </div>
    <?php elseif ($action === 'detail' && isset($_GET['id'])): ?>
        <div class="orders-card">
            <div class="orders-card-header">
                <div>
                    <h2>Đơn xuất #<?= e($_GET['id']) ?></h2>
                    <p><?= $orderInfo ? 'Khách hàng: ' . e($orderInfo['TenKH']) : 'Thông tin chi tiết đơn xuất kho.' ?></p>
                </div><a href="<?= e(getBaseUrl()) ?>/modules/warehouse/export_order.php" class="btn-dashboard light">← Quay lại</a>
            </div>
            <div class="orders-card-body"><?php if (!empty($orderDetails)): ?><div class="orders-table-wrap">
                        <table class="orders-table">
                            <thead>
                                <tr>
                                    <th>Sản phẩm</th>
                                    <th>Số lượng</th>
                                    <th>Đơn giá</th>
                                    <th style="text-align:right">Thành tiền</th>
                                </tr>
                            </thead>
                            <tbody><?php $total = 0;
                                                foreach ($orderDetails as $detail): $subtotal = $detail['SLBH'] * $detail['DGBH'];
                                                    $total += $subtotal; ?><tr>
                                        <td>
                                            <div class="order-cell">
                                                <div class="order-avatar warning">📦</div>
                                                <div>
                                                    <div class="order-name"><?= e($detail['TenSP']) ?></div>
                                                    <div class="order-sub">Mã SP #<?= e($detail['MaSP']) ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?= number_format((int)$detail['SLBH']) ?></td>
                                        <td><?= moneyVnd($detail['DGBH']) ?></td>
                                        <td style="text-align:right;font-weight:700"><?= moneyVnd($subtotal) ?></td>
                                    </tr><?php endforeach; ?></tbody>
                        </table>
                    </div>
                    <div class="order-summary">
                        <div>
                            <div class="order-summary-label">Tổng doanh thu</div>
                            <div class="order-summary-value"><?= moneyVnd($total) ?></div>
                        </div><?= $orderInfo ? orderStatusBadge($orderInfo['NgayGiao']) : '' ?>
                    </div><?php else: ?><div class="orders-empty">
                        <div class="icon">📭</div>
                        <h3>Chưa có chi tiết</h3>
                        <p>Không tìm thấy sản phẩm trong đơn xuất này.</p>
                    </div><?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="orders-stats">
            <div class="orders-stat-card">
                <div class="orders-stat-icon">📤</div>
                <div class="orders-stat-content">
                    <h3>Tổng đơn xuất</h3>
                    <div class="value"><?= number_format((int)$stats['total']) ?></div>
                    <div class="hint">Tất cả đơn bán hàng</div>
                </div>
            </div>
            <div class="orders-stat-card warning">
                <div class="orders-stat-icon">⏳</div>
                <div class="orders-stat-content">
                    <h3>Chờ giao</h3>
                    <div class="value"><?= number_format((int)$stats['pending']) ?></div>
                    <div class="hint">Chưa xác nhận ngày giao</div>
                </div>
            </div>
            <div class="orders-stat-card success">
                <div class="orders-stat-icon">✅</div>
                <div class="orders-stat-content">
                    <h3>Đã giao</h3>
                    <div class="value"><?= number_format((int)$stats['delivered']) ?></div>
                    <div class="hint">Đã giao cho khách hàng</div>
                </div>
            </div>
            <div class="orders-stat-card">
                <div class="orders-stat-icon">💰</div>
                <div class="orders-stat-content">
                    <h3>Tổng doanh thu</h3>
                    <div class="value"><?= number_format($totalValue / 1000000, 1) ?>M ₫</div>
                    <div class="hint">Từ chi tiết bán hàng</div>
                </div>
            </div>
        </div>
        <div class="orders-card">
            <div class="orders-card-header">
                <div>
                    <h2>Danh sách đơn xuất kho</h2>
                    <!-- <p>Giao diện order-list, đồng bộ với hệ thống quản lý kho.</p> -->
                </div>
            </div>
            <div class="orders-card-body">
                <form method="GET" class="orders-filter"><input type="hidden" name="action" value="list">
                    <div class="orders-field"><label>Tìm kiếm</label><input class="orders-input" type="text" name="search" value="<?= e($searchQuery) ?>" placeholder="Tìm theo mã đơn, khách hàng, hợp đồng..."></div>
                    <div class="orders-field"><label>Khách hàng</label><select class="orders-select" name="customer">
                            <option value="">Tất cả</option><?php foreach ($customers as $customer): ?><option value="<?= e($customer['MaKH']) ?>" <?= $filterCustomer == $customer['MaKH'] ? 'selected' : '' ?>><?= e($customer['TenKH']) ?></option><?php endforeach; ?>
                        </select></div>
                    <div class="orders-field"><label>Trạng thái</label><select class="orders-select" name="status">
                            <option value="">Tất cả</option>
                            <option value="pending" <?= $filterStatus === 'pending' ? 'selected' : '' ?>>Chờ giao</option>
                            <option value="delivered" <?= $filterStatus === 'delivered' ? 'selected' : '' ?>>Đã giao</option>
                        </select></div><button class="btn-dashboard solid" type="submit">🔎 Lọc</button><a class="btn-dashboard light" href="<?= e(getBaseUrl()) ?>/modules/warehouse/export_order.php">Đặt lại</a>
                </form>
            </div><?php if (!empty($orders)): ?><div class="orders-table-wrap">
                    <table class="orders-table">
                        <thead>
                            <tr>
                                <th>Đơn xuất</th>
                                <th>Khách hàng</th>
                                <th>Giá trị</th>
                                <th>Số mặt hàng</th>
                                <th>Ngày giao</th>
                                <th>Trạng thái</th>
                                <th style="text-align:right">Hành động</th>
                            </tr>
                        </thead>
                        <tbody><?php foreach ($orders as $order): ?><tr>
                                    <td>
                                        <div class="order-cell">
                                            <div class="order-avatar <?= $order['NgayGiao'] ? 'success' : 'warning' ?>">📤</div>
                                            <div>
                                                <div class="order-name">#<?= e($order['MaDBH']) ?></div>
                                                <div class="order-sub"><?= date('d/m/Y H:i', strtotime($order['NgayDat'])) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?= e($order['TenKH']) ?></td>
                                    <td><strong><?= moneyVnd($order['tong_tien']) ?></strong></td>
                                    <td><span class="orders-badge info"><?= number_format((int)$order['so_mat_hang']) ?> mặt hàng</span></td>
                                    <td><?= $order['NgayGiao'] ? date('d/m/Y', strtotime($order['NgayGiao'])) : '<span class="order-sub">Chưa giao</span>' ?></td>
                                    <td><?= orderStatusBadge($order['NgayGiao']) ?></td>
                                    <td>
                                        <div class="orders-actions-inline"><a href="?action=detail&id=<?= e($order['MaDBH']) ?>" class="btn-dashboard info sm">Chi tiết</a><?php if (!$order['NgayGiao']): ?><form method="POST" style="display:inline"><input type="hidden" name="action" value="confirm_delivery"><input type="hidden" name="ma_dbh" value="<?= e($order['MaDBH']) ?>"><input type="hidden" name="ngay_giao" value="<?= date('Y-m-d') ?>"><button type="submit" class="btn-dashboard success sm">Xác nhận giao</button></form><?php endif; ?><?php if (!$order['NgayGiao'] && function_exists('isAdmin') && isAdmin()): ?><form method="POST" style="display:inline" onsubmit="return confirm('Bạn chắc chắn muốn hủy đơn xuất này? Chỉ đơn chờ xác nhận mới được hủy.');"><input type="hidden" name="action" value="cancel_order"><input type="hidden" name="ma_dbh" value="<?= e($order['MaDBH']) ?>"><button type="submit" class="btn-dashboard danger sm">Hủy đơn</button></form><?php endif; ?></div>
                                    </td>
                                </tr><?php endforeach; ?></tbody>
                    </table>
                </div><?php else: ?><div class="orders-empty">
                    <div class="icon">📤</div>
                    <h3>Chưa có đơn xuất kho</h3>
                    <p>Tạo đơn xuất đầu tiên để bắt đầu quản lý hàng ra kho.</p><a href="?action=create" class="btn-dashboard solid" style="margin-top:16px">＋ Tạo đơn xuất kho</a>
                </div><?php endif; ?>
        </div>
    <?php endif; ?>
</div>
<script>
    document.querySelectorAll('.orders-alert').forEach(function(alert) {
        setTimeout(function() {
            if (alert && alert.parentElement) alert.remove();
        }, 5000);
    });
    let itemCount = 1;
    const productOptions = `<?php foreach ($products as $product): ?><?php $stockQty = (int)($product['SoLuongConHan'] ?? 0); ?><option value="<?= e($product['MaSP']) ?>" data-price="<?= e($product['DonGia'] ?? 0) ?>" data-stock="<?= e($stockQty) ?>" data-total-stock="<?= e((int)($product['TongSoLuongTon'] ?? 0)) ?>" data-expiry="<?= e(dateVNShort($product['HanGanNhat'] ?? '')) ?>" <?= $stockQty <= 0 ? 'disabled' : '' ?>><?= e(productOptionLabel($product)) ?></option><?php endforeach; ?>`;

    function addItem() {
        const container = document.getElementById('itemsContainer');
        const newItem = document.createElement('div');
        newItem.className = 'order-item-card export-item';
        newItem.innerHTML = `<div class="order-item-head"><div class="order-item-title"><span class="order-avatar warning" style="width:34px;height:34px;font-size:16px">📦</span> Sản phẩm #${itemCount + 1}</div><button type="button" class="btn-dashboard danger sm" onclick="removeItem(this)">Xóa</button></div><div class="orders-form-grid three"><div class="orders-field"><label class="required">Sản phẩm</label><select class="orders-select product-select" name="items[${itemCount}][ma_sp]" required><option value="">-- Chọn sản phẩm --</option>${productOptions}</select><div class="orders-help product-stock-note">Chỉ hiển thị để chọn các sản phẩm còn hàng còn hạn sử dụng.</div></div><div class="orders-field"><label class="required">Số lượng</label><input class="orders-input qty-input" type="number" name="items[${itemCount}][sl_bh]" min="1" required></div><div class="orders-field"><label class="required">Đơn giá bán</label><input class="orders-input price-input" type="number" name="items[${itemCount}][dg_bh]" step="0.01" min="0" required><div class="orders-help product-price-note">Chọn sản phẩm để tự lấy đơn giá bán hiện tại.</div></div></div>`;
        container.appendChild(newItem);
        itemCount++;

        bindCalculation();
    }

    function removeItem(button) {
        const items = document.querySelectorAll('.export-item');
        if (items.length > 1) {
            button.closest('.export-item').remove();
            updateOrderTotal();
        } else {
            alert('Phải có ít nhất 1 sản phẩm!');
        }
    }

    function updateOrderTotal() {
        let total = 0;
        document.querySelectorAll('.export-item').forEach(function(item) {
            const qty = parseFloat(item.querySelector('.qty-input')?.value || 0);
            const price = parseFloat(item.querySelector('.price-input')?.value || 0);
            total += qty * price;
        });
        const target = document.getElementById('orderTotal');
        if (target) target.textContent = total.toLocaleString('vi-VN') + ' ₫';
    }

    function fillProductPrice(select) {
        const item = select.closest('.export-item');
        if (!item) return;

        const selected = select.options[select.selectedIndex];
        const price = Number(selected?.dataset?.price || 0);
        const stock = Number(selected?.dataset?.stock || 0);
        const totalStock = Number(selected?.dataset?.totalStock || 0);
        const expiry = selected?.dataset?.expiry || '';

        const priceInput = item.querySelector('.price-input');
        const qtyInput = item.querySelector('.qty-input');
        const priceNote = item.querySelector('.product-price-note');
        const stockNote = item.querySelector('.product-stock-note');

        if (priceInput && price > 0) {
            priceInput.value = price;
            if (priceNote) priceNote.textContent = 'Đơn giá bán hiện tại: ' + price.toLocaleString('vi-VN') + ' ₫. Có thể chỉnh lại nếu cần.';
        } else if (priceNote) {
            priceNote.textContent = 'Sản phẩm này chưa có đơn giá bán trong danh mục.';
        }

        if (qtyInput) {
            if (stock > 0) {
                qtyInput.max = String(stock);
                qtyInput.placeholder = 'Tối đa ' + stock.toLocaleString('vi-VN');
            } else {
                qtyInput.removeAttribute('max');
                qtyInput.placeholder = '';
            }
        }

        if (stockNote) {
            stockNote.classList.remove('ok', 'low', 'out');

            if (!select.value) {
                stockNote.textContent = 'Chọn sản phẩm để xem số lượng còn trong kho.';
            } else if (stock > 0) {
                stockNote.classList.add(stock <= 10 ? 'low' : 'ok');
                stockNote.textContent = 'Tồn kho còn hạn: ' + stock.toLocaleString('vi-VN') + ' sản phẩm' +
                    (expiry ? ' • HSD gần nhất: ' + expiry : '') + '.';
            } else {
                stockNote.classList.add('out');
                stockNote.textContent = totalStock > 0 ?
                    'Sản phẩm còn tồn nhưng đã hết hạn/cận điều kiện xuất. Không thể chọn để xuất.' :
                    'Sản phẩm đã hết hàng trong kho.';
            }
        }

        updateOrderTotal();
    }

    function validateExportItemsBeforeSubmit(event) {
        const selectedProducts = [];
        const requestedByProduct = {};
        const stockByProduct = {};
        const productNameById = {};

        for (const item of document.querySelectorAll('.export-item')) {
            const select = item.querySelector('select[name*="[ma_sp]"]');
            const product = select?.value || '';
            const qty = Number(item.querySelector('.qty-input')?.value || 0);
            const price = Number(item.querySelector('.price-input')?.value || 0);
            const selected = select?.options[select.selectedIndex];
            const stock = Number(selected?.dataset?.stock || 0);
            const productName = selected ? selected.textContent.replace(/\s+—\s+còn.*$/u, '').replace(/\s+—\s+hết hàng.*$/u, '').trim() : product;

            if (!product || qty <= 0 || price <= 0) {
                alert('Mỗi dòng xuất kho phải có sản phẩm, số lượng và đơn giá bán hợp lệ.');
                event.preventDefault();
                return false;
            }

            if (stock <= 0) {
                alert('Sản phẩm "' + productName + '" hiện không còn hàng còn hạn để xuất.');
                event.preventDefault();
                return false;
            }

            selectedProducts.push(product);
            requestedByProduct[product] = (requestedByProduct[product] || 0) + qty;
            stockByProduct[product] = stock;
            productNameById[product] = productName;
        }

        if (selectedProducts.length === 0) {
            alert('Vui lòng thêm ít nhất 1 sản phẩm vào đơn xuất.');
            event.preventDefault();
            return false;
        }

        for (const product in requestedByProduct) {
            if (requestedByProduct[product] > stockByProduct[product]) {
                alert('Sản phẩm "' + productNameById[product] + '" chỉ còn ' +
                    stockByProduct[product].toLocaleString('vi-VN') +
                    ' sản phẩm còn hạn, nhưng bạn đang xuất ' +
                    requestedByProduct[product].toLocaleString('vi-VN') + '.');
                event.preventDefault();
                return false;
            }
        }

        return true;
    }

    function applyCustomerDefaultAddress() {
        const select = document.getElementById('ma_kh');
        const addressInput = document.getElementById('dc_giao_hang');
        const wardInput = document.getElementById('ma_xp');
        const wardDisplay = document.getElementById('ma_xp_display');
        if (!select) return;

        const selected = select.options[select.selectedIndex];
        const address = selected?.dataset?.address || '';
        const maXP = selected?.dataset?.maXp || '';
        const ward = selected?.dataset?.ward || '';

        if (addressInput) addressInput.value = address || 'Khách hàng chưa có địa chỉ mặc định';
        if (wardInput) wardInput.value = maXP;
        if (wardDisplay) wardDisplay.value = ward || 'Khách hàng chưa có phường/xã mặc định';
    }

    function bindCalculation() {
        document.querySelectorAll('.qty-input,.price-input').forEach(function(input) {
            input.removeEventListener('input', updateOrderTotal);
            input.addEventListener('input', updateOrderTotal);
        });

        document.querySelectorAll('.product-select').forEach(function(select) {
            select.onchange = function() {
                fillProductPrice(this);
            };
        });
    }

    const customerSelect = document.getElementById('ma_kh');
    if (customerSelect) {
        customerSelect.addEventListener('change', applyCustomerDefaultAddress);
        applyCustomerDefaultAddress();
    }

    const exportForm = document.getElementById('exportForm');
    if (exportForm) {
        exportForm.addEventListener('submit', validateExportItemsBeforeSubmit);
    }

    bindCalculation();
</script>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/quan_ly_vat_tu/includes/footer.php'; ?>