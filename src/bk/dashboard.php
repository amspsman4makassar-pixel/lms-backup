<?php
// src/bk/dashboard.php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'bk') {
    header("Location: ../../login.php");
    exit;
}

// Stats
$total_tickets    = $pdo->query("SELECT COUNT(*) FROM pengaduan")->fetchColumn();
$pending_tickets  = $pdo->query("SELECT COUNT(*) FROM pengaduan WHERE status='Pending'")->fetchColumn();
$resolved_tickets = $pdo->query("SELECT COUNT(*) FROM pengaduan WHERE status='Selesai'")->fetchColumn();
$bullying_tickets = $pdo->query("SELECT COUNT(*) FROM pengaduan WHERE kategori='Bullying' AND status!='Selesai'")->fetchColumn();

// Chart: Tiket per kategori
$kategori_stats = $pdo->query("SELECT kategori, COUNT(*) as total FROM pengaduan GROUP BY kategori ORDER BY total DESC")->fetchAll();

// Chart: Status distribusi
$status_stats = $pdo->query("SELECT status, COUNT(*) as total FROM pengaduan GROUP BY status")->fetchAll();

// Chart: Tren tiket 6 bulan terakhir
$tren_stats = $pdo->query("
    SELECT DATE_FORMAT(created_at,'%b %Y') as bulan, COUNT(*) as total
    FROM pengaduan
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY YEAR(created_at), MONTH(created_at), bulan
    ORDER BY YEAR(created_at) ASC, MONTH(created_at) ASC
")->fetchAll();

// Recent Tickets
$recent_tickets = $pdo->query("SELECT * FROM pengaduan ORDER BY created_at DESC LIMIT 5")->fetchAll();

// Indonesian Date
$days = ['Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'];
$months = ['January' => 'Januari', 'February' => 'Februari', 'March' => 'Maret', 'April' => 'April', 'May' => 'Mei', 'June' => 'Juni', 'July' => 'Juli', 'August' => 'Agustus', 'September' => 'September', 'October' => 'Oktober', 'November' => 'November', 'December' => 'Desember'];
$dayName = $days[date('l')] ?? date('l');
$monthName = $months[date('F')] ?? date('F');
$dateStr = $dayName . ', ' . date('d') . ' ' . $monthName . ' ' . date('Y');

$hour = (int)date('H');
if ($hour < 11) $greeting = "Selamat Pagi";
elseif ($hour < 15) $greeting = "Selamat Siang";
elseif ($hour < 18) $greeting = "Selamat Sore";
else $greeting = "Selamat Malam";
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta charset="UTF-8">
    <title>Dashboard Guru BK</title>
    <link rel="stylesheet" href="/public/assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .qa-list { display: flex; flex-direction: column; gap: 8px; }
        .qa-item { display: flex; align-items: center; gap: 16px; padding: 14px 16px; border-radius: 12px; background: #f8fafc; border: 1px solid #eef2f7; text-decoration: none; color: inherit; transition: all 0.25s; }
        .qa-item:hover { background: #e0e7ff; border-color: #c7d2fe; transform: translateX(4px); box-shadow: 0 2px 12px rgba(79,70,229,0.06); }
        .qa-ico { width: 42px; height: 42px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.15rem; flex-shrink: 0; background: #e0e7ff; color: #4f46e5; }
        .qa-title { font-size: 0.88rem; font-weight: 600; color: #1e293b; }
        .qa-desc { font-size: 0.75rem; color: #94a3b8; margin-top: 2px; }
        .qa-arrow { margin-left: auto; color: #cbd5e1; font-size: 1.2rem; transition: all 0.2s; }
        .qa-item:hover .qa-arrow { color: #4f46e5; transform: translateX(3px); }

        .ticket-row { display: flex; align-items: center; gap: 14px; padding: 12px 0; border-bottom: 1px solid #f1f5f9; text-decoration: none; color: inherit; transition: all 0.2s; }
        .ticket-row:last-child { border-bottom: none; }
        .ticket-row:hover { padding-left: 6px; }
        .ticket-info { flex: 1; min-width: 0; }
        .ticket-title { font-size: 0.88rem; font-weight: 600; color: #1e293b; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .ticket-meta { font-size: 0.75rem; color: #94a3b8; margin-top: 2px; }
        .badge-status { font-size: 0.7rem; font-weight: 700; padding: 4px 10px; border-radius: 6px; text-transform: uppercase; letter-spacing: 0.04em; white-space: nowrap; }
        .badge-pending { background: #fef3c7; color: #92400e; }
        .badge-proses { background: #dbeafe; color: #1e40af; }
        .badge-selesai { background: #d1fae5; color: #065f46; }


    </style>
</head>
<body>

<div class="app-container">
    <?php include '../templates/sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Page Toolbar -->
        <div class="page-toolbar">
            <div class="page-toolbar-left">
                <h1 class="page-title"><?php echo $greeting; ?>, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</h1>
                <p class="page-subtitle">Dashboard Monitoring Layanan Bimbingan Konseling (E-Counseling)</p>
            </div>
            <div class="page-toolbar-right" style="text-align: right;">
                <p class="page-subtitle"><?php echo $dateStr; ?></p>
            </div>
        </div>

        <div class="page-content">
            <div class="db-stats">
                <div class="db-stat c-violet">
                    <div class="num"><?php echo $total_tickets; ?></div>
                    <div class="lbl">Total Tiket</div>
                </div>
                <div class="db-stat c-amber">
                    <div class="num"><?php echo $pending_tickets; ?></div>
                    <div class="lbl">Tiket Pending</div>
                </div>
                <div class="db-stat c-green">
                    <div class="num"><?php echo $resolved_tickets; ?></div>
                    <div class="lbl">Tiket Selesai</div>
                </div>
                <div class="db-stat c-red">
                    <div class="num"><?php echo $bullying_tickets; ?></div>
                    <div class="lbl">Darurat (Bullying)</div>
                </div>
            </div>

            <div class="db-grid">
                <div class="page-section">
                    <h3>Akses Cepat</h3>
                    <div class="qa-list">
                        <a href="pengaduan.php" class="qa-item">
                            <div class="qa-ico"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div>
                            <div>
                                <div class="qa-title">Kelola Laporan Siswa</div>
                                <div class="qa-desc">Tanggapi dan perbarui status laporan E-Counseling</div>
                            </div>
                            <span class="qa-arrow">&rsaquo;</span>
                        </a>
                        <a href="../profile.php" class="qa-item">
                            <div class="qa-ico"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></div>
                            <div>
                                <div class="qa-title">Profil Saya</div>
                                <div class="qa-desc">Kelola pengaturan akun</div>
                            </div>
                            <span class="qa-arrow">&rsaquo;</span>
                        </a>
                    </div>
                </div>

                <div class="page-section">
                    <h3>Laporan Masuk Terbaru</h3>
                    <div class="qa-list">
                        <?php if (empty($recent_tickets)): ?>
                            <div style="text-align:center; padding: 2rem 0; color: #94a3b8;">Belum ada laporan masuk.</div>
                        <?php else: ?>
                            <?php foreach ($recent_tickets as $t): 
                                $badgeClass = 'badge-pending';
                                if ($t['status'] === 'Diproses') $badgeClass = 'badge-proses';
                                if ($t['status'] === 'Selesai') $badgeClass = 'badge-selesai';
                            ?>
                                <a href="pengaduan.php" class="ticket-row">
                                    <div class="ticket-info">
                                        <div class="ticket-title"><?php echo htmlspecialchars($t['nama_siswa'] ?: 'Siswa (Anonim)'); ?></div>
                                        <div class="ticket-meta"><?php echo htmlspecialchars($t['kategori']); ?> Â· <?php echo date('d M Y', strtotime($t['created_at'])); ?></div>
                                    </div>
                                    <span class="badge-status <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($t['status']); ?></span>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>


            <!-- ====== ANALYTICS CHARTS ====== -->
            <div style="margin-top:20px;">
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">

                    <!-- Kategori Bar Chart -->
                    <div class="page-section">
                        <h3>Distribusi Kategori Pengaduan</h3>
                        <canvas id="chartKategori" height="220"></canvas>
                    </div>

                    <!-- Status Donut Chart -->
                    <div class="page-section">
                        <h3>Status Tiket Overview</h3>
                        <canvas id="chartStatus" height="220"></canvas>
                    </div>

                </div>

                <!-- Tren Line Chart (full width) -->
                <div class="page-section" style="margin-top:20px;">
                    <h3>Tren Tiket Masuk (6 Bulan Terakhir)</h3>
                    <canvas id="chartTren" height="120"></canvas>
                </div>
            </div>

        </div><!-- end .db-content -->
    </main>
</div><!-- end .app-container -->

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const palette = ['#6366f1','#f59e0b','#10b981','#ef4444','#3b82f6','#a855f7','#ec4899'];

// â”€â”€ Kategori Bar Chart â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
new Chart(document.getElementById('chartKategori'), {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_column($kategori_stats, 'kategori')); ?>,
        datasets: [{
            label: 'Jumlah Tiket',
            data:  <?php echo json_encode(array_column($kategori_stats, 'total')); ?>,
            backgroundColor: palette,
            borderRadius: 8,
            borderSkipped: false,
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: '#f1f5f9' } },
            x: { grid: { display: false } }
        }
    }
});

// â”€â”€ Status Donut Chart â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
new Chart(document.getElementById('chartStatus'), {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode(array_column($status_stats, 'status')); ?>,
        datasets: [{
            data: <?php echo json_encode(array_column($status_stats, 'total')); ?>,
            backgroundColor: ['#fbbf24','#60a5fa','#34d399'],
            borderWidth: 3,
            borderColor: '#fff',
            hoverOffset: 10,
        }]
    },
    options: {
        responsive: true,
        cutout: '65%',
        plugins: {
            legend: { position: 'bottom', labels: { padding: 16, boxWidth: 12 } }
        }
    }
});

// â”€â”€ Tren Line Chart â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
new Chart(document.getElementById('chartTren'), {
    type: 'line',
    data: {
        labels: <?php echo json_encode(array_column($tren_stats, 'bulan')); ?>,
        datasets: [{
            label: 'Tiket Masuk',
            data:  <?php echo json_encode(array_column($tren_stats, 'total')); ?>,
            borderColor: '#6366f1',
            backgroundColor: 'rgba(99,102,241,0.1)',
            tension: 0.4,
            fill: true,
            pointBackgroundColor: '#6366f1',
            pointRadius: 5,
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: '#f1f5f9' } },
            x: { grid: { display: false } }
        }
    }
});
</script>
</body>
</html>

