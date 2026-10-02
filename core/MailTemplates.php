<?php

/**
 * HTML + plain-text bodies for every customer email the store sends.
 *
 * Layout rules for email clients: tables rather than flex/grid, inline styles
 * only, a 600px max width, a hidden preheader line so the inbox preview shows
 * something useful, and every dynamic value passed through e() so customer
 * data can never inject markup.
 *
 * Each builder returns ['subject' => string, 'html' => string, 'text' => string]
 * ready to hand straight to Mailer::send().
 */
class MailTemplates
{
    const BRAND = '#c96';   // storefront primary
    const INK = '#1f2430';
    const MUTED = '#5b6472';
    const LINE = '#e6e9ee';

    private static function e($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function money($value)
    {
        return '$' . number_format((float) $value, 2);
    }

    private static function firstName($customer)
    {
        $name = trim((string) ($customer['name'] ?? ''));

        return $name !== '' ? (string) strtok($name, " \t") : 'there';
    }

    private static function date($value)
    {
        $ts = strtotime((string) $value);

        return $ts ? date('j M Y', $ts) : '';
    }

    private static function orderUrl(array $order)
    {
        $base = rtrim((string) Env::get('APP_URL'), '/');

        return $base !== ''
            ? $base . '/public/order-confirmation.php?order=' . rawurlencode((string) ($order['order_number'] ?? ''))
            : '';
    }

    /** Line items, total and delivery address - shared by the order emails. */
    private static function orderSummary(array $order, array $items)
    {
        $rows = '';

        foreach ($items as $item) {
            $qty = (int) ($item['quantity'] ?? 0);
            $lineTotal = (float) ($item['unit_price'] ?? 0) * $qty;

            $rows .= '<tr>'
                . '<td style="padding:12px 0;border-bottom:1px solid ' . self::LINE . ';color:' . self::INK . ';font-size:14px;">'
                . self::e($item['name'] ?? '') . '<br>'
                . '<span style="color:' . self::MUTED . ';font-size:13px;">' . $qty . ' &times; ' . self::e(self::money($item['unit_price'] ?? 0)) . '</span>'
                . '</td>'
                . '<td align="right" style="padding:12px 0;border-bottom:1px solid ' . self::LINE . ';color:' . self::INK . ';font-size:14px;white-space:nowrap;">'
                . self::e(self::money($lineTotal)) . '</td></tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="2" style="padding:12px 0;color:' . self::MUTED . ';font-size:14px;">No line items.</td></tr>';
        }

        $html = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:0 0 20px;">'
            . $rows
            . '<tr><td style="padding:14px 0 4px;font-weight:700;color:' . self::INK . ';font-size:15px;">Total</td>'
            . '<td align="right" style="padding:14px 0 4px;font-weight:700;color:' . self::INK . ';font-size:15px;">'
            . self::e(self::money($order['total_amount'] ?? 0)) . '</td></tr></table>'

            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f7f8fa;border-radius:10px;">'
            . '<tr><td style="padding:16px;font-size:13px;color:' . self::MUTED . ';line-height:1.7;">'
            . '<strong style="color:' . self::INK . ';">Delivery address</strong><br>'
            . nl2br(self::e($order['shipping_address'] ?? ''), false)
            . '<br><strong style="color:' . self::INK . ';">Order number</strong> ' . self::e($order['order_number'] ?? '')
            . '<br><strong style="color:' . self::INK . ';">Placed</strong> ' . self::e(self::date($order['created_at'] ?? ''))
            . '</td></tr></table>';

        return $html;
    }

    /** Shared shell wrapped around every email body. */
    private static function compose($preheader, $heading, $intro, array $blocks, array $order)
    {
        $url = self::orderUrl($order);

        $html = '<!DOCTYPE html><html><body style="margin:0;padding:0;background:#f2f4f7;">'
            . '<div style="display:none;font-size:1px;color:#f2f4f7;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;">'
            . self::e($preheader) . '</div>'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f2f4f7;padding:24px 12px;">'
            . '<tr><td align="center">'
            . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:600px;max-width:600px;background:#ffffff;border-radius:14px;overflow:hidden;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif;">'
            . '<tr><td style="background:' . self::BRAND . ';padding:20px 28px;">'
            . '<span style="color:#ffffff;font-size:20px;font-weight:700;letter-spacing:.4px;">ShopWave</span></td></tr>'
            . '<tr><td style="padding:28px;">'
            . '<h1 style="margin:0 0 12px;font-size:21px;line-height:1.3;color:' . self::INK . ';">' . self::e($heading) . '</h1>'
            . '<div style="margin:0 0 20px;font-size:15px;line-height:1.65;color:' . self::MUTED . ';">' . $intro . '</div>'
            . implode('', $blocks)
            . ($url !== '' ? '<p style="margin:22px 0 0;"><a href="' . self::e($url) . '" style="display:inline-block;background:' . self::BRAND . ';color:#ffffff;text-decoration:none;font-size:14px;font-weight:600;padding:12px 22px;border-radius:8px;">View your order</a></p>' : '')
            . '</td></tr>'
            . '<tr><td style="padding:18px 28px;background:#fafbfc;border-top:1px solid ' . self::LINE . ';color:#8b94a3;font-size:12px;line-height:1.7;">'
            . 'You are receiving this email because you have a ShopWave account.<br>'
            . 'Need anything? Just hit reply and we will get back to you.</td></tr>'
            . '</table></td></tr></table></body></html>';

        return $html;
    }

    private static function pack($subject, $html, array $order, array $items, array $customer)
    {
        $lines = [
            'Hello ' . self::firstName($customer) . ',',
            '',
            $subject,
            '',
        ];

        foreach ($items as $item) {
            $qty = (int) ($item['quantity'] ?? 0);
            $lines[] = '- ' . ($item['name'] ?? '') . ' x ' . $qty . ' = ' . self::money((float) ($item['unit_price'] ?? 0) * $qty);
        }

        $lines[] = '';
        $lines[] = 'Total: ' . self::money($order['total_amount'] ?? 0);
        $lines[] = 'Order number: ' . ($order['order_number'] ?? '');

        return [
            'subject' => $subject,
            'html' => $html,
            'text' => implode("\n", $lines),
        ];
    }

    // ---- Order lifecycle emails ------------------------------------------------

    public static function orderPlaced(array $order, array $items, array $customer)
    {
        $number = (string) ($order['order_number'] ?? '');
        $name = self::firstName($customer);

        $intro = ($order['payment_method'] ?? '') === 'cod'
            ? '<p>We have received your order and it is being prepared. You will pay when it arrives.</p>'
            : '<p>We have received your order and it is being prepared.</p>';

        $html = self::compose(
            'Order ' . $number . ' confirmed',
            'Thanks for your order, ' . $name . '!',
            $intro,
            [self::orderSummary($order, $items)],
            $order
        );

        return self::pack('We have your order ' . $number, $html, $order, $items, $customer);
    }

    public static function paymentCompleted(array $order, array $items, array $customer)
    {
        $number = (string) ($order['order_number'] ?? '');
        $name = self::firstName($customer);

        $html = self::compose(
            'Payment received for order ' . $number,
            'Payment received',
            '<p>Thanks ' . self::e($name) . ', we have received your payment of <strong>'
                . self::e(self::money($order['total_amount'] ?? 0)) . '</strong> for order '
                . self::e($number) . '.</p><p>We are getting it ready for you now.</p>',
            [self::orderSummary($order, $items)],
            $order
        );

        return self::pack('Payment received for order ' . $number, $html, $order, $items, $customer);
    }

    public static function paymentFailed(array $order, array $items, array $customer)
    {
        $number = (string) ($order['order_number'] ?? '');
        $name = self::firstName($customer);

        $html = self::compose(
            'Payment issue with order ' . $number,
            'We could not take your payment',
            '<p>Sorry ' . self::e($name) . ', we were unable to take payment for order '
                . self::e($number) . '.</p><p>Your order is saved - you can try paying again from your account page.</p>',
            [self::orderSummary($order, $items)],
            $order
        );

        return self::pack('Payment issue with order ' . $number, $html, $order, $items, $customer);
    }

    /**
     * Shipping / delivery / cancellation notice for a single status change.
     */
    public static function orderStatusChanged(array $order, array $items, array $customer, $newStatus)
    {
        $number = (string) ($order['order_number'] ?? '');
        $name = self::firstName($customer);

        $copy = [
            'shipped' => [
                'subject' => 'Order ' . $number . ' is on its way',
                'heading' => 'Your order has shipped',
                'intro' => '<p>Good news ' . self::e($name) . ', your order is on its way to you.</p>',
            ],
            'delivered' => [
                'subject' => 'Order ' . $number . ' was delivered',
                'heading' => 'Your order has arrived',
                'intro' => '<p>Your order ' . self::e($number) . ' has been delivered. We hope you love it!</p>',
            ],
            'cancelled' => [
                'subject' => 'Order ' . $number . ' was cancelled',
                'heading' => 'Your order was cancelled',
                'intro' => '<p>Sorry ' . self::e($name) . ', order ' . self::e($number)
                    . ' has been cancelled and will not be dispatched. Any payment taken is refunded to the original method.</p>',
            ],
        ];

        if (!isset($copy[$newStatus])) {
            return null;
        }

        $html = self::compose(
            $copy[$newStatus]['subject'],
            $copy[$newStatus]['heading'],
            $copy[$newStatus]['intro'],
            [self::orderSummary($order, $items)],
            $order
        );

        return self::pack($copy[$newStatus]['subject'], $html, $order, $items, $customer);
    }

    // ---- Account emails -----------------------------------------------------

    public static function welcome(array $customer)
    {
        $name = self::firstName($customer);
        $email = (string) ($customer['email'] ?? '');

        $html = self::compose(
            'Welcome to ShopWave',
            'Welcome to ShopWave, ' . $name . '!',
            '<p>Your account is ready. You can now check out faster, follow your orders and keep your delivery details in one place.</p>'
                . '<p>Happy shopping - ' . self::e($name) . '.</p>',
            [],
            ['order_number' => '']
        );

        return [
            'subject' => 'Welcome to ShopWave, ' . $name,
            'html' => $html,
            'text' => "Hello $name,\n\nYour ShopWave account is ready to use.\n\nHappy shopping!\n",
        ];
    }
}