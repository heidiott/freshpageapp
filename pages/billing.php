<?php
// pages/billing.php  – tenant picks / changes a subscription plan
require_once __DIR__ . '/../inc/auth.php';
require_admin();                // also starts the session & gives $mysqli

// --------------------------------------------------------------------------
//  1.  Environment / Stripe setup
// --------------------------------------------------------------------------
require_once __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

$stripeSecret = $_ENV['STRIPE_SECRET'] ?? '';
$baseUrl      = rtrim($_ENV['URL_BASE'] ?? 'https://example.test', '/');

if (!$stripeSecret) {
    exit('<p style="color:red">STRIPE_SECRET missing in <code>.env</code></p>');
}

\Stripe\Stripe::setApiKey($stripeSecret);

// --------------------------------------------------------------------------
//  2.  Grab tenant branding (logo + colours)
// --------------------------------------------------------------------------
$tid = (int)$_SESSION['tid'];
$brand = $mysqli->query("
        SELECT logo_path, color_primary, color_accent
        FROM   tenants
        WHERE  id = $tid
        LIMIT  1
      ")->fetch_assoc() ?: [];

$primary = $brand['color_primary'] ?: '#1e3a8a';
$accent  = $brand['color_accent']  ?: '#facc15';
$logo    = $brand['logo_path']     ?: '';

// --------------------------------------------------------------------------
//  3.  Pull available plans
// --------------------------------------------------------------------------
$plans = $mysqli->query("
        SELECT id, name, price_usd, storage_mb, stripe_price
        FROM   plans
        ORDER  BY price_usd
      ")->fetch_all(MYSQLI_ASSOC);

// --------------------------------------------------------------------------
//  4.  Handle “Proceed to Checkout” POST
// --------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['plan'])) {
    $planId  = $mysqli->real_escape_string($_POST['plan']);
    $row     = $mysqli->query("
                 SELECT stripe_price
                 FROM   plans
                 WHERE  id = '$planId'
                 LIMIT  1
               ")->fetch_assoc();

    if ($row && $row['stripe_price']) {
        $session = \Stripe\Checkout\Session::create([
            'mode'               => 'subscription',
            'line_items'         => [[
                'price'    => $row['stripe_price'],
                'quantity' => 1,
            ]],
            'client_reference_id' => $_SESSION['tid'],
            'customer_email'      => $_SESSION['email'],
            'success_url'         => "$baseUrl/?p=billing-success&sess={CHECKOUT_SESSION_ID}",
            'cancel_url'          => "$baseUrl/?p=billing&canceled=1",
        ]);
        header('Location: ' . $session->url);
        exit;
    }
    // If we reach here something was wrong with the chosen plan
    $error = 'Unknown or inactive plan selected – please try again.';
}

// --------------------------------------------------------------------------
//  Helper for safe output
// --------------------------------------------------------------------------
function e(string $txt): string { return htmlspecialchars($txt, ENT_QUOTES, 'UTF-8'); }

?>
<!-- ───────────────────────────────  VIEW  ─────────────────────────────── -->
<style>
  .brand-text { color: <?= $primary ?>; }
  .accent-btn { background: <?= $accent ?>; color: #000; }
</style>

<div class="max-w-xl mx-auto mt-8 space-y-6">

  <h1 class="text-2xl font-semibold brand-text flex items-center gap-3">
    <?php if ($logo): ?>
        <img src="<?= e($logo) ?>" alt="logo" class="h-8 w-auto">
    <?php endif; ?>
    Choose a plan
  </h1>

  <?php if (!empty($error)): ?>
      <p class="bg-red-100 text-red-800 p-3 rounded"><?= e($error) ?></p>
  <?php endif; ?>

  <?php if (!$plans): ?>
      <p class="bg-yellow-100 text-yellow-800 p-3 rounded">
          No subscription plans are configured yet.  
          Please contact support.
      </p>
  <?php else: ?>
      <form method="post" class="space-y-3">
        <?php foreach ($plans as $pl): ?>
          <label class="block border p-3 rounded flex justify-between items-center">
            <div>
              <div class="font-semibold"><?= e($pl['name']) ?></div>
              <div class="text-sm text-gray-600">
                <?= (int)$pl['storage_mb'] ?> MB — $<?= number_format($pl['price_usd'], 2) ?>/mo
              </div>
            </div>
            <input type="radio" name="plan" value="<?= e($pl['id']) ?>" required>
          </label>
        <?php endforeach; ?>
        <button class="accent-btn px-4 py-2 rounded mt-2 w-full sm:w-auto">
          Proceed to Checkout
        </button>
      </form>
  <?php endif; ?>

</div>