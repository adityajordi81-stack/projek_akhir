<?php
declare(strict_types=1);
require_once __DIR__ . '/functions.php';

$pdo = null;
$dbError = '';
$error = flash('error');
$success = flash('success');
$editingProduct = null;

try {
    $pdo = db();
} catch (Throwable $exception) {
    $dbError = 'Database belum siap. Jalankan setup terlebih dahulu.';
}

if ($pdo instanceof PDO && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = post_value('action');

    if ($action === 'login' && empty($_SESSION['admin_id'])) {
        $username = post_value('username', 60);
        $password = post_value('password', 200);
        $stmt = $pdo->prepare('SELECT id, username, password_hash FROM admins WHERE username = ?');
        $stmt->execute([$username]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int) $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            redirect('admin.php');
        }
        flash('error', 'Username atau password tidak sesuai.');
        redirect('admin.php');
    }

    if ($action === 'logout') {
        unset($_SESSION['admin_id'], $_SESSION['admin_username']);
        session_regenerate_id(true);
        redirect('admin.php');
    }

    require_admin();

    try {
        if ($action === 'save_product') {
            $id = filter_var($_POST['product_id'] ?? '', FILTER_VALIDATE_INT);
            $game = post_value('game', 100);
            $title = post_value('title', 160);
            $description = post_value('description', 5000);
            $price = filter_var($_POST['price'] ?? '', FILTER_VALIDATE_FLOAT);
            $rank = post_value('rank_label', 80);
            $platform = post_value('platform', 80);
            $privateDetails = post_value('private_details', 5000);
            $imageUrl = post_value('image_url', 500);

            if ($game === '' || $title === '' || $description === '' || $privateDetails === '' || $price === false || $price <= 0) {
                throw new RuntimeException('Isi semua data wajib dan pastikan harga lebih dari nol.');
            }
            if ($imageUrl !== '' && !filter_var($imageUrl, FILTER_VALIDATE_URL)) {
                throw new RuntimeException('URL gambar harus berupa alamat URL yang valid.');
            }

            if ($id) {
                $stmt = $pdo->prepare(
                    'UPDATE products SET game=?, title=?, description=?, price=?, rank_label=?, platform=?, private_details=?, image_url=?
                     WHERE id=?'
                );
                $stmt->execute([$game, $title, $description, $price, $rank, $platform, $privateDetails, $imageUrl, $id]);
                flash('success', 'Listing berhasil diperbarui.');
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO products (game, title, description, price, rank_label, platform, private_details, image_url)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([$game, $title, $description, $price, $rank, $platform, $privateDetails, $imageUrl]);
                flash('success', 'Listing baru berhasil ditambahkan.');
            }
        } elseif ($action === 'delete_product') {
            $id = filter_var($_POST['product_id'] ?? '', FILTER_VALIDATE_INT);
            if (!$id) {
                throw new RuntimeException('Listing tidak valid.');
            }
            $stmt = $pdo->prepare('DELETE FROM products WHERE id = ? AND status <> ?');
            $stmt->execute([$id, 'reserved']);
            if ($stmt->rowCount() === 0) {
                throw new RuntimeException('Listing tidak dapat dihapus: mungkin sedang dipesan atau sudah memiliki riwayat transaksi.');
            }
            flash('success', 'Listing berhasil dihapus.');
        } elseif ($action === 'update_order') {
            $orderId = filter_var($_POST['order_id'] ?? '', FILTER_VALIDATE_INT);
            $newStatus = post_value('status');
            if (!$orderId || !in_array($newStatus, ['pending', 'paid', 'completed', 'cancelled'], true)) {
                throw new RuntimeException('Perubahan status pesanan tidak valid.');
            }
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('SELECT product_id, status FROM orders WHERE id = ? FOR UPDATE');
            $stmt->execute([$orderId]);
            $order = $stmt->fetch();
            if (!$order) {
                throw new RuntimeException('Pesanan tidak ditemukan.');
            }
            if (in_array($order['status'], ['completed', 'cancelled'], true)) {
                throw new RuntimeException('Pesanan yang sudah selesai atau dibatalkan tidak dapat diubah lagi.');
            }
            $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?')->execute([$newStatus, $orderId]);
            $productStatus = match ($newStatus) {
                'completed' => 'sold',
                'cancelled' => 'active',
                default => 'reserved',
            };
            $pdo->prepare('UPDATE products SET status = ? WHERE id = ?')->execute([$productStatus, $order['product_id']]);
            $pdo->commit();
            flash('success', 'Status pesanan berhasil diperbarui.');
        }
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash('error', $exception instanceof RuntimeException
            ? $exception->getMessage()
            : 'Perubahan gagal disimpan. Periksa data dan pastikan tidak ada kendala database.');
    }
    redirect('admin.php' . ($action === 'save_product' ? '#produk' : '#pesanan'));
}

$loggedIn = !empty($_SESSION['admin_id']);
$products = [];
$orders = [];
$stats = ['products' => 0, 'available' => 0, 'orders' => 0, 'pending' => 0, 'revenue' => 0];

