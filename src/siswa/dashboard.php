<?php
// src/siswa/dashboard.php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'siswa') {
    header("Location: ../../login.php");
    exit;
}

$student_id = $_SESSION['user_id'];

$hour = (int)date('H');
if ($hour < 11)      $greeting = "Selamat Pagi";
elseif ($hour < 15)  $greeting = "Selamat Siang";
elseif ($hour < 18)  $greeting = "Selamat Sore";
else                 $greeting = "Selamat Malam";

// Get Student's Class
$stmt = $pdo->prepare("SELECT u.class_id, c.name as class_name FROM users u LEFT JOIN classes c ON u.class_id = c.id WHERE u.id = ?");
$stmt->execute([$student_id]);
$user_data = $stmt->fetch();
$class_id   = $user_data['class_id'];
$class_name = $user_data['class_name'] ?? null;

// Stats
$stmt = $pdo->prepare("SELECT COUNT(*) FROM materials WHERE class_id = ? OR class_id IS NULL");
$stmt->execute([$class_id]);
$total_materials = $stmt->fetchColumn();

// Pending Tasks
$pending_tasks = 0;
if ($class_id) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM assignments a
        JOIN assignment_classes ac ON a.id = ac.assignment_id
        WHERE ac.class_id = ? AND a.status = 'active'
        AND NOT EXISTS (
            SELECT 1 FROM submissions s WHERE s.assignment_id = a.id AND s.student_id = ?
        )
    ");
    $stmt->execute([$class_id, $student_id]);
    $pending_tasks = $stmt->fetchColumn();
}

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM submissions s
    JOIN assignments a ON s.assignment_id = a.id
    JOIN assignment_classes ac ON a.id = ac.assignment_id
    WHERE s.student_id = ? AND ac.class_id = ?
");
$stmt->execute([$student_id, $class_id]);
$completed_tasks = $stmt->fetchColumn();

// Total active assignments for this class
$total_assignments = 0;
if ($class_id) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM assignments a
        JOIN assignment_classes ac ON a.id = ac.assignment_id
        WHERE ac.class_id = ? AND a.status = 'active'
    ");
    $stmt->execute([$class_id]);
    $total_assignments = $stmt->fetchColumn();
}

