# LevelUp Market

Marketplace akun game berbasis PHP native dan MySQL/MariaDB, cocok dijalankan melalui XAMPP.

## Menjalankan di XAMPP

1. Salin folder proyek ke `C:\xampp\htdocs\projek_akhir`.
2. Jalankan **Apache** dan **MySQL** dari XAMPP Control Panel.
3. Jika kredensial MySQL berbeda dari konfigurasi standar XAMPP, ubah `DB_HOST`, `DB_USER`, `DB_PASS`, dan `DB_NAME` di `config.php`.
4. Buka `http://localhost/projek_akhir/setup.php`. Setup akan membuat database dan tabel, lalu meminta pembuatan username serta password admin pertama (minimal 6 karakter).
5. Setelah admin dibuat, masuk melalui `http://localhost/projek_akhir/admin.php`, tambahkan listing, lalu buka halaman utama toko.
6. Setelah setup selesai, hapus `setup.php` atau batasi aksesnya di konfigurasi Apache.

## Fitur

- Katalog publik dengan pencarian, filter game, dan informasi listing.
- Form permintaan pembelian yang menahan listing agar tidak dipesan dua kali.
- Dashboard admin dengan ringkasan toko, pengelolaan listing, data pesanan, dan perubahan status transaksi.
- Detail akun privat hanya ditampilkan pada dashboard admin.
- Password admin disimpan menggunakan `password_hash`; form POST dilindungi token CSRF dan query menggunakan prepared statements.

## Catatan transaksi

Website ini belum mengintegrasikan payment gateway atau pengiriman akun otomatis. Status pembayaran dan serah terima ditangani admin. Pastikan penjual berhak mengalihkan akun, tidak membagikan detail akun sebelum transaksi terkonfirmasi, dan mematuhi ketentuan layanan game terkait.
