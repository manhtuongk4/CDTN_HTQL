<?php
$pageTitle = 'Nhập kho - Hệ thống quản lý vật tư y tế';
require_once $_SERVER['DOCUMENT_ROOT'] . '/quan_ly_vat_tu/config/database.php';
session_start();
requireLogin();

$success = '';
$error = '';
$action = $_GET['action'] ?? 'list';
$searchQuery = trim($_GET['search'] ?? '');
$filterStatus = $_GET['status'] ?? '';
$filterSupplier = $_GET['supplier'] ?? '';


function e($value)
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function moneyVnd($value)
{
    return number_format((float)$value, 0, ',', '.') . ' ₫';
}

function productImportOptionLabel(array $product)
{
    $name = (string)($product['TenSP'] ?? 'Sản phẩm');
    $totalStock = (int)($product['TongTonKho'] ?? 0);
    $validStock = (int)($product['TonConHan'] ?? 0);
    $expiry = $product['HSDGanNhat'] ?? null;

    $stockText = $totalStock > 0
        ? 'đang có ' . number_format($totalStock) . ' trong kho'
        : 'đang hết hàng';

    $validText = $validStock > 0
        ? 'còn hạn ' . number_format($validStock)
        : 'không còn hàng còn hạn';

    $expiryText = $expiry ? ' • HSD gần nhất ' . date('d/m/Y', strtotime($expiry)) : '';

    return $name . ' — ' . $stockText . ' • ' . $validText . $expiryText;
}

