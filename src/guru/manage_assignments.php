<?php
// src/guru/manage_assignments.php
session_start();
require_once '../../config/database.php';

// Check Role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guru') {
    header("Location: ../../login.php");
    exit;
}

// Set active sidebar
$active_tab = 'kelas';

// Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬ Auto-Migration: Ensure is_archived column exists in submissions Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
try {
    $check = $pdo->query("SHOW COLUMNS FROM submissions LIKE 'is_archived'");
    if ($check->rowCount() == 0) {
        $pdo->exec("ALTER TABLE submissions ADD COLUMN is_archived TINYINT(1) DEFAULT 0");
    }
}
catch (Exception $e) {
// Silent fail or log
}
// Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬ End Auto-Migration Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬

// Back Link Logic
$back_url = 'kelas.php';
$back_text = 'Kembali ke Daftar Kelas';

if (isset($_GET['from_class'])) {
    $fid = intval($_GET['from_class']);
    $back_url = "view_class.php?id=$fid";

    // Fetch class name for better UX
    $stmtC = $pdo->prepare("SELECT name FROM teacher_classes WHERE class_id = ? AND teacher_id = ?");
    // Wait, teacher_classes stores class_id but primary key is 'id'. 
    // Let's use the ID passed in 'from_class' which comes from 'view_class.php?id=...' which uses 'teacher_classes.id' usually.
    // In view_class.php: $class_id = $_GET['id']; Query: WHERE tc.id = ?
    // So $fid is the teacher_classes id.

    $stmtC = $pdo->prepare("SELECT name FROM teacher_classes WHERE id = ?");
    $stmtC->execute([$fid]);
    $cName = $stmtC->fetchColumn();

    if ($cName) {
        $back_text = "Kembali ke $cName";
    }
    else {
        $back_text = "Kembali ke Kelas";
    }
}

// Fetch Classes
$classes = $pdo->query("SELECT * FROM classes")->fetchAll();

// Custom Robust Sort (e.g. X-2 before X-10)
usort($classes, function ($a, $b) {
    // Extract Grade and Number manually
    // Matches: "X-1", "X-10", "XI 2", "XII IPA 1"
    preg_match('/^([a-zA-Z\s]+)[^0-9]*([0-9]+)?$/', $a['name'], $mA);
    preg_match('/^([a-zA-Z\s]+)[^0-9]*([0-9]+)?$/', $b['name'], $mB);

    $prefixA = trim($mA[1] ?? $a['name']);
    $prefixB = trim($mB[1] ?? $b['name']);

    $numA = intval($mA[2] ?? 0);
    $numB = intval($mB[2] ?? 0);

    // 1. Compare Prefix (X vs XI vs XII)
    // Convert Roman to Integer for valid comparison "X" < "XI"
    $romans = ['X' => 10, 'XI' => 11, 'XII' => 12];
    $valA = 0;
    $valB = 0;

    foreach ($romans as $key => $val) {
        if (str_contains(strtoupper($prefixA), $key))
            $valA = $val;
        if (str_contains(strtoupper($prefixB), $key))
            $valB = $val;
    }

    if ($valA != $valB)
        return $valA - $valB;

    // 2. If prefixes match roughly (e.g. both X), compare specific strings
    if ($prefixA !== $prefixB)
        return strcmp($prefixA, $prefixB);

    // 3. Compare Numbers (2 vs 10)
    return $numA - $numB;
});

// Handle Actions (Archive / Restore)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = $_GET['id'];
    $action = $_GET['action'];
    $teacher_id = $_SESSION['user_id']; // security check

    if ($action == 'archive') {
        $stmt = $pdo->prepare("UPDATE assignments SET status = 'archived' WHERE id = ? AND teacher_id = ?");
        if ($stmt->execute([$id, $teacher_id]))
            $_SESSION['flash'] = ['type' => 'success', 'message' => "Tugas berhasil diarsipkan (ditarik)."];
    }
    elseif ($action == 'restore') {
        $stmt = $pdo->prepare("UPDATE assignments SET status = 'active' WHERE id = ? AND teacher_id = ?");
        if ($stmt->execute([$id, $teacher_id]))
            $_SESSION['flash'] = ['type' => 'success', 'message' => "Tugas berhasil diterbitkan kembali."];
    }
    elseif ($action == 'delete') {
        // Optional Delete
        $stmt = $pdo->prepare("DELETE FROM assignments WHERE id = ? AND teacher_id = ?");
        if ($stmt->execute([$id, $teacher_id]))
            $_SESSION['flash'] = ['type' => 'success', 'message' => "Tugas berhasil dihapus permanen."];
    }
    // PRG Redirect - Strip action and id params
    header("Location: manage_assignments.php");
    exit;
}

// Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬ Handle Edit Assignment Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_assignment'])) {
    $id = $_POST['assignment_id'];
    $title = $_POST['title'];
    $desc = $_POST['description'];
    $deadline = $_POST['deadline'];
    $teacher_id = $_SESSION['user_id'];

    $stmt = $pdo->prepare("UPDATE assignments SET title = ?, description = ?, deadline = ? WHERE id = ? AND teacher_id = ?");
    if ($stmt->execute([$title, $desc, $deadline, $id, $teacher_id])) {
        $_SESSION['flash'] = ['type' => 'success', 'message' => "Tugas berhasil diperbarui."];
    }
    else {
        $_SESSION['flash'] = ['type' => 'error', 'message' => "Gagal memperbarui tugas."];
    }
    header("Location: manage_assignments.php");
    exit;
}

// Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬ Handle Reset Submissions Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_submissions'])) {
    $id = $_POST['assignment_id'];
    $teacher_id = $_SESSION['user_id'];

    // Verify ownership
    $stmt = $pdo->prepare("SELECT id FROM assignments WHERE id = ? AND teacher_id = ?");
    $stmt->execute([$id, $teacher_id]);
    if ($stmt->fetch()) {
        // Soft delete submissions by setting is_archived = 1
        $pdo->prepare("UPDATE submissions SET is_archived = 1 WHERE assignment_id = ?")->execute([$id]);
        $_SESSION['flash'] = ['type' => 'success', 'message' => "Semua jawaban siswa telah diarsipkan (Reset). Siswa dapat mengerjakan ulang."];
    }
    header("Location: manage_assignments.php");
    exit;
}

// Handle Add Assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_assignment'])) {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $deadline = $_POST['deadline'];
    $teacher_id = $_SESSION['user_id'];

    // Support Multi-Class: Array of class IDs
    $target_classes = $_POST['class_ids'] ?? [];

    if (empty($target_classes)) {
        $_SESSION['flash'] = ['type' => 'error', 'message' => "Pilih setidaknya satu kelas target."];
    }
    else {
        $attachment_path = null;
        $tugas_type = $_POST['a_type'] ?? 'file';

        // Handle File Upload
        if ($tugas_type === 'file' && isset($_FILES['attachment']) && $_FILES['attachment']['error'] == 0) {
            $upload_dir = '../../public/uploads/assignments/';
            if (!file_exists($upload_dir))
                mkdir($upload_dir, 0777, true);

            $file_name = time() . '_' . basename($_FILES['attachment']['name']);
            $target_file = $upload_dir . $file_name;

            $file_type = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
            $allowed = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'mp4', 'jpg', 'png', 'zip', 'xls', 'xlsx', 'epub', 'avi', 'mov'];

            if (in_array($file_type, $allowed)) {
                if (move_uploaded_file($_FILES['attachment']['tmp_name'], $target_file)) {
                    $attachment_path = 'public/uploads/assignments/' . $file_name;
                }
            }
        }
        elseif ($tugas_type === 'link') {
            $link_url = trim($_POST['a_link_url']);
            if (!empty($link_url)) {
                $attachment_path = $link_url;
            }
        }

        // 1. Insert Assignment
        $stmt = $pdo->prepare("INSERT INTO assignments (teacher_id, title, description, deadline, attachment_path, status, assignment_type) VALUES (?, ?, ?, ?, ?, 'active', 'tugas')");
        if ($stmt->execute([$teacher_id, $title, $description, $deadline, $attachment_path])) {
            $assignment_id = $pdo->lastInsertId();

            // 2. Insert into assignment_classes pivot table
            $ins = $pdo->prepare("INSERT INTO assignment_classes (assignment_id, class_id) VALUES (?, ?)");
            foreach ($target_classes as $cid) {
                $ins->execute([$assignment_id, $cid]);
            }

            $_SESSION['flash'] = ['type' => 'success', 'message' => "Tugas berhasil dibuat untuk " . count($target_classes) . " kelas!"];
        }
        else {
            $_SESSION['flash'] = ['type' => 'error', 'message' => "Gagal membuat tugas."];
        }
    }
    header("Location: manage_assignments.php");
    exit;
}

