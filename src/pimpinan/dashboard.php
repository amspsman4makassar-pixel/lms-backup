<?php
// src/pimpinan/dashboard.php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['kepsek', 'wakasek'])) {
    header("Location: ../../login.php");
    exit;
}

$role_display = $_SESSION['role'] === 'kepsek' ? 'Kepala Sekolah' : 'Wakil Kepala Sekolah';

$stats = [];
$stats['guru'] = $pdo->query("SELECT COUNT(*) FROM users WHERE role='guru'")->fetchColumn();
$stats['siswa'] = $pdo->query("SELECT COUNT(*) FROM users WHERE role='siswa'")->fetchColumn();
// Try to get class count, if classes table doesn't exist yet it might fail, so we catch it
try {
    $stats['classes'] = $pdo->query("SELECT COUNT(*) FROM classes")->fetchColumn();
}
catch (PDOException $e) {
    $stats['classes'] = 0;
}
$stats['news'] = $pdo->query("SELECT COUNT(*) FROM news")->fetchColumn();

// Get recent news
$recent_stmt = $pdo->query("SELECT title, created_at FROM news ORDER BY created_at DESC LIMIT 5");
$recent_news = $recent_stmt->fetchAll();

// Get tracer stats
$tracer_stats = [];
try {
    $ts_stmt = $pdo->query("SELECT kegiatan, COUNT(*) as count FROM tracer_study GROUP BY kegiatan");
    while ($row = $ts_stmt->fetch()) {
        $tracer_stats[$row['kegiatan']] = $row['count'];
    }
} catch (PDOException $e) {}

// Indonesian Date
$days = ['Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'];
$months = ['January' => 'Januari', 'February' => 'Februari', 'March' => 'Maret', 'April' => 'April', 'May' => 'Mei', 'June' => 'Juni', 'July' => 'Juli', 'August' => 'Agustus', 'September' => 'September', 'October' => 'Oktober', 'November' => 'November', 'December' => 'Desember'];
$dayName = $days[date('l')] ?? date('l');
$monthName = $months[date('F')] ?? date('F');
$dateStr = $dayName . ', ' . date('d') . ' ' . $monthName . ' ' . date('Y');

$hour = (int)date('H');
if ($hour < 11)
    $greeting = "Selamat Pagi";
elseif ($hour < 15)
    $greeting = "Selamat Siang";
elseif ($hour < 18)
    $greeting = "Selamat Sore";