function orderStatusBadge($date)
{
    return $date ? '<span class="orders-badge success">✅ Đã giao</span>' : '<span class="orders-badge warning">⏳ Chờ giao</span>';
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

function generateReadableLotCode(PDO $pdo, $maSP, $ngaySanXuat = null)
{
    $timestamp = strtotime((string)$ngaySanXuat);
    if (!$timestamp) {
        $timestamp = time();
    }

    $baseCode = 'L' . date('ym', $timestamp) . '_' . productLotSuffix($maSP);

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


// Xử lý tạo đơn nhập kho / xác nhận giao / hủy đơn
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    ensureStockMovementTable($pdo);

    if ($_POST['action'] === 'create_order') {
        try {
            $pdo->beginTransaction();

            $ngayDat = date('Y-m-d H:i:s');
            $today = date('Y-m-d');
            $maNCC = trim($_POST['ma_ncc'] ?? '');

            if ($maNCC === '') {
                throw new Exception('Vui lòng chọn nhà cung cấp!');
            }

            if (!recordExists($pdo, 'SELECT COUNT(*) FROM nhacc WHERE MaNCC = ?', [$maNCC])) {
                throw new Exception('Nhà cung cấp không tồn tại hoặc đã bị xóa.');
            }

            $items = $_POST['items'] ?? [];
            if (!is_array($items) || empty($items)) {
                throw new Exception('Vui lòng thêm ít nhất 1 sản phẩm vào đơn nhập.');
            }

            $validItems = [];
            $seenLots = [];

            foreach ($items as $index => $item) {
                $rowNo = (int)$index + 1;
                $maSP = trim($item['ma_sp'] ?? '');
                $qtyRaw = $item['sl_mh'] ?? '';
                $priceRaw = $item['dg_mh'] ?? '';

                if ($maSP === '' && trim((string)$qtyRaw) === '' && trim((string)$priceRaw) === '') {
                    continue;
                }

                if ($maSP === '') {
                    throw new Exception("Dòng #$rowNo: Vui lòng chọn sản phẩm.");
                }

                if (!recordExists($pdo, 'SELECT COUNT(*) FROM sanpham WHERE MaSP = ?', [$maSP])) {
                    throw new Exception("Dòng #$rowNo: Sản phẩm không tồn tại hoặc đã bị xóa.");
                }

                $soLuong = requirePositiveInt($qtyRaw, "Dòng #$rowNo: Số lượng nhập");
                $donGia = requirePositiveMoney($priceRaw, "Dòng #$rowNo: Đơn giá nhập");

                $maLo = trim($item['ma_lo'] ?? '');

                $ngaySanXuat = trim($item['ngay_san_xuat'] ?? '');
                $hanSuDung = trim($item['han_su_dung'] ?? '');

                if ($maLo === '') {
                    $maLo = generateReadableLotCode($pdo, $maSP, $ngaySanXuat ?: date('Y-m-d'));
                } else {
                    $maLo = strtoupper(preg_replace('/[^A-Za-z0-9_\-]/', '', $maLo));
                }

                if (mb_strlen($maLo, 'UTF-8') > 30) {
                    throw new Exception("Dòng #$rowNo: Mã lô không được vượt quá 30 ký tự để giữ hiển thị gọn trên tồn kho.");
                }

                if ($ngaySanXuat === '' || $hanSuDung === '') {
                    throw new Exception("Dòng #$rowNo: Ngày sản xuất và hạn sử dụng là bắt buộc.");
                }

                if (!isValidDateValue($ngaySanXuat) || !isValidDateValue($hanSuDung)) {
                    throw new Exception("Dòng #$rowNo: Ngày sản xuất hoặc hạn sử dụng không hợp lệ.");
                }

                if ($ngaySanXuat > $today) {
                    throw new Exception("Dòng #$rowNo: Ngày sản xuất không được lớn hơn ngày hiện tại.");
                }

                if ($hanSuDung <= $ngaySanXuat) {
                    throw new Exception("Dòng #$rowNo: Hạn sử dụng phải sau ngày sản xuất.");
                }

                if ($hanSuDung <= $today) {
                    throw new Exception("Dòng #$rowNo: Không được nhập lô đã hết hạn hoặc hết hạn trong hôm nay.");
                }

                $lotKey = $maSP . '|' . mb_strtolower($maLo, 'UTF-8');
                if (isset($seenLots[$lotKey])) {
                    throw new Exception("Dòng #$rowNo: Lô $maLo của sản phẩm này bị nhập trùng trong cùng một đơn.");
                }
                $seenLots[$lotKey] = true;

                $stmt = $pdo->prepare('SELECT MaSP FROM khohang WHERE MaLo = ? AND MaSP <> ? LIMIT 1');
                $stmt->execute([$maLo, $maSP]);
                if ($stmt->fetchColumn()) {
                    throw new Exception("Dòng #$rowNo: Mã lô $maLo đã thuộc sản phẩm khác.");
                }

                $stmt = $pdo->prepare('SELECT NgaySanXuat, HanSuDung FROM khohang WHERE MaLo = ? AND MaSP = ? LIMIT 1');
                $stmt->execute([$maLo, $maSP]);
                $existingLot = $stmt->fetch();
                if ($existingLot) {
                    if (($existingLot['NgaySanXuat'] ?? '') !== $ngaySanXuat || ($existingLot['HanSuDung'] ?? '') !== $hanSuDung) {
                        throw new Exception("Dòng #$rowNo: Lô $maLo đã tồn tại nhưng ngày sản xuất/hạn sử dụng không khớp.");
                    }
                }

                $validItems[] = [
                    'ma_sp' => $maSP,
                    'so_luong' => $soLuong,
                    'don_gia' => $donGia,
                    'ma_lo' => $maLo,
                    'ngay_san_xuat' => $ngaySanXuat,
                    'han_su_dung' => $hanSuDung
                ];
            }

            if (empty($validItems)) {
                throw new Exception('Vui lòng thêm ít nhất 1 sản phẩm hợp lệ vào đơn nhập.');
            }

            // Sinh mã đơn nhập theo đúng độ dài cột MaDMH trong CSDL.
            // Lỗi cũ: code sinh 9 ký tự dạng 202600001, nhưng cột MaDMH có thể chỉ dài 7 ký tự,
            // MySQL cắt còn 2026000 nên gây trùng PRIMARY KEY.
            $yearPrefix = date('Y');
            $suffixLength = 3; // Mặc định tương thích mã 7 ký tự: YYYY + 000

            try {
                $colStmt = $pdo->query("SHOW COLUMNS FROM donmh LIKE 'MaDMH'");
                $colInfo = $colStmt->fetch();
                if ($colInfo && isset($colInfo['Type']) && preg_match('/(?:varchar|char)\((\d+)\)/i', $colInfo['Type'], $m)) {
                    $maxLength = (int)$m[1];
                    $suffixLength = max(1, $maxLength - strlen($yearPrefix));
                }
            } catch (PDOException $e) {
                $suffixLength = 3;
            }

            $maxNumber = (int)str_repeat('9', $suffixLength);

            $stmt = $pdo->prepare("
                SELECT COALESCE(MAX(CAST(SUBSTRING(MaDMH, 5) AS UNSIGNED)), -1)
                FROM donmh
                WHERE MaDMH REGEXP ?
            ");
            $stmt->execute(['^' . $yearPrefix . '[0-9]+$']);
            $nextNumber = ((int)$stmt->fetchColumn()) + 1;

            do {
                if ($nextNumber > $maxNumber) {
                    throw new Exception('Mã đơn nhập năm ' . $yearPrefix . ' đã vượt giới hạn ' . $maxNumber . ' số. Hãy tăng độ dài cột donmh.MaDMH trong database.');
                }

                $maDMH = $yearPrefix . str_pad((string)$nextNumber, $suffixLength, '0', STR_PAD_LEFT);
                $nextNumber++;

                $existsStmt = $pdo->prepare('SELECT COUNT(*) FROM donmh WHERE MaDMH = ?');
                $existsStmt->execute([$maDMH]);
            } while ((int)$existsStmt->fetchColumn() > 0);

            $stmt = $pdo->prepare('INSERT INTO donmh (MaDMH, NgayDat, MaNCC) VALUES (?, ?, ?)');
            $stmt->execute([$maDMH, $ngayDat, $maNCC]);

            foreach ($validItems as $item) {
                $stmt = $pdo->prepare('INSERT INTO chitietmuahang (MaDMH, MaSP, SLMH, DGMH) VALUES (?, ?, ?, ?)');
                $stmt->execute([$maDMH, $item['ma_sp'], $item['so_luong'], $item['don_gia']]);

                $stmt = $pdo->prepare('SELECT SoLuongTon FROM khohang WHERE MaLo = ? AND MaSP = ? FOR UPDATE');
                $stmt->execute([$item['ma_lo'], $item['ma_sp']]);
                $existingQty = $stmt->fetchColumn();

                if ($existingQty !== false) {
                    $stmt = $pdo->prepare('UPDATE khohang SET SoLuongTon = SoLuongTon + ? WHERE MaLo = ? AND MaSP = ?');
                    $stmt->execute([$item['so_luong'], $item['ma_lo'], $item['ma_sp']]);
                } else {
                    $stmt = $pdo->prepare('INSERT INTO khohang (MaLo, MaSP, SoLuongTon, NgaySanXuat, HanSuDung) VALUES (?, ?, ?, ?, ?)');
                    $stmt->execute([$item['ma_lo'], $item['ma_sp'], $item['so_luong'], $item['ngay_san_xuat'], $item['han_su_dung']]);
                }

                $stmt = $pdo->prepare('INSERT INTO stock_movements (OrderType, MaDon, MaSP, MaLo, SoLuong, MovementType) VALUES (?, ?, ?, ?, ?, ?)');
                $stmt->execute(['IMPORT', $maDMH, $item['ma_sp'], $item['ma_lo'], $item['so_luong'], 'IN']);
            }

            $pdo->commit();
            $success = 'Tạo đơn nhập kho thành công! Mã đơn: ' . $maDMH;
            $action = 'list';
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = 'Lỗi: ' . $e->getMessage();
        }
    } elseif ($_POST['action'] === 'confirm_delivery') {
        try {
            $maDMH = trim($_POST['ma_dmh'] ?? '');
            $ngayGiao = trim($_POST['ngay_giao'] ?? date('Y-m-d'));

            if ($maDMH === '') {
                throw new Exception('Thiếu mã đơn nhập.');
            }

            if (!isValidDateValue($ngayGiao)) {
                throw new Exception('Ngày giao không hợp lệ.');
            }

            $stmt = $pdo->prepare('SELECT NgayDat, NgayGiao FROM donmh WHERE MaDMH = ? LIMIT 1');
            $stmt->execute([$maDMH]);
            $order = $stmt->fetch();

            if (!$order) {
                throw new Exception('Không tìm thấy đơn nhập cần xác nhận.');
            }

            if (!empty($order['NgayGiao'])) {
                throw new Exception('Đơn nhập này đã được xác nhận giao trước đó.');
            }

            if ($ngayGiao < date('Y-m-d', strtotime($order['NgayDat']))) {
                throw new Exception('Ngày giao không được trước ngày đặt đơn.');
            }

            if ($ngayGiao > date('Y-m-d')) {
                throw new Exception('Ngày giao không được lớn hơn ngày hiện tại.');
            }

            $stmt = $pdo->prepare('UPDATE donmh SET NgayGiao = ? WHERE MaDMH = ? AND NgayGiao IS NULL');
            $stmt->execute([$ngayGiao, $maDMH]);
            $success = 'Xác nhận giao hàng thành công!';
        } catch (Exception $e) {
            $error = 'Lỗi: ' . $e->getMessage();
        }
    } elseif ($_POST['action'] === 'cancel_order') {
        try {
            assertAdminCanCancel();
            $pdo->beginTransaction();

            $maDMH = trim($_POST['ma_dmh'] ?? '');
            if ($maDMH === '') {
                throw new Exception('Thiếu mã đơn nhập cần hủy.');
            }

            $stmt = $pdo->prepare('SELECT MaDMH, NgayGiao FROM donmh WHERE MaDMH = ? LIMIT 1 FOR UPDATE');
            $stmt->execute([$maDMH]);
            $order = $stmt->fetch();

            if (!$order) {
                throw new Exception('Không tìm thấy đơn nhập cần hủy.');
            }

            if (!empty($order['NgayGiao'])) {
                throw new Exception('Đơn nhập đã được xác nhận giao thành công nên không thể hủy.');
            }

            $stmt = $pdo->prepare('SELECT * FROM stock_movements WHERE OrderType = ? AND MaDon = ? AND MovementType = ? ORDER BY ID ASC');
            $stmt->execute(['IMPORT', $maDMH, 'IN']);
            $movements = $stmt->fetchAll();

            $usedLegacyRollback = false;

            if (!empty($movements)) {
                foreach ($movements as $movement) {
                    $stmt = $pdo->prepare('SELECT SoLuongTon FROM khohang WHERE MaLo = ? AND MaSP = ? FOR UPDATE');
                    $stmt->execute([$movement['MaLo'], $movement['MaSP']]);
                    $currentQty = $stmt->fetchColumn();

                    if ($currentQty === false) {
                        throw new Exception('Không tìm thấy lô ' . $movement['MaLo'] . ' để hoàn tác đơn nhập.');
                    }

                    if ((int)$currentQty < (int)$movement['SoLuong']) {
                        throw new Exception('Không thể hủy đơn nhập vì lô ' . $movement['MaLo'] . ' đã được xuất/bán một phần. Tồn hiện tại không đủ để trừ lại.');
                    }
                }

                foreach ($movements as $movement) {
                    $stmt = $pdo->prepare('UPDATE khohang SET SoLuongTon = SoLuongTon - ? WHERE MaLo = ? AND MaSP = ?');
                    $stmt->execute([(int)$movement['SoLuong'], $movement['MaLo'], $movement['MaSP']]);
                }

                $stmt = $pdo->prepare('DELETE FROM stock_movements WHERE OrderType = ? AND MaDon = ?');
                $stmt->execute(['IMPORT', $maDMH]);
            } else {
                // Đơn cũ được tạo trước khi có bảng stock_movements.
                // Không biết chính xác lô nào được cộng, nên chỉ hủy khi tổng tồn hiện tại của từng sản phẩm còn đủ
                // và trừ tồn theo các lô mới/còn hạn trước. Điều này giúp xử lý các đơn chờ xác nhận cũ mà không bị lỗi.
                $usedLegacyRollback = true;

                $stmt = $pdo->prepare('
                    SELECT MaSP, SUM(SLMH) AS SoLuongCanTru
                    FROM chitietmuahang
                    WHERE MaDMH = ?
                    GROUP BY MaSP
                ');
                $stmt->execute([$maDMH]);
                $legacyItems = $stmt->fetchAll();

                if (empty($legacyItems)) {
                    throw new Exception('Đơn nhập không có chi tiết sản phẩm để hủy.');
                }

                foreach ($legacyItems as $item) {
                    $maSP = $item['MaSP'];
                    $qtyNeeded = (int)$item['SoLuongCanTru'];

                    $stmt = $pdo->prepare('SELECT COALESCE(SUM(SoLuongTon), 0) FROM khohang WHERE MaSP = ? FOR UPDATE');
                    $stmt->execute([$maSP]);
                    $availableQty = (int)$stmt->fetchColumn();

                    if ($availableQty < $qtyNeeded) {
                        throw new Exception('Không thể hủy đơn nhập cũ vì sản phẩm ID ' . $maSP . ' đã được xuất/bán một phần. Tồn hiện tại không đủ để trừ lại.');
                    }
                }

                foreach ($legacyItems as $item) {
                    $maSP = $item['MaSP'];
                    $remainingQty = (int)$item['SoLuongCanTru'];

                    $stmt = $pdo->prepare('
                        SELECT MaLo, SoLuongTon
                        FROM khohang
                        WHERE MaSP = ? AND SoLuongTon > 0
                        ORDER BY HanSuDung DESC, NgaySanXuat DESC, MaLo DESC
                        FOR UPDATE
                    ');
                    $stmt->execute([$maSP]);
                    $lots = $stmt->fetchAll();

                    foreach ($lots as $lot) {
                        if ($remainingQty <= 0) {
                            break;
                        }

                        $qtyToSubtract = min($remainingQty, (int)$lot['SoLuongTon']);
                        if ($qtyToSubtract <= 0) {
                            continue;
                        }

                        $stmt = $pdo->prepare('UPDATE khohang SET SoLuongTon = SoLuongTon - ? WHERE MaLo = ? AND MaSP = ? AND SoLuongTon >= ?');
                        $stmt->execute([$qtyToSubtract, $lot['MaLo'], $maSP, $qtyToSubtract]);

                        if ($stmt->rowCount() !== 1) {
                            throw new Exception('Tồn kho lô ' . $lot['MaLo'] . ' vừa thay đổi. Vui lòng thử lại.');
                        }

                        $remainingQty -= $qtyToSubtract;
                    }

                    if ($remainingQty > 0) {
                        throw new Exception('Không thể hoàn tác đủ tồn kho cho sản phẩm ID ' . $maSP . '.');
                    }
                }
            }

            $stmt = $pdo->prepare('DELETE FROM chitietmuahang WHERE MaDMH = ?');
            $stmt->execute([$maDMH]);

            $stmt = $pdo->prepare('DELETE FROM donmh WHERE MaDMH = ?');
            $stmt->execute([$maDMH]);

            $pdo->commit();
            $success = $usedLegacyRollback
                ? 'Đã hủy đơn nhập kho cũ và hoàn tác tồn kho theo chi tiết sản phẩm.'
                : 'Đã hủy đơn nhập kho và hoàn tác tồn kho thành công!';
            $action = 'list';
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = 'Lỗi: ' . $e->getMessage();
        }
    }
}

// Nhà cung cấp
try {
    $stmt = $pdo->query('SELECT * FROM nhacc ORDER BY TenNCC');
    $suppliers = $stmt->fetchAll();
} catch (PDOException $e) {
    $suppliers = [];
}

// Sản phẩm + tồn kho hiện tại để hiển thị khi tạo đơn nhập
try {
    $stmt = $pdo->query("
        SELECT sp.*,
               COALESCE(SUM(kh.SoLuongTon), 0) AS TongTonKho,
               COALESCE(SUM(CASE WHEN kh.HanSuDung > CURDATE() THEN kh.SoLuongTon ELSE 0 END), 0) AS TonConHan,
               MIN(CASE WHEN kh.SoLuongTon > 0 AND kh.HanSuDung > CURDATE() THEN kh.HanSuDung END) AS HSDGanNhat
        FROM sanpham sp
        LEFT JOIN khohang kh ON sp.MaSP = kh.MaSP
        GROUP BY sp.MaSP
        ORDER BY sp.TenSP
    ");
    $products = $stmt->fetchAll();
} catch (PDOException $e) {
    $products = [];
}

$whereConditions = [];
$params = [];
if ($searchQuery !== '') {
    $whereConditions[] = '(dmh.MaDMH LIKE ? OR nc.TenNCC LIKE ?)';
    $params[] = "%$searchQuery%";
    $params[] = "%$searchQuery%";
}
if ($filterSupplier !== '') {
    $whereConditions[] = 'dmh.MaNCC = ?';
    $params[] = $filterSupplier;
}
if ($filterStatus === 'delivered') {
    $whereConditions[] = 'dmh.NgayGiao IS NOT NULL';
} elseif ($filterStatus === 'pending') {
    $whereConditions[] = 'dmh.NgayGiao IS NULL';
}
$whereClause = $whereConditions ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

try {
    $stmt = $pdo->prepare("SELECT dmh.*, nc.TenNCC,
            COUNT(ctmh.MaSP) AS so_mat_hang,
            COALESCE(SUM(ctmh.SLMH * ctmh.DGMH), 0) AS tong_tien
        FROM donmh dmh
        JOIN nhacc nc ON dmh.MaNCC = nc.MaNCC
        LEFT JOIN chitietmuahang ctmh ON dmh.MaDMH = ctmh.MaDMH
        $whereClause
        GROUP BY dmh.MaDMH
        ORDER BY dmh.NgayDat DESC");
    $stmt->execute($params);
    $orders = $stmt->fetchAll();

    $statsStmt = $pdo->query("SELECT COUNT(*) AS total, SUM(CASE WHEN NgayGiao IS NULL THEN 1 ELSE 0 END) AS pending, SUM(CASE WHEN NgayGiao IS NOT NULL THEN 1 ELSE 0 END) AS delivered FROM donmh");
    $stats = $statsStmt->fetch();

    $valueStmt = $pdo->query('SELECT COALESCE(SUM(SLMH * DGMH), 0) AS total_value FROM chitietmuahang');
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
        $stmt = $pdo->prepare('SELECT dmh.*, nc.TenNCC FROM donmh dmh JOIN nhacc nc ON dmh.MaNCC = nc.MaNCC WHERE dmh.MaDMH = ?');
        $stmt->execute([$_GET['id']]);
        $orderInfo = $stmt->fetch();

        $stmt = $pdo->prepare('SELECT ctmh.*, sp.TenSP FROM chitietmuahang ctmh JOIN sanpham sp ON ctmh.MaSP = sp.MaSP WHERE ctmh.MaDMH = ?');
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
            <h1><?= $action === 'create' ? 'Tạo đơn nhập kho' : ($action === 'detail' ? 'Chi tiết đơn nhập kho' : 'Nhập kho') ?></h1>
            <p><?= $action === 'list' ? 'Theo dõi danh sách đơn nhập, nhà cung cấp, trạng thái giao hàng và tổng giá trị nhập kho.' : 'Quản lý thông tin đơn nhập và chi tiết sản phẩm trong kho.' ?></p>
        </div>
        <div class="orders-actions">
            <?php if ($action === 'list'): ?>
                <a href="?action=create" class="btn-dashboard primary">＋ Tạo đơn nhập kho</a>
            <?php else: ?>
                <a href="<?= e(getBaseUrl()) ?>/modules/warehouse/import_order.php" class="btn-dashboard primary">← Quay lại danh sách</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($success): ?><div class="orders-alert success"><span>✅ <?= e($success) ?></span><button type="button" onclick="this.parentElement.remove()">×</button></div><?php endif; ?>
    <?php if ($error): ?><div class="orders-alert danger"><span>❌ <?= e($error) ?></span><button type="button" onclick="this.parentElement.remove()">×</button></div><?php endif; ?>

    <?php if ($action === 'create'): ?>
        <div class="orders-card">
            <div class="orders-card-header">
                <div>
                    <h2>Thông tin đơn nhập</h2>
                    <p>Chọn nhà cung cấp và thêm các sản phẩm cần nhập kho.</p>
                </div>
            </div>
            <form method="POST" id="importForm">
                <input type="hidden" name="action" value="create_order">
                <div class="orders-card-body">
                    <div class="orders-form-grid one">
                        <div class="orders-field"><label class="required" for="ma_ncc">Nhà cung cấp</label><select class="orders-select" id="ma_ncc" name="ma_ncc" required>
                                <option value="">-- Chọn nhà cung cấp --</option><?php foreach ($suppliers as $supplier): ?><option value="<?= e($supplier['MaNCC']) ?>"><?= e($supplier['TenNCC']) ?></option><?php endforeach; ?>
                            </select></div>
                    </div>
                    <div class="orders-card-header" style="padding:22px 0 14px;border-bottom:0">
                        <div>
                            <h2>Chi tiết nhập kho</h2>
                            <p>Nhập số lượng, đơn giá và thông tin lô hàng.</p>
                        </div><button type="button" class="btn-dashboard info" onclick="addItem()">＋ Thêm sản phẩm</button>
                    </div>
                    <div id="itemsContainer">
                        <div class="order-item-card import-item">
                            <div class="order-item-head">
                                <div class="order-item-title"><span class="order-avatar success" style="width:34px;height:34px;font-size:16px">📦</span> Sản phẩm #1</div><button type="button" class="btn-dashboard danger sm" onclick="removeItem(this)">Xóa</button>
                            </div>
                            <div class="orders-form-grid three">
                                <div class="orders-field"><label class="required">Sản phẩm</label><select class="orders-select product-select" name="items[0][ma_sp]" required>
                                        <option value="">-- Chọn sản phẩm --</option><?php foreach ($products as $product): ?><option value="<?= e($product['MaSP']) ?>" data-sale-price="<?= e($product['DonGia'] ?? 0) ?>" data-total-stock="<?= e($product['TongTonKho'] ?? 0) ?>" data-valid-stock="<?= e($product['TonConHan'] ?? 0) ?>" data-expiry="<?= e($product['HSDGanNhat'] ?? '') ?>"><?= e(productImportOptionLabel($product)) ?></option><?php endforeach; ?>
                                    </select></div>
                                <div class="orders-field"><label class="required">Số lượng</label><input class="orders-input qty-input" type="number" name="items[0][sl_mh]" min="1" required></div>
                                <div class="orders-field"><label class="required">Đơn giá</label><input class="orders-input price-input" type="number" name="items[0][dg_mh]" step="0.01" min="0" required>
                                    <div class="orders-help product-price-note">Chọn sản phẩm để xem tồn kho hiện tại và giá bán trong danh mục.</div>
                                </div>
                            </div>
                            <div class="orders-form-grid three" style="margin-top:14px">
                                <div class="orders-field"><label>Mã lô</label><input class="orders-input" type="text" name="items[0][ma_lo]" placeholder="Tự tạo dạng L2605_23 nếu bỏ trống"></div>
                                <div class="orders-field"><label class="required">Ngày sản xuất</label><input class="orders-input mfg-date" type="date" name="items[0][ngay_san_xuat]" max="<?= date('Y-m-d') ?>" required></div>
                                <div class="orders-field"><label class="required">Hạn sử dụng</label><input class="orders-input exp-date" type="date" name="items[0][han_su_dung]" min="<?= date('Y-m-d', strtotime('+1 day')) ?>" required></div>
                            </div>
                        </div>
                    </div>
                    <div class="order-summary">
                        <div>
                            <div class="order-summary-label">Tạm tính đơn nhập</div>
                            <div class="order-summary-value" id="orderTotal">0 ₫</div>
                        </div><button type="button" class="btn-dashboard info" onclick="addItem()">＋ Thêm dòng</button>
                    </div>
                </div>
                <div class="card-footer orders-footer"><button type="submit" class="btn-dashboard solid">💾 Tạo đơn nhập</button><a href="<?= e(getBaseUrl()) ?>/modules/warehouse/import_order.php" class="btn-dashboard light">Hủy</a></div>
            </form>
        </div>

    <?php elseif ($action === 'detail' && isset($_GET['id'])): ?>
        <div class="orders-card">
            <div class="orders-card-header">
                <div>
                    <h2>Đơn nhập #<?= e($_GET['id']) ?></h2>
                    <p><?= $orderInfo ? 'Nhà cung cấp: ' . e($orderInfo['TenNCC']) : 'Thông tin chi tiết đơn nhập kho.' ?></p>
                </div><a href="<?= e(getBaseUrl()) ?>/modules/warehouse/import_order.php" class="btn-dashboard light">← Quay lại</a>
            </div>
            <div class="orders-card-body">
                <?php if (!empty($orderDetails)): ?>
                    <div class="orders-table-wrap">
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
                                    foreach ($orderDetails as $detail): $subtotal = $detail['SLMH'] * $detail['DGMH'];
                                        $total += $subtotal; ?><tr>
                                        <td>
                                            <div class="order-cell">
                                                <div class="order-avatar success">📦</div>
                                                <div>
                                                    <div class="order-name"><?= e($detail['TenSP']) ?></div>
                                                    <div class="order-sub">Mã SP #<?= e($detail['MaSP']) ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?= number_format((int)$detail['SLMH']) ?></td>
                                        <td><?= moneyVnd($detail['DGMH']) ?></td>
                                        <td style="text-align:right;font-weight:700"><?= moneyVnd($subtotal) ?></td>
                                    </tr><?php endforeach; ?></tbody>
                        </table>
                    </div>
                    <div class="order-summary">
                        <div>
                            <div class="order-summary-label">Tổng cộng</div>
                            <div class="order-summary-value"><?= moneyVnd($total) ?></div>
                        </div><?= $orderInfo ? orderStatusBadge($orderInfo['NgayGiao']) : '' ?>
                    </div>
                <?php else: ?><div class="orders-empty">
                        <div class="icon">📭</div>
                        <h3>Chưa có chi tiết</h3>
                        <p>Không tìm thấy sản phẩm trong đơn nhập này.</p>
                    </div><?php endif; ?>
            </div>
        </div>

    <?php else: ?>
        <div class="orders-stats">
            <div class="orders-stat-card">
                <div class="orders-stat-icon">📥</div>
                <div class="orders-stat-content">
                    <h3>Tổng đơn nhập</h3>
                    <div class="value"><?= number_format((int)$stats['total']) ?></div>
                    <div class="hint">Tất cả đơn mua hàng</div>
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
                    <div class="hint">Đã nhập kho hoàn tất</div>
                </div>
            </div>
            <div class="orders-stat-card">
                <div class="orders-stat-icon">💰</div>
                <div class="orders-stat-content">
                    <h3>Tổng giá trị nhập</h3>
                    <div class="value"><?= number_format($totalValue / 1000000, 1) ?>M ₫</div>
                    <div class="hint">Từ chi tiết mua hàng</div>
                </div>
            </div>
        </div>
        <div class="orders-card">
            <div class="orders-card-header">
                <div>
                    <h2>Danh sách đơn nhập kho</h2>
                    <!-- <p>Giao diện order-list, đồng bộ với hệ thống quản lý kho.</p> -->
                </div>
            </div>
            <div class="orders-card-body">
                <form method="GET" class="orders-filter"><input type="hidden" name="action" value="list">
                    <div class="orders-field"><label>Tìm kiếm</label><input class="orders-input" type="text" name="search" value="<?= e($searchQuery) ?>" placeholder="Tìm theo mã đơn hoặc nhà cung cấp..."></div>
                    <div class="orders-field"><label>Nhà cung cấp</label><select class="orders-select" name="supplier">
                            <option value="">Tất cả</option><?php foreach ($suppliers as $supplier): ?><option value="<?= e($supplier['MaNCC']) ?>" <?= $filterSupplier == $supplier['MaNCC'] ? 'selected' : '' ?>><?= e($supplier['TenNCC']) ?></option><?php endforeach; ?>
                        </select></div>
                    <div class="orders-field"><label>Trạng thái</label><select class="orders-select" name="status">
                            <option value="">Tất cả</option>
                            <option value="pending" <?= $filterStatus === 'pending' ? 'selected' : '' ?>>Chờ giao</option>
                            <option value="delivered" <?= $filterStatus === 'delivered' ? 'selected' : '' ?>>Đã giao</option>
                        </select></div><button class="btn-dashboard solid" type="submit">🔎 Lọc</button><a class="btn-dashboard light" href="<?= e(getBaseUrl()) ?>/modules/warehouse/import_order.php">Đặt lại</a>
                </form>
            </div>
            <?php if (!empty($orders)): ?><div class="orders-table-wrap">
                    <table class="orders-table">
                        <thead>
                            <tr>
                                <th>Đơn nhập</th>
                                <th>Nhà cung cấp</th>
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
                                            <div class="order-avatar <?= $order['NgayGiao'] ? 'success' : 'warning' ?>">📥</div>
                                            <div>
                                                <div class="order-name">#<?= e($order['MaDMH']) ?></div>
                                                <div class="order-sub"><?= date('d/m/Y H:i', strtotime($order['NgayDat'])) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?= e($order['TenNCC']) ?></td>
                                    <td><strong><?= moneyVnd($order['tong_tien']) ?></strong></td>
                                    <td><span class="orders-badge info"><?= number_format((int)$order['so_mat_hang']) ?> mặt hàng</span></td>
                                    <td><?= $order['NgayGiao'] ? date('d/m/Y', strtotime($order['NgayGiao'])) : '<span class="order-sub">Chưa giao</span>' ?></td>
                                    <td><?= orderStatusBadge($order['NgayGiao']) ?></td>
                                    <td>
                                        <div class="orders-actions-inline"><a href="?action=detail&id=<?= e($order['MaDMH']) ?>" class="btn-dashboard info sm">Chi tiết</a><?php if (!$order['NgayGiao']): ?><form method="POST" style="display:inline"><input type="hidden" name="action" value="confirm_delivery"><input type="hidden" name="ma_dmh" value="<?= e($order['MaDMH']) ?>"><input type="hidden" name="ngay_giao" value="<?= date('Y-m-d') ?>"><button type="submit" class="btn-dashboard success sm">Xác nhận giao</button></form><?php endif; ?><?php if (!$order['NgayGiao'] && function_exists('isAdmin') && isAdmin()): ?><form method="POST" style="display:inline" onsubmit="return confirm('Bạn chắc chắn muốn hủy đơn nhập này? Chỉ đơn chờ xác nhận mới được hủy.');"><input type="hidden" name="action" value="cancel_order"><input type="hidden" name="ma_dmh" value="<?= e($order['MaDMH']) ?>"><button type="submit" class="btn-dashboard danger sm">Hủy đơn</button></form><?php endif; ?></div>
                                    </td>
                                </tr><?php endforeach; ?></tbody>
                    </table>
                </div><?php else: ?><div class="orders-empty">
                    <div class="icon">📥</div>
                    <h3>Chưa có đơn nhập kho</h3>
                    <p>Tạo đơn nhập đầu tiên để bắt đầu quản lý hàng vào kho.</p><a href="?action=create" class="btn-dashboard solid" style="margin-top:16px">＋ Tạo đơn nhập kho</a>
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
    const productOptions = `<?php foreach ($products as $product): ?><option value="<?= e($product['MaSP']) ?>" data-sale-price="<?= e($product['DonGia'] ?? 0) ?>" data-total-stock="<?= e($product['TongTonKho'] ?? 0) ?>" data-valid-stock="<?= e($product['TonConHan'] ?? 0) ?>" data-expiry="<?= e($product['HSDGanNhat'] ?? '') ?>"><?= e(productImportOptionLabel($product)) ?></option><?php endforeach; ?>`;

    function addItem() {
        const container = document.getElementById('itemsContainer');
        const newItem = document.createElement('div');
        newItem.className = 'order-item-card import-item';
        newItem.innerHTML = `<div class="order-item-head"><div class="order-item-title"><span class="order-avatar success" style="width:34px;height:34px;font-size:16px">📦</span> Sản phẩm #${itemCount + 1}</div><button type="button" class="btn-dashboard danger sm" onclick="removeItem(this)">Xóa</button></div><div class="orders-form-grid three"><div class="orders-field"><label class="required">Sản phẩm</label><select class="orders-select product-select" name="items[${itemCount}][ma_sp]" required><option value="">-- Chọn sản phẩm --</option>${productOptions}</select></div><div class="orders-field"><label class="required">Số lượng</label><input class="orders-input qty-input" type="number" name="items[${itemCount}][sl_mh]" min="1" required></div><div class="orders-field"><label class="required">Đơn giá</label><input class="orders-input price-input" type="number" name="items[${itemCount}][dg_mh]" step="0.01" min="0" required><div class="orders-help product-price-note">Chọn sản phẩm để xem tồn kho hiện tại và giá bán trong danh mục.</div></div></div><div class="orders-form-grid three" style="margin-top:14px"><div class="orders-field"><label>Mã lô</label><input class="orders-input" type="text" name="items[${itemCount}][ma_lo]" placeholder="Tự tạo dạng L2605_23 nếu bỏ trống"></div><div class="orders-field"><label class="required">Ngày sản xuất</label><input class="orders-input mfg-date" type="date" name="items[${itemCount}][ngay_san_xuat]" max="<?= date('Y-m-d') ?>" required></div><div class="orders-field"><label class="required">Hạn sử dụng</label><input class="orders-input exp-date" type="date" name="items[${itemCount}][han_su_dung]" min="<?= date('Y-m-d', strtotime('+1 day')) ?>" required></div></div>`;
        container.appendChild(newItem);
        itemCount++;

        const importForm = document.getElementById('importForm');
        if (importForm) {
            importForm.addEventListener('submit', function(event) {
                const today = new Date();
                today.setHours(0, 0, 0, 0);

                for (const item of document.querySelectorAll('.import-item')) {
                    const product = item.querySelector('select[name*="[ma_sp]"]')?.value || '';
                    const qty = Number(item.querySelector('.qty-input')?.value || 0);
                    const price = Number(item.querySelector('.price-input')?.value || 0);
                    const mfg = item.querySelector('.mfg-date')?.value || '';
                    const exp = item.querySelector('.exp-date')?.value || '';

                    if (!product || qty <= 0 || price <= 0 || !mfg || !exp) {
                        alert('Mỗi dòng nhập kho phải có sản phẩm, số lượng, đơn giá, ngày sản xuất và hạn sử dụng hợp lệ.');
                        event.preventDefault();
                        return;
                    }

                    const mfgDate = new Date(mfg + 'T00:00:00');
                    const expDate = new Date(exp + 'T00:00:00');

                    if (mfgDate > today) {
                        alert('Ngày sản xuất không được lớn hơn ngày hiện tại.');
                        event.preventDefault();
                        return;
                    }

                    if (expDate <= mfgDate) {
                        alert('Hạn sử dụng phải sau ngày sản xuất.');
                        event.preventDefault();
                        return;
                    }

                    if (expDate <= today) {
                        alert('Không được nhập lô đã hết hạn hoặc hết hạn trong hôm nay.');
                        event.preventDefault();
                        return;
                    }
                }
            });
        }

        bindCalculation();
    }

    function removeItem(button) {
        const items = document.querySelectorAll('.import-item');
        if (items.length > 1) {
            button.closest('.import-item').remove();
            updateOrderTotal();
        } else {
            alert('Phải có ít nhất 1 sản phẩm!');
        }
    }

    function updateOrderTotal() {
        let total = 0;
        document.querySelectorAll('.import-item').forEach(function(item) {
            const qty = parseFloat(item.querySelector('.qty-input')?.value || 0);
            const price = parseFloat(item.querySelector('.price-input')?.value || 0);
            total += qty * price;
        });
        const target = document.getElementById('orderTotal');
        if (target) target.textContent = total.toLocaleString('vi-VN') + ' ₫';
    }

    function formatDateVN(dateString) {
        if (!dateString) return '';
        const date = new Date(dateString + 'T00:00:00');
        if (Number.isNaN(date.getTime())) return '';
        return date.toLocaleDateString('vi-VN');
    }

    function updateSalePriceHint(select) {
        const item = select.closest('.import-item');
        if (!item) return;
        const selected = select.options[select.selectedIndex];
        const salePrice = Number(selected?.dataset?.salePrice || 0);
        const totalStock = Number(selected?.dataset?.totalStock || 0);
        const validStock = Number(selected?.dataset?.validStock || 0);
        const expiry = selected?.dataset?.expiry || '';
        const note = item.querySelector('.product-price-note');

        if (!note || !select.value) {
            if (note) note.textContent = 'Chọn sản phẩm để xem tồn kho hiện tại và giá bán trong danh mục.';
            return;
        }

        const stockPart = totalStock > 0 ?
            'Tồn kho hiện tại: ' + totalStock.toLocaleString('vi-VN') + ' sản phẩm' :
            'Tồn kho hiện tại: đã hết hàng';

        const validPart = validStock > 0 ?
            'còn hạn: ' + validStock.toLocaleString('vi-VN') + ' sản phẩm' :
            'không còn hàng còn hạn';

        const expiryPart = expiry ? ', HSD gần nhất: ' + formatDateVN(expiry) : '';

        const pricePart = salePrice > 0 ?
            'Giá bán hiện tại: ' + salePrice.toLocaleString('vi-VN') + ' ₫. Hãy nhập giá nhập thấp hơn/hợp lý theo biên lợi nhuận.' :
            'Sản phẩm này chưa có giá bán trong danh mục.';

        note.textContent = stockPart + ' (' + validPart + expiryPart + '). ' + pricePart;
    }

    function bindCalculation() {
        document.querySelectorAll('.qty-input,.price-input').forEach(function(input) {
            input.removeEventListener('input', updateOrderTotal);
            input.addEventListener('input', updateOrderTotal);
        });

        document.querySelectorAll('.product-select').forEach(function(select) {
            select.onchange = function() {
                updateSalePriceHint(this);
            };
        });
    }

    const importForm = document.getElementById('importForm');
    if (importForm) {
        importForm.addEventListener('submit', function(event) {
            const today = new Date();
            today.setHours(0, 0, 0, 0);

            for (const item of document.querySelectorAll('.import-item')) {
                const product = item.querySelector('select[name*="[ma_sp]"]')?.value || '';
                const qty = Number(item.querySelector('.qty-input')?.value || 0);
                const price = Number(item.querySelector('.price-input')?.value || 0);
                const mfg = item.querySelector('.mfg-date')?.value || '';
                const exp = item.querySelector('.exp-date')?.value || '';

                if (!product || qty <= 0 || price <= 0 || !mfg || !exp) {
                    alert('Mỗi dòng nhập kho phải có sản phẩm, số lượng, đơn giá, ngày sản xuất và hạn sử dụng hợp lệ.');
                    event.preventDefault();
                    return;
                }

                const mfgDate = new Date(mfg + 'T00:00:00');
                const expDate = new Date(exp + 'T00:00:00');

                if (mfgDate > today) {
                    alert('Ngày sản xuất không được lớn hơn ngày hiện tại.');
                    event.preventDefault();
                    return;
                }

                if (expDate <= mfgDate) {
                    alert('Hạn sử dụng phải sau ngày sản xuất.');
                    event.preventDefault();
                    return;
                }

                if (expDate <= today) {
                    alert('Không được nhập lô đã hết hạn hoặc hết hạn trong hôm nay.');
                    event.preventDefault();
                    return;
                }
            }
        });
    }

    bindCalculation();
</script>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/quan_ly_vat_tu/includes/footer.php'; ?>