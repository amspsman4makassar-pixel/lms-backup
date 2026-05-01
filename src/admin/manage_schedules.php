<?php
// src/admin/manage_schedules.php
session_start();
require_once '../../config/database.php';
require_once '../../src/lib/SimpleXLSX.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../login.php");
    exit;
}

// â”€â”€ Handle Excel Upload â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
if (isset($_POST['upload_excel'])) {
    $file = $_FILES['schedule_file'];
    if ($file['error'] === UPLOAD_ERR_OK) {
        if ($xlsx = Shuchkin\SimpleXLSX::parse($file['tmp_name'])) {
            $rows = $xlsx->rows();

        $header = array_shift($rows);
        $header = array_map(fn($h) => strtoupper(trim($h ?? '')), $header);

        $colMap = [];
        foreach ($header as $idx => $col) {
            if (str_contains($col, 'HARI'))           $colMap['hari']           = $idx;
            if (str_contains($col, 'JAM'))            $colMap['jam_ke']         = $idx;
            if (str_contains($col, 'WAKTU'))          $colMap['waktu']          = $idx;
            if (str_contains($col, 'KELAS'))          $colMap['kelas']          = $idx;
            if (str_contains($col, 'MATA PELAJARAN')) $colMap['mata_pelajaran'] = $idx;
            if (str_contains($col, 'NAMA GURU'))      $colMap['nama_guru']      = $idx;
        }

        $required = ['hari','jam_ke','waktu','kelas','mata_pelajaran','nama_guru'];
        $missing  = array_diff($required, array_keys($colMap));

        if (!empty($missing)) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Kolom tidak ditemukan: ' . implode(', ', $missing)];
        } else {
            if (isset($_POST['replace_data'])) {
                $pdo->exec("DELETE FROM schedules");
            }
            $stmt = $pdo->prepare("INSERT INTO schedules (hari, jam_ke, waktu, kelas, mata_pelajaran, nama_guru) VALUES (?,?,?,?,?,?)");
            $imported = 0;
            foreach ($rows as $row) {
                $hari = trim($row[$colMap['hari']] ?? '');
                if (empty($hari)) continue;
                $stmt->execute([
                    $hari,
                    trim($row[$colMap['jam_ke']]         ?? ''),
                    trim($row[$colMap['waktu']]          ?? ''),
                    trim($row[$colMap['kelas']]          ?? ''),
                    trim($row[$colMap['mata_pelajaran']] ?? ''),
                    trim($row[$colMap['nama_guru']]      ?? ''),
                ]);
                $imported++;
            }
            $_SESSION['flash'] = ['type' => 'success', 'message' => "$imported jadwal berhasil diimport."];
        }
        } else {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Gagal membaca file Excel.'];
        }
    } else {
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Gagal mengupload file.'];
    }
    header("Location: manage_schedules.php");
    exit;
}

// â”€â”€ Handle Delete All â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
if (isset($_POST['delete_all'])) {
    $pdo->exec("DELETE FROM schedules");
    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Semua data jadwal telah dihapus.'];
    header("Location: manage_schedules.php");
    exit;
}

// â”€â”€ Fetch with search + pagination â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$search    = trim($_GET['search'] ?? '');
$page      = max(1, (int)($_GET['page'] ?? 1));
$per_page  = 25;
$params    = [];

$where = '';
if ($search) {
    $where = "WHERE nama_guru LIKE ? OR kelas LIKE ? OR mata_pelajaran LIKE ? OR hari LIKE ?";
    $like  = "%$search%";
    $params = [$like, $like, $like, $like];
}

$total_schedules_filtered = $pdo->prepare("SELECT COUNT(*) FROM schedules $where");
$total_schedules_filtered->execute($params);
$total_schedules_filtered = (int)$total_schedules_filtered->fetchColumn();
$total_pages = max(1, (int)ceil($total_schedules_filtered / $per_page));
$page        = min($page, $total_pages);
$offset      = ($page - 1) * $per_page;

