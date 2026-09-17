<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Session.php';

Session::start();

// Already signed in as admin - no need to see the login screen again.
if (Auth::isLoggedIn() && Auth::isAdmin()) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $auth = new Auth();
    $result = $auth->login($email, $password);

    if (!$result['success']) {
        $error = $result['message'];
    } elseif ($result['role'] !== 'admin') {
        // Right credentials, wrong door - customers sign in on the storefront.
        Auth::logout();
        $error = 'This login is for administrators only.';
    } else {
        header('Location: index.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Admin Login - ShopWave</title>
    <link href="assets/css/nucleo-icons.css" rel="stylesheet">
    <link href="assets/css/nucleo-svg.css" rel="stylesheet">
    <link href="assets/css/material-dashboard.min.css" rel="stylesheet">
</head>
<body class="bg-gray-200">
    <main class="main-content mt-0">
        <div class="page-header align-items-start min-vh-100" style="background: linear-gradient(310deg, #212121, #454545);">
            <span class="mask bg-gradient-dark opacity-6"></span>
            <div class="container my-auto">
                <div class="row">
                    <div class="col-lg-4 col-md-8 col-12 mx-auto">
                        <div class="card z-index-0">
                            <div class="card-header p-0 position-relative mt-n4 mx-3 z-index-2">
                                <div class="bg-gradient-dark shadow-dark border-radius-lg py-3 pe-1">
                                    <h4 class="text-white font-weight-bolder text-center mt-2 mb-0">ShopWave Admin</h4>
                                    <p class="text-white text-center mb-0">Sign in to manage the store</p>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if ($error): ?>
                                    <div class="alert alert-danger text-white"><?= htmlspecialchars($error) ?></div>
                                <?php endif; ?>
                                <form method="post" class="text-start">
                                    <div class="input-group input-group-outline my-3">
                                        <label class="form-label">Email</label>
                                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                                    </div>
                                    <div class="input-group input-group-outline mb-3">
                                        <label class="form-label">Password</label>
                                        <input type="password" name="password" class="form-control" required>
                                    </div>
                                    <div class="text-center">
                                        <button type="submit" class="btn bg-gradient-dark w-100 my-4 mb-2">Sign in</button>
                                    </div>
                                    <p class="mt-2 text-sm text-center text-muted">
                                        Demo admin: admin@shop.com / admin123
                                    </p>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    <script src="assets/js/core/bootstrap.bundle.min.js"></script>
    <script src="assets/js/material-dashboard.min.js"></script>
</body>
</html>
