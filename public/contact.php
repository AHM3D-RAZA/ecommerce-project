<?php
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Validator.php';
Session::start();

$contactError = null;
$old = ['name' => '', 'email' => '', 'subject' => '', 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    foreach ($old as $key => $default) {
        $old[$key] = trim($_POST[$key] ?? '');
    }

    $v = new Validator();
    $v->required($old['name'], 'name')
      ->required($old['email'], 'email')->email($old['email'])
      ->required($old['subject'], 'subject')
      ->required($old['message'], 'message');

    if ($v->fails()) {
        $contactError = $v->first();
    } else {
        // Demo store: no mail server configured, so acknowledge the message instead of sending it.
        Session::flash('success', 'Thank you for reaching out! We will get back to you at ' . $old['email'] . ' shortly.');
        header('Location: contact.php');
        exit;
    }
}

$pageTitle = 'Contact Us';

require_once __DIR__ . '/../includes/header.php';
?>
            <div class="page-header text-center" style="background-image: url('assets/images/page-header-bg.jpg')">
                <div class="container">
                    <h1 class="page-title">Contact Us<span>Shop</span></h1>
                </div><!-- End .container -->
            </div><!-- End .page-header -->

            <nav aria-label="breadcrumb" class="breadcrumb-nav mb-3">
                <div class="container">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Contact Us</li>
                    </ol>
                </div><!-- End .container -->
            </nav><!-- End .breadcrumb-nav -->

            <div class="page-content pb-5">
                <div class="container">
                    <?php if ($contactError): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($contactError) ?></div>
                    <?php endif; ?>

                    <div class="row">
                        <div class="col-lg-4 mb-4 mb-lg-0">
                            <h2 class="title mb-3">Contact Info</h2>

                            <div class="icon-box icon-box-side mb-4">
                                <span class="icon-box-icon text-primary"><i class="icon-map-marker"></i></span>
                                <div class="icon-box-content">
                                    <h3 class="icon-box-title">Address</h3>
                                    <p>123 Electronics Avenue<br>Suite 400<br>Springfield, ST 12345</p>
                                </div>
                            </div>

                            <div class="icon-box icon-box-side mb-4">
                                <span class="icon-box-icon text-primary"><i class="icon-phone"></i></span>
                                <div class="icon-box-content">
                                    <h3 class="icon-box-title">Phone</h3>
                                    <p><a href="tel:+0123456789">+0123 456 789</a><br>Got a question? Call us 24/7</p>
                                </div>
                            </div>

                            <div class="icon-box icon-box-side mb-4">
                                <span class="icon-box-icon text-primary"><i class="icon-envelope"></i></span>
                                <div class="icon-box-content">
                                    <h3 class="icon-box-title">Email</h3>
                                    <p><a href="mailto:support@shopwave.test">support@shopwave.test</a></p>
                                </div>
                            </div>

                            <div class="icon-box icon-box-side">
                                <span class="icon-box-icon text-primary"><i class="icon-clock"></i></span>
                                <div class="icon-box-content">
                                    <h3 class="icon-box-title">Opening Hours</h3>
                                    <p>Monday - Saturday: 9am - 7pm<br>Sunday: Closed</p>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-8">
                            <h2 class="title mb-3">Send Us a Message</h2>

                            <form action="contact.php" method="post">
                                <div class="row">
                                    <div class="col-md-6">
                                        <label for="contact-name" class="sr-only">Your name *</label>
                                        <input type="text" class="form-control mb-2" id="contact-name" name="name" placeholder="Your name *" value="<?= htmlspecialchars($old['name']) ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="contact-email" class="sr-only">Your email *</label>
                                        <input type="email" class="form-control mb-2" id="contact-email" name="email" placeholder="Your email *" value="<?= htmlspecialchars($old['email']) ?>" required>
                                    </div>
                                </div>

                                <label for="contact-subject" class="sr-only">Subject *</label>
                                <input type="text" class="form-control mb-2" id="contact-subject" name="subject" placeholder="Subject *" value="<?= htmlspecialchars($old['subject']) ?>" required>

                                <label for="contact-message" class="sr-only">Message *</label>
                                <textarea class="form-control mb-3" id="contact-message" name="message" cols="30" rows="6" placeholder="Message *" required><?= htmlspecialchars($old['message']) ?></textarea>

                                <button type="submit" name="send_message" value="1" class="btn btn-primary btn-round">
                                    <span>Send Message</span><i class="icon-long-arrow-right"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div><!-- End .container -->
            </div><!-- End .page-content -->
        </main><!-- End .main -->
<?php require_once __DIR__ . '/../includes/footer.php'; ?>