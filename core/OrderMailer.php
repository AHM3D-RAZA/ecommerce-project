<?php

require_once __DIR__ . '/Mailer.php';
require_once __DIR__ . '/MailTemplates.php';

/**
 * Turns a business event into the right customer email.
 *
 * Call sites stay tiny - they only say *what happened*:
 *     OrderMailer::orderPlaced($db, $orderId);
 *     OrderMailer::paymentSettled($db, $orderId, 'completed');
 *     OrderMailer::orderUpdated($db, $orderId, $oldStatus, $newStatus, $oldPayment, $newPayment);
 *
 * Everything is resolved from the database by order id (never from whatever the
 * page happens to have in scope), and nothing here can throw - mail is always
 * secondary to actually taking the order.
 */
class OrderMailer
{
    /** Order + customer + line items, or null if anything is missing. */
    private static function load($db, $orderId)
    {
        $order = $db->selectOne("SELECT * FROM orders WHERE id = ?", [(int) $orderId]);

        if (!$order) {
            return null;
        }

        $customer = $db->selectOne("SELECT name, email FROM users WHERE id = ?", [$order['user_id']]);

        if (!$customer) {
            return null;
        }

        $items = $db->select(
            "SELECT oi.*, p.name, p.slug
             FROM order_items oi JOIN products p ON p.id = oi.product_id
             WHERE oi.order_id = ? ORDER BY oi.id ASC",
            [$order['id']]
        );

        return ['order' => $order, 'customer' => $customer, 'items' => $items];
    }

    /** Hands a built message to the mailer, replying to the shop address. */
    private static function deliver(array $customer, $message)
    {
        if (!is_array($message)) {
            return false;
        }

        return Mailer::send(
            $customer['email'] ?? '',
            $customer['name'] ?? '',
            $message['subject'] ?? '',
            $message['html'] ?? '',
            $message['text'] ?? null,
            Env::get('MAIL_FROM_ADDRESS') ?: null
        );
    }

    /** An order was created (both COD and Stripe). */
    public static function orderPlaced($db, $orderId)
    {
        $ctx = self::load($db, $orderId);

        if ($ctx === null) {
            return false;
        }

        return self::deliver(
            $ctx['customer'],
            MailTemplates::orderPlaced($ctx['order'], $ctx['items'], $ctx['customer'])
        );
    }

    /**
     * Payment reached a final state. $paymentStatus is 'completed' or 'failed'.
     * Anything still pending sends nothing - that is deliberate, so a shopper
     * reloads the confirmation page without getting an email each time.
     */
    public static function paymentSettled($db, $orderId, $paymentStatus)
    {
        if ($paymentStatus !== 'completed' && $paymentStatus !== 'failed') {
            return false;
        }

        $ctx = self::load($db, $orderId);

        if ($ctx === null) {
            return false;
        }

        $message = $paymentStatus === 'completed'
            ? MailTemplates::paymentCompleted($ctx['order'], $ctx['items'], $ctx['customer'])
            : MailTemplates::paymentFailed($ctx['order'], $ctx['items'], $ctx['customer']);

        return self::deliver($ctx['customer'], $message);
    }

    /** Shipping / delivery / cancellation. Unknown statuses send nothing. */
    public static function statusChanged($db, $orderId, $newStatus)
    {
        $ctx = self::load($db, $orderId);

        if ($ctx === null) {
            return false;
        }

        return self::deliver(
            $ctx['customer'],
            MailTemplates::orderStatusChanged($ctx['order'], $ctx['items'], $ctx['customer'], $newStatus)
        );
    }

    /**
     * Admin saved the order form. Compares against the previous values so the
     * customer is only emailed about what actually changed - saving the form
     * without touching anything sends nothing at all.
     *
     * @return array ['status' => bool, 'payment' => bool]
     */
    public static function orderUpdated($db, $orderId, $oldStatus, $newStatus, $oldPayment, $newPayment)
    {
        $sent = ['status' => false, 'payment' => false];

        if ($newStatus !== $oldStatus) {
            $sent['status'] = self::statusChanged($db, $orderId, $newStatus);
        }

        if ($newPayment !== $oldPayment) {
            $sent['payment'] = self::paymentSettled($db, $orderId, $newPayment);
        }

        return $sent;
    }

    /** New customer account. */
    public static function welcome(array $customer)
    {
        return self::deliver($customer, MailTemplates::welcome($customer));
    }
}