<?php
// pages/billing-success.php
require_once __DIR__ . '/../inc/auth.php';
require_admin();                         // tenant admins only
require_once __DIR__ . '/../vendor/autoload.php';

Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
$stripeSecret = $_ENV['STRIPE_SECRET'] ?? '';
if (!$stripeSecret) {
    exit('STRIPE_SECRET missing in .env');
}
\Stripe\Stripe::setApiKey($stripeSecret);

$tid      = $_SESSION['tid'];
$sessionId = $_GET['sess'] ?? '';

$ok = false;          // flip to true when everything checks out
$message = 'Unable to confirm payment. Please refresh in a few seconds.';

if ($sessionId) {
    try {
        /* ── 1.  Fetch Checkout Session (+subscription & customer) ─────────── */
        $sess = \Stripe\Checkout\Session::retrieve([
            'id'     => $sessionId,
            'expand' => ['subscription', 'customer']
        ]);

        if ($sess && $sess->payment_status === 'paid') {

            $subscription = $sess->subscription;
            $customer     = $sess->customer;
            $priceId      = $subscription->items->data[0]->price->id;

            /* ── 2.  Map Stripe price → our plans table ───────────────────── */
            require_once __DIR__ . '/../inc/db.php';
            $plan = $mysqli->query("
                SELECT id, storage_mb
                FROM   plans
                WHERE  stripe_price = '{$mysqli->real_escape_string($priceId)}'
                LIMIT  1
            ")->fetch_assoc();

            if ($plan) {
                /* ── 3.  Update tenant with new subscription details ───────── */
                $stmt = $mysqli->prepare(
                    "UPDATE tenants
                        SET plan_id      = ?, 
                            storage_mb   = ?, 
                            stripe_sub   = ?, 
                            stripe_cust  = ?, 
                            trial_ends   = NULL   -- clear trial if any
                      WHERE id = ?"
                );
                $stmt->bind_param(
                    'sissi',
                    $plan['id'],
                    $plan['storage_mb'],
                    $subscription->id,
                    $customer->id,
                    $tid
                );
                $stmt->execute();

                $ok       = true;
                $message  = 'Subscription activated!  🎉';
            }
        }

    } catch (\Throwable $e) {
        $message = 'Stripe error: ' . $e->getMessage();
    }
}

/* ── Tenant brand for the look-and-feel (optional) ───────────────────────── */
$brand = $mysqli->query(
    "SELECT logo_path, color_primary, color_accent
       FROM tenants WHERE id = $tid LIMIT 1"
)->fetch_assoc() ?: [];

$primary = $brand['color_primary'] ?? '#1e3a8a';
$logo    = $brand['logo_path']     ?? '';
?>
<style>
  .brand-text { color: <?= $primary ?>; }
</style>

<div class="max-w-xl mx-auto mt-20 p-6 bg-white rounded shadow text-center space-y-4">
    <?php if ($logo): ?>
      <img src="<?= htmlspecialchars($logo) ?>" class="h-10 mx-auto" alt="logo">
    <?php endif; ?>

    <h1 class="text-2xl font-semibold brand-text">
        <?= $ok ? 'Thank you!' : 'Hmm…' ?>
    </h1>

    <p><?= htmlspecialchars($message) ?></p>

    <a href="/?p=billing" class="underline text-blue-600">Back&nbsp;to&nbsp;billing</a>
    <a href="/"           class="underline text-blue-600 ml-4">Dashboard</a>
</div>