<?php
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Cart.php';

Session::start();

/**
 * Add-to-cart endpoint.
 *
 * "Add to cart" never sends the shopper to the cart page. With JavaScript the
 * form posts here in the background and the page shows a toast instead; without
 * JavaScript the same POST works and the shopper is returned to the page they
 * came from.
 */

$db = new Database();
$isAjax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';

// Only ever bounce back to a page on this same site.
function cart_back_target($fallback = 'index.php')
{
    $referer = $_SERVER['HTTP_REFERER'] ?? '';

    if ($referer !== '') {
        $parts = parse_url($referer);
        $host = $parts['host'] ?? '';
        $myHost = $_SERVER['HTTP_HOST'] ?? '';

        // Same host as us? Then return only its path + query, so the redirect
        // stays on this site whatever the referer looked like.
        if ($host !== '' && strcasecmp($host, $myHost) === 0) {
            $path = $parts['path'] ?? '';
            $query = isset($parts['query']) ? '?' . $parts['query'] : '';

            if ($path !== '' && strpos($path, '//') !== 0) {
                return $path . $query;
            }
        }
    }

    return $fallback;
}

// `op`, not `action`: a form input called "action" shadows the form's own
// .action property in the DOM, which would break form.action in JavaScript.
$action = $_POST['op'] ?? $_GET['op'] ?? $_POST['action'] ?? $_GET['action'] ?? '';
$result = ['success' => false, 'message' => 'Something went wrong. Please try again.'];

if ($action === 'add') {
    $result = Cart::add(
        $db,
        (int) ($_POST['product_id'] ?? $_GET['product_id'] ?? 0),
        (int) ($_POST['qty'] ?? 1)
    );
}

if ($isAjax) {
    $summary = Cart::summarise(Cart::items($db));

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => $result['success'],
        'message' => $result['message'],
        'count' => $summary['count'],
        'total' => number_format($summary['total'], 2),
    ]);
    exit;
}

// No JavaScript: acknowledge with a message and return to where they were.
Session::flash($result['success'] ? 'success' : 'error', $result['message']);
header('Location: ' . cart_back_target());
exit;