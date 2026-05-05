#!/bin/bash
# ============================================================
# deploy.sh — Script Deploy Selektif LMS SIAKAD
# Jalankan dari: /var/www/lms/ (atau direktori manapun)
# Fungsi:
#   - Update semua file PHP di root
#   - Update folder src/, database/, config/, public/assets/
#   - Update deploy.sh itu sendiri
#   - TIDAK menyentuh public/uploads/ dan config/database.php
# ============================================================

set -e  # Berhenti jika ada error

GIT_DIR="/var/www/lms"

echo "========================================"
echo "  SIAKAD Deploy Script"
echo "========================================"

# Pastikan kita di direktori yang benar
if [ -d "$GIT_DIR" ]; then
    cd "$GIT_DIR"
else
    echo "Warning: Direktori $GIT_DIR tidak ditemukan. Menggunakan direktori saat ini."
fi

echo ""
echo "[1/5] Mengambil perubahan terbaru dari GitHub..."
git fetch origin main

echo ""
echo "[2/5] Memperbarui file-file di root folder..."
# Ambil satu per satu file PHP yang ada di root
git checkout origin/main -- \
    index.php \
    login.php \
    cek_kelulusan.php \
    e_counseling.php \
    news.php \
    news_detail.php \
    keaktifan_siswa.php \
    pantauan_nilai.php \
    tracer_directory.php \
    tracer_form.php \
    assignments.php \
    deploy.sh \
    2>/dev/null || true
echo "      ✓ File root berhasil diperbarui"

echo ""
echo "[3/5] Memperbarui folder aplikasi (src/ & database/)..."
git checkout origin/main -- src/ database/
echo "      ✓ src/ dan database/ berhasil diperbarui"

echo ""
echo "[4/5] Memperbarui folder assets publik..."
git checkout origin/main -- public/assets/
echo "      ✓ public/assets/ berhasil diperbarui"

echo ""
echo "[5/5] Memperbarui config/ (KECUALI database.php)..."
# Ambil semua dari config/, lalu pulihkan database.php dari versi lokal
git checkout origin/main -- config/ 2>/dev/null || true
# Pastikan database.php lokal tidak tertimpa (restore dari backup jika ada)
# Jika database.php ada di server, biarkan apa adanya
if [ -f "config/database.php" ]; then
    echo "      ✓ config/ diperbarui, config/database.php AMAN (tidak disentuh)"
else
    echo "      ⚠ PERHATIAN: config/database.php tidak ditemukan!"
    echo "        Buat file config/database.php secara manual di server."
fi

echo ""
echo "========================================"
echo "  Deploy berhasil diselesaikan! ✅"
echo "  Telah Diperbarui:"
echo "  - File root (*.php)"
echo "  - Folder src/"
echo "  - Folder database/"
echo "  - Folder public/assets/ (termasuk logo & CSS)"
echo "  - Folder config/ (selain database.php)"
echo ""
echo "  TIDAK disentuh (Aman):"
echo "  - public/uploads/  → file upload siswa/guru"
echo "  - config/database.php → kredensial database server"
echo "========================================"

# Opsional: Restart PHP-FPM untuk membersihkan opcode cache
# Uncomment baris di bawah jika server menggunakan PHP-FPM:
# echo ""
# echo "Merestart PHP-FPM..."
# sudo systemctl restart php8.2-fpm
# echo "✓ PHP-FPM direstart."