// Fetch Assignments grouped by class
$type_condition = "AND assignments.assignment_type = 'tugas'";
$params = [$_SESSION['user_id']];

// Query per-row per class (compatible with MySQL 5.x+)
$stmt = $pdo->prepare("
    SELECT assignments.*,
           classes.id   AS class_id,
           classes.name AS class_name
    FROM assignments
    JOIN assignment_classes ON assignments.id = assignment_classes.assignment_id
    JOIN classes ON assignment_classes.class_id = classes.id
    WHERE assignments.teacher_id = ?
    $type_condition
    ORDER BY classes.name ASC, assignments.deadline ASC
");
$stmt->execute($params);
$raw_rows = $stmt->fetchAll();

// Group into [class_name => [assignments...]]
// Use assignment id dedupe within each class group
$grouped_active   = []; // ['XII IPA 1' => ['assignments' => [], 'class_id' => x]]
$grouped_archived = [];
$seen_ids_per_class = []; // prevent duplicate rows if query ever returns them

foreach ($raw_rows as $row) {
    $cn = $row['class_name'];
    $aid = $row['id'];
    $status = $row['status'];

    if ($status === 'active') {
        if (!isset($grouped_active[$cn])) {
            $grouped_active[$cn] = ['class_id' => $row['class_id'], 'assignments' => []];
        }
        if (!in_array($aid, array_column($grouped_active[$cn]['assignments'], 'id'))) {
            $grouped_active[$cn]['assignments'][] = $row;
        }
    } else {
        if (!isset($grouped_archived[$cn])) {
            $grouped_archived[$cn] = ['class_id' => $row['class_id'], 'assignments' => []];
        }
        if (!in_array($aid, array_column($grouped_archived[$cn]['assignments'], 'id'))) {
            $grouped_archived[$cn]['assignments'][] = $row;
        }
    }
}

// Total counts for summary
$total_active = array_sum(array_map(fn($g) => count($g['assignments']), $grouped_active));
$total_archived = array_sum(array_map(fn($g) => count($g['assignments']), $grouped_archived));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta charset="UTF-8">
    <title>Kelola Tugas - Guru</title>
    <link rel="stylesheet" href="/public/assets/css/style.css">
</head>
<body class="unified-layout">

<div class="app-container">
    <?php include '../templates/sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Unified Dashboard Hero -->
        <div class="page-toolbar">
            <div class="page-toolbar-left">
                <h1 class="page-title">Kelola Tugas</h1>
                <p class="page-subtitle">Buat dan kelola tugas untuk kelas Anda</p>
            </div>
        </div>

        <div class="page-content">

        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem;">
            <!-- List Tugas -->
            <div style="display: flex; flex-direction: column; gap: 2rem;">

                <!-- ===== ACTIVE ===== -->
                <div class="page-section">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                        <div>
                            <h3 style="margin: 0;">Daftar Tugas Aktif</h3>
                            <p style="margin: 4px 0 0; font-size: 0.85rem; color: var(--text-muted);"><?php echo $total_active; ?> tugas di <?php echo count($grouped_active); ?> kelas</p>
                        </div>

                    </div>

                    <?php if (empty($grouped_active)): ?>
                        <p style="color: var(--text-muted);">Tidak ada tugas aktif.</p>
                    <?php else: ?>
                        <div style="display: flex; flex-direction: column; gap: 1rem;">
                        <?php $acc_idx = 0; foreach ($grouped_active as $class_name => $group): $acc_idx++; $acc_id = 'acc_active_' . $acc_idx; $count = count($group['assignments']); ?>

                            <!-- Class Accordion -->
                            <div style="border: 1px solid #e2e8f0; border-radius: 14px; overflow: hidden;">
                                <!-- Header -->
                                <button onclick="toggleAccordion('<?php echo $acc_id; ?>')" style="width:100%; display:flex; justify-content:space-between; align-items:center; padding: 1rem 1.25rem; background: linear-gradient(135deg,#eef2ff 0%,#f0f9ff 100%); border:none; cursor:pointer; text-align:left; gap:10px;">
                                    <div style="display:flex; align-items:center; gap:10px;">
                                        <div style="width:36px; height:36px; border-radius:10px; background:#4f46e5; display:flex; align-items:center; justify-content:center;">
                                            <svg xmlns='http://www.w3.org/2000/svg' width='18' height='18' viewBox='0 0 24 24' fill='none' stroke='#fff' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><path d='M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2'/><circle cx='9' cy='7' r='4'/><path d='M23 21v-2a4 4 0 0 0-3-3.87'/><path d='M16 3.13a4 4 0 0 1 0 7.75'/></svg>
                                        </div>
                                        <div>
                                            <span style="font-size:1rem; font-weight:700; color:#1e293b;"><?php echo htmlspecialchars($class_name); ?></span>
                                            <span style="margin-left:8px; background:#4f46e5; color:#fff; padding:2px 8px; border-radius:20px; font-size:0.72rem; font-weight:700;"><?php echo $count; ?> tugas</span>
                                        </div>
                                    </div>
                                    <svg id="arrow_<?php echo $acc_id; ?>" xmlns='http://www.w3.org/2000/svg' width='18' height='18' viewBox='0 0 24 24' fill='none' stroke='#4f46e5' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round' style='transition:transform 0.3s; flex-shrink:0;'><polyline points='6 9 12 15 18 9'/></svg>
                                </button>

                                <!-- Body -->
                                <div id="<?php echo $acc_id; ?>" style="display:none; flex-direction:column; gap:0;">
                                <?php foreach ($group['assignments'] as $a): ?>
                                    <div style="border-top: 1px solid #f1f5f9; padding: 1.2rem 1.25rem; display:flex; justify-content:space-between; align-items:flex-start; background:#fff; transition:background 0.2s;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='#fff'">
                                        <div style="flex:1; min-width:0;">
                                            <?php
                                            $assign_type = $a['assignment_type'] ?? 'tugas';
                                            $type_badge_bg = $assign_type === 'absensi' ? '#dcfce7' : '#ede9fe';
                                            $type_badge_color = $assign_type === 'absensi' ? '#166534' : '#5b21b6';
                                            $type_label = $assign_type === 'absensi' ? 'Absensi' : 'Tugas';
                                            ?>
                                            <span style="background:<?php echo $type_badge_bg; ?>; color:<?php echo $type_badge_color; ?>; padding:2px 8px; border-radius:4px; font-size:0.72rem; font-weight:700;"><?php echo $type_label; ?></span>
                                            <h4 style="margin: 6px 0 4px; font-size:1rem;"><?php echo htmlspecialchars($a['title']); ?></h4>
                                            <p style="font-size:0.85rem; color:var(--text-muted); margin:0;">
                                                <svg xmlns='http://www.w3.org/2000/svg' width='13' height='13' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' style='vertical-align:middle; margin-right:3px;'><circle cx='12' cy='12' r='10'/><polyline points='12 6 12 12 16 14'/></svg>
                                                Deadline: <?php echo date('d M Y, H:i', strtotime($a['deadline'])); ?>
                                            </p>
                                            <?php if ($a['attachment_path']):
                                                $is_link = (strpos($a['attachment_path'], 'http') === 0);
                                                $href = $is_link ? $a['attachment_path'] : '../../' . $a['attachment_path'];
                                                $label = $is_link ? "Buka Link / Google Form" : "Lihat Lampiran";
                                            ?>
                                            <a href="<?php echo htmlspecialchars($href); ?>" target="_blank" style="font-size:0.82rem; color:var(--primary); font-weight:600; display:inline-flex; align-items:center; gap:4px; margin-top:6px;">
                                                <svg width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><path d='m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48'/></svg> <?php echo $label; ?>
                                            </a>
                                            <?php endif; ?>
                                        </div>
                                        <div style="display:flex; gap:5px; flex-direction:column; align-items:flex-end; margin-left:12px; flex-shrink:0;">
                                            <div style="display:flex; gap:5px;">
                                                <button onclick='openEditModal(<?php echo json_encode($a); ?>)' class="btn" style="background:#3b82f6; padding:5px 10px; font-size:0.8rem; display:inline-flex; align-items:center; gap:4px;">
                                                    <svg xmlns='http://www.w3.org/2000/svg' width='13' height='13' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><path d='M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7'/><path d='M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z'/></svg> Edit
                                                </button>
                                                <a href="view_submissions.php?assignment_id=<?php echo $a['id']; ?>" class="btn" style="padding:5px 10px; font-size:0.8rem; display:inline-flex; align-items:center; gap:4px;">
                                                    <svg xmlns='http://www.w3.org/2000/svg' width='13' height='13' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><line x1='8' y1='6' x2='21' y2='6'/><line x1='8' y1='12' x2='21' y2='12'/><line x1='8' y1='18' x2='21' y2='18'/><line x1='3' y1='6' x2='3.01' y2='6'/><line x1='3' y1='12' x2='3.01' y2='12'/><line x1='3' y1='18' x2='3.01' y2='18'/></svg> Lihat Nilai
                                                </a>
                                            </div>
                                            <a href="manage_assignments.php?action=archive&id=<?php echo $a['id']; ?>" class="btn btn-secondary" style="font-size:0.78rem; padding:5px 10px; background:#fef3c7; color:#92400e; border:1px solid #fcd34d; display:inline-flex; align-items:center; gap:4px;" onclick="return confirm('Tarik tugas ini? Siswa tidak akan bisa melihatnya lagi.')">
                                                <svg xmlns='http://www.w3.org/2000/svg' width='13' height='13' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><polyline points='21 8 21 21 3 21 3 8'/><rect x='1' y='3' width='22' height='5'/><line x1='10' y1='12' x2='14' y2='12'/></svg> Arsipkan
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- ===== ARCHIVED ===== -->
                <div class="page-section" style="background: #f8fafc; border: 1px dashed #cbd5e1;">
                    <h3 style="color: #64748b; font-size: 1rem; margin-bottom: 1rem;">Tugas Diarsipkan / Ditarik</h3>
                    <?php if (empty($grouped_archived)): ?>
                        <p style="color: var(--text-muted); font-size: 0.9rem;">Belum ada tugas yang diarsipkan.</p>
                    <?php else: ?>
                        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                        <?php $arc_idx = 0; foreach ($grouped_archived as $class_name => $group): $arc_idx++; $arc_id = 'acc_arch_' . $arc_idx; ?>
                            <div style="border: 1px solid #cbd5e1; border-radius: 10px; overflow:hidden;">
                                <button onclick="toggleAccordion('<?php echo $arc_id; ?>')" style="width:100%; display:flex; justify-content:space-between; align-items:center; padding: 0.75rem 1rem; background: #e2e8f0; border:none; cursor:pointer; text-align:left;">
                                    <span style="font-size:0.9rem; font-weight:700; color:#475569;"><?php echo htmlspecialchars($class_name); ?></span>
                                    <div style="display:flex; align-items:center; gap:8px;">
                                        <span style="background:#94a3b8; color:#fff; padding:1px 7px; border-radius:20px; font-size:0.7rem; font-weight:700;"><?php echo count($group['assignments']); ?></span>
                                        <svg id="arrow_<?php echo $arc_id; ?>" xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='#64748b' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round' style='transition:transform 0.3s;'><polyline points='6 9 12 15 18 9'/></svg>
                                    </div>
                                </button>
                                <div id="<?php echo $arc_id; ?>">
                                <?php foreach ($group['assignments'] as $a): ?>
                                    <div style="padding: 0.75rem 1rem; display:flex; justify-content:space-between; align-items:center; background:#e2e8f0; border-top:1px solid #cbd5e1; opacity:0.85;">
                                        <div>
                                            <strong style="color:#475569; font-size:0.9rem;"><?php echo htmlspecialchars($a['title']); ?></strong>
                                            <br><small style="color:#94a3b8;">Deadline: <?php echo date('d M Y', strtotime($a['deadline'])); ?></small>
                                        </div>
                                        <div style="display:flex; gap:5px;">
                                            <a href="manage_assignments.php?action=restore&id=<?php echo $a['id']; ?>" class="btn btn-secondary" style="font-size:0.75rem; padding:4px 8px;">Restore</a>
                                            <a href="manage_assignments.php?action=delete&id=<?php echo $a['id']; ?>" class="btn btn-danger" style="font-size:0.75rem; padding:4px 8px;" onclick="return confirm('Hapus permanen?')">Hapus</a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Create Form -->
            <div class="page-section" style="height: fit-content;">
                <h3>Buat Tugas Baru</h3>
                <?php
if (isset($_SESSION['flash'])) {
    $flash = $_SESSION['flash'];
    $bg = '#dcfce7';
    $color = '#166534'; // Success
    if ($flash['type'] == 'error') {
        $bg = '#fee2e2';
        $color = '#991b1b';
    }
    echo "<div style='background:$bg; color:$color; padding:10px; border-radius:8px; margin-bottom:15px; font-size:0.9rem;'>
                            " . htmlspecialchars($flash['message']) . "
                          </div>";
    unset($_SESSION['flash']);
}
?>
                
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="add_assignment" value="1">
                    
                    <div class="form-group">
                        <label>Judul Tugas</label>
                        <input type="text" name="title" required placeholder="Contoh: Latihan Soal Bab 1">
                    </div>

                    <div class="form-group">
                        <label>Target Kelas</label>
                        <select name="class_ids[]" required>
                            <option value="">-- Pilih Kelas --</option>
                            <?php foreach ($classes as $c): ?>
                                <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                            <?php
endforeach; ?>
                        </select>
                        <small style="color: var(--text-muted);">Pilih kelas untuk tugas ini.</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Deskripsi / Instruksi</label>
                        <textarea name="description" rows="4" required placeholder="Jelaskan detail tugas..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Tipe Lampiran</label>
                        <div class="radio-group" style="display: flex; gap: 12px; margin-top: 5px; margin-bottom: 10px;">
                            <label style="display: flex; align-items: center; gap: 6px; cursor: pointer; font-size: 0.85rem; font-weight: 500; padding: 8px 16px; border-radius: 8px; border: 1px solid #e2e8f0;"><input type="radio" name="a_type" value="file" checked onclick="toggleManageAssignmentInput('file')" style="accent-color: #4f46e5;"><span> Upload File</span></label>
                            <label style="display: flex; align-items: center; gap: 6px; cursor: pointer; font-size: 0.85rem; font-weight: 500; padding: 8px 16px; border-radius: 8px; border: 1px solid #e2e8f0;"><input type="radio" name="a_type" value="link" onclick="toggleManageAssignmentInput('link')" style="accent-color: #4f46e5;"><span> Link (Google Form, dll)</span></label>
                        </div>
                    </div>
                    
                    <div id="manage-assign-file" class="form-group">
                        <label>Lampiran (PDF, Word, PPT, Excel, dll)</label>
                        <input type="file" name="attachment" accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.epub,.mp4,.avi,.mov,.jpg,.png,.zip" style="width: 100%; padding: 10px 14px; border: 1px solid #e2e8f0; border-radius: 10px; font-size: 0.88rem; background: #f8fafc;">
                    </div>

                    <div id="manage-assign-link" class="form-group" style="display:none;">
                        <label>URL Link Pengumpulan / Tugas (Contoh: Google Form)</label>
                        <input type="url" name="a_link_url" placeholder="https://forms.gle/..." style="width: 100%; padding: 10px 14px; border: 1px solid #e2e8f0; border-radius: 10px; font-size: 0.88rem; background: #f8fafc;">
                    </div>
                    
                    <div class="form-group">
                        <label>Batas Waktu (Deadline)</label>
                        <input type="datetime-local" name="deadline" required>
                    </div>
                    
                    <button type="submit" class="btn" style="width: 100%;">Terbitkan Tugas</button>
                </form>
            </div>
        </div>
        </div>
    </main>
</div>

<!-- Ã¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢Â Edit Assignment Modal Ã¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢Â -->
<div id="editAssignmentModal" class="modal-overlay" style="display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5); backdrop-filter:blur(4px);">
    <div class="modal-box" style="background:#fff; margin:2% auto; padding:2rem; border-radius:18px; width:90%; max-width:600px; position:relative; max-height: 90vh; overflow-y: auto;">
        <button onclick="document.getElementById('editAssignmentModal').style.display='none'" style="position:absolute; right:20px; top:20px; font-size:1.5rem; background:none; border:none; cursor:pointer;">&times;</button>
        <h2 style="margin-bottom:1.5rem;">Edit Tugas / Ujian</h2>
        
        <form method="POST">
            <input type="hidden" name="edit_assignment" value="1">
            <input type="hidden" name="assignment_id" id="edit_id">
            
            <div class="form-group">
                <label>Judul</label>
                <input type="text" name="title" id="edit_title" required>
            </div>
            
            <div class="form-group">
                <label>Deskripsi</label>
                <textarea name="description" id="edit_desc" rows="3" required></textarea>
            </div>
            
            <div class="form-group">
                <label>Deadline</label>
                <input type="datetime-local" name="deadline" id="edit_deadline" required>
            </div>
            
            <button type="submit" class="btn-submit">Simpan Perubahan</button>
        </form>

        <hr style="margin: 2rem 0; border: 0; border-top: 1px solid #e2e8f0;">
        
        <h3 style="color: #475569; font-size: 1rem; margin-bottom: 1rem;">Aksi Lanjutan</h3>
        
        <div style="display: flex; flex-direction: column; gap: 10px;">
            <a href="#" id="downloadZipLink" class="btn btn-secondary" style="text-align:center; background:#ecfccb; color:#365314; border:1px solid #d9f99d; display: flex; align-items: center; justify-content: center; gap: 8px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg> Download Semua Jawaban (ZIP)
            </a>

            <form method="POST" onsubmit="return confirm('RESET PERFORMA KELAS?\n\nSemua jawaban siswa yang ada saat ini akan diarsipkan (disembunyikan). \nTugas ini akan dianggap baru untuk siswa.\n\nPastikan Anda sudah mendownload rekap nilai/file sebelum melakukan ini!');">
                <input type="hidden" name="reset_submissions" value="1">
                <input type="hidden" name="assignment_id" id="reset_id_input">
                <button type="submit" class="btn-submit" style="background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; display: flex; align-items: center; justify-content: center; gap: 8px;">
                     <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg> Reset / Arsipkan Jawaban (Mulai Semester Baru)
                </button>
            </form>
        </div>
    </div>
</div>

<script>
function openEditModal(data) {
    document.getElementById('editAssignmentModal').style.display = 'block';
    document.getElementById('edit_id').value = data.id;
    document.getElementById('reset_id_input').value = data.id;
    document.getElementById('edit_title').value = data.title;
    document.getElementById('edit_desc').value = data.description;
    
    // Format datetime-local: YYYY-MM-DDTHH:MM
    const date = new Date(data.deadline);
    const offset = date.getTimezoneOffset();
    const local = new Date(date.getTime() - offset * 60000);
    document.getElementById('edit_deadline').value = local.toISOString().slice(0, 16);
    
    // Set Download Link
    document.getElementById('downloadZipLink').href = 'download_zip.php?assignment_id=' + data.id;
}

function toggleManageAssignmentInput(type) {
    document.getElementById('manage-assign-file').style.display = type === 'file' ? 'block' : 'none';
    document.getElementById('manage-assign-link').style.display = type === 'link' ? 'block' : 'none';
}

// Accordion: toggle open/close
function toggleAccordion(id) {
    const body = document.getElementById(id);
    const arrow = document.getElementById('arrow_' + id);
    const isOpen = body.style.display === 'flex';
    body.style.display = isOpen ? 'none' : 'flex';
    body.style.flexDirection = isOpen ? '' : 'column';
    if (arrow) arrow.style.transform = isOpen ? 'rotate(-90deg)' : 'rotate(0deg)';
}
</script>

</body>
</html>


