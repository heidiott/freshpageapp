<?php
// pages/login.php
// Displays the login form and handles POST

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../inc/db.php';
    require_once __DIR__ . '/../inc/auth.php';

    $email = trim($_POST['email']    ?? '');
    $pass  = $_POST['password']      ?? '';

    // fetch user by email
    $stmt = $mysqli->prepare(
        "SELECT id, password, role, tenant_id, email
           FROM users
          WHERE email = ?
          LIMIT 1"
    );
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $stmt->bind_result($uid, $hash, $role, $tid, $dbEmail);
    $stmt->fetch();

    if ($uid && password_verify($pass, $hash)) {
        $stmt->close();                               // free result set
        login($uid, $role, $tid, $dbEmail);           // 4-arg call
        header('Location: /');                        // dashboard
        exit;
    }

    $error = 'Invalid email or password';
}
?>

<div class="max-w-md mx-auto mt-12 bg-white p-6 shadow rounded">
    <h1 class="text-2xl font-semibold mb-4">Login</h1>

    <?php if ($error): ?>
        <p class="mb-4 text-red-600"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="post" class="space-y-4">
        <div>
            <label class="block mb-1">Email</label>
            <input type="email"
                   name="email"
                   class="w-full border p-2"
                   required
                   autofocus>
        </div>

        <div>
            <label class="block mb-1">Password</label>
            <input type="password"
                   name="password"
                   class="w-full border p-2"
                   required>
        </div>

        <button class="w-full bg-blue-600 text-white py-2 rounded">
            Login
        </button>
    </form>
</div>