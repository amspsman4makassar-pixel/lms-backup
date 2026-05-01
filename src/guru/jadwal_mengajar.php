<?php
session_start();
require_once '../../config/database.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guru') {
    header("Location: ../../login.php"); exit;
}

$teacher_id = $_SESSION['user_id'];
$guru_name  = $_SESSION['full_name'];
$searchName = trim(explode(',', $guru_name)[0]);
$search_query = isset($_GET['q']) ? trim($_GET['q']) : $searchName;

$stmt = $pdo->prepare("SELECT * FROM schedules WHERE LOWER(nama_guru) LIKE LOWER(?)
    ORDER BY FIELD(hari,'Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu'),
    CAST(jam_ke AS UNSIGNED) ASC, id ASC");
$stmt->execute(['%' . $search_query . '%']);
$schedules = $stmt->fetchAll();

// Group by day
$by_day = [];
foreach ($schedules as $s) { $by_day[$s['hari']][] = $s; }
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Jadwal Mengajar — Guru</title>
<link rel="stylesheet" href="/public/assets/css/style.css">
</head>
<body>
<div class="app-container">
<?php include '../templates/sidebar.php'; ?>
<main class="main-content">

    <div class="page-toolbar">
        <div class="page-toolbar-left">
            <h1 class="page-title">Jadwal Mengajar</h1>
            <p class="page-subtitle">Jadwal mengajar Anda per hari dan mata pelajaran</p>
        </div>
    </div>

    <div class="page-content">

        <!-- Search -->
        <form method="GET" class="filter-bar" style="margin-bottom:20px;">
            <input type="text" name="q" class="filter-input"
                   placeholder="Cari nama guru lain..."
                   value="<?php echo htmlspecialchars($search_query); ?>">
            <button type="submit" class="btn btn-sm">Cari</button>
            <?php if ($search_query !== $searchName): ?>
                <a href="jadwal_mengajar.php" class="btn btn-secondary btn-sm">Reset</a>
            <?php endif; ?>
        </form>

        <?php if (empty($schedules)): ?>
        <div class="empty-state">
            <div class="empty-icon">📅</div>
            <h4>Jadwal tidak ditemukan</h4>
            <p>Coba ubah kata kunci pencarian, atau hubungi admin untuk mengupload jadwal.</p>
        </div>
        <?php else: ?>

        <!-- Per-day panels -->
        <?php foreach ($by_day as $hari => $rows): ?>
        <div class="page-section" style="margin-bottom:16px;">
            <div class="panel-header">
                <h3 class="panel-title"><?php echo htmlspecialchars($hari); ?></h3>
                <span style="font-size:0.75rem;color:var(--text-muted);"><?php echo count($rows); ?> jam pelajaran</span>
            </div>
            <div class="page-table-wrap" style="border:none;border-radius:0;">
                <table class="page-table">
                    <thead>
                        <tr>
                            <th style="width:80px;">Jam Ke</th>
                            <th>Mata Pelajaran</th>
                            <th>Kelas</th>
                            <th>Ruang</th>
                            <th>Waktu</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): ?>
                        <tr>
                            <td class="num-col" data-label="Jam Ke"><?php echo htmlspecialchars($r['jam_ke']); ?></td>
                            <td style="font-weight:600;" data-label="Mata Pelajaran"><?php echo htmlspecialchars($r['mata_pelajaran'] ?? '-'); ?></td>
                            <td data-label="Kelas"><span class="badge-class"><?php echo htmlspecialchars($r['kelas'] ?? '-'); ?></span></td>
                            <td style="color:var(--text-muted);" data-label="Ruang"><?php echo htmlspecialchars($r['ruang'] ?? '-'); ?></td>
                            <td style="color:var(--text-muted);font-size:0.75rem;" data-label="Waktu">
                                <?php echo htmlspecialchars($r['waktu'] ?? '-'); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endforeach; ?>

        <div class="table-footer">
            <span>Total <?php echo count($schedules); ?> jam pelajaran ditemukan untuk "<?php echo htmlspecialchars($search_query); ?>"</span>
        </div>

        <?php endif; ?>
    </div>
</main>
</div>
</body>
</html>
