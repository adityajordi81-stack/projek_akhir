<?php
declare(strict_types=1);
require_once __DIR__ . '/functions.php';

$pdo = null;
$databaseError = '';
$success = flash('success');
$error = flash('error');
$search = '';
$gameFilter = '';

try {
    $pdo = db();
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && post_value('action') === 'order') {
        verify_csrf();
        $productId = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT);
        $customerName = post_value('customer_name', 120);
        $whatsapp = post_value('whatsapp', 30);
        $note = post_value('note', 1000);

        if (!$productId || $customerName === '' || !preg_match('/^[0-9+()\\-\\s]{8,30}$/', $whatsapp)) {
            flash('error', 'Lengkapi nama dan nomor WhatsApp yang valid, lalu coba lagi.');
            redirect('index.php#katalog');
        }

        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('SELECT id, title, price FROM products WHERE id = ? AND status = ? FOR UPDATE');
            $stmt->execute([$productId, 'active']);
            $product = $stmt->fetch();
            if (!$product) {
                throw new RuntimeException('Akun ini sudah tidak tersedia. Silakan pilih listing lain.');
            }

            $orderCode = 'LU' . date('ymd') . strtoupper(bin2hex(random_bytes(3)));
            $stmt = $pdo->prepare(
                'INSERT INTO orders (order_code, product_id, customer_name, whatsapp, note, total)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$orderCode, $productId, $customerName, $whatsapp, $note, $product['price']]);
            $pdo->prepare("UPDATE products SET status = 'reserved' WHERE id = ?")->execute([$productId]);
            $pdo->commit();

            flash('success', 'Permintaan berhasil dibuat dengan kode ' . $orderCode . '. Admin akan menghubungimu melalui WhatsApp untuk proses pembayaran dan serah terima.');
            redirect('index.php#katalog');
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            flash('error', $exception instanceof RuntimeException
                ? $exception->getMessage()
                : 'Pesanan belum dapat dibuat. Silakan coba kembali atau hubungi admin.');
            redirect('index.php#katalog');
        }
    }

    $search = trim((string) ($_GET['q'] ?? ''));
    $gameFilter = trim((string) ($_GET['game'] ?? ''));
    $sql = "SELECT id, game, title, description, price, rank_label, platform, image_url
            FROM products WHERE status = 'active'";
    $params = [];
    if ($search !== '') {
        $sql .= ' AND (title LIKE ? OR game LIKE ? OR description LIKE ?)';
        $term = '%' . $search . '%';
        array_push($params, $term, $term, $term);
    }
    if ($gameFilter !== '') {
        $sql .= ' AND game = ?';
        $params[] = $gameFilter;
    }
    $sql .= ' ORDER BY created_at DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll();
    $games = $pdo->query("SELECT DISTINCT game FROM products WHERE status = 'active' ORDER BY game")->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $exception) {
    $databaseError = 'Toko belum tersambung ke database. Jalankan setup melalui tombol di bawah dan pastikan MySQL XAMPP aktif.';
    $products = [];
    $games = [];
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Temukan akun game pilihan dengan proses transaksi yang jelas di Pusat Jual Beli Akun Game.">
    <title>pusat jual beli akun game</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="site-header">
    <div class="container nav-wrap">
        <a class="brand" href="index.php"><span class="brand-mark">L</span> JUAL BELI<span class="brand-muted"> MARKET</span></a>
        <nav class="main-nav"><a href="#katalog">Katalog</a><a href="#cara-kerja">Cara kerja</a></nav>
        <a class="button button-outline nav-admin" href="admin.php">Admin <span>↗</span></a>
    </div>
</header>

<main>
    <section class="hero">
        <div class="container hero-grid">
            <div class="hero-copy">
                <p class="eyebrow"><span class="live-dot"></span> MARKETPLACE AKUN GAME PILIHAN</p>
                <h1>Mulai dari akun<br>yang <span class="text-gradient">tepat.</span></h1>
                <p class="hero-description">Temukan akun game incaranmu. Detail, harga, dan proses transaksi yang jelas—supaya kamu bisa langsung fokus main.</p>
                <div class="hero-actions"><a class="button button-primary" href="#katalog">Jelajahi katalog <span>↓</span></a><span class="hero-caption">Pilihan lintas game, satu tempat.</span></div>
                <div class="hero-stats"><div><strong><?= is_array($products) ? count($products) : 0 ?>+</strong><span>Akun tersedia</span></div><i></i><div><strong><?= count($games ?? []) ?>+</strong><span>Game pilihan</span></div><i></i><div><strong>1:1</strong><span>Bantuan admin</span></div></div>
            </div>
            <div class="hero-art" aria-label="Ilustrasi game">
                <div class="art-orbit orbit-one"></div><div class="art-orbit orbit-two"></div>
                <div class="art-glow"></div>
                <div class="floating-tag tag-top"><span class="tag-icon">jb</span><span><b>Temukan akunmu</b><small>Game favorit, siap dimainkan</small></span></div>
                <div class="hero-emblem"><span class="emblem-cross">jb</span><span class="emblem-word">PLAY<br><b>YOUR WAY</b></span><span class="emblem-small">LEVEL UP / 2026</span></div>
                <div class="floating-tag tag-bottom"><span class="online-avatar">L</span><span><b>Proses transparan</b><small>Admin bantu sampai selesai</small></span><span class="verified">✓</span></div>
                <span class="art-spark spark-a">✳</span><span class="art-spark spark-b">jb</span><span class="art-spark spark-c">✧</span>
            </div>
        </div>
    </section>

    <section class="trust-strip"><div class="container trust-inner"><span><b>✳</b> Pilihan akun lintas game</span><span><b>◇</b> Harga tampil transparan</span><span><b>↗</b> Admin bantu proses transaksi</span></div></section>

    <section class="catalog-section" id="katalog">
        <div class="container">
            <div class="section-heading">
                <div><p class="eyebrow">TEMUKAN MAIN BERIKUTNYA</p><h2>Katalog <span class="text-gradient">pilihan.</span></h2><p class="muted">Akun yang cocok untuk petualanganmu selanjutnya.</p></div>
                <span class="listing-count"><span class="live-dot"></span> <?= count($products) ?> listing tersedia</span>
            </div>
            <?php if ($databaseError !== ''): ?>
                <div class="notice notice-info"><?= e($databaseError) ?> <a href="setup.php">Buka setup →</a></div>
            <?php endif; ?>
            <?php if ($success): ?><div class="notice notice-success"><?= e($success) ?></div><?php endif; ?>
            <?php if ($error): ?><div class="notice notice-error"><?= e($error) ?></div><?php endif; ?>

            <form class="filter-bar" method="get" action="index.php#katalog">
                <label class="search-field"><span>⌕</span><input type="search" name="q" value="<?= e($search ?? '') ?>" placeholder="Cari game atau akun..."></label>
                <label class="select-field"><span>Game</span><select name="game"><option value="">Semua game</option><?php foreach ($games as $game): ?><option value="<?= e($game) ?>" <?= ($gameFilter ?? '') === $game ? 'selected' : '' ?>><?= e($game) ?></option><?php endforeach; ?></select></label>
                <button class="button button-dark" type="submit">Cari akun <span>→</span></button>
            </form>

            <?php if (!$products): ?>
                <div class="empty-state"><div class="empty-icon">✳</div><h3><?= $search || $gameFilter ? 'Belum ada akun yang cocok.' : 'Listing pertama segera hadir.' ?></h3><p><?= $search || $gameFilter ? 'Coba kata kunci atau filter game lainnya.' : 'Admin dapat menambahkan akun melalui dashboard.' ?></p><?php if ($search || $gameFilter): ?><a href="index.php#katalog" class="text-link">Hapus filter →</a><?php endif; ?></div>
            <?php else: ?>
                <div class="product-grid">
                    <?php foreach ($products as $index => $product): ?>
                        <article class="product-card">
                            <div class="product-visual visual-<?= $index % 4 ?>">
                                <?php if ($product['image_url'] !== ''): ?><img src="<?= e($product['image_url']) ?>" alt="<?= e($product['title']) ?>" loading="lazy"><?php else: ?><?php if ($index % 4 === 0): ?><span class="visual-symbol visual-jb">jb</span><?php else: ?><span class="visual-symbol"><?= ['◇', '◈', '✳', '⬡'][$index % 4] ?></span><?php endif; ?><?php endif; ?>
                                <span class="game-pill"><?= e($product['game']) ?></span><span class="available-pill"><i></i> Tersedia</span>
                            </div>
                            <div class="product-info">
                                <div class="product-title-row"><h3><?= e($product['title']) ?></h3><span class="product-arrow">↗</span></div>
                                <p class="product-description"><?= e($product['description']) ?></p>
                                <div class="product-meta"><?php if ($product['rank_label']): ?><span>♢ <?= e($product['rank_label']) ?></span><?php endif; ?><?php if ($product['platform']): ?><span>⌘ <?= e($product['platform']) ?></span><?php endif; ?></div>
                                <div class="product-footer"><div><small>HARGA</small><strong><?= e(money($product['price'])) ?></strong></div><button class="button button-small button-primary order-button" type="button" data-id="<?= (int) $product['id'] ?>" data-title="<?= e($product['title']) ?>" data-price="<?= e(money($product['price'])) ?>">Pesan akun <span>→</span></button></div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="how-section" id="cara-kerja">
        <div class="container how-grid">
            <div><p class="eyebrow">SIMPLE, JELAS, LANGSUNG</p><h2>Transaksi yang<br><span class="text-gradient">nggak bikin bingung.</span></h2><p class="muted">Setiap pesanan ditangani admin secara langsung agar proses serah terima akun lebih terarah.</p></div>
            <div class="steps"><div class="step"><span>01</span><div><h3>Pilih akun incaran</h3><p>Bandingkan game, detail, dan harga di katalog.</p></div></div><div class="step"><span>02</span><div><h3>Kirim permintaan</h3><p>Isi nama dan WhatsApp. Listing akan dipesan sementara untukmu.</p></div></div><div class="step"><span>03</span><div><h3>Koordinasi dengan admin</h3><p>Admin menghubungi untuk konfirmasi pembayaran dan serah terima.</p></div></div></div>
        </div>
    </section>
</main>

<footer class="site-footer"><div class="container footer-inner"><a class="brand" href="index.php"><span class="brand-mark">L</span> LEVELUP<span class="brand-muted"> MARKET</span></a><span>Transaksi aman dimulai dari komunikasi yang jelas. Pastikan akun dapat dialihkan dan sesuai ketentuan game.</span><span>© <?= date('Y') ?> LevelUp Market</span></div></footer>

<dialog class="order-dialog" id="orderDialog">
    <form method="post" class="order-form">
        <button class="dialog-close" type="button" onclick="document.getElementById('orderDialog').close()" aria-label="Tutup">×</button>
        <p class="eyebrow">BUAT PERMINTAAN</p><h2>Amankan akun pilihanmu.</h2><p class="muted">Admin akan menghubungimu untuk mengonfirmasi ketersediaan dan proses pembayaran.</p>
        <div class="order-summary"><span id="orderTitle">Akun pilihan</span><strong id="orderPrice"></strong></div>
        <input type="hidden" name="action" value="order"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="product_id" id="productId">
        <label>Nama kamu<input name="customer_name" maxlength="120" required autocomplete="name" placeholder="Nama lengkap"></label>
        <label>Nomor WhatsApp<input name="whatsapp" maxlength="30" required inputmode="tel" placeholder="Contoh: 081234567890"></label>
        <label>Catatan (opsional)<textarea name="note" maxlength="1000" rows="3" placeholder="Waktu terbaik untuk dihubungi, dll."></textarea></label>
        <button class="button button-primary button-full" type="submit">Kirim permintaan <span>→</span></button>
        <p class="tiny-note">Belum ada pembayaran otomatis. Jangan kirim uang sebelum detail transaksi dikonfirmasi admin.</p>
    </form>
</dialog>
<script>
function openOrder(button) {
    const id = button.dataset.id;
    const title = button.dataset.title;
    const price = button.dataset.price;
    document.getElementById('productId').value = id;
    document.getElementById('orderTitle').textContent = title;
    document.getElementById('orderPrice').textContent = price;
    document.getElementById('orderDialog').showModal();
}
document.querySelectorAll('.order-button').forEach(button => button.addEventListener('click', () => openOrder(button)));
</script>
</body>
</html>
