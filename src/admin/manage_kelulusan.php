<?php
// src/admin/manage_kelulusan.php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../login.php"); exit;
}

$success_msg = '';
$error_msg   = '';

// ─── HAPUS ───────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id) {
        $pdo->prepare("DELETE FROM kelulusan_siswa WHERE id = ?")->execute([$id]);
        $success_msg = "Data berhasil dihapus.";
    }
}

// ─── HAPUS MASSAL ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'bulk_delete') {
    $tahun_hapus = trim($_POST['tahun_hapus'] ?? '');
    if ($tahun_hapus) {
        $stmt = $pdo->prepare("DELETE FROM kelulusan_siswa WHERE tahun_kelulusan = ?");
        $stmt->execute([$tahun_hapus]);
        $count = $stmt->rowCount();
        $success_msg = "$count data kelulusan tahun $tahun_hapus berhasil dihapus.";
    } else {
        $error_msg = "Pilih tahun kelulusan yang ingin dihapus secara massal.";
    }
}

// ─── TAMBAH / EDIT MANUAL ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    $id       = (int)($_POST['id'] ?? 0);
    $nisn     = trim($_POST['nisn']      ?? '');
    $nama     = trim($_POST['nama']      ?? '');
    $kelas    = trim($_POST['kelas']     ?? '');
    $status   = ($_POST['status'] ?? 'lulus') === 'tidak_lulus' ? 'tidak_lulus' : 'lulus';
    $tahun    = trim($_POST['tahun']     ?? '');
    $catatan  = trim($_POST['catatan']   ?? '');

    if (empty($nisn) || empty($nama)) {
        $error_msg = "NISN dan Nama Siswa wajib diisi.";
    } else {
        try {
            if ($id) {
                $stmt = $pdo->prepare("UPDATE kelulusan_siswa SET nisn=?, nama_siswa=?, kelas=?, status_kelulusan=?, tahun_kelulusan=?, catatan=? WHERE id=?");
                $stmt->execute([$nisn, $nama, $kelas, $status, $tahun, $catatan, $id]);
                $success_msg = "Data berhasil diperbarui.";
            } else {
                $stmt = $pdo->prepare("INSERT INTO kelulusan_siswa (nisn, nama_siswa, kelas, status_kelulusan, tahun_kelulusan, catatan) VALUES (?,?,?,?,?,?)");
                $stmt->execute([$nisn, $nama, $kelas, $status, $tahun, $catatan]);
                $success_msg = "Data berhasil ditambahkan.";
            }
        } catch (PDOException $e) {
            $error_msg = str_contains($e->getMessage(), 'Duplicate') ? "NISN $nisn sudah terdaftar." : "Error: " . $e->getMessage();
        }
    }
}

