<?php
// pages/billing.php
require_once __DIR__ . '/../inc/auth.php';
require_admin();                       // ensures $_SESSION + $mysqli

/* ───────────────  Load .env + Stripe secret  ─────────────── */
require_once __DIR__ . '/../vendor/autoload.php';
Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();

$stripeSecret = $_ENV['STRIPE_SECRET'] ?? '';
if (!$stripeSecret) {
    exit('STRIPE_SECRET missing in .env');
}
$baseUrl = rtrim($_ENV['URL_BASE'] ?? 'https://freshpageapp:8890', '/');

/* ───────────────  Stripe SDK  ─────────────── */
\Stripe\Stripe::setApiKey($stripeSecret);

/* ───────────────  Plans (for the picker)  ─────────────── */
$plans = $mysqli->query("
    SELECT id,name,price_usd,storage_mb,stripe_price
    FROM   plans
    ORDER BY price_usd
")->fetch_all(MYSQLI_ASSOC);

/* ───────────────  Tenant brand (logo + colors)  ─────────────── */
$tid = $_SESSION['tid'];
$brand = $mysqli->query(
    "SELECT logo_path, color_primary, color_accent
       FROM tenants
       WHERE id = $tid LIMIT 1"
)->fetch_assoc() ?: [];                       // safe default: []

$primary = $brand['color_primary'] ?? '#1e3a8a';
$accent  = $brand['color_accent']  ?? '#facc15';
$logo    = $brand['logo_path']     ?? '';

$error = '';

/* ───────────────  Handle POST → create Checkout  ─────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['plan'])) {

    $planId  = $mysqli->real_escape_string($_POST['plan']);
    $planRow = $mysqli->query("
        SELECT stripe_price
        FROM   plans
        WHERE  id = '$planId'
        LIMIT  1
    ")->fetch_assoc();

    if ($planRow && !empty($planRow['stripe_price'])) {

        $session = \Stripe\Checkout\Session::create([
            'mode'    => 'subscription',
            'line_items' => [[
                'price'    => $planRow['stripe_price'],
                'quantity' => 1,
            ]],
            'success_url' => "$baseUrl/?p=billing-success&sess={CHECKOUT_SESSION_ID}",
            'cancel_url'  => "$baseUrl/?p=billing",
            'client_reference_id' => $_SESSION['tid'],
            'customer_email'      => $_SESSION['email'],
        ]);

        header('Location: ' . $session->url);
        exit;

    } else {
        $error = 'Selected plan could not be found – please try again.';
    }
}
?>
<!-- ───────────────  View  ─────────────── -->
<style>
  .brand-text { color: <?= $primary ?>; }
  .accent-btn { background: <?= $accent ?>; color:#000; }
</style>

<div class="max-w-xl mx-auto mt-8 space-y-6">

  <h1 class="text-2xl font-semibold brand-text flex items-center gap-3">
    <?php if ($logo): ?>
      <img src="<?= htmlspecialchars($logo) ?>" class="h-8 w-auto" alt="logo">
    <?php endif; ?>
    Choose a plan
  </h1>

  <?php if ($error): ?>
    <p class="bg-red-100 text-red-700 p-3 rounded"><?= htmlspecialchars($error) ?></p>
  <?php endif; ?>

  <form method="post" class="space-y-3">
    <?php foreach ($plans as $pl): ?>
      <label class="block border p-3 rounded flex justify-between items-center">
        <div>
          <div class="font-semibold"><?= htmlspecialchars($pl['name']) ?></div>
          <div class="text-sm text-gray-600">
            <?= (int)$pl['storage_mb'] ?> MB — $<?= number_format($pl['price_usd'], 2) ?>/mo
          </div>
        </div>
        <input type="radio" name="plan" value="<?= htmlspecialchars($pl['id']) ?>" required>
      </label>
    <?php endforeach; ?>

    <button class="accent-btn px-4 py-2 rounded mt-2">Proceed&nbsp;to&nbsp;Checkout</button>
  </form>

</div>