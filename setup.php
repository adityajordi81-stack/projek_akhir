<?php
declare(strict_types=1);
require_once __DIR__ . '/functions.php';

$error = '';
$message = '';
$adminExists = false;

try {
    $server = server_db();
    $server->exec(
        'CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
    );
    $server->exec('USE `' . DB_NAME . '`');
    $server->exec(
        "CREATE TABLE IF NOT EXISTS admins (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(60) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB"
    );
    $server->exec(
        "CREATE TABLE IF NOT EXISTS products (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            game VARCHAR(100) NOT NULL,
            title VARCHAR(160) NOT NULL,
            description TEXT NOT NULL,
            price DECIMAL(12,2) NOT NULL,
            rank_label VARCHAR(80) NOT NULL DEFAULT '',
            platform VARCHAR(80) NOT NULL DEFAULT '',
            private_details TEXT NOT NULL,
            image_url VARCHAR(500) NOT NULL DEFAULT '',
            status ENUM('active','reserved','sold') NOT NULL DEFAULT 'active',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_products_status_game (status, game)
        ) ENGINE=InnoDB"
    );
    $server->exec(
        "CREATE TABLE IF NOT EXISTS orders (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            order_code VARCHAR(32) NOT NULL UNIQUE,
            product_id INT UNSIGNED NOT NULL,
            customer_name VARCHAR(120) NOT NULL,
            whatsapp VARCHAR(30) NOT NULL,
            note VARCHAR(1000) NOT NULL DEFAULT '',
            total DECIMAL(12,2) NOT NULL,
            status ENUM('pending','paid','completed','cancelled') NOT NULL DEFAULT 'pending',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_orders_product FOREIGN KEY (product_id) REFERENCES products(id)
                ON UPDATE CASCADE ON DELETE RESTRICT,
            INDEX idx_orders_status_created (status, created_at)
        ) ENGINE=InnoDB"
    );

    $adminExists = (int) $server->query('SELECT COUNT(*) FROM admins')->fetchColumn() > 0;
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$adminExists) {
        verify_csrf();
        $username = post_value('username', 60);
        $password = post_value('password', 200);
        $confirmation = post_value('password_confirmation', 200);

        if (!preg_match('/^[a-zA-Z0-9_.-]{4,60}$/', $username)) {
            $error = 'Username harus 4–60 karakter dan hanya boleh berisi huruf, angka, titik, garis bawah, atau tanda hubung.';
        } elseif (strlen($password) < 6) {
            $error = 'Password admin minimal 6 karakter.';
        } elseif (!hash_equals($password, $confirmation)) {
            $error = 'Konfirmasi password tidak cocok.';
        } else {
            $stmt = $server->prepare('INSERT INTO admins (username, password_hash) VALUES (?, ?)');
            $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
            $adminExists = true;
            $message = 'Admin berhasil dibuat. Silakan masuk melalui halaman admin.';
        }
    }
} catch (Throwable $exception) {
    $error = 'Setup database gagal: ' . $exception->getMessage()
        . ' Periksa apakah MySQL XAMPP aktif dan pengaturan database di config.php sudah benar.';
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Setup · <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="setup-page">
<main class="setup-card">
    <a class="brand" href="index.php"><span class="brand-mark">L</span> LEVELUP<span class="brand-muted"> MARKET</span></a>
    <p class="eyebrow">PENYIAPAN TOKO</p>
    <h1>Siapkan akun admin</h1>
    <p class="muted">Database toko telah disiapkan. Buat kredensial admin pertamamu untuk mengelola produk dan pesanan.</p>
    <?php if ($error !== ''): ?><div class="notice notice-error"><?= e($error) ?></div><?php endif; ?>
    <?php if ($message !== ''): ?><div class="notice notice-success"><?= e($message) ?></div><?php endif; ?>
    <?php if ($adminExists): ?>
        <div class="notice notice-info">Akun admin sudah tersedia atau baru saja dibuat. Halaman setup dikunci untuk pembuatan akun berikutnya.</div>
        <a class="button button-primary button-full" href="admin.php">Masuk ke dashboard admin <span>→</span></a>
    <?php else: ?>
        <form method="post" class="form-stack">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <label>Username admin<input name="username" required minlength="4" maxlength="60" autocomplete="username"></label>
            <label>Password (minimal 6 karakter)<input type="password" name="password" required minlength="6" autocomplete="new-password"></label>
            <label>Ulangi password<input type="password" name="password_confirmation" required minlength="6" autocomplete="new-password"></label>
            <button class="button button-primary button-full" type="submit">Buat akun admin <span>→</span></button>
        </form>
    <?php endif; ?>
    <p class="tiny-note">Setelah selesai, hapus atau batasi akses ke file <code>setup.php</code>.</p>
</main>
</body>
</html>
