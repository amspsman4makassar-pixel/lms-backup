<?php
// src/siswa/kelas_siswa.php
// Halaman "Kelas Saya" Ã¢â‚¬â€ menampilkan kelas siswa beserta ringkasan materi & tugas
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'siswa') {
    header("Location: ../../login.php");
    exit;
}

$student_id = $_SESSION['user_id'];

// Get student's class info
$stmt = $pdo->prepare("
    SELECT u.class_id, c.name as class_name
    FROM users u
    LEFT JOIN classes c ON u.class_id = c.id
    WHERE u.id = ?
");
$stmt->execute([$student_id]);
$student = $stmt->fetch();
$class_id   = $student['class_id'];
$class_name = $student['class_name'] ?? null;

// Count materials for this class
$total_materials = 0;
if ($class_id) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM materials WHERE class_id = ? OR class_id IS NULL");
    $stmt->execute([$class_id]);
    $total_materials = $stmt->fetchColumn();
}

// Count active assignments
$total_assignments = 0;
if ($class_id) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM assignments a
        JOIN assignment_classes ac ON a.id = ac.assignment_id
        WHERE ac.class_id = ? AND a.status = 'active'
    ");
    $stmt->execute([$class_id]);
    $total_assignments = $stmt->fetchColumn();
}

// Count pending (belum dikerjakan)
$pending = 0;
if ($class_id) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM assignments a
        JOIN assignment_classes ac ON a.id = ac.assignment_id
        WHERE ac.class_id = ? AND a.status = 'active'
        AND NOT EXISTS (
            SELECT 1 FROM submissions s WHERE s.assignment_id = a.id AND s.student_id = ?
        )
    ");
    $stmt->execute([$class_id, $student_id]);
    $pending = $stmt->fetchColumn();
}

// Get teachers for this class (from assignments and materials)
$teachers = [];
if ($class_id) {
    $stmt = $pdo->prepare("
        SELECT DISTINCT u.full_name, s.name as subject_name
        FROM users u
        LEFT JOIN subjects s ON u.subject_id = s.id
        WHERE u.role = 'guru'
        AND (
            EXISTS (SELECT 1 FROM materials m WHERE m.teacher_id = u.id AND (m.class_id = ? OR m.class_id IS NULL))
            OR
            EXISTS (SELECT 1 FROM assignments a JOIN assignment_classes ac ON a.id = ac.assignment_id WHERE a.teacher_id = u.id AND ac.class_id = ?)
        )
        ORDER BY u.full_name ASC
        LIMIT 6
    ");
    $stmt->execute([$class_id, $class_id]);
    $teachers = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta charset="UTF-8">
    <title>Kelas Saya</title>
    <link rel="stylesheet" href="/public/assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
<div class="app-container">
    <?php include '../templates/sidebar.php'; ?>
    <main class="main-content">

        <!-- Page Toolbar -->
        <div class="page-toolbar">
            <div class="page-toolbar-left">
                <h1 class="page-title">Kelas Saya</h1>
                <p class="page-subtitle">Akses materi dan tugas dari kelas Anda</p>
            </div>
        </div>

        <div class="page-content">
            <?php if (!$class_id || !$class_name): ?>
            <div class="page-section">
                <div class="empty-state">
                    <div class="empty-icon"><svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg></div>
                    <h4>Anda belum terdaftar di kelas manapun</h4>
                    <p>Hubungi Admin untuk mendaftarkan Anda ke kelas.</p>
                </div>
            </div>

            <?php else: ?>
            <!-- Class Card -->
            <div class="class-card" style="background: #fff; border: 1px solid var(--border); border-radius: var(--radius-xl); padding: 24px; display: flex; flex-direction: column; gap: 20px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px;">
                    <div>
                        <div style="font-weight: 800; color: #1e293b; font-size: 1.5rem;"><?php echo htmlspecialchars($class_name); ?></div>
                        <?php
                        $sub_label = '';
                        if (!empty($student['grade']))  $sub_label .= 'Kelas ' . htmlspecialchars($student['grade']);
                        if (!empty($student['major']))  $sub_label .= ' &middot; ' . htmlspecialchars($student['major']);
                        ?>
                        <?php if ($sub_label): ?>
                            <div style="color: #64748b; font-size: 1rem; margin-top: 4px;"><?php echo $sub_label; ?></div>
                        <?php endif; ?>
                    </div>
                    <a href="kelas_detail_siswa.php?class_id=<?php echo intval($class_id); ?>" class="btn btn-primary" style="padding: 10px 24px; font-weight: 700;">
                        Masuk Kelas &rarr;
                    </a>
                </div>

                <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                    <span style="display:inline-block; background:#e0e7ff; color:#4338ca; padding:6px 12px; border-radius:8px; font-size:0.85rem; font-weight:700;"><svg width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' style='display:inline-block; vertical-align:middle; line-height:1; margin-top:-2px;'><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg> <?php echo $total_materials; ?> Materi</span>
                    <span style="display:inline-block; background:#d1fae5; color:#059669; padding:6px 12px; border-radius:8px; font-size:0.85rem; font-weight:700;"><svg width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' style='display:inline-block; vertical-align:middle; line-height:1; margin-top:-2px;'><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg> <?php echo $total_assignments; ?> Tugas</span>
                    <?php if ($pending > 0): ?>
                        <span style="display:inline-block; background:#fee2e2; color:#dc2626; padding:6px 12px; border-radius:8px; font-size:0.85rem; font-weight:700;"><svg width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' style='display:inline-block; vertical-align:middle; line-height:1; margin-top:-2px;'><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg> <?php echo $pending; ?> Menunggu</span>
                    <?php endif; ?>
                </div>

                <div style="border-top: 1px solid var(--border); padding-top: 16px;">
                    <div style="font-size: 0.85rem; color: #64748b; font-weight: 700; margin-bottom: 8px; text-transform: uppercase;">Guru Pengajar:</div>
                    <?php if (!empty($teachers)): ?>
                        <div style="display: flex; flex-wrap: wrap; gap: 6px;">
                        <?php foreach ($teachers as $t): ?>
                        <span style="display:inline-flex; align-items:center; gap:6px; background:#f8fafc; border:1px solid #e2e8f0; color:#475569; font-size:0.85rem; font-weight:600; padding:4px 12px; border-radius:20px;">
                            <div style="width:20px; height:20px; border-radius:50%; background:#e2e8f0; display:flex; align-items:center; justify-content:center; font-size:0.6rem; color:#64748b; font-weight:800;"><?php echo strtoupper(substr($t['full_name'], 0, 1)); ?></div>
                            <?php echo htmlspecialchars($t['full_name']); ?><?php if ($t['subject_name']): ?> <span style="color:#94a3b8; font-weight:500;">&middot; <?php echo htmlspecialchars($t['subject_name']); ?></span><?php endif; ?>
                        </span>
                        <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <span style="color: #94a3b8; font-size: 0.85rem;">Belum ada guru pengajar</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

    </main>
</div>
</body>
</html>


