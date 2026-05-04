#!/bin/bash
# ============================================================
# deploy.sh — Script Deploy Selektif LMS SIAKAD
# Jalankan dari: /var/www/lms/
# Fungsi:
#   - Update file utama di root (*.php)
#   - Update folder src/, database/, dan public/assets/
#   - TIDAK menyentuh public/uploads/ dan config/database.php
# ============================================================

set -e  # Berhenti jika ada error

GIT_DIR="/var/www/lms"

echo "========================================"
echo "  SIAKAD Deploy Script (Versi Baru)"
echo "========================================"

# Pastikan kita di direktori yang benar
if [ -d "$GIT_DIR" ]; then
    cd "$GIT_DIR"
else
    echo "Warning: Direktori $GIT_DIR tidak ditemukan. Menggunakan direktori saat ini."
fi

echo ""
echo "[1/4] Mengambil perubahan terbaru dari GitHub..."
git fetch origin main

echo ""
echo "[2/4] Memperbarui file utama di root folder..."
# Mengambil semua file PHP di root folder (termasuk index.php, cek_kelulusan.php, dll)
git checkout origin/main -- \*.php
echo "      ✓ File root (*.php) berhasil diperbarui"

echo ""
echo "[3/4] Memperbarui folder aplikasi (src/ & database/)..."
git checkout origin/main -- src/ database/
echo "      ✓ src/ dan database/ berhasil diperbarui"

echo ""
echo "[4/4] Memperbarui folder assets publik..."
git checkout origin/main -- public/assets/
echo "      ✓ public/assets/ berhasil diperbarui"

echo ""
echo "========================================"
echo "  Deploy berhasil diselesaikan! ✅"
echo "  Telah Diperbarui:"
echo "  - File root (*.php)"
echo "  - Folder src/"
echo "  - Folder database/"
echo "  - Folder public/assets/"
echo ""
echo "  TIDAK disentuh (Aman):"
echo "  - public/uploads/ (Data file yang diupload siswa/guru)"
echo "  - config/database.php (Kredensial database lokal)"
echo "========================================"
