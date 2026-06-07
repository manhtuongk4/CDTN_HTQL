<?php
$pageTitle = 'Đổi mật khẩu - Hệ thống quản lý vật tư y tế';
require_once $_SERVER['DOCUMENT_ROOT'] . '/quan_ly_vat_tu/config/database.php';
session_start();
requireLogin();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $oldPassword = $_POST['old_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($oldPassword) || empty($newPassword) || empty($confirmPassword)) {
        $error = 'Vui lòng điền đầy đủ tất cả các trường!';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Mật khẩu mới không khớp!';
    } elseif (strlen($newPassword) < 6) {
        $error = 'Mật khẩu mới phải có ít nhất 6 ký tự!';
    } else {
        try {
            // Kiểm tra mật khẩu cũ
            $stmt = $pdo->prepare('SELECT MatKhau FROM nhanvien WHERE MaNV = ?');
            $stmt->execute([$_SESSION['MaNV']]);
            $user = $stmt->fetch();

            if ($user && $user['MatKhau'] == $oldPassword) {
                // Cập nhật mật khẩu mới
                $stmt = $pdo->prepare('UPDATE nhanvien SET MatKhau = ? WHERE MaNV = ?');
                $stmt->execute([$newPassword, $_SESSION['MaNV']]);
                $success = '✅ Đổi mật khẩu thành công!';
            } else {
                $error = '❌ Mật khẩu cũ không chính xác!';
            }
        } catch (PDOException $e) {
            $error = 'Lỗi hệ thống: ' . $e->getMessage();
        }
    }
}

include $_SERVER['DOCUMENT_ROOT'] . '/quan_ly_vat_tu/includes/header.php';
include $_SERVER['DOCUMENT_ROOT'] . '/quan_ly_vat_tu/includes/sidebar.php';
?>

<div class="page-header">
    <h1>🔐 Đổi mật khẩu</h1>
    <p>Cập nhật mật khẩu tài khoản của bạn</p>
</div>

<?php if ($success): ?>
    <div class="alert alert-success">
        <?php echo $success; ?>
        <button class="alert-close" onclick="this.parentElement.style.display='none';">×</button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger">
        <?php echo $error; ?>
        <button class="alert-close" onclick="this.parentElement.style.display='none';">×</button>
    </div>
<?php endif; ?>

<div class="card" style="max-width: 500px;">
    <form method="POST">
        <div class="form-group">
            <label for="old_password" class="required">Mật khẩu cũ</label>
            <input type="password" id="old_password" name="old_password" required>
        </div>

        <div class="form-group">
            <label for="new_password" class="required">Mật khẩu mới</label>
            <input type="password" id="new_password" name="new_password" required>
        </div>

        <div class="form-group">
            <label for="confirm_password" class="required">Xác nhận mật khẩu mới</label>
            <input type="password" id="confirm_password" name="confirm_password" required>
        </div>

        <div style="display: flex; gap: 10px;">
            <button type="submit" class="btn btn-primary">Đổi mật khẩu</button>
            <a href="<?php echo getBaseUrl(); ?>/index.php" class="btn btn-secondary">Quay lại</a>
        </div>
    </form>
</div>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/quan_ly_vat_tu/includes/footer.php'; ?>