// ─── IMPORT EXCEL / CSV ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'import') {
    if (isset($_FILES['import_file']) && $_FILES['import_file']['error'] === 0) {
        $ext  = strtolower(pathinfo($_FILES['import_file']['name'], PATHINFO_EXTENSION));
        $rows = [];

        if ($ext === 'csv') {
            $fh = fopen($_FILES['import_file']['tmp_name'], 'r');
            while (($r = fgetcsv($fh)) !== false) $rows[] = $r;
            fclose($fh);
        } elseif (in_array($ext, ['xlsx', 'xls'])) {
            require_once '../../src/lib/SimpleXLSX.php';
            if ($xlsx = Shuchkin\SimpleXLSX::parse($_FILES['import_file']['tmp_name'])) {
                $rows = $xlsx->rows();
            } else {
                $error_msg = "Gagal parsing file Excel.";
            }
        } else {
            $error_msg = "Hanya file CSV atau Excel (.xlsx/.xls) yang didukung.";
        }

        if ($rows && empty($error_msg)) {
            $ok = $skip = $fail = 0;
            
            // Auto-detect format based on header (Kolom 1: NO, Kolom 2: NAMA, Kolom 3: NISN)
            $is_simple_format = false;
            foreach (array_slice($rows, 0, 10) as $r) {
                if (isset($r[0]) && strtoupper(trim((string)$r[0])) === 'NO' && isset($r[1]) && stripos((string)$r[1], 'NAMA') !== false) {
                    $is_simple_format = true;
                    break;
                }
            }

            foreach ($rows as $i => $r) {
                // Skip empty rows
                if (empty(implode('', $r))) continue;

                if ($is_simple_format) {
                    // Format: No | Nama Siswa | NISN
                    $no = strtoupper(trim((string)($r[0] ?? '')));
                    if ($no === 'NO' || stripos($no, 'DAFTAR') !== false || empty($no)) continue;

                    $nama   = trim((string)($r[1] ?? ''));
                    $nisn   = trim((string)($r[2] ?? ''));
                    if (ctype_digit($nisn) && strlen($nisn) > 0 && strlen($nisn) < 10) {
                        $nisn = str_pad($nisn, 10, "0", STR_PAD_LEFT);
                    }
                    $kelas  = '';
                    $st     = 'lulus'; // default lulus for this format
                    $tahun  = date('Y');
                    $cat    = '';
                } else {
                    // Skip header row for standard format
                    if ($i === 0 && isset($r[0]) && stripos((string)$r[0], 'nisn') !== false) continue;

                    $nisn   = trim((string)($r[0] ?? ''));
                    if (ctype_digit($nisn) && strlen($nisn) > 0 && strlen($nisn) < 10) {
                        $nisn = str_pad($nisn, 10, "0", STR_PAD_LEFT);
                    }
                    $nama   = trim((string)($r[1] ?? ''));
                    $kelas  = trim((string)($r[2] ?? ''));
                    $st     = strtolower(trim((string)($r[3] ?? 'lulus')));
                    $tahun  = trim((string)($r[4] ?? date('Y')));
                    $cat    = trim((string)($r[5] ?? ''));
                }

                if (empty($nisn) || empty($nama) || $nisn === '#REF!' || $nisn === '#VALUE!') { $fail++; continue; }

                $status = ($st === 'tidak_lulus' || $st === 'tidak lulus' || $st === '0') ? 'tidak_lulus' : 'lulus';

                try {
                    $stmt = $pdo->prepare("INSERT INTO kelulusan_siswa (nisn, nama_siswa, kelas, status_kelulusan, tahun_kelulusan, catatan)
                                          VALUES (?,?,?,?,?,?)
                                          ON DUPLICATE KEY UPDATE nama_siswa=VALUES(nama_siswa), kelas=VALUES(kelas),
                                          status_kelulusan=VALUES(status_kelulusan), tahun_kelulusan=VALUES(tahun_kelulusan), catatan=VALUES(catatan)");
                    $stmt->execute([$nisn, $nama, $kelas, $status, $tahun, $cat]);
                    $ok++;
                } catch (PDOException $e) { $fail++; }
            }
            $success_msg = "Import selesai: $ok data berhasil, $fail baris dilewati/gagal.";
        }
    } else {
        $error_msg = "File gagal diupload atau tidak dipilih.";
    }
}

// ─── EDIT MODE ────────────────────────────────────────────────────────────────
$edit_data = null;
if (isset($_GET['edit'])) {
    $s = $pdo->prepare("SELECT * FROM kelulusan_siswa WHERE id = ?");
    $s->execute([(int)$_GET['edit']]);
    $edit_data = $s->fetch();
}

// ─── FETCH LIST ───────────────────────────────────────────────────────────────
$search = trim($_GET['q'] ?? '');
$tahun_filter = trim($_GET['tahun'] ?? '');
$page  = max(1, (int)($_GET['page'] ?? 1));
$per   = 20;
$offset = ($page - 1) * $per;

