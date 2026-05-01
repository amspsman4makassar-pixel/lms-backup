<?php
// src/admin/dashboard.php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../login.php");
    exit;
}

$stats = [];
$stats['users']       = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$stats['guru']        = $pdo->query("SELECT COUNT(*) FROM users WHERE role='guru'")->fetchColumn();
$stats['siswa']       = $pdo->query("SELECT COUNT(*) FROM users WHERE role='siswa'")->fetchColumn();
$stats['news']        = $pdo->query("SELECT COUNT(*) FROM news")->fetchColumn();
$stats['materials']   = $pdo->query("SELECT COUNT(*) FROM materials")->fetchColumn();
$stats['assignments'] = $pdo->query("SELECT COUNT(*) FROM assignments")->fetchColumn();

$recent_stmt  = $pdo->query("SELECT full_name, role, created_at FROM users ORDER BY created_at DESC LIMIT 5");
$recent_users = $recent_stmt->fetchAll();

$days   = ['Sunday'=>'Minggu','Monday'=>'Senin','Tuesday'=>'Selasa','Wednesday'=>'Rabu','Thursday'=>'Kamis','Friday'=>'Jumat','Saturday'=>'Sabtu'];
$months = ['January'=>'Januari','February'=>'Februari','March'=>'Maret','April'=>'April','May'=>'Mei','June'=>'Juni','July'=>'Juli','August'=>'Agustus','September'=>'September','October'=>'Oktober','November'=>'November','December'=>'Desember'];
$dateStr = ($days[date('l')] ?? date('l')) . ', ' . date('d') . ' ' . ($months[date('F')] ?? date('F')) . ' ' . date('Y');

