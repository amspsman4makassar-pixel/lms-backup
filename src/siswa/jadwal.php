<?php
// src/siswa/jadwal.php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'siswa') {
    header("Location: ../../login.php");
    exit;
}

$siswa_id = $_SESSION['user_id'];

// Get user's class info
$stmt_user = $pdo->prepare("SELECT class_id FROM users WHERE id = ?");
$stmt_user->execute([$siswa_id]);
$user = $stmt_user->fetch();
$class_id = $user['class_id'] ?? null;

$class_name = "Belum Terdaftar di Kelas";
$schedules = [];

// Handle search query
$search_query = isset($_GET['q']) ? trim($_GET['q']) : '';

if ($class_id) {
    // Get class name
    $stmt_class = $pdo->prepare("SELECT name FROM classes WHERE id = ?");
    $stmt_class->execute([$class_id]);
    $class_info = $stmt_class->fetch();

    if ($class_info) {
        $class_name = $class_info['name'];

        // Fetch schedules for this exact class name, optionally filtered by search query
        $sql = "
            SELECT * FROM schedules 
            WHERE REPLACE(REPLACE(LOWER(kelas), ' ', ''), '-', '') = REPLACE(REPLACE(LOWER(?), ' ', ''), '-', '')
        ";
        $params = [$class_name];

        if (!empty($search_query)) {
            $sql .= " AND LOWER(mata_pelajaran) LIKE LOWER(?)";
            $params[] = '%' . $search_query . '%';
        }

        $sql .= "
            ORDER BY
            FIELD(hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'),
            CAST(jam_ke AS UNSIGNED) ASC, id ASC
        ";

        $stmt_sched = $pdo->prepare($sql);
        $stmt_sched->execute($params);
        $schedules = $stmt_sched->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta charset="UTF-8">
    <title>Jadwal Pelajaran - Siswa</title>
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
                <h1 class="page-title">Jadwal Pelajaran</h1>
                <p class="page-subtitle">Anda berada di Kelas <?= htmlspecialchars($class_name) ?></p>
            </div>
        </div>

        <div class="page-content">
            <div class="page-section">
                <?php if (!$class_id): ?>
                    <div class="empty-state">
                        <div class="empty-icon"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div>
                        <h4>Akun Anda Belum Terdaftar di Kelas</h4>
                        <p>Silakan hubungi administrator untuk memasukkan Anda ke kelas.</p>
                    </div>
                <?php else: ?>
                    <form method="GET" style="display:flex;gap:10px;align-items:center;margin-bottom:1.25rem;">
                        <input type="text" name="q" value="<?= htmlspecialchars($search_query) ?>" placeholder="Cari berdasarkan mata pelajaran..." style="flex:1;padding:9px 14px;border:1.5px solid #e2e8f0;border-radius:8px;font-family:inherit;font-size:0.9rem;">
                        <button type="submit" class="btn btn-primary btn-sm">Cari Jadwal</button>
                        <?php if (!empty($search_query)): ?>
                            <a href="jadwal.php" class="btn btn-ghost btn-sm">Reset</a>
                        <?php endif; ?>
                    </form>

                    <?php if (empty($schedules)): ?>
                        <div class="empty-state">
                            <div class="empty-icon"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg></div>
                            <h4>Jadwal Tidak Ditemukan</h4>
                            <?php if (!empty($search_query)): ?>
                                <p>Belum ada jadwal yang ditemukan untuk mata pelajaran <strong>"<?= htmlspecialchars($search_query) ?>"</strong> di kelas Anda.</p>
                            <?php else: ?>
                                <p>Belum ada jadwal yang diunggah untuk <strong>Kelas <?= htmlspecialchars($class_name) ?></strong>.</p>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <?php
                        $schedulesByDay = [];
                        foreach ($schedules as $s) {
                            $schedulesByDay[$s['hari']][] = $s;
                        }
                        ?>

                        <?php foreach ($schedulesByDay as $hari => $daySchedules): ?>
                            <h3 class="section-title" style="margin-top: 1.5rem;">Hari <?= htmlspecialchars($hari) ?></h3>
                            <div class="page-table-wrap" style="border:none;border-radius:0;margin-bottom:2rem;">
                                <table class="page-table">
                                    <thead>
                                        <tr>
                                            <th style="width:10%;">Jam Ke</th>
                                            <th style="width:15%;">Waktu</th>
                                            <th style="width:15%;">Kelas</th>
                                            <th style="width:30%;">Mata Pelajaran</th>
                                            <th style="width:30%;">Nama Guru</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($daySchedules as $s): ?>
                                        <tr>
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
                        <?php endforeach; ?>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div><!-- /.page-content -->
    </main>
</div>

</body>
</html>