$where  = "WHERE 1=1";
$params = [];
if ($search) {
    $where .= " AND (nisn LIKE ? OR nama_siswa LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($tahun_filter) {
    $where .= " AND tahun_kelulusan = ?";
    $params[] = $tahun_filter;
}

$total = $pdo->prepare("SELECT COUNT(*) FROM kelulusan_siswa $where");
$total->execute($params);
$total_rows  = $total->fetchColumn();
$total_pages = max(1, ceil($total_rows / $per));

$stmt = $pdo->prepare("SELECT * FROM kelulusan_siswa $where ORDER BY nama_siswa ASC LIMIT $per OFFSET $offset");
$stmt->execute($params);
$list = $stmt->fetchAll();

// Tahun list for filter
$tahun_list = $pdo->query("SELECT DISTINCT tahun_kelulusan FROM kelulusan_siswa WHERE tahun_kelulusan IS NOT NULL AND tahun_kelulusan != '' ORDER BY tahun_kelulusan DESC")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kelola Data Kelulusan — Admin</title>
<link rel="stylesheet" href="/public/assets/css/style.css">
<style>
.status-badge {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; letter-spacing: .4px;
}
.badge-lulus     { background: rgba(16,185,129,.15); color: #059669; }
.badge-tidak     { background: rgba(239,68,68,.12);  color: #dc2626; }
.import-zone     { border: 2px dashed var(--border); border-radius: var(--radius-lg); padding: 2rem; text-align: center; transition: border-color .2s; }
.import-zone:hover { border-color: var(--primary); }
.tab-pane        { display: none; }
.tab-pane.active { display: block; }
.seg-btn         { padding: .5rem 1.25rem; border: 1px solid var(--border); background: var(--bg-surface); color: var(--text-primary); border-radius: var(--radius-md); cursor: pointer; font-size: .85rem; font-weight: 600; transition: all .15s; }
.seg-btn.active  { background: var(--primary); color: #fff; border-color: var(--primary); }
</style>
</head>
<body>
<div class="app-container">
<?php include '../templates/sidebar.php'; ?>
<main class="main-content">

    <div class="page-toolbar">
        <div class="page-toolbar-left">
            <h1 class="page-title">Kelola Data Kelulusan</h1>
            <p class="page-subtitle">Tambah, import, dan kelola data kelulusan siswa yang ditampilkan di portal publik</p>
        </div>
    </div>

    <div class="page-content">

        <?php if ($success_msg): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success_msg) ?></div>
        <?php endif; ?>
        <?php if ($error_msg): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_msg) ?></div>
        <?php endif; ?>

        <!-- Segmented Control -->
        <div style="display:flex; gap:.5rem; margin-bottom:1.5rem; flex-wrap:wrap;">
            <button class="seg-btn <?= !$edit_data ? 'active' : '' ?>" onclick="switchTab('tab-form', this)">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-3px;margin-right:4px;"><path d="M12 20h9"></path><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg> <?= $edit_data ? 'Edit Data' : 'Tambah Manual' ?>
            </button>
            <button class="seg-btn" onclick="switchTab('tab-import', this)"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-3px;margin-right:4px;"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg> Import Excel / CSV</button>
            <button class="seg-btn active" onclick="switchTab('tab-list', this)" id="btn-list"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-3px;margin-right:4px;"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg> Daftar Data (<?= $total_rows ?>)</button>
        </div>

        <!-- FORM TAMBAH / EDIT -->
        <div id="tab-form" class="tab-pane page-section <?= $edit_data ? 'active' : '' ?>">
            <div class="panel-header">
                <h3 class="panel-title"><?= $edit_data ? '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-3px;margin-right:4px;"><path d="M12 20h9"></path><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg> Edit Data Kelulusan' : '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-3px;margin-right:4px;"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg> Tambah Data Kelulusan' ?></h3>
            </div>
            <form method="POST" style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= (int)($edit_data['id'] ?? 0) ?>">

                <div class="form-group">
                    <label>NISN <span style="color:#ef4444">*</span></label>
                    <input type="text" name="nisn" class="form-control" required maxlength="20"
                           value="<?= htmlspecialchars($edit_data['nisn'] ?? '') ?>" placeholder="Contoh: 0071234567">
                </div>
                <div class="form-group">
                    <label>Nama Siswa <span style="color:#ef4444">*</span></label>
                    <input type="text" name="nama" class="form-control" required maxlength="255"
                           value="<?= htmlspecialchars($edit_data['nama_siswa'] ?? '') ?>" placeholder="Nama lengkap">
                </div>
                <div class="form-group">
                    <label>Kelas</label>
                    <input type="text" name="kelas" class="form-control" maxlength="100"
                           value="<?= htmlspecialchars($edit_data['kelas'] ?? '') ?>" placeholder="Contoh: XII IPA 1">
                </div>
                <div class="form-group">
                    <label>Tahun Kelulusan</label>
                    <input type="text" name="tahun" class="form-control" maxlength="10"
                           value="<?= htmlspecialchars($edit_data['tahun_kelulusan'] ?? date('Y')) ?>" placeholder="Contoh: 2025">
                </div>
                <div class="form-group">
                    <label>Status Kelulusan</label>
                    <select name="status" class="form-control">
                        <option value="lulus" <?= ($edit_data['status_kelulusan'] ?? 'lulus') === 'lulus' ? 'selected' : '' ?>>Lulus</option>
                        <option value="tidak_lulus" <?= ($edit_data['status_kelulusan'] ?? '') === 'tidak_lulus' ? 'selected' : '' ?>>Tidak Lulus</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Catatan (opsional)</label>
                    <input type="text" name="catatan" class="form-control" maxlength="500"
                           value="<?= htmlspecialchars($edit_data['catatan'] ?? '') ?>" placeholder="Misal: Nilai terbaik angkatan">
                </div>

                <div style="grid-column:1/-1; display:flex; gap:.75rem;">
                    <button type="submit" class="btn"><?= $edit_data ? 'Simpan Perubahan' : 'Tambah Data' ?></button>
                    <?php if ($edit_data): ?>
                    <a href="manage_kelulusan.php" class="btn" style="background:var(--bg-surface);color:var(--text-primary);border:1px solid var(--border);">Batal Edit</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- IMPORT -->
        <div id="tab-import" class="tab-pane page-section">
            <div class="panel-header">
                <h3 class="panel-title"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-4px;margin-right:6px;"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg> Import dari Excel / CSV</h3>
            </div>
            <div class="import-zone" id="importDropZone">
                <form method="POST" enctype="multipart/form-data" id="importForm">
                    <input type="hidden" name="action" value="import">
                    <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom:.75rem"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    <p style="margin:.25rem 0 .75rem; color:var(--text-muted); font-size:.9rem;">Drag & drop atau klik untuk pilih file</p>
                    <input type="file" name="import_file" id="importFile" accept=".csv,.xlsx,.xls" required style="display:none">
                    <button type="button" class="btn" style="margin-bottom:.5rem" onclick="document.getElementById('importFile').click()">Pilih File</button>
                    <span id="importFileName" style="display:block; font-size:.8rem; color:var(--text-muted); margin-top:.5rem;"></span>
                    <button type="submit" class="btn btn-success" id="importSubmit" style="display:none; margin-top:.75rem">Upload & Import</button>
                </form>
            </div>
            <div style="margin-top:1.5rem; background:var(--bg-muted); border-radius:var(--radius-md); padding:1rem; font-size:.85rem;">
                <strong>Mendukung Format File Referensi Sekolah:</strong>
                <p style="margin-bottom:.5rem;">Sistem otomatis mendeteksi file Excel dengan kolom: <strong>NO | NAMA SISWA | NISN</strong>. Status kelulusan akan otomatis diset "Lulus" dengan tahun <?= date('Y') ?>.</p>
                <div style="overflow-x:auto; margin-top:.5rem;">
                    <table class="page-table">
                        <thead><tr>
                            <th>NO</th>
                            <th>NAMA SISWA</th>
                            <th>NISN</th>
                        </tr></thead>
                        <tbody><tr>
                            <td>1</td><td>Ahmad Yusuf</td><td>0071234567</td>
                        </tr></tbody>
                    </table>
                </div>
                
                <hr style="margin: 1.5rem 0; border: 0; border-top: 1px solid var(--border);">

                <strong>Atau Format Lengkap (Opsional):</strong>
                <div style="overflow-x:auto; margin-top:.5rem;">
                    <table class="page-table">
                        <thead><tr>
                            <th>NISN</th>
                            <th>Nama Siswa</th>
                            <th>Kelas</th>
                            <th>Status (lulus/tidak_lulus)</th>
                            <th>Tahun</th>
                            <th>Catatan</th>
                        </tr></thead>
                        <tbody><tr>
                            <td>0071234567</td><td>Ahmad Yusuf</td><td>XII IPA 1</td><td>lulus</td><td>2025</td><td></td>
                        </tr></tbody>
                    </table>
                </div>
                <p style="margin-top:.5rem; color:var(--text-muted);">⚠️ Jika NISN sudah ada, data akan <strong>diperbarui</strong> (upsert). Pastikan kolom NISN unik per siswa.</p>
            </div>
        </div>

        <!-- DAFTAR -->
        <div id="tab-list" class="tab-pane page-section active">
            <div class="panel-header">
                <h3 class="panel-title"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-3px;margin-right:4px;"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg> Daftar Data Kelulusan</h3>
            </div>

            <div style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:1rem; margin-bottom:1rem; align-items:center;">
                <!-- Search & Filter -->
                <form method="GET" style="display:flex; gap:.75rem; flex-wrap:wrap; flex:1;">
                    <input type="text" name="q" class="form-control" style="flex:1; min-width:200px;" placeholder="Cari NISN atau nama..." value="<?= htmlspecialchars($search) ?>">
                    <select name="tahun" class="form-control" style="width:140px;">
                        <option value="">Semua Tahun</option>
                        <?php foreach ($tahun_list as $t): ?>
                        <option value="<?= htmlspecialchars($t) ?>" <?= $tahun_filter === $t ? 'selected' : '' ?>><?= htmlspecialchars($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px;margin-right:2px;"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg> Cari</button>
                    <?php if ($search || $tahun_filter): ?>
                    <a href="manage_kelulusan.php" class="btn" style="background:var(--bg-surface);color:var(--text-primary);border:1px solid var(--border);">Reset</a>
                    <?php endif; ?>
                </form>

                <!-- Hapus Massal -->
                <form method="POST" style="display:inline-flex; gap:.5rem; align-items:center; background:var(--bg-surface); padding:.5rem; border-radius:var(--radius-md); border:1px solid var(--border);" onsubmit="return confirm('PERINGATAN: Semua data kelulusan tahun ' + this.tahun_hapus.value + ' akan dihapus permanen. Lanjutkan?')">
                    <input type="hidden" name="action" value="bulk_delete">
                    <span style="font-size:.8rem; font-weight:600; color:var(--text-muted);">Hapus Massal:</span>
                    <select name="tahun_hapus" class="form-control" style="width:120px; padding:.3rem; height:auto; font-size:.85rem;" required>
                        <option value="">Pilih Tahun...</option>
                        <?php foreach ($tahun_list as $t): ?>
                        <option value="<?= htmlspecialchars($t) ?>"><?= htmlspecialchars($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-danger btn-xs" style="padding:.3rem .6rem; height:auto;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px;margin-right:2px;"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg> Eksekusi</button>
                </form>
            </div>

            <?php if (empty($list)): ?>
            <div class="empty-state">
                <div class="empty-icon" style="margin-bottom:1rem;"><svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="var(--text-muted)" stroke-width="1.5"><path d="M22 10v6M2 10l10-5 10 5-10 5z"></path><path d="M6 12v5c3 3 9 3 12 0v-5"></path></svg></div>
                <h4>Belum ada data kelulusan</h4>
                <p>Tambahkan data secara manual atau import dari Excel/CSV.</p>
            </div>
            <?php else: ?>
            <div style="overflow-x:auto;">
            <table class="page-table">
                <thead><tr>
                    <th>#</th>
                    <th>NISN</th>
                    <th>Nama Siswa</th>
                    <th>Kelas</th>
                    <th>Tahun</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr></thead>
                <tbody>
                <?php foreach ($list as $i => $row): ?>
                <tr>
                    <td><?= $offset + $i + 1 ?></td>
                    <td><code><?= htmlspecialchars($row['nisn']) ?></code></td>
                    <td><?= htmlspecialchars($row['nama_siswa']) ?></td>
                    <td><?= htmlspecialchars($row['kelas'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($row['tahun_kelulusan'] ?? '-') ?></td>
                    <td>
                        <?php if ($row['status_kelulusan'] === 'lulus'): ?>
                        <span class="status-badge badge-lulus"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px;"><polyline points="20 6 9 17 4 12"></polyline></svg> Lulus</span>
                        <?php else: ?>
                        <span class="status-badge badge-tidak"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px;"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg> Tidak Lulus</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div style="display:flex; gap:.4rem;">
                            <a href="manage_kelulusan.php?edit=<?= $row['id'] ?>" class="btn btn-xs" title="Edit"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"></path><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg></a>
                            <form method="POST" onsubmit="return confirm('Hapus data <?= htmlspecialchars(addslashes($row['nama_siswa'])) ?>?')" style="display:inline">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-xs" title="Hapus"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg></button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div style="display:flex; gap:.4rem; justify-content:center; margin-top:1rem; flex-wrap:wrap;">
                <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                <a href="?page=<?= $p ?>&q=<?= urlencode($search) ?>&tahun=<?= urlencode($tahun_filter) ?>"
                   class="btn btn-xs <?= $p === $page ? '' : '' ?>"
                   style="<?= $p === $page ? 'background:var(--primary);color:#fff;' : '' ?>"><?= $p ?></a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>

    </div>
</main>
</div>

<script>
// Tab switching
function switchTab(id, btn) {
    document.querySelectorAll('.tab-pane').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.seg-btn').forEach(el => el.classList.remove('active'));
    document.getElementById(id).classList.add('active');
    btn.classList.add('active');
}

// Auto-open correct tab
document.addEventListener('DOMContentLoaded', () => {
    <?php if ($edit_data): ?>
    // edit mode – already handled by PHP class
    <?php else: ?>
    document.getElementById('btn-list').click();
    <?php endif; ?>

    // File input display
    const fi = document.getElementById('importFile');
    fi.addEventListener('change', () => {
        const name = fi.files[0]?.name ?? '';
        document.getElementById('importFileName').textContent = name ? '📄 ' + name : '';
        document.getElementById('importSubmit').style.display = name ? 'inline-flex' : 'none';
    });

    // Drag & drop
    const zone = document.getElementById('importDropZone');
    zone.addEventListener('dragover', e => { e.preventDefault(); zone.style.borderColor = 'var(--primary)'; });
    zone.addEventListener('dragleave', () => { zone.style.borderColor = ''; });
    zone.addEventListener('drop', e => {
        e.preventDefault(); zone.style.borderColor = '';
        const file = e.dataTransfer.files[0];
        if (!file) return;
        const dt = new DataTransfer(); dt.items.add(file); fi.files = dt.files;
        fi.dispatchEvent(new Event('change'));
    });
});
</script>
</body>
</html>