else
    $greeting = "Selamat Malam";
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta charset="UTF-8">
    <title>Dashboard Pimpinan</title>
    <link rel="stylesheet" href="/public/assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .qa-list { display: flex; flex-direction: column; gap: 8px; }
        .qa-item {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 14px 16px;
            border-radius: 12px;
            background: #f8fafc;
            border: 1px solid #eef2f7;
            text-decoration: none;
            color: inherit;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .qa-item:hover {
            background: #e0f2fe;
            border-color: #bae6fd;
            transform: translateX(4px);
            box-shadow: 0 2px 12px rgba(14,165,233,0.06);
        }
        .qa-ico {
            width: 42px; height: 42px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
            flex-shrink: 0;
        }
        .qa-item:nth-child(1) .qa-ico { background: #dbeafe; }
        .qa-item:nth-child(2) .qa-ico { background: #fef3c7; }
        .qa-item:nth-child(3) .qa-ico { background: #ede9fe; }
        .qa-title { font-size: 0.88rem; font-weight: 600; color: #1e293b; }
        .qa-desc { font-size: 0.75rem; color: #94a3b8; margin-top: 2px; }
        .qa-arrow {
            margin-left: auto;
            color: #cbd5e1;
            font-size: 1.2rem;
            transition: all 0.2s;
        }
        .qa-item:hover .qa-arrow { color: #0ea5e9; transform: translateX(3px); }
    </style>
</head>
<body>

<div class="app-container">
    <?php include '../templates/sidebar.php'; ?>
    
    <main class="main-content">
        <div class="page-toolbar">
            <div class="page-toolbar-left">
                <h1 class="page-title"><?php echo $greeting; ?>, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</h1>
                <p class="page-subtitle">Dashboard Monitoring <?php echo $role_display; ?></p>
            </div>
            <div class="page-toolbar-right" style="text-align: right;">
                <p class="page-subtitle"><?php echo $dateStr; ?></p>
            </div>
        </div>

        <div class="page-content">
            <div class="db-stats">
                <div class="db-stat c-blue">
                    <div class="num"><?php echo $stats['guru']; ?></div>
                    <div class="lbl">Total Guru</div>
                </div>
                <div class="db-stat c-violet">
                    <div class="num"><?php echo $stats['siswa']; ?></div>
                    <div class="lbl">Total Siswa</div>
                </div>
                <div class="db-stat c-amber">
                    <div class="num"><?php echo $stats['classes']; ?></div>
                    <div class="lbl">Total Kelas</div>
                </div>
                <div class="db-stat c-green">
                    <div class="num"><?php echo $stats['news']; ?></div>
                    <div class="lbl">Berita Sekolah</div>
                </div>
            </div>

            <div class="db-grid">

                <!-- Quick Actions -->
                <div class="db-panel">
                    <h3>Akses Cepat Monitoring</h3>
                    <div class="qa-list">
                        <a href="guru_list.php" class="qa-item">
                            <div class="qa-ico"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block; vertical-align:middle; line-height:1;"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><path d="M12 14h.01"/><path d="M16 14h.01"/><path d="M8 14h.01"/></svg></div>
                            <div>
                                <div class="qa-title">Data Guru</div>
                                <div class="qa-desc">Pantau daftar dan jadwal guru</div>
                            </div>
                            <span class="qa-arrow">&rarr;</span>
                        </a>
                        <a href="siswa_list.php" class="qa-item">
                            <div class="qa-ico"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block; vertical-align:middle; line-height:1;"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg></div>
                            <div>
                                <div class="qa-title">Data Siswa</div>
                                <div class="qa-desc">Pantau daftar siswa per kelas</div>
                            </div>
                            <span class="qa-arrow">&rarr;</span>
                        </a>
                        <a href="jadwal_sekolah.php" class="qa-item">
                            <div class="qa-ico"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block; vertical-align:middle; line-height:1;"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>
                            <div>
                                <div class="qa-title">Jadwal Sekolah</div>
                                <div class="qa-desc">Lihat jadwal kegiatan belajar mengajar</div>
                            </div>
                            <span class="qa-arrow">&rarr;</span>
                        </a>
                    </div>
                </div>

                <!-- Recent News -->
                <div class="db-panel">
                    <h3>Berita & Informasi Terbaru</h3>
                    <div class="page-table-wrap">
                        <table class="page-table">
                            <thead>
                                <tr>
                                    <th>Judul Informasi</th>
                                    <th style="text-align: right;">Tanggal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recent_news)): ?>
                                <tr>
                                    <td colspan="2" style="text-align:center; color:#94a3b8;">Belum ada informasi.</td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($recent_news as $n): ?>
                                    <tr>
                                        <td class="name" style="font-weight: 600;"><?php echo htmlspecialchars($n['title']); ?></td>
                                        <td class="date-muted" style="text-align: right; color: #94a3b8;"><?php echo date('d M Y', strtotime($n['created_at'])); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Tracer Study Chart -->
                <div class="db-panel" style="grid-column: 1 / -1;">
                    <h3>Statistik Penelusuran Alumni (Tracer Study)</h3>
                    <div style="height: 280px; display: flex; justify-content: center;">
                        <canvas id="tracerChart"></canvas>
                    </div>
                </div>

            </div>
        </div>
    </main>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const ctx = document.getElementById('tracerChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: [
                    'Kuliah (<?php echo $tracer_stats['Kuliah'] ?? 0; ?>)', 
                    'Kerja (<?php echo $tracer_stats['Kerja'] ?? 0; ?>)', 
                    'Wirausaha (<?php echo $tracer_stats['Wirausaha'] ?? 0; ?>)', 
                    'Belum/Tidak Bekerja (<?php echo $tracer_stats["Belum/Tidak Bekerja"] ?? 0; ?>)'
                ],
                datasets: [{
                    data: [
                        <?php echo $tracer_stats['Kuliah'] ?? 0; ?>,
                        <?php echo $tracer_stats['Kerja'] ?? 0; ?>,
                        <?php echo $tracer_stats['Wirausaha'] ?? 0; ?>,
                        <?php echo $tracer_stats["Belum/Tidak Bekerja"] ?? 0; ?>
                    ],
                    backgroundColor: ['#3b82f6', '#10b981', '#f59e0b', '#ef4444'],
                    borderWidth: 0,
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'right', labels: { font: { family: "'Plus Jakarta Sans', sans-serif" } } }
                },
                cutout: '70%'
            }
        });
    }
});
</script>

</body>
</html>

