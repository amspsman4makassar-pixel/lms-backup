<?php
// src/siswa/kelas_detail_siswa.php
// Detail kelas: tab Materi dan tab Tugas
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'siswa') {
    header("Location: ../../login.php");
    exit;
}

$student_id = $_SESSION['user_id'];
$class_id   = intval($_GET['class_id'] ?? 0);
$tab        = $_GET['tab'] ?? 'materi'; // 'materi' or 'tugas'

// Verify this student belongs to this class
$stmt = $pdo->prepare("SELECT u.class_id, c.name as class_name FROM users u LEFT JOIN classes c ON u.class_id = c.id WHERE u.id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch();

if (!$student || intval($student['class_id']) !== $class_id) {
    echo "<p style='padding:2rem; color:red;'>Anda tidak memiliki akses ke kelas ini.</p>";
    exit;
}

$class_name = $student['class_name'] ?? 'Kelas';

// ────────────────────────────────────────────────
// TAB: MATERI - fetch subjects then materials
// ────────────────────────────────────────────────
$subject_id = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : null;
$is_general = isset($_GET['general']);
$current_subject = null;

if ($tab === 'materi') {
    if ($subject_id || $is_general) {
        $condition = $is_general ? "COALESCE(m.subject_id, u.subject_id) IS NULL" : "COALESCE(m.subject_id, u.subject_id) = ?";
        $sql = "SELECT m.*, u.full_name as teacher_name,
                COALESCE(s_mat.name, s_user.name) as subject_name
                FROM materials m
                JOIN users u ON m.teacher_id = u.id
                LEFT JOIN subjects s_user ON u.subject_id = s_user.id
                LEFT JOIN subjects s_mat ON m.subject_id = s_mat.id
                WHERE (m.class_id = ? OR m.class_id IS NULL)
                AND $condition
                ORDER BY m.created_at DESC";
        $stmt = $pdo->prepare($sql);
        if ($is_general) {
            $stmt->execute([$class_id]);
            $current_subject = "Umum / Lainnya";
        } else {
            $stmt->execute([$class_id, $subject_id]);
        }
        $materials = $stmt->fetchAll();

        if ($subject_id && !$current_subject) {
            $stmt_sub = $pdo->prepare("SELECT name FROM subjects WHERE id = ?");
            $stmt_sub->execute([$subject_id]);
            $current_subject = $stmt_sub->fetchColumn();
        }
    } else {
        // List subjects that have materials
        $sql = "SELECT COALESCE(m.subject_id, u.subject_id) as id,
                    COALESCE(s_mat.name, s_user.name) as name,
                    COUNT(m.id) as material_count
                FROM materials m
                JOIN users u ON m.teacher_id = u.id
                LEFT JOIN subjects s_user ON u.subject_id = s_user.id
                LEFT JOIN subjects s_mat ON m.subject_id = s_mat.id
                WHERE (m.class_id = ? OR m.class_id IS NULL)
                GROUP BY id, name
                ORDER BY name ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$class_id]);
        $subjects = $stmt->fetchAll();
        $materials = [];
    }
}

// ────────────────────────────────────────────────
// TAB: TUGAS - per-subject grouping (mirrors materi)
// ────────────────────────────────────────────────
$tugas_subject_id  = isset($_GET['tugas_subject_id']) ? intval($_GET['tugas_subject_id']) : null;
$is_tugas_general  = isset($_GET['tugas_general']);
$current_tugas_subject = null;
$tugas_subjects    = [];
$pending_tasks     = [];
$completed_tasks   = [];