$hour = (int)date('H');
if ($hour < 11)      $greeting = "Selamat Pagi";
elseif ($hour < 15)  $greeting = "Selamat Siang";
elseif ($hour < 18)  $greeting = "Selamat Sore";
else                 $greeting = "Selamat Malam";
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin — SIAKAD</title>
    <link rel="stylesheet" href="/public/assets/css/style.css">
    <style>
        /* Dashboard-specific layout */
        .main-content { padding: 0; }

        /* Stats bar */
        .db-stats {
            display: flex;
            background: var(--bg-surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            margin-bottom: 20px;
            overflow: hidden;
        }
        .db-stat {
            flex: 1;
            min-width: 0;
            padding: 20px 24px;
            border-right: 1px solid var(--border);
            position: relative;
            transition: background 0.15s;
        }
        .db-stat:last-child { border-right: none; }
        .db-stat:hover { background: var(--bg-muted); }
        .db-stat::after {
            content: '';
            position: absolute;
            bottom: 0; left: 0;
            width: 100%; height: 2px;
        }
        .db-stat.c1::after { background: var(--primary); }
        .db-stat.c2::after { background: #7C3AED; }
        .db-stat.c3::after { background: var(--success); }
        .db-stat.c4::after { background: var(--warning); }
        .db-num {
            font-size: 1.875rem;
            font-weight: 700;
            line-height: 1;
            margin-bottom: 4px;
            letter-spacing: -0.02em;
        }
        .db-stat.c1 .db-num { color: var(--primary); }
        .db-stat.c2 .db-num { color: #7C3AED; }
        .db-stat.c3 .db-num { color: var(--success); }
        .db-stat.c4 .db-num { color: var(--warning); }
        .db-lbl {
            font-size: 0.6875rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--text-muted);
        }

        /* Two-panel grid */
        .db-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .db-panel {
            background: var(--bg-surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 20px;
            overflow-x: auto;
        }
        .db-panel-title {
            font-size: 0.6875rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            color: var(--text-muted);
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border);
        }

        /* Quick Actions */
        .qa-list { display: flex; flex-direction: column; gap: 6px; }
        .qa-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 14px;
            border-radius: var(--radius-md);
            background: var(--bg-muted);
            border: 1px solid var(--border);
            text-decoration: none;
            color: inherit;
            transition: background 0.15s, border-color 0.15s;
        }
        .qa-item:hover { background: var(--primary-light); border-color: var(--primary-border); }
        .qa-ico {
            width: 36px; height: 36px;
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            background: var(--bg-surface);
            border: 1px solid var(--border);
            font-size: 1rem;
        }
        .qa-title { font-size: 0.8125rem; font-weight: 600; color: var(--text-primary); }
        .qa-desc  { font-size: 0.75rem; color: var(--text-muted); margin-top: 1px; }
        .qa-arrow { margin-left: auto; color: var(--text-muted); font-size: 1rem; }
        .qa-item:hover .qa-arrow { color: var(--primary); }

        @media (max-width: 900px) {
            .db-stats { flex-wrap: wrap; }
            .db-stat  { min-width: calc(50% - 0.5px); border-bottom: 1px solid var(--border); }
            .db-grid  { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<div class="app-container">
    <?php include '../templates/sidebar.php'; ?>

    <main class="main-content">

        <!-- Page Toolbar (replaces gradient hero) -->
        <div class="page-toolbar">
            <div class="page-toolbar-left">
                <h1 class="page-title"><?php echo $greeting; ?>, Administrator</h1>
                <p class="page-subtitle">Overview sistem dan pengelolaan data sekolah</p>
            </div>
            <div class="page-toolbar-right">
                <span style="font-size:0.8125rem; color:var(--text-muted);"><?php echo $dateStr; ?></span>
                <a href="manage_users.php" class="btn btn-sm">+ Tambah Pengguna</a>
            </div>
        </div>

        <!-- Content -->
        <div style="padding: 24px 32px;">

            <!-- Stats Bar -->
            <div class="db-stats">
                <div class="db-stat c1">
                    <div class="db-num"><?php echo $stats['users']; ?></div>
                    <div class="db-lbl">Total Pengguna</div>
                </div>
                <div class="db-stat c2">
                    <div class="db-num"><?php echo $stats['guru']; ?></div>
                    <div class="db-lbl">Jumlah Guru</div>
                </div>
                <div class="db-stat c3">
                    <div class="db-num"><?php echo $stats['siswa']; ?></div>
                    <div class="db-lbl">Jumlah Siswa</div>
                </div>
                <div class="db-stat c4">
                    <div class="db-num"><?php echo $stats['news']; ?></div>
                    <div class="db-lbl">Berita Terbit</div>
                </div>
            </div>

            <!-- Two-panel grid -->
            <div class="db-grid">

                <!-- Recent Users -->
                <div class="db-panel">
                    <div class="db-panel-title">Pengguna Terbaru</div>
                    <div class="page-table-wrap" style="border:none; border-radius:0;">
                        <table class="page-table">
                            <thead>
                                <tr>
                                    <th>Nama</th>
                                    <th>Role</th>
                                    <th>Bergabung</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_users as $u): ?>
                                <tr>
                                    <td style="font-weight:600;"><?php echo htmlspecialchars($u['full_name']); ?></td>
                                    <td><span class="role-badge role-<?php echo $u['role']; ?>"><?php echo $u['role']; ?></span></td>
                                    <td style="color:var(--text-muted); font-size:0.75rem;"><?php echo date('d M Y', strtotime($u['created_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="db-panel">
                    <div class="db-panel-title">Menu Cepat</div>
                    <div class="qa-list">
                        <a href="manage_users.php" class="qa-item">
                            <div class="qa-ico">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                            </div>
                            <div>
                                <div class="qa-title">Kelola Pengguna</div>
                                <div class="qa-desc">Tambah, edit, atau hapus akun</div>
                            </div>
                            <span class="qa-arrow">&rarr;</span>
                        </a>
                        <a href="manage_news.php" class="qa-item">
                            <div class="qa-ico">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 22h16a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v16a2 2 0 0 1-2 2Zm0 0a2 2 0 0 1-2-2v-9c0-1.1.9-2 2-2h2"/><path d="M18 14h-8"/><path d="M15 18h-5"/></svg>
                            </div>
                            <div>
                                <div class="qa-title">Kelola Berita</div>
                                <div class="qa-desc">Tulis dan atur berita sekolah</div>
                            </div>
                            <span class="qa-arrow">&rarr;</span>
                        </a>
                        <a href="manage_classes.php" class="qa-item">
                            <div class="qa-ico">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 22h16a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v16a2 2 0 0 1-2 2Zm0 0a2 2 0 0 1-2-2v-9c0-1.1.9-2 2-2h2"/><path d="M18 14h-8"/><path d="M15 18h-5"/></svg>
                            </div>
                            <div>
                                <div class="qa-title">Kelola Kelas</div>
                                <div class="qa-desc">Buat dan atur kelas untuk guru</div>
                            </div>
                            <span class="qa-arrow">&rarr;</span>
                        </a>
                        <a href="manage_schedules.php" class="qa-item">
                            <div class="qa-ico">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            </div>
                            <div>
                                <div class="qa-title">Kelola Jadwal</div>
                                <div class="qa-desc">Upload jadwal pelajaran via Excel</div>
                            </div>
                            <span class="qa-arrow">&rarr;</span>
                        </a>
                        <a href="../profile.php" class="qa-item">
                            <div class="qa-ico">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                            </div>
                            <div>
                                <div class="qa-title">Pengaturan Profil</div>
                                <div class="qa-desc">Ubah data diri dan password</div>
                            </div>
                            <span class="qa-arrow">&rarr;</span>
                        </a>
                    </div>
                </div>

            </div><!-- /db-grid -->
        </div><!-- /content -->

    </main>
</div>

</body>
</html>
