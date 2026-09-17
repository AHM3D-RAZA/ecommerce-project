<?php
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Validator.php';
Session::start();

if (Auth::isLoggedIn()) {
    header('Location: account.php');
    exit;
}

$registerError = null;
$registerName = '';
$registerEmail = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $registerName = trim($_POST['name'] ?? '');
    $registerEmail = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';

    $v = new Validator();
    $v->required($registerName, 'name')
      ->required($registerEmail, 'email')->email($registerEmail)
      ->required($password, 'password')->minLength($password, 6, 'password')
      ->matches($password, $passwordConfirm, 'password_confirm');

    if ($v->fails()) {
        $registerError = $v->first();
    } else {
        $auth = new Auth();
        $result = $auth->register($registerName, $registerEmail, $password);

        if ($result['success']) {
            // Log the new account straight in - no need to make them sign in twice
            $auth->login($registerEmail, $password);
            Session::flash('success', 'Welcome to ShopWave! Your account has been created.');
            header('Location: account.php');
            exit;
        }

        $registerError = $result['message'];
    }
}

$activeTab = 'register';
$pageTitle = 'Register';
require_once __DIR__ . '/../includes/header.php';
?>
            <nav aria-label="breadcrumb" class="breadcrumb-nav border-0 mb-0">
                <div class="container">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Register</li>
                    </ol>
                </div><!-- End .container -->
            </nav><!-- End .breadcrumb-nav -->

            <?php require_once __DIR__ . '/../includes/auth-form.php'; ?>
        </main><!-- End .main -->
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