if ($tab === 'tugas') {
    if ($tugas_subject_id || $is_tugas_general) {
        // ── Subject condition ──
        if ($is_tugas_general) {
            $subj_cond = "COALESCE(a.subject_id, u.subject_id) IS NULL";
            $current_tugas_subject = 'Umum / Lainnya';
            $exec_extra = [];
        } else {
            $subj_cond = "COALESCE(a.subject_id, u.subject_id) = ?";
            $exec_extra = [$tugas_subject_id];
            $st2 = $pdo->prepare("SELECT name FROM subjects WHERE id = ?");
            $st2->execute([$tugas_subject_id]);
            $current_tugas_subject = $st2->fetchColumn() ?: 'Mata Pelajaran';
        }

        // Pending
        $sql = "
            SELECT a.*, u.full_name as teacher_name, COALESCE(s_a.name, s_u.name, 'Umum') as subject_name
            FROM assignments a
            JOIN assignment_classes ac ON a.id = ac.assignment_id
            JOIN users u ON a.teacher_id = u.id
            LEFT JOIN subjects s_u ON u.subject_id = s_u.id
            LEFT JOIN subjects s_a ON a.subject_id = s_a.id
            WHERE ac.class_id = ? AND a.status = 'active'
            AND $subj_cond
            AND NOT EXISTS (
                SELECT 1 FROM submissions s WHERE s.assignment_id = a.id AND s.student_id = ?
            )
            ORDER BY a.created_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge([$class_id], $exec_extra, [$student_id]));
        $pending_tasks = $stmt->fetchAll();

        // Completed
        $sql2 = "
            SELECT a.title, a.deadline, a.assignment_type,
                   s.submitted_at, s.grade, s.status as sub_status,
                   a.id as assignment_id, s.id as submission_id,
                   u.full_name as teacher_name
            FROM submissions s
            JOIN assignments a ON s.assignment_id = a.id
            JOIN assignment_classes ac ON a.id = ac.assignment_id
            JOIN users u ON a.teacher_id = u.id
            LEFT JOIN subjects s_u ON u.subject_id = s_u.id
            LEFT JOIN subjects s_a ON a.subject_id = s_a.id
            WHERE s.student_id = ? AND ac.class_id = ?
            AND $subj_cond
            ORDER BY s.submitted_at DESC";
        $stmt2 = $pdo->prepare($sql2);
        $stmt2->execute(array_merge([$student_id, $class_id], $exec_extra));
        $completed_tasks = $stmt2->fetchAll();

    } else {
        // ── Subject list view ──
        $sql = "
            SELECT
                COALESCE(a.subject_id, u.subject_id)   as id,
                COALESCE(s_a.name, s_u.name, 'Umum / Lainnya')  as name,
                u.full_name                            as teacher_name,
                COUNT(DISTINCT a.id)                   as total_tugas,
                SUM(CASE WHEN NOT EXISTS (
                    SELECT 1 FROM submissions sub
                    WHERE sub.assignment_id = a.id AND sub.student_id = ?
                ) AND a.status='active' THEN 1 ELSE 0 END) as pending_count
            FROM assignments a
            JOIN assignment_classes ac ON a.id = ac.assignment_id
            JOIN users u ON a.teacher_id = u.id
            LEFT JOIN subjects s_u ON u.subject_id = s_u.id
            LEFT JOIN subjects s_a ON a.subject_id = s_a.id
            WHERE ac.class_id = ?
            GROUP BY id, name, u.full_name
            ORDER BY name ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$student_id, $class_id]);
        $tugas_subjects = $stmt->fetchAll();
    }
}

