-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Máy chủ: 127.0.0.1
-- Thời gian đã tạo: Th6 07, 2026 lúc 08:42 PM
-- Phiên bản máy phục vụ: 10.4.32-MariaDB
-- Phiên bản PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Cơ sở dữ liệu: `quanly_vattu_yte`
--

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `chitietbanhang`
--

CREATE TABLE `chitietbanhang` (
  `MaDBH` char(9) NOT NULL,
  `MaSP` smallint(6) NOT NULL,
  `SLBH` smallint(6) NOT NULL,
  `DGBH` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `chitietbanhang`
--

INSERT INTO `chitietbanhang` (`MaDBH`, `MaSP`, `SLBH`, `DGBH`) VALUES
('080648521', 2, 50, 850000.00),
('080648522', 3, 300, 35000.00),
('080648524', 5, 100, 18500.00),
('080648524', 7, 100, 1500.00),
('080648524', 11, 100, 1500.00),
('080648524', 15, 100, 65000.00),
('080648525', 17, 100, 950000.00),
('080648525', 23, 100, 550000.00),
('080648525', 29, 100, 35000.00),
('292854692', 1, 600, 15500.00),
('292854692', 4, 500, 1200.00),
('292854693', 8, 200, 1200.00),
('292854693', 23, 100, 550000.00),
('292854693', 25, 100, 1200000.00),
('292854693', 31, 50, 40000.00),
('292854694', 18, 50, 350000.00),
('292854694', 30, 150, 45000.00),
('292854694', 34, 60, 85000.00);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `chitietmuahang`
--

CREATE TABLE `chitietmuahang` (
  `MaDMH` char(7) NOT NULL,
  `MaSP` smallint(6) NOT NULL,
  `SLMH` smallint(6) NOT NULL,
  `DGMH` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `chitietmuahang`
--

INSERT INTO `chitietmuahang` (`MaDMH`, `MaSP`, `SLMH`, `DGMH`) VALUES
('2026000', 25, 30, 1000000.00),
('2026001', 3, 400, 20000.00),
('2026002', 2, 50, 600000.00),
('2026002', 8, 50, 900.00),
('5423019', 1, 1000, 13000.00),
('5469210', 2, 100, 650000.00),
('5470001', 3, 500, 32000.00),
('5470002', 4, 2000, 1100.00),
('5470003', 5, 500, 15000.00),
('5470003', 6, 500, 22000.00),
('5470003', 7, 500, 1200.00),
('5470003', 8, 500, 1000.00),
('5470003', 9, 500, 3000.00),
('5470003', 10, 500, 2000.00),
('5470004', 11, 500, 1000.00),
('5470004', 12, 500, 1500.00),
('5470004', 13, 500, 100000.00),
('5470004', 14, 500, 12000.00),
('5470004', 15, 500, 50000.00),
('5470004', 16, 500, 10000.00),
('5470004', 17, 500, 800000.00),
('5470004', 18, 500, 280000.00),
('5470004', 19, 500, 600000.00),
('5470004', 20, 500, 75000.00),
('5470004', 21, 500, 900000.00),
('5470004', 22, 500, 800000.00),
('5470005', 23, 500, 450000.00),
('5470005', 24, 500, 500000.00),
('5470005', 25, 500, 1000000.00),
('5470005', 26, 500, 700000.00),
('5470005', 27, 500, 9000.00),
('5470005', 28, 500, 50000.00),
('5470005', 29, 500, 25000.00),
('5470005', 30, 500, 35000.00),
('5470005', 31, 500, 30000.00),
('5470005', 32, 500, 20000.00),
('5470005', 33, 500, 18000.00),
('5470005', 34, 500, 70000.00);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `donbh`
--

CREATE TABLE `donbh` (
  `MaDBH` char(9) NOT NULL,
  `NgayDat` datetime NOT NULL,
  `NgayGiao` datetime DEFAULT NULL,
  `DCGH` varchar(150) DEFAULT NULL,
  `MaHopDongThau` varchar(20) DEFAULT NULL,
  `MaXP` char(5) DEFAULT NULL,
  `MaKH` smallint(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `donbh`
--

INSERT INTO `donbh` (`MaDBH`, `NgayDat`, `NgayGiao`, `DCGH`, `MaHopDongThau`, `MaXP`, `MaKH`) VALUES
('080648521', '2026-04-20 00:00:00', '2026-05-03 00:00:00', 'Kho vật tư- BVĐK tỉnh Khánh Hòa', '125/QĐ-SYT-KH', '03001', 1),
('080648522', '2026-05-05 08:30:00', '2026-05-10 14:00:00', 'Kho A - Bệnh viện Bạch Mai', '234/QĐ-SYT-HN', '01002', 3),
('080648524', '2026-05-22 08:30:00', '2026-05-23 10:00:00', 'Kho A - Bệnh viện Chợ Rẫy', '502/QĐ-SYT-HCM', '04001', 2),
('080648525', '2026-05-25 09:00:00', '2026-05-26 00:00:00', 'Kho trung tâm - BV Đà Nẵng', '331/QĐ-SYT-DN', '02002', 4),
('292854692', '2026-04-16 00:00:00', '2026-04-27 00:00:00', 'Kho dược – Bệnh viện Chợ Rẫy', '487/QĐ-SYT-HCM', '04001', 2),
('292854693', '2026-06-02 19:42:08', '2026-06-02 00:00:00', '19 Yersin', '126/QĐ-SYT-KH', '03001', 1),
('292854694', '2026-06-02 19:44:12', NULL, '78 Giải Phóng', '235/QĐ-SYT-HN', '01002', 3);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `donmh`
--

CREATE TABLE `donmh` (
  `MaDMH` char(7) NOT NULL,
  `NgayDat` datetime NOT NULL,
  `NgayGiao` datetime DEFAULT NULL,
  `MaNCC` smallint(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `donmh`
--

INSERT INTO `donmh` (`MaDMH`, `NgayDat`, `NgayGiao`, `MaNCC`) VALUES
('2026000', '2026-05-28 21:13:04', '2026-05-28 00:00:00', 3),
('2026001', '2026-06-02 20:10:50', '2026-06-02 00:00:00', 3),
('2026002', '2026-06-02 20:14:46', NULL, 4),
('5423019', '2026-04-16 00:00:00', '2026-04-28 00:00:00', 1),
('5469210', '2026-04-17 00:00:00', '2026-05-03 00:00:00', 2),
('5470001', '2026-05-01 09:30:00', '2026-05-10 14:15:00', 3),
('5470002', '2026-05-12 10:00:00', NULL, 4),
('5470003', '2026-05-15 08:00:00', '2026-05-16 10:00:00', 1),
('5470004', '2026-05-16 09:30:00', '2026-05-17 14:00:00', 2),
('5470005', '2026-05-18 10:15:00', '2026-05-20 09:00:00', 3);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `donvitinh`
--

CREATE TABLE `donvitinh` (
  `MaDVT` tinyint(3) UNSIGNED NOT NULL,
  `TenDVT` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `donvitinh`
--

INSERT INTO `donvitinh` (`MaDVT`, `TenDVT`) VALUES
(1, 'Cái'),
(2, 'Hộp'),
(3, 'Viên'),
(4, 'Vỉ'),
(5, 'Lọ');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `khachhang`
--

CREATE TABLE `khachhang` (
  `MaKH` smallint(6) NOT NULL,
  `TenKH` varchar(60) NOT NULL,
  `DienThoai` varchar(11) DEFAULT NULL,
  `Email` varchar(50) DEFAULT NULL,
  `DiaChi` varchar(150) DEFAULT NULL,
  `MaSoThue` char(14) DEFAULT NULL,
  `MaXP` char(5) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `khachhang`
--

INSERT INTO `khachhang` (`MaKH`, `TenKH`, `DienThoai`, `Email`, `DiaChi`, `MaSoThue`, `MaXP`) VALUES
(1, 'Bệnh viện Đa khoa Tỉnh Khánh Hòa', '02839554137', 'bvdktkh@khanhhoa.gov.vn', '19 Yersin', '0300246589', '03001'),
(2, 'Bệnh viện Chợ Rẫy', '0264832601', 'bvcr@hcm.vnn.vn', '201B Nguyễn Chí Thanh', '4200356421', '04001'),
(3, 'Bệnh viện Bạch Mai', '02438693731', 'hospital@bachmai.gov.vn', '78 Giải Phóng', '0100234567', '01002'),
(4, 'Bệnh viện Đà Nẵng', '02363821118', 'benhviendanang@danang.gov.vn', '124 Hải Phòng', '0400123456', '02002');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `khohang`
--

CREATE TABLE `khohang` (
  `MaLo` varchar(30) NOT NULL,
  `MaSP` smallint(6) NOT NULL,
  `SoLuongTon` int(11) NOT NULL,
  `NgaySanXuat` date DEFAULT NULL,
  `HanSuDung` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `khohang`
--

INSERT INTO `khohang` (`MaLo`, `MaSP`, `SoLuongTon`, `NgaySanXuat`, `HanSuDung`) VALUES
('L202404_01', 1, 5000, '2024-04-01', '2027-04-01'),
('L202511_05', 1, 2000, '2025-11-15', '2028-11-15'),
('L2605_02', 2, 50, '2026-05-10', '2036-05-01'),
('L2605_05', 5, 400, '2025-05-01', '2028-05-01'),
('L2605_06', 6, 500, '2025-02-15', '2028-02-15'),
('L2605_07', 7, 300, '2025-08-20', '2029-08-20'),
('L2605_08', 8, 300, '2025-10-10', '2028-10-10'),
('L2605_08_02', 8, 50, '2026-05-26', '2027-08-18'),
('L2605_09', 9, 500, '2025-04-12', '2028-04-12'),
('L2605_10', 10, 500, '2026-01-05', '2029-01-05'),
('L2605_11', 11, 400, '2026-03-20', '2031-03-20'),
('L2605_12', 12, 500, '2026-03-22', '2031-03-22'),
('L2605_13', 13, 500, '2026-04-10', '2029-04-10'),
('L2605_14', 14, 500, '2025-11-25', '2030-11-25'),
('L2605_15', 15, 400, '2026-01-10', '2029-01-10'),
('L2605_16', 16, 500, '2026-02-14', '2029-02-14'),
('L2605_17', 17, 400, '2025-12-01', '2035-12-01'),
('L2605_18', 18, 450, '2025-09-15', '2035-09-15'),
('L2605_19', 19, 500, '2026-02-28', '2036-02-28'),
('L2605_20', 20, 500, '2025-10-20', '2035-10-20'),
('L2605_21', 21, 500, '2026-01-15', '2036-01-15'),
('L2605_22', 22, 500, '2025-11-11', '2035-11-11'),
('L2605_23', 23, 300, '2026-04-05', '2027-10-05'),
('L2605_24', 24, 500, '2026-04-10', '2027-10-10'),
('L2605_25', 25, 430, '2025-12-25', '2027-12-25'),
('L2605_25_02', 25, 0, '2026-05-24', '2026-06-04'),
('L2605_26', 26, 500, '2025-12-20', '2027-12-20'),
('L2605_27', 27, 500, '2026-03-15', '2028-03-15'),
('L2605_28', 28, 500, '2026-03-18', '2028-03-18'),
('L2605_29', 29, 400, '2025-05-20', '2035-05-20'),
('L2605_30', 30, 350, '2025-06-15', '2035-06-15'),
('L2605_31', 31, 450, '2025-07-10', '2035-07-10'),
('L2605_32', 32, 500, '2025-08-25', '2035-08-25'),
('L2605_33', 33, 500, '2026-01-05', '2036-01-05'),
('L2605_34', 34, 440, '2026-02-12', '2036-02-12'),
('L2606_03', 3, 0, '2026-06-01', '2029-02-01'),
('L2606_03_02', 3, 400, '2026-06-01', '2030-02-01'),
('OM_HEM_26A', 2, 150, '2025-12-10', '2030-12-10'),
('R2606_04', 4, 1000, '2026-06-02', '2027-06-02');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `loaisp`
--

CREATE TABLE `loaisp` (
  `MaLSP` smallint(6) NOT NULL,
  `TenLSP` varchar(60) NOT NULL,
  `MaNSP` tinyint(3) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `loaisp`
--

INSERT INTO `loaisp` (`MaLSP`, `TenLSP`, `MaNSP`) VALUES
(0, 'Thuốc nhỏ mắt', 1),
(1, 'Thuốc kháng sinh', 1),
(2, 'Thuốc giảm đau, hạ sốt', 1),
(3, 'Thuốc tiêu hóa', 1),
(4, 'Bơm kim tiêm các loại', 2),
(5, 'Bông, băng, gạc y tế', 2),
(6, 'Găng tay y tế', 2),
(7, 'Máy đo huyết áp', 3),
(8, 'Nhiệt kế điện tử', 3),
(9, 'Máy đo đường huyết', 3),
(10, 'Hóa chất xét nghiệm sinh hóa', 4),
(11, 'Hóa chất xét nghiệm huyết học', 4),
(12, 'Test thử nhanh các loại', 4),
(13, 'Panh, kẹp phẫu thuật', 5),
(14, 'Kéo y tế chuyên dụng', 5),
(15, 'Khay, hộp đựng dụng cụ Inox', 5);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `nhacc`
--

CREATE TABLE `nhacc` (
  `MaNCC` smallint(6) NOT NULL,
  `TenNCC` varchar(100) NOT NULL,
  `DienThoai` char(11) DEFAULT NULL,
  `Email` varchar(50) DEFAULT NULL,
  `DiaChi` varchar(150) DEFAULT NULL,
  `GiayPhepGPP` varchar(20) DEFAULT NULL,
  `MaXP` char(5) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `nhacc`
--

INSERT INTO `nhacc` (`MaNCC`, `TenNCC`, `DienThoai`, `Email`, `DiaChi`, `GiayPhepGPP`, `MaXP`) VALUES
(0, 'CÔNG TY CỔ PHẦN DƯỢC PHẨM DƯỢC LIỆU PHARMEDIC', '02648569465', 'contact@pharmedic.vn', '1/67 Nguyễn Văn Quá Kp5 - ĐHT, Phường Bến Thành, Hồ Chí Minh', '472/GPP', '04002'),
(1, 'Công ty CP Dược Hậu Giang', '0292364528', 'contact@dhgpharma.vn', '288 Nguyễn Văn Cừ', '134/GPP', '04002'),
(2, 'Công ty TNHH Thiết bị Y tế Omron', '02431234586', 'contact@omron.vn', 'Khu công nghiệp VSIP', '965/GPP', '01001'),
(3, 'Công ty CP Dược phẩm Trung Ương 1', '02438533115', 'info@pharbaco.com.vn', '160 Tôn Đức Thắng', '228/GPP', '01002'),
(4, 'Công ty TNHH Thiết bị Y tế Medtronic', '02839157300', 'info@medtronic.com', 'Tòa nhà Kumho Asiana', '112/GPP', '04001');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `nhanvien`
--

CREATE TABLE `nhanvien` (
  `MaNV` smallint(6) NOT NULL,
  `Hoten` varchar(60) NOT NULL,
  `TenDangNhap` varchar(50) NOT NULL,
  `MatKhau` varchar(30) NOT NULL,
  `SoDienThoai` varchar(11) NOT NULL,
  `VaiTro` tinyint(4) NOT NULL,
  `TrangThai` tinyint(4) NOT NULL,
  `Avatar` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `nhanvien`
--

INSERT INTO `nhanvien` (`MaNV`, `Hoten`, `TenDangNhap`, `MatKhau`, `SoDienThoai`, `VaiTro`, `TrangThai`, `Avatar`) VALUES
(1, 'Nguyễn Mạnh Tường', 'admin', 'Admin0868', '0868219140', 1, 1, 'assets/images/users/user_20260523085221_32fdd61a.png'),
(2, 'Trần Thanh Huyền', 'Nhanvien1', 'Nhanvien001', '0891335432', 2, 1, 'assets/images/users/user_20260523085110_1e2047dc.png');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `nhomsp`
--

CREATE TABLE `nhomsp` (
  `MaNSP` tinyint(3) UNSIGNED NOT NULL,
  `TenNSP` varchar(50) NOT NULL,
  `MoTaNhom` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `nhomsp`
--

INSERT INTO `nhomsp` (`MaNSP`, `TenNSP`, `MoTaNhom`) VALUES
(1, 'Thuốc tân dược', 'Các loại thuốc hóa dược sản xuất công nghiệp'),
(2, 'Vật tư y tế', 'Vật tư tiêu hao dùng trong khám chữa bệnh'),
(3, 'Thiết bị y tế', 'Các loại máy móc, trang thiết bị phục vụ chẩn đoán'),
(4, 'Hóa chất xét nghiệm', 'Các loại hóa chất, dung môi, sinh phẩm'),
(5, 'Dụng cụ y khoa', 'Dụng cụ phẫu thuật, dụng cụ khám chuyên khoa');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `sanpham`
--

CREATE TABLE `sanpham` (
  `MaSP` smallint(6) NOT NULL,
  `TenSP` varchar(150) NOT NULL,
  `DonGia` decimal(10,2) NOT NULL,
  `HoatChatChinh` varchar(80) DEFAULT NULL,
  `HamLuong` varchar(30) DEFAULT NULL,
  `MoTaCT` varchar(255) DEFAULT NULL,
  `SoDangKy` varchar(20) DEFAULT NULL,
  `LaHangKiemSoat` tinyint(1) NOT NULL,
  `DieuKienBaoQuan` varchar(50) DEFAULT NULL,
  `XuatXu` varchar(50) DEFAULT NULL,
  `CongTySanXuat` varchar(100) DEFAULT NULL,
  `HinhAnh` varchar(255) DEFAULT NULL,
  `MaLSP` smallint(6) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `sanpham`
--

INSERT INTO `sanpham` (`MaSP`, `TenSP`, `DonGia`, `HoatChatChinh`, `HamLuong`, `MoTaCT`, `SoDangKy`, `LaHangKiemSoat`, `DieuKienBaoQuan`, `XuatXu`, `CongTySanXuat`, `HinhAnh`, `MaLSP`) VALUES
(0, 'Thuốc nhỏ mắt Natri clorid 0,9%', 5000.00, 'Natri clorid 0,9%', '10', 'Vệ sinh mắt: Rửa trôi bụi bẩn, ghèn dử mắt, làm dịu mắt khi bị khô rát, mỏi mắt.\r\nHỗ trợ điều trị: Phòng ngừa các bệnh lý về mắt (đau mắt đỏ), rửa hốc mũi, hỗ trợ giảm triệu chứng nghẹt mũi, sổ mũi, viêm mũi dị ứng.\r\nChống chỉ định: Không dùng cho người c', 'VN-89310', 0, 'bảo quản thuốc ở nhiệt độ không quá 30 độ C, chỉ s', 'Việt Nam', 'CÔNG TY CỔ PHẦN DƯỢC PHẨM DƯỢC LIỆU PHARMEDIC', 'assets/images/products/thuoc_tri_seo_tham_sau_bong_2_fe29036442-20260605201520-34000a.jpeg', 0),
(1, 'Paracetamol 500mg', 15500.00, 'Paracetamol', '500mg', 'Thuốc giảm đau, hạ sốt', 'VD-52487-25', 0, 'Nhiệt độ phòng, nơi khô ráo, thoáng mát', 'Việt Nam', 'Công ty CP Dược Hậu Giang', 'assets/images/products/paracetamol_500mg-20260523084408-72f024.webp', 2),
(2, 'Máy đo huyết áp điện tử Omron HEM-7120', 850000.00, '', '', 'Đo huyết áp bắp tay tự động', 'TBYT-2026', 0, 'Tránh ẩm ướt', 'Nhật Bản', 'Omron Healthcare Co., Ltd.', 'assets/images/products/omron-hem-7120-2-20260523084352-7d0b38.webp', 7),
(3, 'Kháng sinh Amoxicillin 500mg', 35000.00, 'Amoxicillin trihydrate', '500mg', 'Kháng sinh nhóm penicillin, trị nhiễm khuẩn', 'VD-12345-21', 1, 'Nơi khô ráo, tránh ánh sáng trực tiếp', 'Ấn Độ', 'Ranbaxy Laboratories Ltd.', 'assets/images/products/amoxici-20260523084323-006b2a.jpg', 1),
(4, 'Bơm tiêm nhựa dùng một lần 5ml', 1200.00, '', '', 'Bơm tiêm tiệt trùng bằng khí EO', 'TBYT-1025', 0, 'Nơi thoáng mát, tránh nhiệt độ cao', 'Việt Nam', 'Công ty CP Thiết bị y tế Vinahankook', 'assets/images/products/bom_tiem_5-20260523065114-af51f9.webp', 4),
(5, 'Augmentin 1g', 18500.00, 'Amoxicillin + Clavulanate', '1g', 'Kháng sinh phổ rộng trị nhiễm khuẩn hô hấp, tiết niệu', 'VN-19642-16', 1, 'Dưới 25°C, tránh ẩm', 'Pháp', 'GlaxoSmithKline (GSK)', 'assets/images/products/00000964_augmentin_1g_9477_63aa_large_13876bb403-20260528194522-c73b3f.webp', 1),
(6, 'Zinnat 500mg', 25000.00, 'Cefuroxime', '500mg', 'Kháng sinh nhóm Cephalosporin thế hệ 2', 'VN-17551-13', 1, 'Bảo quản dưới 30°C', 'Anh', 'GlaxoSmithKline (GSK)', 'assets/images/products/8243-20260528194434-4016bd.webp', 1),
(7, 'Panadol Extra', 1500.00, 'Paracetamol, Caffeine', '500mg/65mg', 'Giảm đau nhanh, hạ sốt, đau đầu', 'VD-21345-14', 0, 'Nơi khô ráo, dưới 30°C', 'Việt Nam', 'Sanofi Aventis', 'assets/images/products/images-2-20260528194404-bf6331.jpg', 2),
(8, 'Ibuprofen 400mg Domesco', 1200.00, 'Ibuprofen', '400mg', 'Thuốc kháng viêm không steroid (NSAID), giảm đau', 'VD-20121-13', 0, 'Nhiệt độ phòng', 'Việt Nam', 'Domesco', 'assets/images/products/500-6037d78a9402e33868c3355b-dbuikpbdz3qjd25fj6z2d3ql-20260528194334-af644d.jpeg', 2),
(9, 'Thuốc trị tiêu chảy Smecta', 3800.00, 'Diosmectite', '3g', 'Điều trị tiêu chảy cấp và mãn tính', 'VN-18234-14', 0, 'Nơi khô ráo, tránh ánh sáng', 'Pháp', 'Ipsen Pharma', 'assets/images/products/images-1-20260528194242-58328a.jpg', 3),
(10, 'Oresol 245', 2500.00, 'Glucose, Natri, Kali', '24.5g', 'Bù nước và điện giải', 'VD-14562-11', 0, 'Nhiệt độ phòng, tránh ẩm', 'Việt Nam', 'Dược phẩm TW1', 'assets/images/products/images-20260528194203-c8695f.jpg', 3),
(11, 'Bơm tiêm nhựa 1ml (Tiêm Insulin)', 1500.00, '', '', 'Bơm tiêm Insulin 1ml U-100 tiệt trùng EO', 'TBYT-1026', 0, 'Nơi khô ráo, sạch sẽ', 'Việt Nam', 'Vinahankook', 'assets/images/products/bom-kim-tiem-insulin-su-dung-1-lan-vinahankook-1ml-cc-30g-x-1-2-hop-100cai-10-638884506152966226-20260528194132-8b3ccc.jpg', 4),
(12, 'Bơm tiêm nhựa 10ml', 2000.00, '', '', 'Bơm tiêm 10ml dùng 1 lần, tiệt trùng', 'TBYT-1027', 0, 'Nơi khô ráo, sạch sẽ', 'Việt Nam', 'Vinahankook', 'assets/images/products/1436hop-bom-tiem-10ml-hop-jpg-20260528194046-a6e545.jpeg', 4),
(13, 'Bông y tế Bạch Tuyết 1kg', 120000.00, '', '', 'Bông gòn y tế cắt sẵn, thấm hút tốt', 'TBYT-2001', 0, 'Nơi khô, tránh bụi bẩn', 'Việt Nam', 'Bông Bạch Tuyết', 'assets/images/products/b1a46eebc32661a16a4794b7b92f8ec9-20260528193852-a380df.jpg', 5),
(14, 'Băng thun y tế 3 móc (10cm x 5m)', 15000.00, '', '', 'Băng thun bó gót, cố định chấn thương', 'TBYT-2002', 0, 'Nhiệt độ phòng', 'Việt Nam', 'Bảo Thạch', 'assets/images/products/bang_thun-20260528193804-0863e7.png', 5),
(15, 'Găng tay y tế Vglove (Hộp 100 cái)', 65000.00, '', '', 'Găng tay cao su y tế có bột', 'TBYT-3001', 0, 'Tránh ánh nắng mặt trời', 'Việt Nam', 'VRG Khải Hoàn', 'assets/images/products/gang-tay-y-te-co-bot-24cm-vglove-5-0g-hop-100-cai-20260526211239-81e909.webp', 6),
(16, 'Găng tay phẫu thuật tiệt trùng Merufa', 12000.00, '', '', 'Găng phẫu thuật tiệt trùng Size 7.5', 'TBYT-3002', 0, 'Bảo quản nơi mát, khô ráo', 'Việt Nam', 'Merufa', 'assets/images/products/gang-tay-phau-thuat-merufa-1-20260526211203-d047bf.jpg', 6),
(17, 'Máy đo huyết áp điện tử Microlife B3 Basic', 950000.00, '', '', 'Đo bắp tay, cảnh báo rối loạn nhịp tim', 'TBYT-4456', 0, 'Tránh va đập, nơi khô ráo', 'Thụy Sĩ', 'Microlife', 'assets/images/products/tu-dong-microlife-b3-basic-2-638887045804050763-20260526211126-9c1ae6.jpg', 7),
(18, 'Máy đo huyết áp cơ Spirit CK-111', 350000.00, '', '', 'Đo huyết áp cơ kết hợp ống nghe y tế', 'TBYT-7812', 0, 'Tránh nơi có độ ẩm cao', 'Đài Loan', 'Spirit', 'assets/images/products/ck111-av2-min-20260526211041-efaf4e.png', 7),
(19, 'Nhiệt kế hồng ngoại đo trán Microlife FR1MF1', 750000.00, '', '', 'Đo trán không chạm, tốc độ 1 giây', 'TBYT-6652', 0, 'Tháo pin nếu không dùng lâu', 'Thụy Sĩ', 'Microlife', 'assets/images/products/nhiet-ke-microlife-fr1mf1-1-20260526210943-f5472c.jpg', 8),
(20, 'Nhiệt kế điện tử kẹp nách Omron MC-246', 90000.00, '', '', 'Đo nhiệt độ nách, miệng, an toàn cho bé', 'TBYT-8891', 0, 'Lau sạch đầu dò sau dùng', 'Nhật Bản', 'Omron Healthcare', 'assets/images/products/nhiet_ke_dien_tu_omron_mc-246_476f9ddff6dc4539aee78377f2addb4d_grande-20260526210859-24db3f.jpg', 8),
(21, 'Máy đo đường huyết Accu-Chek Instant', 1150000.00, '', '', 'Máy đo đường huyết cá nhân, độ chính xác cao', 'TBYT-1123', 0, 'Tránh nhiệt độ quá cao', 'Đức', 'Roche Diagnostics', 'assets/images/products/143339-cobo2-accu-check-instant-20260526210819-f8cb56.jpg', 9),
(22, 'Máy đo đường huyết OneTouch Select Simple', 950000.00, '', '', 'Đo đường huyết không cần cài đặt mã code', 'TBYT-1145', 0, 'Bảo quản hộp kín', 'Mỹ', 'Johnson & Johnson', 'assets/images/products/may-do-duong-huyet-onetouch-20260526210656-6fc65f.png', 9),
(23, 'Hóa chất đo Glucose (Glucose GOD FS)', 550000.00, 'Glucose Oxidase', 'R1 4x25ml', 'Thuốc thử phân tích định lượng Glucose', 'HCXN-1001', 0, 'Bảo quản 2-8°C', 'Đức', 'DiaSys', 'assets/images/products/17-hoa-chat-human-20260526210548-dcc396.jpg', 10),
(24, 'Hóa chất đo Cholesterol (Cholesterol FS)', 600000.00, 'Cholesterol Esterase', 'R1 4x25ml', 'Thuốc thử định lượng Cholesterol toàn phần', 'HCXN-1002', 0, 'Bảo quản 2-8°C', 'Đức', 'DiaSys', 'assets/images/products/cholesterol-powder-bioreagent-suitable-for-cell-culture--99-20260526210428-ccc56b.jpg', 10),
(25, 'Dung dịch pha loãng (Diluent) Mindray 20L', 1200000.00, '', '20 Lít', 'Dung dịch pha loãng cho máy huyết học Mindray', 'HCXN-2001', 0, 'Nhiệt độ phòng (15-30°C)', 'Trung Quốc', 'Mindray', 'assets/images/products/m_53d_diluent_505x505-20260526210333-ba0060.jpg', 11),
(26, 'Dung dịch phá vỡ hồng cầu (Lyse) Mindray', 850000.00, '', '500 ml', 'Dung dịch lyse chuyên dụng máy Mindray', 'HCXN-2002', 0, 'Nhiệt độ phòng (15-30°C)', 'Trung Quốc', 'Mindray', 'assets/images/products/m-52_lh_lyse-20260526205931-56d76c.jpg', 11),
(27, 'Test nhanh Viêm gan B (HBsAg) Abon', 12000.00, '', '1 Test', 'Khay thử xét nghiệm định tính HBsAg', 'TBYT-4001', 0, 'Bảo quản 2-30°C', 'Trung Quốc', 'Abon Biopharm', 'assets/images/products/14830291941618_test_thu_viem_gan_b_hbsag_abon-305738f18698-20260526205456-9f3737.jpg', 12),
(28, 'Test nhanh Sốt xuất huyết (Dengue NS1) SD', 65000.00, '', '1 Test', 'Khay thử phát hiện kháng nguyên Dengue NS1', 'TBYT-4002', 0, 'Bảo quản 1-30°C', 'Hàn Quốc', 'SD Biosensor', 'assets/images/products/dengue-ns1-test-kit-20260526205422-1d81d2.jpg', 12),
(29, 'Kẹp phẫu tích có răng 14cm', 35000.00, '', '', 'Kẹp phẫu tích Inox, dùng trong phẫu thuật', 'DCYK-1001', 0, 'Tiệt trùng sau khi sử dụng', 'Pakistan', 'Hilbro', 'assets/images/products/brand-devemed-1-20260526205315-9f09da.png', 13),
(30, 'Panh cầm máu thẳng 16cm', 45000.00, '', '', 'Panh thẳng kẹp máu, hợp kim không gỉ', 'DCYK-1002', 0, 'Tiệt trùng sau khi sử dụng', 'Pakistan', 'Hilbro', 'assets/images/products/20220106_zuvz1kmhmjdkzr8juejaexiw-20260526205026-df2e3f.jpeg', 13),
(31, 'Kéo phẫu thuật cong nhọn 14cm', 40000.00, '', '', 'Kéo phẫu thuật cong 2 mũi nhọn Inox', 'DCYK-2001', 0, 'Tiệt trùng sau khi sử dụng', 'Pakistan', 'Hilbro', 'assets/images/products/jjt7b4kvz2bne-20260526204919-fa6615.jpg', 14),
(32, 'Kéo cắt chỉ y tế thẳng 12cm', 30000.00, '', '', 'Kéo cắt chỉ khâu vết thương', 'DCYK-2002', 0, 'Tiệt trùng sau khi sử dụng', 'Pakistan', 'Hilbro', 'assets/images/products/k-o-c-t-ch--one-touch-20260526204825-193475.jpg', 14),
(33, 'Khay hạt đậu Inox 20cm', 25000.00, '', '', 'Khay đựng dung dịch, rác thải y tế nhỏ', 'DCYK-3001', 0, 'Rửa sạch, lau khô sau dùng', 'Việt Nam', 'TNE', 'assets/images/products/khay-dung-hat-dau-inox-20260526204737-335c26.jpg', 15),
(34, 'Hộp chữ nhật Inox có nắp (20x10x5cm)', 85000.00, '', '', 'Hộp lưu trữ bông cồn, dụng cụ phẫu thuật nhỏ', 'DCYK-3002', 0, 'Rửa sạch, lau khô sau dùng', 'Việt Nam', 'TNE', 'assets/images/products/hop_cn-20260526204645-f595e0.jpg', 15);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `stock_movements`
--

CREATE TABLE `stock_movements` (
  `ID` int(11) NOT NULL,
  `OrderType` varchar(10) NOT NULL,
  `MaDon` varchar(50) NOT NULL,
  `MaSP` varchar(50) NOT NULL,
  `MaLo` varchar(100) NOT NULL,
  `SoLuong` int(11) NOT NULL,
  `MovementType` varchar(10) NOT NULL,
  `CreatedAt` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `stock_movements`
--

INSERT INTO `stock_movements` (`ID`, `OrderType`, `MaDon`, `MaSP`, `MaLo`, `SoLuong`, `MovementType`, `CreatedAt`) VALUES
(3, 'IMPORT', '202600001', '25', 'L2605_25_02', 30, 'IN', '2026-05-29 02:13:04'),
(4, 'EXPORT', '292854693', '23', 'L2605_23', 100, 'OUT', '2026-06-03 00:42:08'),
(5, 'EXPORT', '292854693', '25', 'L2605_25_02', 30, 'OUT', '2026-06-03 00:42:08'),
(6, 'EXPORT', '292854693', '25', 'L2605_25', 70, 'OUT', '2026-06-03 00:42:08'),
(7, 'EXPORT', '292854693', '8', 'L2605_08', 200, 'OUT', '2026-06-03 00:42:08'),
(8, 'EXPORT', '292854693', '31', 'L2605_31', 50, 'OUT', '2026-06-03 00:42:08'),
(9, 'EXPORT', '292854694', '34', 'L2605_34', 60, 'OUT', '2026-06-03 00:44:12'),
(10, 'EXPORT', '292854694', '18', 'L2605_18', 50, 'OUT', '2026-06-03 00:44:12'),
(11, 'EXPORT', '292854694', '30', 'L2605_30', 150, 'OUT', '2026-06-03 00:44:12'),
(13, 'IMPORT', '2026001', '3', 'L2606_03_02', 400, 'IN', '2026-06-03 01:10:50'),
(14, 'IMPORT', '2026002', '8', 'L2605_08_02', 50, 'IN', '2026-06-03 01:14:46'),
(15, 'IMPORT', '2026002', '2', 'L2605_02', 50, 'IN', '2026-06-03 01:14:46');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `tinh`
--

CREATE TABLE `tinh` (
  `MaTinh` char(2) NOT NULL,
  `TenTinh` varchar(30) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `tinh`
--

INSERT INTO `tinh` (`MaTinh`, `TenTinh`) VALUES
('01', 'Hà Nội'),
('02', 'Đà Nẵng'),
('03', 'Khánh Hòa'),
('04', 'Hồ Chí Minh'),
('05', 'Hải Phòng');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `xaphuong`
--

CREATE TABLE `xaphuong` (
  `MaXP` char(5) NOT NULL,
  `TenXP` varchar(40) NOT NULL,
  `MaTinh` char(2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `xaphuong`
--

INSERT INTO `xaphuong` (`MaXP`, `TenXP`, `MaTinh`) VALUES
('01001', 'Phường Tràng Tiền', '01'),
('01002', 'Phường Hàng Bài', '01'),
('02001', 'Phường Hải Châu I', '02'),
('02002', 'Phường Thạch Thang', '02'),
('03001', 'Phường Lộc Thọ', '03'),
('03002', 'Phường Phước Hải', '03'),
('04001', 'Phường Bến Nghé', '04'),
('04002', 'Phường Bến Thành', '04');

--
-- Chỉ mục cho các bảng đã đổ
--

--
-- Chỉ mục cho bảng `chitietbanhang`
--
ALTER TABLE `chitietbanhang`
  ADD PRIMARY KEY (`MaDBH`,`MaSP`),
  ADD KEY `MaSP` (`MaSP`);

--
-- Chỉ mục cho bảng `chitietmuahang`
--
ALTER TABLE `chitietmuahang`
  ADD PRIMARY KEY (`MaDMH`,`MaSP`),
  ADD KEY `MaSP` (`MaSP`);

--
-- Chỉ mục cho bảng `donbh`
--
ALTER TABLE `donbh`
  ADD PRIMARY KEY (`MaDBH`),
  ADD KEY `MaXP` (`MaXP`),
  ADD KEY `MaKH` (`MaKH`);

--
-- Chỉ mục cho bảng `donmh`
--
ALTER TABLE `donmh`
  ADD PRIMARY KEY (`MaDMH`),
  ADD KEY `MaNCC` (`MaNCC`);

--
-- Chỉ mục cho bảng `donvitinh`
--
ALTER TABLE `donvitinh`
  ADD PRIMARY KEY (`MaDVT`);

--
-- Chỉ mục cho bảng `khachhang`
--
ALTER TABLE `khachhang`
  ADD PRIMARY KEY (`MaKH`),
  ADD KEY `MaXP` (`MaXP`);

--
-- Chỉ mục cho bảng `khohang`
--
ALTER TABLE `khohang`
  ADD PRIMARY KEY (`MaLo`,`MaSP`),
  ADD KEY `MaSP` (`MaSP`);

--
-- Chỉ mục cho bảng `loaisp`
--
ALTER TABLE `loaisp`
  ADD PRIMARY KEY (`MaLSP`),
  ADD KEY `MaNSP` (`MaNSP`);

--
-- Chỉ mục cho bảng `nhacc`
--
ALTER TABLE `nhacc`
  ADD PRIMARY KEY (`MaNCC`),
  ADD KEY `MaXP` (`MaXP`);

--
-- Chỉ mục cho bảng `nhanvien`
--
ALTER TABLE `nhanvien`
  ADD PRIMARY KEY (`MaNV`);

--
-- Chỉ mục cho bảng `nhomsp`
--
ALTER TABLE `nhomsp`
  ADD PRIMARY KEY (`MaNSP`);

--
-- Chỉ mục cho bảng `sanpham`
--
ALTER TABLE `sanpham`
  ADD PRIMARY KEY (`MaSP`),
  ADD KEY `MaLSP` (`MaLSP`);

--
-- Chỉ mục cho bảng `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `idx_order` (`OrderType`,`MaDon`),
  ADD KEY `idx_lot` (`MaLo`,`MaSP`);

--
-- Chỉ mục cho bảng `tinh`
--
ALTER TABLE `tinh`
  ADD PRIMARY KEY (`MaTinh`);

--
-- Chỉ mục cho bảng `xaphuong`
--
ALTER TABLE `xaphuong`
  ADD PRIMARY KEY (`MaXP`),
  ADD KEY `MaTinh` (`MaTinh`);

--
-- AUTO_INCREMENT cho các bảng đã đổ
--

--
-- AUTO_INCREMENT cho bảng `nhanvien`
--
ALTER TABLE `nhanvien`
  MODIFY `MaNV` smallint(6) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT cho bảng `stock_movements`
--
ALTER TABLE `stock_movements`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- Các ràng buộc cho các bảng đã đổ
--

--
-- Các ràng buộc cho bảng `chitietbanhang`
--
ALTER TABLE `chitietbanhang`
  ADD CONSTRAINT `chitietbanhang_ibfk_1` FOREIGN KEY (`MaDBH`) REFERENCES `donbh` (`MaDBH`),
  ADD CONSTRAINT `chitietbanhang_ibfk_2` FOREIGN KEY (`MaSP`) REFERENCES `sanpham` (`MaSP`);

--
-- Các ràng buộc cho bảng `chitietmuahang`
--
ALTER TABLE `chitietmuahang`
  ADD CONSTRAINT `chitietmuahang_ibfk_1` FOREIGN KEY (`MaDMH`) REFERENCES `donmh` (`MaDMH`),
  ADD CONSTRAINT `chitietmuahang_ibfk_2` FOREIGN KEY (`MaSP`) REFERENCES `sanpham` (`MaSP`);

--
-- Các ràng buộc cho bảng `donbh`
--
ALTER TABLE `donbh`
  ADD CONSTRAINT `donbh_ibfk_1` FOREIGN KEY (`MaXP`) REFERENCES `xaphuong` (`MaXP`),
  ADD CONSTRAINT `donbh_ibfk_2` FOREIGN KEY (`MaKH`) REFERENCES `khachhang` (`MaKH`);

--
-- Các ràng buộc cho bảng `donmh`
--
ALTER TABLE `donmh`
  ADD CONSTRAINT `donmh_ibfk_1` FOREIGN KEY (`MaNCC`) REFERENCES `nhacc` (`MaNCC`);

--
-- Các ràng buộc cho bảng `khachhang`
--
ALTER TABLE `khachhang`
  ADD CONSTRAINT `khachhang_ibfk_1` FOREIGN KEY (`MaXP`) REFERENCES `xaphuong` (`MaXP`);

--
-- Các ràng buộc cho bảng `khohang`
--
ALTER TABLE `khohang`
  ADD CONSTRAINT `khohang_ibfk_1` FOREIGN KEY (`MaSP`) REFERENCES `sanpham` (`MaSP`);

--
-- Các ràng buộc cho bảng `loaisp`
--
ALTER TABLE `loaisp`
  ADD CONSTRAINT `loaisp_ibfk_1` FOREIGN KEY (`MaNSP`) REFERENCES `nhomsp` (`MaNSP`);

--
-- Các ràng buộc cho bảng `nhacc`
--
ALTER TABLE `nhacc`
  ADD CONSTRAINT `nhacc_ibfk_1` FOREIGN KEY (`MaXP`) REFERENCES `xaphuong` (`MaXP`);

--
-- Các ràng buộc cho bảng `sanpham`
--
ALTER TABLE `sanpham`
  ADD CONSTRAINT `sanpham_ibfk_1` FOREIGN KEY (`MaLSP`) REFERENCES `loaisp` (`MaLSP`);

--
-- Các ràng buộc cho bảng `xaphuong`
--
ALTER TABLE `xaphuong`
  ADD CONSTRAINT `xaphuong_ibfk_1` FOREIGN KEY (`MaTinh`) REFERENCES `tinh` (`MaTinh`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
