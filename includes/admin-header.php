<?php
// Shared chrome for every admin screen: the dark sidebar on the left and the
// slim top navbar. Each admin page includes this after doing its own
// Auth::requireAdmin() check and setting $pageTitle / $activeNav.

require_once __DIR__ . '/../core/Session.php';

// Pages one folder deeper than /admin (categories, products, users, orders)
// pass $base = '../' so links and asset paths still resolve correctly.
$base = $base ?? '';
$activeNav = $activeNav ?? '';
$pageTitle = $pageTitle ?? 'Dashboard';
$adminName = Session::get('user_name', 'Admin');

function nav_active($key, $activeNav)
{
    return $key === $activeNav ? 'active bg-gradient-dark text-white' : 'text-dark';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title><?= htmlspecialchars($pageTitle) ?> - ShopWave Admin</title>
    <link rel="shortcut icon" href="<?= $base ?>../public/assets/images/icons/favicon.ico">
    <link href="<?= $base ?>assets/css/nucleo-icons.css" rel="stylesheet">
    <link href="<?= $base ?>assets/css/nucleo-svg.css" rel="stylesheet">
    <link id="pagestyle" href="<?= $base ?>assets/css/material-dashboard.min.css" rel="stylesheet">
</head>
<body class="g-sidenav-show bg-gray-100">

    <aside class="sidenav navbar navbar-vertical navbar-expand-xs border-radius-lg fixed-start ms-2 bg-white my-2" id="sidenav-main">
        <div class="sidenav-header">
            <i class="ni ni-fat-remove p-3 cursor-pointer text-dark opacity-5 position-absolute end-0 top-0 d-none d-xl-none" id="iconSidenav"></i>
            <a class="navbar-brand px-4 py-3 m-0" href="<?= $base ?>index.php">
                <span class="ms-1 font-weight-bold text-dark">ShopWave <span class="text-primary">Admin</span></span>
            </a>
        </div>
        <hr class="horizontal dark mt-0 mb-2">
        <div class="collapse navbar-collapse w-auto" id="sidenav-collapse-main">
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link <?= nav_active('dashboard', $activeNav) ?>" href="<?= $base ?>index.php">
                        <i class="ni ni-shop text-sm opacity-8 me-2"></i>
                        <span class="nav-link-text ms-1">Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= nav_active('categories', $activeNav) ?>" href="<?= $base ?>categories/index.php">
                        <i class="ni ni-collection text-sm opacity-8 me-2"></i>
                        <span class="nav-link-text ms-1">Categories</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= nav_active('products', $activeNav) ?>" href="<?= $base ?>products/index.php">
                        <i class="ni ni-box-2 text-sm opacity-8 me-2"></i>
                        <span class="nav-link-text ms-1">Products</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= nav_active('orders', $activeNav) ?>" href="<?= $base ?>orders/index.php">
                        <i class="ni ni-cart text-sm opacity-8 me-2"></i>
                        <span class="nav-link-text ms-1">Orders</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= nav_active('users', $activeNav) ?>" href="<?= $base ?>users/index.php">
                        <i class="ni ni-single-02 text-sm opacity-8 me-2"></i>
                        <span class="nav-link-text ms-1">Users</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= nav_active('reports', $activeNav) ?>" href="<?= $base ?>reports.php">
                        <i class="ni ni-chart-bar-32 text-sm opacity-8 me-2"></i>
                        <span class="nav-link-text ms-1">Sales Reports</span>
                    </a>
                </li>
                <li class="nav-item mt-3">
                    <hr class="horizontal dark">
                </li>
                <li class="nav-item">
                    <a class="nav-link text-dark" href="<?= $base ?>../public/index.php" target="_blank">
                        <i class="ni ni-world text-sm opacity-8 me-2"></i>
                        <span class="nav-link-text ms-1">View Store</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-dark" href="<?= $base ?>logout.php">
                        <i class="ni ni-button-power text-sm opacity-8 me-2"></i>
                        <span class="nav-link-text ms-1">Logout</span>
                    </a>
                </li>
            </ul>
        </div>
    </aside>

    <main class="main-content position-relative max-height-vh-100 h-100 border-radius-lg">
        <nav class="navbar navbar-main navbar-expand-lg px-0 mx-3 shadow-none border-radius-xl" id="navbarBlur" data-scroll="true">
            <div class="container-fluid py-1 px-3">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb bg-transparent mb-0 pb-0 pt-1 px-0 me-sm-6 me-5">
                        <li class="breadcrumb-item text-sm"><a class="opacity-5 text-dark" href="<?= $base ?>index.php">Admin</a></li>
                        <li class="breadcrumb-item text-sm text-dark active" aria-current="page"><?= htmlspecialchars($pageTitle) ?></li>
                    </ol>
                </nav>
                <div class="collapse navbar-collapse mt-sm-0 mt-2 me-md-0 me-sm-4" id="navbar">
                    <ul class="navbar-nav d-flex align-items-center justify-content-end ms-auto">
                        <li class="nav-item d-xl-none ps-3 d-flex align-items-center">
                            <a href="javascript:;" class="nav-link text-body p-0" id="iconNavbarSidenav">
                                <div class="sidenav-toggler-inner">
                                    <i class="sidenav-toggler-line"></i>
                                    <i class="sidenav-toggler-line"></i>
                                    <i class="sidenav-toggler-line"></i>
                                </div>
                            </a>
                        </li>
                        <li class="nav-item d-flex align-items-center px-3">
                            <span class="text-sm text-dark font-weight-bold">
                                <i class="ni ni-circle-08 me-1 text-primary"></i>
                                <?= htmlspecialchars($adminName) ?>
                            </span>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
        <div class="container-fluid py-3">
            <?php $flash = Session::flash('success'); ?>
            <?php if ($flash): ?>
                <div class="alert alert-success text-white" role="alert"><?= htmlspecialchars($flash) ?></div>
            <?php endif; ?>
            <?php $flashError = Session::flash('error'); ?>
            <?php if ($flashError): ?>
                <div class="alert alert-danger text-white" role="alert"><?= htmlspecialchars($flashError) ?></div>
            <?php endif; ?>
