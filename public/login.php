<?php
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Validator.php';
Session::start();

if (Auth::isLoggedIn()) {
    header('Location: account.php');
    exit;
}

$signinError = null;
$signinEmail = '';
$redirect = trim($_GET['redirect'] ?? $_POST['redirect'] ?? '');
// Only ever redirect to another page on this same site. Block anything with a
// scheme (://), a protocol-relative host (//...), a leading /, or backslashes
// (browsers treat \ like /, so "\evil.com" would otherwise escape the site).
if (
    $redirect === ''
    || strpos($redirect, '://') !== false
    || strpos($redirect, '//') === 0
    || $redirect[0] === '/'
    || strpos($redirect, '\\') !== false
) {
    $redirect = 'account.php';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $signinEmail = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $v = new Validator();
    $v->required($signinEmail, 'email')->email($signinEmail)->required($password, 'password');

    if ($v->fails()) {
        $signinError = $v->first();
    } else {
        $auth = new Auth();
        $result = $auth->login($signinEmail, $password);

        if ($result['success']) {
            header('Location: ' . $redirect);
            exit;
        }

        $signinError = $result['message'];
    }
}

$activeTab = 'signin';
$pageTitle = 'Sign In';
require_once __DIR__ . '/../includes/header.php';
?>
            <nav aria-label="breadcrumb" class="breadcrumb-nav border-0 mb-0">
                <div class="container">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Sign In</li>
                    </ol>
                </div><!-- End .container -->
            </nav><!-- End .breadcrumb-nav -->

            <?php require_once __DIR__ . '/../includes/auth-form.php'; ?>
        </main><!-- End .main -->
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
