<?php
$currentUri = $_SERVER['REQUEST_URI'] ?? '';
$currentFile = basename($_SERVER['PHP_SELF'] ?? '');

if (!function_exists('sidebarContainsAny')) {
    function sidebarContainsAny($uri, array $patterns)
    {
        foreach ($patterns as $pattern) {
            if (strpos($uri, $pattern) !== false) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('sidebarActiveClass')) {
    function sidebarActiveClass($uri, array $patterns)
    {
        return sidebarContainsAny($uri, $patterns) ? ' active' : '';
    }
}

$isDashboardActive = $currentFile === 'index.php';
$isAdminGroupActive = sidebarContainsAny($currentUri, ['manage_users']);
$isCatalogGroupActive = sidebarContainsAny($currentUri, ['categories', 'products', 'units', 'locations']);
$isPartnersGroupActive = sidebarContainsAny($currentUri, ['suppliers', 'hospitals']);
$isWarehouseGroupActive = sidebarContainsAny($currentUri, ['import_order', 'export_order', 'inventory', 'alerts']);
$isReportsGroupActive = sidebarContainsAny($currentUri, ['revenue_costs', 'debts']);
?>

<style>
    .sidebar {
        width: var(--app-sidebar-width, 286px);
        flex: 0 0 var(--app-sidebar-width, 286px);
        position: sticky;
        top: var(--app-header-height, 68px);
        height: calc(100vh - var(--app-header-height, 68px));
        overflow-y: auto;
        overflow-x: hidden;
        overscroll-behavior: contain;
        background: #fff;
        border-right: 1px solid var(--border-color);
        padding: 18px 14px;
        z-index: 900;
        scroll-behavior: auto;
    }

    .sidebar::-webkit-scrollbar {
        width: 6px;
    }

    .sidebar::-webkit-scrollbar-thumb {
        background: rgba(102, 126, 234, .28);
        border-radius: 99px;
    }

    :root[data-theme="dark"] .sidebar {
        background: #111827;
        border-color: #263244;
    }

    .sidebar-menu {
        margin: 0;
        padding: 0;
        list-style: none;
        display: grid;
        gap: 8px;
    }

    .sidebar-brand-mini {
        padding: 10px 10px 16px;
        margin-bottom: 6px;
        border-bottom: 1px solid var(--border-color);
    }

    .sidebar-brand-mini .eyebrow {
        color: var(--text-light);
        font-size: 11px;
        letter-spacing: .6px;
        text-transform: uppercase;
        font-weight: 700;
        margin-bottom: 5px;
    }

    .sidebar-brand-mini .title {
        color: var(--text-dark);
        font-size: 14px;
        font-weight: 700;
    }

    .sidebar-item,
    .sidebar-section-title {
        list-style: none;
    }

    .sidebar-section-title {
        margin: 12px 8px 4px;
        color: var(--text-light);
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .65px;
    }

    .sidebar-link,
    .sidebar-parent-btn {
        width: 100%;
        border: 0;
        background: transparent;
        color: var(--text-dark);
        display: flex;
        align-items: center;
        gap: 11px;
        min-height: 44px;
        padding: 10px 12px;
        border-radius: 10px;
        text-decoration: none;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: background .22s, color .22s, transform .22s;
        text-align: left;
        font-family: inherit;
    }

    .sidebar-link:hover,
    .sidebar-parent-btn:hover {
        background: rgba(102, 126, 234, .10);
        color: var(--primary-color);
        transform: translateX(2px);
    }

    .sidebar-link.active,
    .sidebar-parent-btn.active {
        background: linear-gradient(135deg, rgba(102, 126, 234, .16), rgba(118, 75, 162, .12));
        color: var(--primary-color);
    }

    .sidebar-icon {
        width: 30px;
        height: 30px;
        border-radius: 9px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        background: var(--light-bg);
        font-size: 15px;
    }

    .sidebar-link.active .sidebar-icon,
    .sidebar-parent-btn.active .sidebar-icon {
        background: rgba(102, 126, 234, .18);
    }

    .sidebar-text {
        flex: 1;
        min-width: 0;
    }

    .sidebar-chevron {
        color: var(--text-light);
        font-size: 12px;
        transform: rotate(-90deg);
        transition: transform .2s ease;
    }

    .sidebar-group.open .sidebar-chevron {
        transform: rotate(0deg);
    }

    .sidebar-submenu {
        display: none;
        margin: 6px 0 4px 42px;
        padding: 0 0 0 11px;
        border-left: 1px dashed var(--border-color);
        list-style: none;
    }

    .sidebar-group.open .sidebar-submenu {
        display: grid;
        gap: 3px;
    }

    .sidebar-submenu .sidebar-link {
        min-height: 36px;
        padding: 8px 10px;
        font-size: 13px;
        font-weight: 500;
        border-radius: 8px;
    }

    .sidebar-submenu .sidebar-icon {
        width: 24px;
        height: 24px;
        font-size: 12px;
        background: transparent;
    }

    :root[data-theme="dark"] .sidebar-icon {
        background: #0b1220;
    }

    :root[data-theme="dark"] .sidebar-link:hover,
    :root[data-theme="dark"] .sidebar-parent-btn:hover,
    :root[data-theme="dark"] .sidebar-link.active,
    :root[data-theme="dark"] .sidebar-parent-btn.active {
        background: rgba(102, 126, 234, .18);
    }

    @media (max-width: 1000px) {
        .sidebar {
            position: fixed;
            left: 0;
            top: var(--app-header-height, 68px);
            bottom: 0;
            transform: translateX(0);
            width: 260px;
            flex-basis: 260px;
        }
    }
</style>

<!-- Sidebar -->
<aside class="sidebar" id="appSidebar">
    <div class="sidebar-brand-mini">
        <div class="eyebrow">Store Inventory</div>
        <div class="title">Quản lý vật tư y tế</div>
    </div>

    <ul class="sidebar-menu">
        <li class="sidebar-section-title">MENU CHÍNH</li>
        <li class="sidebar-item">
            <a href="<?php echo getBaseUrl(); ?>/index.php" class="sidebar-link<?php echo $isDashboardActive ? ' active' : ''; ?>">
                <span class="sidebar-icon">📊</span>
                <span class="sidebar-text">Trang chủ</span>
            </a>
        </li>

        <?php if (isAdmin()): ?>
            <li class="sidebar-item sidebar-group<?php echo $isAdminGroupActive ? ' open' : ''; ?>" data-sidebar-group="admin">
                <button type="button" class="sidebar-parent-btn<?php echo $isAdminGroupActive ? ' active' : ''; ?>" aria-expanded="<?php echo $isAdminGroupActive ? 'true' : 'false'; ?>">
                    <span class="sidebar-icon">🛡️</span>
                    <span class="sidebar-text">Quản trị hệ thống</span>
                    <span class="sidebar-chevron">⌄</span>
                </button>
                <ul class="sidebar-submenu">
                    <li>
                        <a href="<?php echo getBaseUrl(); ?>/modules/admin/manage_users.php" class="sidebar-link<?php echo sidebarActiveClass($currentUri, ['manage_users']); ?>">
                            <span class="sidebar-icon">👥</span>
                            <span class="sidebar-text">Quản lý nhân sự</span>
                        </a>
                    </li>
                </ul>
            </li>
        <?php endif; ?>

        <li class="sidebar-item sidebar-group<?php echo $isCatalogGroupActive ? ' open' : ''; ?>" data-sidebar-group="catalog">
            <button type="button" class="sidebar-parent-btn<?php echo $isCatalogGroupActive ? ' active' : ''; ?>" aria-expanded="<?php echo $isCatalogGroupActive ? 'true' : 'false'; ?>">
                <span class="sidebar-icon">📚</span>
                <span class="sidebar-text">Quản lý danh mục</span>
                <span class="sidebar-chevron">⌄</span>
            </button>
            <ul class="sidebar-submenu">
                <li><a href="<?php echo getBaseUrl(); ?>/modules/catalog/categories.php" class="sidebar-link<?php echo sidebarActiveClass($currentUri, ['categories']); ?>"><span class="sidebar-icon">📂</span><span class="sidebar-text">Danh mục sản phẩm</span></a></li>
                <li><a href="<?php echo getBaseUrl(); ?>/modules/catalog/products.php" class="sidebar-link<?php echo sidebarActiveClass($currentUri, ['products', 'product_details']); ?>"><span class="sidebar-icon">📦</span><span class="sidebar-text">Sản phẩm / Vật tư</span></a></li>
                <li><a href="<?php echo getBaseUrl(); ?>/modules/catalog/units.php" class="sidebar-link<?php echo sidebarActiveClass($currentUri, ['units']); ?>"><span class="sidebar-icon">📏</span><span class="sidebar-text">Đơn vị tính</span></a></li>
                <li><a href="<?php echo getBaseUrl(); ?>/modules/catalog/locations.php" class="sidebar-link<?php echo sidebarActiveClass($currentUri, ['locations']); ?>"><span class="sidebar-icon">🗺️</span><span class="sidebar-text">Địa điểm</span></a></li>
            </ul>
        </li>

        <li class="sidebar-item sidebar-group<?php echo $isPartnersGroupActive ? ' open' : ''; ?>" data-sidebar-group="partners">
            <button type="button" class="sidebar-parent-btn<?php echo $isPartnersGroupActive ? ' active' : ''; ?>" aria-expanded="<?php echo $isPartnersGroupActive ? 'true' : 'false'; ?>">
                <span class="sidebar-icon">🤝</span>
                <span class="sidebar-text">Quản lý đối tác</span>
                <span class="sidebar-chevron">⌄</span>
            </button>
            <ul class="sidebar-submenu">
                <li><a href="<?php echo getBaseUrl(); ?>/modules/partners/suppliers.php" class="sidebar-link<?php echo sidebarActiveClass($currentUri, ['suppliers']); ?>"><span class="sidebar-icon">🏭</span><span class="sidebar-text">Nhà cung cấp</span></a></li>
                <li><a href="<?php echo getBaseUrl(); ?>/modules/partners/hospitals.php" class="sidebar-link<?php echo sidebarActiveClass($currentUri, ['hospitals']); ?>"><span class="sidebar-icon">🏥</span><span class="sidebar-text">Khách hàng</span></a></li>
            </ul>
        </li>

        <li class="sidebar-item sidebar-group<?php echo $isWarehouseGroupActive ? ' open' : ''; ?>" data-sidebar-group="warehouse">
            <button type="button" class="sidebar-parent-btn<?php echo $isWarehouseGroupActive ? ' active' : ''; ?>" aria-expanded="<?php echo $isWarehouseGroupActive ? 'true' : 'false'; ?>">
                <span class="sidebar-icon">🏬</span>
                <span class="sidebar-text">Nghiệp vụ kho</span>
                <span class="sidebar-chevron">⌄</span>
            </button>
            <ul class="sidebar-submenu">
                <li><a href="<?php echo getBaseUrl(); ?>/modules/warehouse/import_order.php" class="sidebar-link<?php echo sidebarActiveClass($currentUri, ['import_order']); ?>"><span class="sidebar-icon">📥</span><span class="sidebar-text">Nhập kho</span></a></li>
                <li><a href="<?php echo getBaseUrl(); ?>/modules/warehouse/export_order.php" class="sidebar-link<?php echo sidebarActiveClass($currentUri, ['export_order']); ?>"><span class="sidebar-icon">📤</span><span class="sidebar-text">Xuất kho</span></a></li>
                <li><a href="<?php echo getBaseUrl(); ?>/modules/warehouse/inventory.php" class="sidebar-link<?php echo sidebarActiveClass($currentUri, ['inventory']); ?>"><span class="sidebar-icon">📋</span><span class="sidebar-text">Kiểm kê tồn kho</span></a></li>
                <li><a href="<?php echo getBaseUrl(); ?>/modules/warehouse/alerts.php" class="sidebar-link<?php echo sidebarActiveClass($currentUri, ['alerts']); ?>"><span class="sidebar-icon">⚠️</span><span class="sidebar-text">Cảnh báo cận date</span></a></li>
            </ul>
        </li>

        <li class="sidebar-item sidebar-group<?php echo $isReportsGroupActive ? ' open' : ''; ?>" data-sidebar-group="reports">
            <button type="button" class="sidebar-parent-btn<?php echo $isReportsGroupActive ? ' active' : ''; ?>" aria-expanded="<?php echo $isReportsGroupActive ? 'true' : 'false'; ?>">
                <span class="sidebar-icon">📈</span>
                <span class="sidebar-text">Báo cáo & thống kê</span>
                <span class="sidebar-chevron">⌄</span>
            </button>
            <ul class="sidebar-submenu">
                <li><a href="<?php echo getBaseUrl(); ?>/modules/reports/revenue_costs.php" class="sidebar-link<?php echo sidebarActiveClass($currentUri, ['revenue_costs']); ?>"><span class="sidebar-icon">💰</span><span class="sidebar-text">Doanh thu & Chi phí</span></a></li>
                <li><a href="<?php echo getBaseUrl(); ?>/modules/reports/debts.php" class="sidebar-link<?php echo sidebarActiveClass($currentUri, ['debts']); ?>"><span class="sidebar-icon">💳</span><span class="sidebar-text">Công nợ</span></a></li>
            </ul>
        </li>
    </ul>
</aside>

<script>
    (function() {
        const sidebar = document.getElementById('appSidebar');
        const groups = Array.from(document.querySelectorAll('.sidebar-group'));
        const scrollKey = 'app-sidebar-scroll-top';
        const openKey = 'app-sidebar-open-groups';

        if (sidebar) {
            const savedScrollTop = parseInt(sessionStorage.getItem(scrollKey) || '0', 10);
            if (!Number.isNaN(savedScrollTop)) {
                requestAnimationFrame(function() {
                    sidebar.scrollTop = savedScrollTop;
                });
            }

            sidebar.addEventListener('scroll', function() {
                sessionStorage.setItem(scrollKey, String(sidebar.scrollTop));
            }, {
                passive: true
            });
        }

        let savedOpenGroups = [];
        try {
            savedOpenGroups = JSON.parse(localStorage.getItem(openKey) || '[]');
        } catch (e) {
            savedOpenGroups = [];
        }

        groups.forEach(function(group) {
            const key = group.getAttribute('data-sidebar-group');
            const hasActive = !!group.querySelector('.sidebar-link.active');
            if (!hasActive && savedOpenGroups.includes(key)) {
                group.classList.add('open');
                const btn = group.querySelector('.sidebar-parent-btn');
                if (btn) btn.setAttribute('aria-expanded', 'true');
            }
        });

        function persistOpenGroups() {
            const keys = groups
                .filter(function(group) {
                    return group.classList.contains('open');
                })
                .map(function(group) {
                    return group.getAttribute('data-sidebar-group');
                });
            localStorage.setItem(openKey, JSON.stringify(keys));
        }

        groups.forEach(function(group) {
            const btn = group.querySelector('.sidebar-parent-btn');
            if (!btn) return;

            btn.addEventListener('click', function() {
                const isOpen = group.classList.toggle('open');
                btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                persistOpenGroups();
            });
        });
    })();
</script>

<!-- Content Wrapper -->
<div class="content-wrapper">
    <div class="content">