// Recent Materials
$stmt = $pdo->prepare("
    SELECT m.title, m.type, m.file_path, m.created_at, u.full_name as teacher_name
    FROM materials m
    JOIN users u ON m.teacher_id = u.id
    WHERE (m.class_id = ? OR m.class_id IS NULL)
    ORDER BY m.created_at DESC LIMIT 5
");
$stmt->execute([$class_id]);
$recent_materials = $stmt->fetchAll();

// Upcoming / Pending Assignments
$upcoming_assignments = [];
if ($class_id) {
    $stmt = $pdo->prepare("
        SELECT a.title, a.deadline, u.full_name as teacher_name, a.id
        FROM assignments a
        JOIN assignment_classes ac ON a.id = ac.assignment_id
        JOIN users u ON a.teacher_id = u.id
        WHERE ac.class_id = ? AND a.status = 'active'
        AND NOT EXISTS (
            SELECT 1 FROM submissions s WHERE s.assignment_id = a.id AND s.student_id = ?
        )
        ORDER BY a.deadline ASC LIMIT 5
    ");
    $stmt->execute([$class_id, $student_id]);
    $upcoming_assignments = $stmt->fetchAll();
}

// Indonesian Date
$days   = ['Sunday'=>'Minggu','Monday'=>'Senin','Tuesday'=>'Selasa','Wednesday'=>'Rabu','Thursday'=>'Kamis','Friday'=>'Jumat','Saturday'=>'Sabtu'];
$months = ['January'=>'Januari','February'=>'Februari','March'=>'Maret','April'=>'April','May'=>'Mei','June'=>'Juni','July'=>'Juli','August'=>'Agustus','September'=>'September','October'=>'Oktober','November'=>'November','December'=>'Desember'];
$dateStr = ($days[date('l')] ?? date('l')) . ', ' . date('d') . ' ' . ($months[date('F')] ?? date('F')) . ' ' . date('Y');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Siswa — SIAKAD</title>
    <link rel="stylesheet" href="/public/assets/css/style.css">
    <style>
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
        .db-stat.c4 .db-num { color: #DC2626; }
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
            display: flex;
            align-items: center;
            justify-content: space-between;
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

        /* Material & task rows */
        .mat-row {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 0;
            border-bottom: 1px solid var(--border);
            text-decoration: none;
            color: inherit;
            transition: background 0.1s;
        }
        .mat-row:last-child { border-bottom: none; padding-bottom: 0; }
        .mat-row:first-child { padding-top: 0; }
        .mat-row:hover { color: var(--primary); }
        .mat-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
        .mat-body { flex: 1; min-width: 0; }
        .mat-name { font-size: 0.82rem; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .mat-sub  { font-size: 0.72rem; color: var(--text-muted); margin-top: 1px; }

        .empty-hint { text-align: center; padding: 2rem 1rem; color: var(--text-muted); font-size: 0.85rem; }

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

        <!-- Page Toolbar -->
        <div class="page-toolbar">
            <div class="page-toolbar-left">
                <h1 class="page-title"><?php echo $greeting; ?>, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</h1>
                <?php if (!empty($_SESSION['nis'])): ?>
                    <p class="page-subtitle" style="margin-bottom:2px;">NIS: <?php echo htmlspecialchars($_SESSION['nis']); ?></p>
                <?php endif; ?>
                <p class="page-subtitle">Ruang belajar dan aktivitas akademik kamu<?php if ($class_name): ?> — Kelas <?php echo htmlspecialchars($class_name); ?><?php endif; ?></p>
            </div>
            <div class="page-toolbar-right">
                <span style="font-size:0.8125rem; color:var(--text-muted);"><?php echo $dateStr; ?></span>
                <?php if ($class_id): ?>
                <a href="kelas_detail_siswa.php?class_id=<?php echo intval($class_id); ?>&tab=tugas" class="btn btn-sm">Kerjakan Tugas</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Content -->
        <div style="padding: 24px 32px;">

            <!-- Stats Bar -->
            <div class="db-stats">
                <div class="db-stat c1">
                    <div class="db-num"><?php echo $total_materials; ?></div>
                    <div class="db-lbl">Total Materi</div>
                </div>
                <div class="db-stat c2">
                    <div class="db-num"><?php echo $total_assignments; ?></div>
                    <div class="db-lbl">Tugas Aktif</div>
                </div>
                <div class="db-stat c3">
                    <div class="db-num"><?php echo $completed_tasks; ?></div>
                    <div class="db-lbl">Sudah Dikumpulkan</div>
                </div>
                <div class="db-stat c4">
                    <div class="db-num"><?php echo $pending_tasks; ?></div>
                    <div class="db-lbl">Belum Dikerjakan</div>
                </div>
            </div>

            <!-- Two-panel grid -->
            <div class="db-grid">

                <!-- Materi Terbaru -->
                <div class="db-panel">
                    <div class="db-panel-title">
                        Materi Terbaru
                        <?php if ($class_id): ?>
                        <a href="kelas_detail_siswa.php?class_id=<?php echo intval($class_id); ?>&tab=materi" style="font-size:0.72rem; font-weight:700; color:var(--primary); text-decoration:none;">Lihat Semua →</a>
                        <?php endif; ?>
                    </div>
                    <?php if (empty($recent_materials)): ?>
                        <div class="empty-hint">Belum ada materi untuk kelas ini.</div>
                    <?php else: ?>
                        <?php foreach ($recent_materials as $m):
                            $typeColors = ['pdf'=>'#DC2626','video'=>'#2563EB','ppt'=>'#D97706','link'=>'#059669'];
                            $dotColor = $typeColors[$m['type']] ?? '#6B7280';
                            $href = $m['file_path'];
                            if ($m['type'] !== 'link' && strpos($href, 'http') !== 0) $href = '/' . $href;
                        ?>
                        <a href="<?php echo htmlspecialchars($href); ?>" target="_blank" class="mat-row">
                            <div class="mat-dot" style="background:<?php echo $dotColor; ?>;"></div>
                            <div class="mat-body">
                                <div class="mat-name"><?php echo htmlspecialchars($m['title']); ?></div>
                                <div class="mat-sub"><?php echo htmlspecialchars($m['teacher_name']); ?> · <?php echo date('d M Y', strtotime($m['created_at'])); ?></div>
                            </div>
                            <span style="font-size:0.65rem; font-weight:700; text-transform:uppercase; background:var(--bg-muted); color:var(--text-muted); padding:2px 7px; border-radius:4px;"><?php echo strtoupper(htmlspecialchars($m['type'])); ?></span>
                        </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Menu Cepat + Tugas Mendatang -->
                <div style="display:flex; flex-direction:column; gap:20px;">

                    <!-- Tugas Mendatang -->
                    <div class="db-panel">
                        <div class="db-panel-title">
                            Tugas Mendatang
                            <?php if ($class_id): ?>
                            <a href="kelas_detail_siswa.php?class_id=<?php echo intval($class_id); ?>&tab=tugas" style="font-size:0.72rem; font-weight:700; color:var(--primary); text-decoration:none;">Lihat Semua →</a>
                            <?php endif; ?>
                        </div>
                        <?php if (empty($upcoming_assignments)): ?>
                            <div class="empty-hint">
                                <?php if (!$class_id): ?>
                                    Anda belum terdaftar di kelas.
                                <?php else: ?>
                                    ✅ Semua tugas sudah dikerjakan!
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <?php foreach ($upcoming_assignments as $a):
                                $deadline_ts = strtotime($a['deadline']);
                                $is_overdue  = $deadline_ts < time();
                            ?>
                            <div class="mat-row">
                                <div class="mat-dot" style="background:<?php echo $is_overdue ? '#DC2626' : '#F59E0B'; ?>;"></div>
                                <div class="mat-body">
                                    <div class="mat-name"><?php echo htmlspecialchars($a['title']); ?></div>
                                    <div class="mat-sub <?php echo $is_overdue ? 'deadline-passed' : ''; ?>">
                                        Deadline: <?php echo date('d M Y, H:i', $deadline_ts); ?>
                                        <?php if ($is_overdue): ?> · <strong>LEWAT</strong><?php endif; ?>
                                    </div>
                                </div>
                                <?php if (!$is_overdue): ?>
                                <a href="kelas_detail_siswa.php?class_id=<?php echo intval($class_id); ?>&tab=tugas" style="font-size:0.72rem; font-weight:700; color:var(--primary); white-space:nowrap; text-decoration:none;">Kerjakan →</a>
                                <?php else: ?>
                                <span style="font-size:0.7rem; font-weight:700; color:#DC2626; white-space:nowrap;">Terlambat</span>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Menu Cepat -->
                    <div class="db-panel">
                        <div class="db-panel-title">Menu Cepat</div>
                        <div class="qa-list">
                            <?php if ($class_id): ?>
                            <a href="kelas_detail_siswa.php?class_id=<?php echo intval($class_id); ?>&tab=materi" class="qa-item">
                                <div class="qa-ico">📚</div>
                                <div>
                                    <div class="qa-title">Buka Materi Kelas</div>
                                    <div class="qa-desc">Lihat semua materi pelajaran</div>
                                </div>
                                <span class="qa-arrow">›</span>
                            </a>
                            <a href="kelas_detail_siswa.php?class_id=<?php echo intval($class_id); ?>&tab=tugas" class="qa-item">
                                <div class="qa-ico">✏️</div>
                                <div>
                                    <div class="qa-title">Tugas Saya</div>
                                    <div class="qa-desc">Lihat & kerjakan tugas yang diberikan</div>
                                </div>
                                <span class="qa-arrow">›</span>
                            </a>
                            <?php endif; ?>
                            <a href="jadwal.php" class="qa-item">
                                <div class="qa-ico">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                </div>
                                <div>
                                    <div class="qa-title">Jadwal Pelajaran</div>
                                    <div class="qa-desc">Lihat jadwal belajar minggu ini</div>
                                </div>
                                <span class="qa-arrow">›</span>
                            </a>
                            <a href="../profile.php" class="qa-item">
                                <div class="qa-ico">⚙️</div>
                                <div>
                                    <div class="qa-title">Profil Saya</div>
                                    <div class="qa-desc">Ubah data diri dan password</div>
                                </div>
                                <span class="qa-arrow">›</span>
                            </a>
                        </div>
                    </div>

                </div><!-- /right column -->
            </div><!-- /db-grid -->
        </div><!-- /content -->

    </main>
</div>

</body>
</html>
