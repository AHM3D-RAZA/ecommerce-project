<?php

/**
 * Legal state transitions for an order and its payment.
 *
 * Without this the admin order form could happily move a *delivered* order back
 * to "processing", or re-open a payment that was already marked completed.
 * These rules make both fields move forwards only, with settled states final.
 *
 *   order_status   processing -> shipped -> delivered   (forward jumps allowed)
 *                  cancelled reachable from processing/shipped
 *                  delivered and cancelled are final
 *
 *   payment_status pending -> completed | failed
 *                  failed -> pending | completed  (a retry is allowed)
 *                  completed is final
 */
class OrderStatus
{
    const ORDER_STATES = ['processing', 'shipped', 'delivered', 'cancelled'];
    const PAYMENT_STATES = ['pending', 'completed', 'failed'];

    /** Order statuses reachable from the current one. */
    public static function allowedOrderStatuses($current)
    {
        switch ($current) {
            case 'delivered':
            case 'cancelled':
                return [$current]; // final - nothing follows

            case 'shipped':
                return ['shipped', 'delivered', 'cancelled'];

            default: // processing
                return ['processing', 'shipped', 'delivered', 'cancelled'];
        }
    }

    /** Payment statuses reachable from the current one. */
    public static function allowedPaymentStatuses($current)
    {
        switch ($current) {
            case 'completed':
                return ['completed']; // settled - cannot be re-opened

            case 'failed':
                return ['failed', 'pending', 'completed']; // customer can retry

            default: // pending
                return ['pending', 'completed', 'failed'];
        }
    }

    /**
     * Check a proposed change.
     *
     * @return array human-readable problems; empty means the change is legal.
     */
    public static function validate($currentOrder, $newOrder, $currentPayment, $newPayment)
    {
        $errors = [];

        $allowedOrder = self::allowedOrderStatuses($currentOrder);

        if (!in_array($newOrder, self::ORDER_STATES, true)) {
            $errors[] = 'That is not a valid order status.';
        } elseif (!in_array($newOrder, $allowedOrder, true)) {
            $errors[] = sprintf(
                'Order status cannot go back from "%s" to "%s". %s',
                ucfirst((string) $currentOrder),
                ucfirst((string) $newOrder),
                self::hint($allowedOrder, $currentOrder)
            );
        }

        $allowedPayment = self::allowedPaymentStatuses($currentPayment);

        if (!in_array($newPayment, self::PAYMENT_STATES, true)) {
            $errors[] = 'That is not a valid payment status.';
        } elseif (!in_array($newPayment, $allowedPayment, true)) {
            $errors[] = sprintf(
                'Payment status cannot go back from "%s" to "%s". %s',
                ucfirst((string) $currentPayment),
                ucfirst((string) $newPayment),
                self::hint($allowedPayment, $currentPayment)
            );
        }

        // Cancelling and settling in the same save is illogical. Only reported
        // when each change is individually legal, so a genuine error above isn't
        // buried under a second, redundant one.
        if ($newOrder === 'cancelled'
            && $newPayment === 'completed'
            && in_array($newOrder, $allowedOrder, true)
            && in_array($newPayment, $allowedPayment, true)
        ) {
            $errors[] = 'A cancelled order cannot be marked as paid.';
        }

        return $errors;
    }

    /** "Allowed from here: Shipped, Delivered." or "This status is final." */
    private static function hint(array $allowed, $current)
    {
        $others = array_values(array_diff($allowed, [(string) $current]));

        if ($others === []) {
            return 'This status is final.';
        }

        return 'Allowed from here: ' . implode(', ', array_map('ucfirst', $others)) . '.';
    }
}