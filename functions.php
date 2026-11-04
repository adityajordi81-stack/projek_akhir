<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
    ]);
    session_start();
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(403);
        exit('Permintaan tidak valid. Muat ulang halaman, lalu coba lagi.');
    }
}

function require_admin(): void
{
    if (empty($_SESSION['admin_id'])) {
        header('Location: admin.php');
        exit;
    }
}

function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return null;
    }

    $value = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return is_string($value) ? $value : null;
}

function money(int|float|string $amount): string
{
    return 'Rp ' . number_format((float) $amount, 0, ',', '.');
}

function status_label(string $status): string
{
    return match ($status) {
        'pending' => 'Menunggu pembayaran',
        'paid' => 'Sudah dibayar',
        'completed' => 'Selesai',
        'cancelled' => 'Dibatalkan',
        'active' => 'Tersedia',
        'reserved' => 'Dipesan',
        'sold' => 'Terjual',
        default => ucfirst($status),
    };
}

function redirect(string $location): void
{
    header('Location: ' . $location);
    exit;
}

function post_value(string $key, int $maxLength = 5000): string
{
    $value = $_POST[$key] ?? '';
    if (!is_string($value)) {
        return '';
    }

    $value = trim($value);
    return function_exists('mb_substr')
        ? mb_substr($value, 0, $maxLength)
        : substr($value, 0, $maxLength);
}
