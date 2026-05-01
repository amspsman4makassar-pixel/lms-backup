<?php
// snbp_diag.php - Halaman Diagnostik Upload SNBP
// HAPUS FILE INI SETELAH SELESAI DIAGNOSA!
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Akses Ditolak.");
}

$upload_dir = __DIR__ . '/../../public/uploads/index_kelulusan_snbp/';
$debug_log  = __DIR__ . '/debug_upload.txt';

echo "<pre style='font-family:monospace; font-size:14px; background:#1e1e1e; color:#d4d4d4; padding:2rem;'>";
echo "<b style='color:#569cd6; font-size:1.2rem;'>== SNBP UPLOAD DIAGNOSTIK ==</b>\n\n";

echo "<b style='color:#4ec9b0;'>1. INFORMASI SERVER</b>\n";
echo "  SERVER_SOFTWARE : " . ($_SERVER['SERVER_SOFTWARE'] ?? 'N/A') . "\n";
echo "  PHP Version     : " . PHP_VERSION . "\n";
echo "  upload_max_filesize: " . ini_get('upload_max_filesize') . "\n";
echo "  post_max_size   : " . ini_get('post_max_size') . "\n";
echo "  memory_limit    : " . ini_get('memory_limit') . "\n";
echo "  max_execution_time: " . ini_get('max_execution_time') . "\n\n";

echo "<b style='color:#4ec9b0;'>2. GD LIBRARY</b>\n";
echo "  GD aktif        : " . (extension_loaded('gd') ? "<b style='color:#6a9955;'>YA ✓</b>" : "<b style='color:#f44747;'>TIDAK ✗ (Upload tanpa GD tetap bisa lewat fallback)</b>") . "\n\n";

echo "<b style='color:#4ec9b0;'>3. DIREKTORI UPLOAD</b>\n";
echo "  Path            : $upload_dir\n";
echo "  Exists          : " . (is_dir($upload_dir) ? "<b style='color:#6a9955;'>YA ✓</b>" : "<b style='color:#f44747;'>TIDAK ✗ (Folder tidak ada!)</b>") . "\n";
echo "  Bisa Ditulis    : " . (is_writable($upload_dir) ? "<b style='color:#6a9955;'>YA ✓</b>" : "<b style='color:#f44747;'>TIDAK ✗ (Permission error! Jalankan: chmod 755 \"$upload_dir\")</b>") . "\n";

$files_in_dir = glob($upload_dir . '*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE);
echo "  Jumlah file     : " . count($files_in_dir) . " foto\n\n";

echo "<b style='color:#4ec9b0;'>4. FILE LOG DEBUG</b>\n";
if (file_exists($debug_log)) {
    echo "  File log ada : YA\n";
    echo "  Isi log (100 baris terakhir):\n";
    echo "<b style='color:#ce9178;'>---LOG START---</b>\n";
    $lines = file($debug_log);
    $last  = array_slice($lines, -100);
    echo htmlspecialchars(implode('', $last));
    echo "\n<b style='color:#ce9178;'>---LOG END---</b>\n";
} else {
    echo "  File log ada : <b style='color:#f44747;'>TIDAK ✗ (Belum ada upload yang masuk ke PHP sama sekali!)</b>\n";
    echo "  Artinya: Request AJAX tidak pernah sampai ke PHP - diblok Nginx/Cloudflare\n";
}

echo "\n<b style='color:#4ec9b0;'>5. TEST UPLOAD SEDERHANA (Submit untuk uji)</b>\n";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['test_file'])) {
    $f = $_FILES['test_file'];
    echo "  File diterima  : " . htmlspecialchars($f['name']) . "\n";
    echo "  Size           : " . number_format($f['size'] / 1024, 1) . " KB\n";
    echo "  Type           : " . $f['type'] . "\n";
    echo "  Error code     : " . $f['error'] . "\n";
    if ($f['error'] === 0) {
        $dest = $upload_dir . 'test_diag_' . time() . '.jpg';
        if (move_uploaded_file($f['tmp_name'], $dest)) {
            echo "  Simpan file    : <b style='color:#6a9955;'>BERHASIL ✓ → $dest</b>\n";
        } else {
            echo "  Simpan file    : <b style='color:#f44747;'>GAGAL ✗ (Permission error atau path salah)</b>\n";
        }
    } else {
        echo "  Error          : <b style='color:#f44747;'>PHP Error Code " . $f['error'] . " (1=terlalu besar, 3=parsial)</b>\n";
    }
}
echo "</pre>";
?>
<form method="POST" enctype="multipart/form-data" style="padding:2rem; background:#252526;">
    <p style="color:white; font-family:sans-serif;">Test Upload 1 Foto:</p>
    <input type="file" name="test_file" accept="image/*" style="color:white;">
    <button type="submit" style="padding:0.5rem 1rem; background:#3b82f6; color:white; border:none; border-radius:4px; margin-left:1rem; cursor:pointer;">Test Upload</button>
</form>
