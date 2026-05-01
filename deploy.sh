#!/bin/bash
# ============================================================
# deploy.sh — Script Deploy Selektif LMS SIAKAD
# Jalankan dari: /var/www/lms/
# Fungsi:
#   - Update folder src/ dari GitHub
#   - Update folder public/assets/ dari GitHub
#   - TIDAK menyentuh public/uploads/ dan config/database.php
# ============================================================

set -e  # Berhenti jika ada error

GIT_DIR="/var/www/lms"

echo "========================================"
echo "  SIAKAD Deploy Script"
echo "========================================"

# Pastikan kita di direktori yang benar
cd "$GIT_DIR"

echo ""
echo "[1/3] Mengambil perubahan dari GitHub..."
git fetch origin main

echo ""
echo "[2/3] Memperbarui folder src/..."
git checkout origin/main -- src/
echo "      ✓ src/ berhasil diperbarui"

echo ""
echo "[3/3] Memperbarui public/assets/ (uploads/ tidak disentuh)..."
git checkout origin/main -- public/assets/
echo "      ✓ public/assets/ berhasil diperbarui"

echo ""
echo "========================================"
echo "  Deploy selesai!"
echo "  - src/            → $GIT_DIR/src/"
echo "  - public/assets/  → $GIT_DIR/public/assets/"
echo "  - public/uploads/ TIDAK disentuh ✓"
echo "  - config/         TIDAK disentuh ✓"
echo "========================================"


echo "========================================"
echo "  SIAKAD Deploy Script"
echo "========================================"

# Pastikan kita di direktori yang benar
cd "$GIT_DIR"

echo ""
echo "[1/3] Mengambil perubahan dari GitHub..."
git fetch origin main

echo ""
echo "[2/3] Memperbarui folder src/..."
git checkout origin/main -- src/
echo "      ✓ src/ berhasil diperbarui"

echo ""
echo "[3/3] Menyinkronkan public/assets/ ke $PUBLIC_TARGET/assets/..."
# Buat direktori target jika belum ada
mkdir -p "$PUBLIC_TARGET/assets"
# Gunakan rsync jika tersedia, fallback ke cp
if command -v rsync &> /dev/null; then
    rsync -av --delete "$GIT_DIR/public/assets/" "$PUBLIC_TARGET/assets/"
else
    cp -r "$GIT_DIR/public/assets/." "$PUBLIC_TARGET/assets/"
fi
echo "      ✓ public/assets/ berhasil disinkronkan"

echo ""
echo "========================================"
echo "  Deploy selesai!"
echo "  - src/         → $GIT_DIR/src/"
echo "  - public/assets/ → $PUBLIC_TARGET/assets/"
echo "  - public/uploads/ TIDAK disentuh"
echo "  - config/database.php TIDAK disentuh"
echo "========================================"