// ── Icon helpers ────────────────────────────────
function getTypeIcon(string $type): array {
    $icons = [
        'pdf'   => ['pdf',  '📄', '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg>'],
        'video' => ['video','🎬','<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2" ry="2"/></svg>'],
        'ppt'   => ['ppt',  '📊', '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>'],
        'epub'  => ['epub', '📚', '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>'],
        'link'  => ['link', '🔗', '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>'],
    ];
    return $icons[$type] ?? ['doc', '📝', '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($class_name); ?></title>
    <link rel="stylesheet" href="/public/assets/css/style.css?v=<?php echo time(); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* Tabs */
        .tab-bar {
            display: flex;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 2px 16px rgba(0,0,0,.06);
            margin-bottom: 1.5rem;
            overflow: hidden;
        }
        .tab-btn {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 14px;
            font-size: .9rem;
            font-weight: 700;
            color: #64748b;
            text-decoration: none;
            transition: background .2s, color .2s;
            border-bottom: 3px solid transparent;
        }
        .tab-btn.active {
            color: #4f46e5;
            border-bottom-color: #4f46e5;
            background: #f5f3ff;
        }
        .tab-btn:hover:not(.active) { background: #f8fafc; color: #334155; }



        /* Material list */
        .material-list { display: flex; flex-direction: column; gap: 12px; }
        .material-row {
            background: #fff;
            border-radius: 14px;
            padding: 1rem 1.25rem;
            display: flex;
            align-items: center;
            gap: 14px;
            box-shadow: 0 1px 6px rgba(0,0,0,.04);
            text-decoration: none;
            color: inherit;
            transition: box-shadow .2s, transform .15s;
        }
        .material-row:hover { box-shadow: 0 6px 20px rgba(0,0,0,.08); transform: translateX(4px); }
        .mat-ico { width:42px; height:42px; border-radius:12px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
        .mat-ico.pdf   { background:#fee2e2; color:#dc2626; }
        .mat-ico.video { background:#dbeafe; color:#2563eb; }
        .mat-ico.ppt   { background:#fef3c7; color:#d97706; }
        .mat-ico.epub  { background:#fce7f3; color:#be185d; }
        .mat-ico.link  { background:#d1fae5; color:#059669; }
        .mat-ico.doc   { background:#ede9fe; color:#7c3aed; }
        .mat-info { flex:1; min-width:0; }
        .mat-title { font-size:.9rem; font-weight:700; color:#1e293b; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .mat-meta  { font-size:.73rem; color:#94a3b8; margin-top:3px; }
        .mat-type-badge { font-size:.65rem; font-weight:700; text-transform:uppercase; padding:2px 8px; border-radius:4px; background:#f1f5f9; color:#64748b; margin-left:6px; }

        /* Task cards */
        .task-section { margin-bottom: 2rem; }
        .task-section-title { font-size:.75rem; font-weight:700; text-transform:uppercase; letter-spacing:.08em; color:#94a3b8; margin-bottom:10px; }
        .task-card {
            background: #fff;
            border-radius: 14px;
            padding: 1rem 1.25rem;
            display: flex;
            align-items: flex-start;
            gap: 14px;
            box-shadow: 0 1px 6px rgba(0,0,0,.04);
            margin-bottom: 10px;
            transition: box-shadow .2s;
        }
        .task-card:hover { box-shadow: 0 6px 20px rgba(0,0,0,.08); }
        .task-dot { width:10px; height:10px; border-radius:50%; flex-shrink:0; margin-top:5px; }
        .task-info { flex:1; min-width:0; }
        .task-title { font-size:.9rem; font-weight:700; color:#1e293b; margin-bottom:3px; }
        .task-meta  { font-size:.76rem; color:#94a3b8; }
        .task-badge { font-size:.72rem; font-weight:700; padding:4px 12px; border-radius:8px; white-space:nowrap; flex-shrink:0; }
        .badge-pending { background:#fef3c7; color:#92400e; }
        .badge-overdue { background:#fee2e2; color:#991b1b; }
        .badge-done    { background:#d1fae5; color:#065f46; }
        .badge-graded  { background:#dbeafe; color:#1e40af; }
        .badge-late    { background:#fce7f3; color:#9d174d; }

        .deadline-passed { color:#dc2626; font-weight:600; }

        .kerjakan-btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: #4f46e5;
            color: #fff;
            padding: 5px 14px;
            border-radius: 8px;
            font-size: .78rem;
            font-weight: 700;
            text-decoration: none;
            white-space: nowrap;
            flex-shrink: 0;
            transition: background .2s;
        }
        .kerjakan-btn:hover { background: #4338ca; }
        .kerjakan-btn.disabled { background: #e2e8f0; color: #94a3b8; pointer-events: none; }

        .empty-state { text-align:center; padding:3rem 1rem; color:#94a3b8; }

        .breadcrumb { display:flex; align-items:center; gap:6px; margin-bottom:16px; font-size:.82rem; color:#94a3b8; }
        .breadcrumb a { color:#4f46e5; text-decoration:none; font-weight:600; }
        .breadcrumb a:hover { text-decoration:underline; }
    </style>
</head>
<body>
<div class="app-container">
    <?php include '../templates/sidebar.php'; ?>
    <main class="main-content">

        <!-- Page Toolbar -->
        <div class="page-toolbar">
            <div class="page-toolbar-left">
                <a href="kelas_siswa.php" class="btn btn-secondary btn-sm" style="margin-bottom: 12px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5"/><path d="M12 19l-7-7 7-7"/></svg>
                    Kelas Saya
                </a>
                <h1 class="page-title"><?php echo htmlspecialchars($class_name); ?></h1>
                <p class="page-subtitle">Materi pelajaran dan tugas untuk kelas Anda</p>
            </div>
        </div>

        <div class="page-content">

            <!-- Tab Bar -->
            <div class="tab-bar">
                <a href="?class_id=<?php echo $class_id; ?>&tab=materi" class="tab-btn <?php echo $tab === 'materi' ? 'active' : ''; ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                    Materi Pelajaran
                </a>
                <a href="?class_id=<?php echo $class_id; ?>&tab=tugas" class="tab-btn <?php echo $tab === 'tugas' ? 'active' : ''; ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                    Tugas Saya
                </a>
            </div>

            <?php if ($tab === 'materi'): ?>
            <!-- ══════════ TAB MATERI ══════════ -->

            <?php if ($subject_id || $is_general): ?>
                <!-- Breadcrumb + Back -->
                <div class="breadcrumb">
                    <a href="?class_id=<?php echo $class_id; ?>&tab=materi">Semua Mapel</a>
                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
                    <span style="color:#1e293b; font-weight:600;"><?php echo htmlspecialchars($current_subject ?? ''); ?></span>
                </div>

                <?php if (empty($materials)): ?>
                <div class="empty-state">
                    <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#cbd5e1" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom:10px;"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg>
                    <p>Belum ada materi untuk mata pelajaran ini.</p>
                </div>
                <?php else: ?>
                <div class="material-list">
                    <?php foreach ($materials as $m):
                        [$cls, , $icon] = getTypeIcon($m['type']);
                        $href = $m['file_path'];
                        if ($m['type'] !== 'link' && strpos($href, 'http') !== 0) $href = '../../' . $href;
                    ?>
                    <a href="<?php echo htmlspecialchars($href); ?>" target="_blank" class="material-row">
                        <div class="mat-ico <?php echo $cls; ?>"><?php echo $icon; ?></div>
                        <div class="mat-info">
                            <div class="mat-title">
                                <?php echo htmlspecialchars($m['title']); ?>
                                <span class="mat-type-badge"><?php echo strtoupper(htmlspecialchars($m['type'])); ?></span>
                            </div>
                            <div class="mat-meta"><?php echo htmlspecialchars($m['teacher_name']); ?> &middot; <?php echo date('d M Y', strtotime($m['created_at'])); ?></div>
                        </div>
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

            <?php else: ?>
                <!-- Subject Grid -->
                <?php if (empty($subjects)): ?>
                <div class="empty-state">
                    <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#cbd5e1" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom:10px;"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                    <p>Belum ada materi untuk kelas ini.</p>
                </div>
                <?php else: ?>
                <div class="page-table-wrap">
                    <table class="page-table">
                        <thead>
                            <tr>
                                <th class="num-col">No</th>
                                <th>Mata Pelajaran</th>
                                <th>Materi</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($subjects as $i => $sub):
                            $href = ($sub['id'] === null) ? "?class_id={$class_id}&tab=materi&general=1" : "?class_id={$class_id}&tab=materi&subject_id={$sub['id']}";
                        ?>
                            <tr>
                                <td class="num-col" data-label="No"><?php echo $i + 1; ?></td>
                                <td style="font-weight:700; color:#1e293b;" data-label="Mata Pelajaran"><?php echo htmlspecialchars($sub['name'] ?? 'Umum'); ?></td>
                                <td data-label="Materi"><span class="count-b"><?php echo $sub['material_count']; ?> Materi</span></td>
                                <td data-label="Aksi">
                                    <a href="<?php echo $href; ?>" class="sub-btn">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg>
                                        Lihat Materi
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php else: ?>
            <!-- ══════════ TAB TUGAS (per-subject) ══════════ -->

            <?php if ($tugas_subject_id || $is_tugas_general): ?>

                <!-- Breadcrumb -->
                <div class="breadcrumb">
                    <a href="?class_id=<?php echo $class_id; ?>&tab=tugas">Semua Mapel</a>
                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
                    <span style="color:#1e293b; font-weight:600;"><?php echo htmlspecialchars($current_tugas_subject ?? ''); ?></span>
                </div>

                <!-- PENDING -->
                <div class="task-section">
                    <div class="task-section-title">
                        Belum Dikerjakan
                        <span style="background:#fef3c7; color:#92400e; padding:2px 8px; border-radius:10px; font-size:.68rem; margin-left:6px;"><?php echo count($pending_tasks); ?></span>
                    </div>
                    <?php if (empty($pending_tasks)): ?>
                    <div class="empty-state" style="padding:2rem; background:#fff; border-radius:14px; box-shadow:0 1px 6px rgba(0,0,0,.04);">
                        <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#34d399" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom:8px;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        <p style="color:#059669; font-weight:600;">Semua tugas mata pelajaran ini sudah dikerjakan!</p>
                    </div>
                    <?php else: ?>
                    <?php foreach ($pending_tasks as $t):
                        $deadline_ts = strtotime($t['deadline']);
                        $is_overdue  = $deadline_ts < time();
                        $type_label  = ($t['assignment_type'] ?? 'tugas') === 'absensi' ? 'Absensi' : 'Tugas';
                        $type_bg     = ($t['assignment_type'] ?? 'tugas') === 'absensi' ? '#dcfce7' : '#ede9fe';
                        $type_color  = ($t['assignment_type'] ?? 'tugas') === 'absensi' ? '#166534' : '#5b21b6';
                    ?>
                    <div class="task-card">
                        <div class="task-dot" style="background:<?php echo $is_overdue ? '#ef4444' : '#f59e0b'; ?>;"></div>
                        <div class="task-info">
                            <div class="task-title">
                                <span style="background:<?php echo $type_bg; ?>; color:<?php echo $type_color; ?>; font-size:.65rem; font-weight:700; padding:1px 6px; border-radius:3px; margin-right:5px;"><?php echo $type_label; ?></span>
                                <?php echo htmlspecialchars($t['title']); ?>
                            </div>
                            <div class="task-meta <?php echo $is_overdue ? 'deadline-passed' : ''; ?>">
                                Deadline: <?php echo date('d M Y, H:i', $deadline_ts); ?>
                                <?php if ($is_overdue): ?> &middot; <strong>SUDAH LEWAT</strong><?php endif; ?>
                                &middot; <?php echo htmlspecialchars($t['teacher_name']); ?>
                            </div>
                        </div>
                        <a href="assignments.php?assignment_id=<?php echo $t['id']; ?>" class="kerjakan-btn" style="<?php echo $is_overdue ? 'background:#fef3c7; color:#92400e; border-color:#fcd34d;' : ''; ?>">
                            <?php if ($is_overdue): ?>
                            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            Kerjakan (Terlambat)
                            <?php else: ?>
                            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                            Kerjakan
                            <?php endif; ?>
                        </a>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- COMPLETED -->
                <div class="task-section">
                    <div class="task-section-title">
                        Riwayat Pengerjaan
                        <span style="background:#d1fae5; color:#065f46; padding:2px 8px; border-radius:10px; font-size:.68rem; margin-left:6px;"><?php echo count($completed_tasks); ?></span>
                    </div>
                    <?php if (empty($completed_tasks)): ?>
                    <div class="empty-state" style="padding:2rem; background:#fff; border-radius:14px; box-shadow:0 1px 6px rgba(0,0,0,.04);">
                        <p>Belum ada tugas yang dikumpulkan untuk mapel ini.</p>
                    </div>
                    <?php else: ?>
                    <?php foreach ($completed_tasks as $d):
                        $is_late = ($d['sub_status'] === 'terlambat') || (!empty($d['submitted_at']) && strtotime($d['submitted_at']) > strtotime($d['deadline']));
                    ?>
                    <div class="task-card">
                        <div class="task-dot" style="background:#10b981;"></div>
                        <div class="task-info">
                            <div class="task-title"><?php echo htmlspecialchars($d['title']); ?></div>
                            <div class="task-meta">
                                Dikumpulkan: <?php echo date('d M Y, H:i', strtotime($d['submitted_at'])); ?>
                                <?php if ($is_late): ?> &middot; <span style="color:#be185d; font-weight:600;">Terlambat</span><?php endif; ?>
                                &middot; <?php echo htmlspecialchars($d['teacher_name']); ?>
                            </div>
                        </div>
                        <?php if (($d['assignment_type'] ?? 'tugas') === 'absensi'): ?>
                            <span class="task-badge badge-done" style="border:1px solid #bbf7d0;"><?php echo htmlspecialchars($d['sub_status'] ?? 'Hadir'); ?></span>
                        <?php elseif ($d['grade'] !== null): ?>
                            <span class="task-badge badge-graded">Nilai: <?php echo $d['grade']; ?></span>
                        <?php elseif ($is_late): ?>
                            <span class="task-badge badge-late">Terlambat</span>
                        <?php else: ?>
                            <span class="task-badge badge-done">Selesai</span>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>

            <?php else: ?>
                <!-- ── Subject list ── -->
                <?php if (empty($tugas_subjects)): ?>
                <div class="empty-state">
                    <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#cbd5e1" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom:10px;"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                    <p>Belum ada tugas untuk kelas ini.</p>
                </div>
                <?php else: ?>
                <div class="page-table-wrap">
                    <table class="page-table">
                        <thead>
                            <tr>
                                <th class="num-col">No</th>
                                <th>Mata Pelajaran</th>
                                <th>Guru</th>
                                <th>Total</th>
                                <th>Belum Dikerjakan</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($tugas_subjects as $i => $sub):
                            $href = ($sub['id'] === null)
                                ? "?class_id={$class_id}&tab=tugas&tugas_general=1"
                                : "?class_id={$class_id}&tab=tugas&tugas_subject_id={$sub['id']}";
                            $has_pending = intval($sub['pending_count']) > 0;
                        ?>
                            <tr>
                                <td class="num-col" data-label="No"><?php echo $i + 1; ?></td>
                                <td style="font-weight:700; color:#1e293b;" data-label="Mata Pelajaran"><?php echo htmlspecialchars($sub['name']); ?></td>
                                <td style="color:#64748b; font-size:0.85rem;" data-label="Guru"><?php echo htmlspecialchars($sub['teacher_name']); ?></td>
                                <td data-label="Total"><span class="count-b"><?php echo intval($sub['total_tugas']); ?> Tugas</span></td>
                                <td data-label="Belum Dikerjakan">
                                    <?php if ($has_pending): ?>
                                        <span style="display:inline-block; background:#fee2e2; color:#dc2626; padding:3px 10px; border-radius:6px; font-size:0.78rem; font-weight:700;"><?php echo intval($sub['pending_count']); ?> Belum</span>
                                    <?php else: ?>
                                        <span style="display:inline-block; background:#d1fae5; color:#059669; padding:3px 10px; border-radius:6px; font-size:0.78rem; font-weight:700;"><svg width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='3' stroke-linecap='round' stroke-linejoin='round' style='display:inline-block;vertical-align:middle;margin-top:-2px;margin-right:2px;'><polyline points='20 6 9 17 4 12'></polyline></svg>Semua Selesai</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Aksi">
                                    <a href="<?php echo $href; ?>" class="sub-btn">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                                        Lihat Tugas
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            <?php endif; ?><!-- end if tugas_subject / else subject-list -->

            <?php endif; ?><!-- end if tab===materi / else tugas -->

        </div><!-- /.page-content -->
    </main>
</div>
</body>
</html>