if ($pdo instanceof PDO && $loggedIn) {
    $stats['products'] = (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
    $stats['available'] = (int) $pdo->query("SELECT COUNT(*) FROM products WHERE status = 'active'")->fetchColumn();
    $stats['orders'] = (int) $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn();
    $stats['pending'] = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn();
    $stats['revenue'] = (float) $pdo->query("SELECT COALESCE(SUM(total), 0) FROM orders WHERE status = 'completed'")->fetchColumn();
    $products = $pdo->query('SELECT * FROM products ORDER BY created_at DESC')->fetchAll();
    $orders = $pdo->query(
        'SELECT o.*, p.game, p.title, p.private_details
         FROM orders o JOIN products p ON p.id = o.product_id
         ORDER BY o.created_at DESC LIMIT 100'
    )->fetchAll();

    $editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);
    if ($editId) {
        $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
        $stmt->execute([$editId]);
        $editingProduct = $stmt->fetch() ?: null;
    }
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin · <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="admin-page">
<header class="site-header">
    <div class="container nav-wrap"><a class="brand" href="index.php"><span class="brand-mark">L</span> LEVELUP<span class="brand-muted"> MARKET</span></a><div class="admin-nav-right"><a href="index.php">← Lihat toko</a><?php if ($loggedIn): ?><span class="admin-who">Admin · <?= e($_SESSION['admin_username'] ?? '') ?></span><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="logout"><button class="button button-outline button-small" type="submit">Keluar</button></form><?php endif; ?></div></div>
</header>

<main class="container admin-main">
    <?php if ($dbError !== ''): ?>
        <section class="login-card"><p class="eyebrow">PENYIAPAN DIPERLUKAN</p><h1>Dashboard admin</h1><p class="muted"><?= e($dbError) ?></p><a class="button button-primary button-full" href="setup.php">Jalankan setup →</a></section>
    <?php elseif (!$loggedIn): ?>
        <section class="login-card"><div class="login-symbol">L</div><p class="eyebrow">AREA ADMIN</p><h1>Selamat datang kembali.</h1><p class="muted">Masuk untuk mengelola listing dan pesanan LevelUp Market.</p><?php if ($error): ?><div class="notice notice-error"><?= e($error) ?></div><?php endif; ?><?php if ($success): ?><div class="notice notice-success"><?= e($success) ?></div><?php endif; ?>
            <form method="post" class="form-stack"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="login"><label>Username<input name="username" required autocomplete="username"></label><label>Password<input type="password" name="password" required autocomplete="current-password"></label><button class="button button-primary button-full" type="submit">Masuk ke dashboard <span>→</span></button></form>
            <a class="back-link" href="setup.php">Belum punya admin? Setup awal</a>
        </section>
    <?php else: ?>
        <section class="dashboard-heading"><div><p class="eyebrow">RINGKASAN TOKO</p><h1>Dashboard <span class="text-gradient">admin.</span></h1><p class="muted">Kelola listing, pantau pesanan, dan koordinasikan transaksi.</p></div><a class="button button-primary" href="#produk">+ Tambah listing</a></section>
        <?php if ($error): ?><div class="notice notice-error"><?= e($error) ?></div><?php endif; ?><?php if ($success): ?><div class="notice notice-success"><?= e($success) ?></div><?php endif; ?>
        <section class="stats-grid"><article class="stat-card"><span>Total listing</span><strong><?= $stats['products'] ?></strong><small>Semua status</small></article><article class="stat-card"><span>Akun tersedia</span><strong><?= $stats['available'] ?></strong><small>Siap dipesan</small></article><article class="stat-card"><span>Perlu ditangani</span><strong><?= $stats['pending'] ?></strong><small>Dari <?= $stats['orders'] ?> pesanan</small></article><article class="stat-card"><span>Penjualan selesai</span><strong class="stat-revenue"><?= e(money($stats['revenue'])) ?></strong><small>Total pesanan selesai</small></article></section>

        <section class="admin-section" id="produk">
            <div class="admin-section-heading"><div><p class="eyebrow">ETALASE TOKO</p><h2><?= $editingProduct ? 'Edit listing' : 'Tambah listing baru' ?></h2></div></div>
            <form method="post" class="product-admin-form">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save_product"><input type="hidden" name="product_id" value="<?= e($editingProduct['id'] ?? '') ?>">
                <div class="form-grid"><label>Nama game *<input name="game" required maxlength="100" placeholder="Contoh: Valorant" value="<?= e($editingProduct['game'] ?? '') ?>"></label><label>Judul listing *<input name="title" required maxlength="160" placeholder="Contoh: Akun level 200, banyak skin" value="<?= e($editingProduct['title'] ?? '') ?>"></label><label>Harga (Rp) *<input type="number" name="price" required min="1" step="1000" placeholder="250000" value="<?= e($editingProduct['price'] ?? '') ?>"></label><label>Rank / level<input name="rank_label" maxlength="80" placeholder="Contoh: Immortal / Level 200" value="<?= e($editingProduct['rank_label'] ?? '') ?>"></label><label>Platform<input name="platform" maxlength="80" placeholder="Contoh: PC / Mobile" value="<?= e($editingProduct['platform'] ?? '') ?>"></label><label>URL gambar (opsional)<input type="url" name="image_url" maxlength="500" placeholder="https://..." value="<?= e($editingProduct['image_url'] ?? '') ?>"></label><label class="form-span">Deskripsi publik *<textarea name="description" rows="3" maxlength="5000" required placeholder="Informasi akun yang boleh dilihat calon pembeli"><?= e($editingProduct['description'] ?? '') ?></textarea></label><label class="form-span">Detail akun rahasia *<textarea name="private_details" rows="3" maxlength="5000" required placeholder="Email akun, metode serah terima, atau catatan privat. Hanya terlihat di dashboard admin."><?= e($editingProduct['private_details'] ?? '') ?></textarea><small>Jangan masukkan kredensial aktif sebelum siap melakukan serah terima. Data ini hanya ditampilkan di area admin.</small></label></div>
                <div class="form-actions"><button class="button button-primary" type="submit"><?= $editingProduct ? 'Simpan perubahan' : 'Terbitkan listing' ?> <span>→</span></button><?php if ($editingProduct): ?><a class="button button-outline" href="admin.php#produk">Batal edit</a><?php endif; ?></div>
            </form>
            <div class="table-wrap"><table><thead><tr><th>Listing</th><th>Harga</th><th>Status</th><th>Dibuat</th><th>Aksi</th></tr></thead><tbody>
                <?php foreach ($products as $product): ?><tr><td><strong><?= e($product['title']) ?></strong><small><?= e($product['game']) ?><?php if ($product['private_details'] !== ''): ?> · Detail: <?= e($product['private_details']) ?><?php endif; ?></small></td><td><?= e(money($product['price'])) ?></td><td><span class="status-badge status-<?= e($product['status']) ?>"><?= e(status_label($product['status'])) ?></span></td><td><?= e(date('d M Y', strtotime($product['created_at']))) ?></td><td class="action-cell"><a class="text-link" href="admin.php?edit=<?= (int) $product['id'] ?>#produk">Edit</a><?php if ($product['status'] !== 'reserved'): ?><form method="post" onsubmit="return confirm('Hapus listing ini?')"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete_product"><input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>"><button class="text-button text-danger" type="submit">Hapus</button></form><?php endif; ?></td></tr><?php endforeach; ?>
                <?php if (!$products): ?><tr><td colspan="5" class="table-empty">Belum ada listing. Tambahkan akun pertama melalui formulir di atas.</td></tr><?php endif; ?>
            </tbody></table></div>
        </section>

        <section class="admin-section" id="pesanan">
            <div class="admin-section-heading"><div><p class="eyebrow">PESANAN MASUK</p><h2>Kelola transaksi</h2></div><span class="listing-count"><?= count($orders) ?> pesanan terbaru</span></div>
            <div class="table-wrap"><table><thead><tr><th>Kode / pelanggan</th><th>Akun</th><th>Kontak</th><th>Total</th><th>Status</th><th>Detail akun (admin)</th></tr></thead><tbody>
                <?php foreach ($orders as $order): ?><tr><td><strong><?= e($order['order_code']) ?></strong><small><?= e($order['customer_name']) ?> · <?= e(date('d M Y H:i', strtotime($order['created_at']))) ?></small><?php if ($order['note']): ?><small>Catatan: <?= e($order['note']) ?></small><?php endif; ?></td><td><strong><?= e($order['title']) ?></strong><small><?= e($order['game']) ?></small></td><td><a class="text-link" href="https://wa.me/<?= e(preg_replace('/\D+/', '', $order['whatsapp'])) ?>" target="_blank" rel="noopener noreferrer"><?= e($order['whatsapp']) ?> ↗</a></td><td><?= e(money($order['total'])) ?></td><td><form method="post" class="status-form"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="update_order"><input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>"><select name="status" aria-label="Status pesanan" <?= in_array($order['status'], ['completed', 'cancelled'], true) ? 'disabled' : '' ?> onchange="this.form.submit()"><?php foreach (['pending', 'paid', 'completed', 'cancelled'] as $status): ?><option value="<?= e($status) ?>" <?= $order['status'] === $status ? 'selected' : '' ?>><?= e(status_label($status)) ?></option><?php endforeach; ?></select></form></td><td class="private-cell"><?= e($order['private_details']) ?></td></tr><?php endforeach; ?>
                <?php if (!$orders): ?><tr><td colspan="6" class="table-empty">Belum ada pesanan masuk.</td></tr><?php endif; ?>
            </tbody></table></div>
        </section>
    <?php endif; ?>
</main>
<footer class="admin-footer"><div class="container">LevelUp Market Admin · Jaga keamanan kredensial dan detail akun pelanggan.</div></footer>
</body>
</html>