$stmt = $pdo->prepare("SELECT * FROM schedules $where
    ORDER BY FIELD(hari,'Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu'), CAST(jam_ke AS UNSIGNED) ASC
    LIMIT $per_page OFFSET $offset");
$stmt->execute($params);
$schedules = $stmt->fetchAll();

$total_schedules = $pdo->query("SELECT COUNT(*) FROM schedules")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta charset="UTF-8">
    <title>Kelola Jadwal - Admin</title>
    <link rel="stylesheet" href="/public/assets/css/style.css">
    </head>
<body>

<div class="app-container">
    <?php include '../templates/sidebar.php'; ?>
    
    <main class="main-content">
        <div class="page-toolbar">
            <div class="page-toolbar-left">
                <h1 class="page-title">Kelola Jadwal</h1>
                <p class="page-subtitle">Upload dan kelola jadwal pelajaran via file Excel</p>
            </div>
        </div>
            <div class="hero-deco hero-deco-tr"></div>
        <div class="page-content">
            <div class="two-col-layout">

                <!-- â”€â”€ Upload Panel â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
                <div class="page-section">
                    <div class="section-title">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d='M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4'/><polyline points='17 8 12 3 7 8'/><line x1='12' y1='3' x2='12' y2='15'/></svg>
                        Upload Excel
                    </div>

                    <?php if (isset($_SESSION['flash'])): $flash = $_SESSION['flash']; unset($_SESSION['flash']); ?>
                        <div class="<?= $flash['type'] === 'error' ? 'flash-error' : 'flash-success' ?>">
                            <?= $flash['type'] === 'error'
                                ? '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>'
                                : '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>'; ?>
                            <?= htmlspecialchars($flash['message']) ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" enctype="multipart/form-data">
                        <div class="form-group">
                            <label>File Excel (.xlsx)</label>
                            <input type="file" name="schedule_file" accept=".xlsx" required style="width:100%;padding:8px;border:1.5px solid #e2e8f0;border-radius:8px;font-family:inherit;background:#fff;">
                            <span class="form-hint">Kolom yang dibutuhkan: HARI, JAM KE, WAKTU, KELAS, MATA PELAJARAN, NAMA GURU.</span>
                        </div>
                        <div class="form-group">
                            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:500;">
                                <input type="checkbox" name="replace_data" value="1" checked>
                                Hapus data lama sebelum import
                            </label>
                        </div>
                        <button type="submit" name="upload_excel" class="btn btn-primary" style="width:100%;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d='M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4'/><polyline points='17 8 12 3 7 8'/><line x1='12' y1='3' x2='12' y2='15'/></svg>
                            Import Jadwal
                        </button>
                    </form>

                    <div class="danger-zone">
                        <span class="danger-zone-label">Hapus semua data jadwal secara permanen</span>
                        <form method="POST" onsubmit="return confirm('Yakin ingin menghapus SEMUA data jadwal?');" style="margin:0;">
                            <button type="submit" name="delete_all" class="btn btn-danger btn-sm">Kosongkan</button>
                        </form>
                    </div>
                </div>

                <!-- â”€â”€ Preview Panel â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
                <div class="page-section">
                    <div class="panel-header">
                        <h3 class="panel-title">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d='M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2'/><rect x='8' y='2' width='8' height='4' rx='1' ry='1'/></svg>
                            Data Jadwal
                            <span style="background:#e0f2fe;color:#0369a1;padding:2px 10px;border-radius:20px;font-size:0.75rem;font-weight:700;"><?= $total_schedules ?></span>
                        </h3>
                        <form method="GET" style="display:flex;gap:8px;align-items:center;">
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Cari guru, kelas, mapel..." style="padding:7px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-family:inherit;min-width:200px;font-size:0.88rem;">
                            <button type="submit" class="btn btn-primary btn-sm">Cari</button>
                            <?php if ($search): ?><a href="manage_schedules.php" class="btn btn-ghost btn-sm">Reset</a><?php endif; ?>
                        </form>
                    </div>

                    <?php if (empty($schedules)): ?>
                        <div class="empty-state">
                            <div class="empty-icon">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            </div>
                            <h4><?= $search ? 'Tidak ada hasil pencarian' : 'Belum ada data jadwal' ?></h4>
                            <p><?= $search ? 'Coba kata kunci lain.' : 'Upload file Excel untuk mengisi tabel jadwal.' ?></p>
                        </div>
                    <?php else: ?>
                        <div class="page-table-wrap" style="border:none;border-radius:0;margin-bottom:0;">
                            <table class="page-table">
                                <thead>
                                    <tr>
                                        <th>Hari</th>
                                        <th>Jam</th>
                                        <th>Waktu</th>
                                        <th>Kelas</th>
                                        <th>Mata Pelajaran</th>
                                        <th>Guru</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($schedules as $s): ?>
                                    <tr>
                                        <td><span class="badge-day"><?= htmlspecialchars($s['hari']) ?></span></td>
                                        <td><span class="badge-jam">Ke-<?= htmlspecialchars($s['jam_ke']) ?></span></td>
                                        <td style="color:#64748b;font-size:0.85rem;"><?= htmlspecialchars($s['waktu']) ?></td>
                                        <td><span class="badge-class"><?= htmlspecialchars($s['kelas']) ?></span></td>
                                        <td style="font-weight:600;color:#0f172a;"><?= htmlspecialchars($s['mata_pelajaran']) ?></td>
                                        <td style="color:#64748b;"><?= htmlspecialchars($s['nama_guru']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="table-footer">
                            <span>Menampilkan <?= count($schedules) ?> dari <?= $total_schedules_filtered ?> hasil<?= $search ? " untuk \"".htmlspecialchars($search)."\"" : '' ?></span>
                            <?php if ($total_pages > 1): ?>
                                <div style="display:flex;gap:5px;align-items:center;">
                                    <?php if ($page > 1): ?><a href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>" style="padding:4px 10px;border:1px solid #e2e8f0;border-radius:6px;text-decoration:none;color:#475569;font-weight:600;font-size:0.82rem;">&laquo;</a><?php endif; ?>
                                    <span style="padding:4px 10px;border-radius:6px;background:#4f46e5;color:#fff;font-weight:700;font-size:0.82rem;"><?= $page ?>/<?= $total_pages ?></span>
                                    <?php if ($page < $total_pages): ?><a href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>" style="padding:4px 10px;border:1px solid #e2e8f0;border-radius:6px;text-decoration:none;color:#475569;font-weight:600;font-size:0.82rem;">&raquo;</a><?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </main>
</div>

</body>
</html>

