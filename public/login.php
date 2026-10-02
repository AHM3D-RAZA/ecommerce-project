<?php
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Validator.php';
require_once __DIR__ . '/../core/Errors.php';
Session::start();

if (Auth::isLoggedIn()) {
    header('Location: account.php');
    exit;
}

$signinErrors = [];
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
        // Collect every problem so they can all be listed in one box.
        $signinErrors = array_values($v->errors());
    } else {
        $auth = new Auth();
        $result = $auth->authenticate($signinEmail, $password);

        if (isset($result['error'])) {
            $signinErrors = [$result['error']];
        } elseif ($result['user']['role'] === Auth::ADMIN) {
            // Administrators run the store from the admin panel, so they never
            // get a storefront session here - they need a customer account to
            // shop. The credentials are valid, so sign them into the admin guard
            // and send them straight to their dashboard.
            $auth->establish($result['user'], Auth::ADMIN);
            Session::flash('success', 'That is an administrator account - taking you to the admin dashboard.');
            header('Location: ../admin/index.php');
            exit;
        } else {
            $auth->establish($result['user'], Auth::CUSTOMER);
            header('Location: ' . $redirect);
            exit;
        }
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
