<?php
// src/pimpinan/jadwal_sekolah.php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['kepsek', 'wakasek'])) {
    header("Location: ../../login.php");
    exit;
}

$search_query = $_GET['search'] ?? '';
$filter_hari = $_GET['hari'] ?? '';
$filter_kelas = $_GET['kelas'] ?? '';

// Update classes dynamically from database or schedules
try {
    $classes_query = $pdo->query("SELECT DISTINCT kelas FROM schedules ORDER BY kelas ASC");
    $class_options = $classes_query->fetchAll(PDO::FETCH_COLUMN);
}
catch (PDOException $e) {
    $class_options = [];
}

// Filter logic
$sql = "SELECT * FROM schedules WHERE 1=1 ";
$params = [];

if ($search_query) {
    $sql .= " AND (nama_guru LIKE ? OR mata_pelajaran LIKE ?) ";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
}

if ($filter_hari) {
    $sql .= " AND hari = ? ";
    $params[] = $filter_hari;
}

if ($filter_kelas) {
    $sql .= " AND kelas = ? ";
    $params[] = $filter_kelas;
}

// Order logically by Hari, Kelas, Jam
$sql .= " ORDER BY 
    FIELD(hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'),
    kelas ASC,
    CAST(jam_ke AS UNSIGNED) ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$schedules = $stmt->fetchAll();

$days = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta charset="UTF-8">
    <title>Jadwal Sekolah - Pimpinan</title>
    <link rel="stylesheet" href="/public/assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="admin-full-layout">

<div class="app-container">
    <?php include '../templates/sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Page Toolbar -->
        <div class="page-toolbar">
            <div class="page-toolbar-left">
                <h1 class="page-title">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 6px;"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    Jadwal Sekolah
                </h1>
                <p class="page-subtitle">Pantau seluruh kegiatan belajar mengajar berdasarkan hari dan kelas.</p>
            </div>
        </div>

        <div class="page-content">
            <div class="page-section">
                <div class="panel-header">
                    <h3 class="panel-title">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        Jadwal Belajar Mengajar
                    </h3>
                    <span style="background:#dbeafe;color:#1e40af;padding:3px 12px;border-radius:20px;font-size:0.75rem;font-weight:700;"><?= count($schedules) ?> sesi</span>
                </div>

                <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:1.25rem;">
                    <input type="text" name="search" placeholder="Cari nama guru, mapel..." value="<?= htmlspecialchars($search_query) ?>" style="flex:2;min-width:180px;padding:9px 14px;border:1.5px solid #e2e8f0;border-radius:8px;font-family:inherit;font-size:0.9rem;">
                    <select name="hari" style="flex:1;min-width:140px;padding:9px 14px;border:1.5px solid #e2e8f0;border-radius:8px;font-family:inherit;background:white;font-size:0.9rem;">
                        <option value="">Semua Hari</option>
                        <?php foreach ($days as $d): ?>
                            <option value="<?= $d ?>" <?= $filter_hari == $d ? 'selected' : '' ?>><?= $d ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="kelas" style="flex:1;min-width:140px;padding:9px 14px;border:1.5px solid #e2e8f0;border-radius:8px;font-family:inherit;background:white;font-size:0.9rem;">
                        <option value="">Semua Kelas</option>
                        <?php foreach ($class_options as $c): ?>
                            <option value="<?= $c ?>" <?= $filter_kelas == $c ? 'selected' : '' ?>>Kelas <?= htmlspecialchars($c) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                    <?php if ($search_query || $filter_hari || $filter_kelas): ?>
                        <a href="jadwal_sekolah.php" class="btn btn-ghost btn-sm">Reset</a>
                    <?php endif; ?>
                </form>

                <?php if (empty($schedules)): ?>
                    <div class="empty-state">
                        <div class="empty-icon"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>
                        <h4>Jadwal Tidak Ditemukan</h4>
                        <p>Silakan sesuaikan filter pencarian atau data belum diupload oleh admin.</p>
                    </div>
                <?php else: ?>
                    <div class="page-table-wrap" style="border:none;border-radius:0;margin-bottom:0;">
                        <table class="page-table">
                            <thead>
                                <tr>
                                    <th>Hari</th><th>Jam Ke</th><th>Waktu</th><th>Kelas</th><th>Mata Pelajaran</th><th>Guru Pengajar</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($schedules as $s): ?>
                                <tr>
                                    <td><span class="badge-day"><?= htmlspecialchars($s['hari']) ?></span></td>
                                    <td><span class="badge-jam">Jam <?= htmlspecialchars($s['jam_ke']) ?></span></td>
                                    <td style="color:#64748b;font-size:0.88rem;font-weight:500;"><?= htmlspecialchars($s['waktu']) ?></td>
                                    <td><span class="badge-class">Kelas <?= htmlspecialchars($s['kelas']) ?></span></td>
                                    <td style="font-weight:600;color:#0f172a;"><?= htmlspecialchars($s['mata_pelajaran']) ?></td>
                                    <td style="color:#64748b;font-size:0.85rem;"><?= htmlspecialchars($s['nama_guru']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div style="margin-top:0.75rem;font-size:0.83rem;color:#94a3b8;text-align:right;">Total: <?= count($schedules) ?> sesi (terfilter)</div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

</body>
</html>


