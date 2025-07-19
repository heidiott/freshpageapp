<?php
// stripe-webhook.php  (placed in /public/ or routed via .htaccess)
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../inc/db.php';

Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
$endpointSecret = $_ENV['STRIPE_WEBHOOK_SECRET'] ?? '';
\Stripe\Stripe::setApiKey($_ENV['STRIPE_SECRET'] ?? '');

$payload = @file_get_contents('php://input');
$sig     = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

try {
    $event = \Stripe\Webhook::constructEvent($payload, $sig, $endpointSecret);
} catch (\Throwable $e) {
    http_response_code(400);
    exit('⚠️  Invalid signature');
}

/* ── handle events ────────────────────────────────────────────── */
switch ($event->type) {

case 'invoice.paid':
    $sub  = $event->data->object->subscription;
    $cust = $event->data->object->customer;
    $mysqli->query(
        "UPDATE tenants
            SET status='active'
          WHERE stripe_sub='{$mysqli->real_escape_string($sub)}'
            AND stripe_cust='{$mysqli->real_escape_string($cust)}'
          LIMIT 1"
    );
    break;

case 'customer.subscription.deleted':
    $sub  = $event->data->object->id;
    $mysqli->query(
        "UPDATE tenants
            SET status='canceled'
          WHERE stripe_sub='{$mysqli->real_escape_string($sub)}'
          LIMIT 1"
    );
    break;

/* add more cases when needed */
default:
    // just log
}

http_response_code(200);
echo '✅';