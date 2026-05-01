<?php
// src/guru/dashboard.php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guru') {
    header("Location: ../../login.php");
    exit;
}

$teacher_id = $_SESSION['user_id'];
$success = "";
$error = "";

// â”€â”€â”€ Handle Add Assignment (Quick Action) â”€â”€â”€
// â”€â”€â”€ Handle Add Assignment (Quick Action) â”€â”€â”€
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quick_add_assignment'])) {
    $target_class_id = $_POST['class_id']; // This is teacher_classes.id
    $title = trim($_POST['a_title']);
    $description = trim($_POST['a_description'] ?? '');
    $deadline = $_POST['a_deadline'];

    // Fetch class details to get real class_id and subject_id
    $stmt = $pdo->prepare("SELECT class_id, subject_id FROM teacher_classes WHERE id = ? AND teacher_id = ?");
    $stmt->execute([$target_class_id, $teacher_id]);
    $class_info = $stmt->fetch();

    if ($class_info) {
        $attachment_path = null;
        if (isset($_FILES['a_attachment']) && $_FILES['a_attachment']['error'] == 0) {
            $upload_dir = '../../public/uploads/assignments/';
            if (!file_exists($upload_dir))
                mkdir($upload_dir, 0777, true);
            $file_name = time() . '_' . basename($_FILES['a_attachment']['name']);
            if (move_uploaded_file($_FILES['a_attachment']['tmp_name'], $upload_dir . $file_name)) {
                $attachment_path = 'public/uploads/assignments/' . $file_name;
            }
        }

        $stmt = $pdo->prepare("INSERT INTO assignments (teacher_id, title, description, deadline, attachment_path, status, assignment_type, teacher_class_id, subject_id) VALUES (?, ?, ?, ?, ?, 'active', 'tugas', ?, ?)");
        if ($stmt->execute([$teacher_id, $title, $description, $deadline, $attachment_path, $target_class_id, $class_info['subject_id']])) {
            $assignment_id = $pdo->lastInsertId();
            $pdo->prepare("INSERT INTO assignment_classes (assignment_id, class_id) VALUES (?, ?)")->execute([$assignment_id, $class_info['class_id']]);
            $_SESSION['flash'] = ['type' => 'success', 'message' => "Tugas berhasil dibuat untuk kelas terpilih!"];
        }
        else {
            $_SESSION['flash'] = ['type' => 'error', 'message' => "Gagal membuat tugas."];
        }
    }
    else {
        $_SESSION['flash'] = ['type' => 'error', 'message' => "Kelas tidak valid."];
    }
    header("Location: dashboard.php");
    exit;
}

// â”€â”€â”€ Handle Add Material (Quick Action) â”€â”€â”€
// â”€â”€â”€ Handle Add Material (Quick Action) â”€â”€â”€
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quick_add_material'])) {
    $target_class_id = $_POST['class_id'];
    $title = trim($_POST['m_title']);
    $description = trim($_POST['m_description']);
    $type = $_POST['m_type'];

    // Fetch class details
    $stmt = $pdo->prepare("SELECT class_id, subject_id FROM teacher_classes WHERE id = ? AND teacher_id = ?");
    $stmt->execute([$target_class_id, $teacher_id]);
    $class_info = $stmt->fetch();

    if ($class_info) {
        $file_path = null;
        $material_type = 'pdf'; // Default
        $error_msg = "";

        if ($type === 'file') {
            if (isset($_FILES['m_file']) && $_FILES['m_file']['error'] == 0) {
                if ($_FILES['m_file']['size'] > 20 * 1024 * 1024) {
                    $error_msg = "Ukuran file terlalu besar (Max 20MB).";
                }
                else {
                    $target_dir = "../../public/uploads/materials/";
                    if (!file_exists($target_dir))
                        mkdir($target_dir, 0777, true);
                    $file_name = time() . '_' . basename($_FILES["m_file"]["name"]);
                    $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

                    if (in_array($ext, ['mp4', 'avi', 'mov']))
                        $material_type = 'video';
                    elseif (in_array($ext, ['doc', 'docx']))
                        $material_type = 'word';
                    elseif (in_array($ext, ['ppt', 'pptx']))
                        $material_type = 'ppt';
                    elseif ($ext === 'epub')
                        $material_type = 'epub';

                    if (move_uploaded_file($_FILES["m_file"]["tmp_name"], $target_dir . $file_name)) {
                        $file_path = "public/uploads/materials/" . $file_name;
                    }
                    else {
                        $error_msg = "Gagal upload file.";
                    }
                }
            }
            else {
                $error_msg = "File wajib diupload.";
            }
        }
        elseif ($type === 'link') {
            $link_url = trim($_POST['m_link_url']);
            if (!empty($link_url)) {
                $file_path = $link_url;
                $material_type = 'link';
            }
            else {
                $error_msg = "Link URL wajib diisi.";
            }
        }

        if (empty($error_msg) && $file_path) {
            $stmt = $pdo->prepare("INSERT INTO materials (title, description, type, file_path, teacher_id, teacher_class_id, class_id, subject_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            if ($stmt->execute([$title, $description, $material_type, $file_path, $teacher_id, $target_class_id, $class_info['class_id'], $class_info['subject_id']])) {
                $_SESSION['flash'] = ['type' => 'success', 'message' => "Materi berhasil ditambahkan!"];
            }
            else {
                $_SESSION['flash'] = ['type' => 'error', 'message' => "Database error."];
            }
        }
        else {
            if (!empty($error_msg)) {
                $_SESSION['flash'] = ['type' => 'error', 'message' => $error_msg];
            }
        }
    }
    else {
        $_SESSION['flash'] = ['type' => 'error', 'message' => "Kelas tidak valid."];
    }
    header("Location: dashboard.php");
    exit;
}

$gender = $_SESSION['gender'] ?? '';
$sapaan = "Bapak/Ibu";
if ($gender === 'L')
    $sapaan = "Bapak";
elseif ($gender === 'P')
    $sapaan = "Ibu";

$hour = (int)date('H');
if ($hour < 11)
    $greeting = "Selamat Pagi";
elseif ($hour < 15)
    $greeting = "Selamat Siang";
elseif ($hour < 18)
    $greeting = "Selamat Sore";
else
    $greeting = "Selamat Malam";

// Determine assignment with most ungraded (or just the oldest one) &mdash; exclude attendance
$stmt = $pdo->prepare("
    SELECT a.id, COUNT(*) as count 
    FROM submissions s 
    JOIN assignments a ON s.assignment_id = a.id 
    WHERE a.teacher_id = ? AND s.grade IS NULL AND a.assignment_type != 'absensi'
    GROUP BY a.id, a.created_at
    ORDER BY count DESC, a.created_at ASC 
    LIMIT 1
");
$stmt->execute([$teacher_id]);
$priority_assignment = $stmt->fetch();
$priority_assignment_id = $priority_assignment ? $priority_assignment['id'] : null;

// Counts
// Counts - Only count materials from existing teacher classes
$stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM materials m
    JOIN teacher_classes tc ON m.teacher_class_id = tc.id
    WHERE m.teacher_id = ?
");
$stmt->execute([$teacher_id]);
$my_materials = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM assignments WHERE teacher_id = ? AND deadline > NOW() AND assignment_type != 'absensi'");
$stmt->execute([$teacher_id]);
$active_assignments = $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM submissions s 
    JOIN assignments a ON s.assignment_id = a.id 
    JOIN teacher_classes tc ON a.teacher_class_id = tc.id
    WHERE a.teacher_id = ? AND a.assignment_type != 'absensi'
");
$stmt->execute([$teacher_id]);
$total_submissions = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM teacher_classes WHERE teacher_id = ?");
$stmt->execute([$teacher_id]);
$my_classes_count = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM submissions s JOIN assignments a ON s.assignment_id = a.id WHERE a.teacher_id = ? AND s.grade IS NULL AND a.assignment_type != 'absensi'");
$stmt->execute([$teacher_id]);
$ungraded = $stmt->fetchColumn();

// Fetch Recent Classes
$stmt = $pdo->prepare("
    SELECT tc.*, c.name as school_class_name,
    (SELECT COUNT(*) FROM users u WHERE u.class_id = tc.class_id AND u.role = 'siswa') as student_count
    FROM teacher_classes tc JOIN classes c ON tc.class_id = c.id
    WHERE tc.teacher_id = ? ORDER BY tc.created_at DESC LIMIT 5
");
$stmt->execute([$teacher_id]);
$recent_classes = $stmt->fetchAll();

// Fetch All Classes for Dropdowns
$stmt = $pdo->prepare("SELECT id, name, subject FROM teacher_classes WHERE teacher_id = ? ORDER BY created_at DESC");
$stmt->execute([$teacher_id]);
$dropdown_classes = $stmt->fetchAll();

$days = ['Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'];
$months = ['January' => 'Januari', 'February' => 'Februari', 'March' => 'Maret', 'April' => 'April', 'May' => 'Mei', 'June' => 'Juni', 'July' => 'Juli', 'August' => 'Agustus', 'September' => 'September', 'October' => 'Oktober', 'November' => 'November', 'December' => 'Desember'];
$dayName = $days[date('l')] ?? date('l');
$monthName = $months[date('F')] ?? date('F');
$dateStr = $dayName . ', ' . date('d') . ' ' . $monthName . ' ' . date('Y');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta charset="UTF-8">
    <title>Dashboard Guru</title>
    <link rel="stylesheet" href="/public/assets/css/style.css">

</head>
<body>

<div class="app-container">
    <?php include '../templates/sidebar.php'; ?>
    
    <main class="main-content">

        <!-- Hero -->
        <!-- Hero -->
        <div class="page-toolbar">
            <div class="page-toolbar-left">
                <h1 class="page-title">Dashboard Guru</h1>
                <p class="page-subtitle">Ringkasan aktivitas mengajar Anda</p>
            </div>
        </div>
        <div class="page-content">
            <?php
if (isset($_SESSION['flash'])) {
    $flash = $_SESSION['flash'];
    $bg = '#f0fdf4';
    $color = '#166534';
    $border = '#bbf7d0'; // Success default
    if ($flash['type'] == 'error') {
        $bg = '#fef2f2';
        $color = '#991b1b';
        $border = '#fecaca';
    }
    echo "<div style='background:$bg; color:$color; padding:16px; border-radius:12px; margin-bottom:24px; border:1px solid $border; box-shadow:0 4px 6px -1px rgba(0,0,0,0.05);'>
                        " . ($flash['type'] == 'error' ? "<svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' style='display:inline-block; vertical-align:middle; line-height:1;'><path d=\"m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z\"/><line x1=\"12\" y1=\"9\" x2=\"12\" y2=\"13\"/><line x1=\"12\" y1=\"17\" x2=\"12.01\" y2=\"17\"/></svg> " : "<svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' style='display:inline-block; vertical-align:middle; line-height:1;'><polyline points=\"20 6 9 17 4 12\"/></svg> ") . htmlspecialchars($flash['message']) . "
                      </div>";
    unset($_SESSION['flash']);
}
?>

            <!-- Stats -->
            <div class="db-stats">
                <div class="db-stat c-blue">
                    <div class="num"><?php echo $my_classes_count; ?></div>
                    <div class="lbl">Kelas Saya</div>
                </div>
                <div class="db-stat c-violet">
                    <div class="num"><?php echo $my_materials; ?></div>
                    <div class="lbl">Materi</div>
                </div>
                <div class="db-stat c-amber">
                    <div class="num"><?php echo $active_assignments; ?></div>
                    <div class="lbl">Tugas Aktif</div>
                </div>
                <div class="db-stat c-green">
                    <div class="num"><?php echo $total_submissions; ?></div>
                    <div class="lbl">Tugas Masuk</div>
                </div>
            </div>

            <!-- Alert -->
            <?php if ($ungraded > 0): ?>
            <div class="db-alert">
                <span style="font-size:1.2rem;"><svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' style='display:inline-block; vertical-align:middle; line-height:1;'><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></span>
                <span>Ada <strong><?php echo $ungraded; ?> tugas</strong> siswa yang belum dinilai.</span>
                <?php if ($priority_assignment_id): ?>
                    <a href="view_submissions.php?assignment_id=<?php echo $priority_assignment_id; ?>">Nilai Sekarang &rarr;</a>
                <?php
    else: ?>
                    <a href="kelas.php">Lihat Kelas &rarr;</a>
                <?php
    endif; ?>
            </div>
            <?php
endif; ?>

            <!-- Grid -->
            <div class="db-grid">

                <!-- Quick Actions -->
                <div class="db-panel">
                    <h3>Menu Cepat</h3>
                    <div class="qa-list">
                        <a href="kelas.php" class="qa-item">
                            <div class="qa-ico"><svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' style='display:inline-block; vertical-align:middle; line-height:1;'><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg></div>
                            <div>
                                <div class="qa-title">Kelas Saya</div>
                                <div class="qa-desc">Kelola kelas dan lihat daftar siswa</div>
                            </div>
                            <span class="qa-arrow">&rsaquo;</span>
                        </a>
                        <!-- Upload Material Trigger -->
                        <div class="qa-item" onclick="openModal('addMaterialModal')">
                            <div class="qa-ico"><svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' style='display:inline-block; vertical-align:middle; line-height:1;'><path d='M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z'/></svg></div>
                            <div>
                                <div class="qa-title">Upload Materi</div>
                                <div class="qa-desc">Bagikan modul atau video ke kelas</div>
                            </div>
                            <span class="qa-arrow">+</span>
                        </div>
                        <!-- Add Assignment Trigger -->
                        <div class="qa-item" onclick="openModal('addAssignmentModal')">
                            <div class="qa-ico"><svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' style='display:inline-block; vertical-align:middle; line-height:1;'><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg></div>
                            <div>
                                <div class="qa-title">Buat Tugas</div>
                                <div class="qa-desc">Berikan tugas baru untuk siswa</div>
                            </div>
                            <span class="qa-arrow">+</span>
                        </div>
                        <a href="jadwal_mengajar.php" class="qa-item">
                            <div class="qa-ico"><svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' style='display:inline-block; vertical-align:middle; line-height:1;'><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>
                            <div>
                                <div class="qa-title">Jadwal Mengajar</div>
                                <div class="qa-desc">Lihat jadwal pelajaran Anda</div>
                            </div>
                            <span class="qa-arrow">&rsaquo;</span>
                        </a>
                        <a href="../profile.php" class="qa-item">
                            <div class="qa-ico"><svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' style='display:inline-block; vertical-align:middle; line-height:1;'><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></div>
                            <div>
                                <div class="qa-title">Profil Saya</div>
                                <div class="qa-desc">Ubah data diri dan password</div>
                            </div>
                            <span class="qa-arrow">&rsaquo;</span>
                        </a>
                    </div>
                </div>

                <!-- Recent Classes -->
                <div class="db-panel">
                    <h3>Kelas Terakhir</h3>
                    <?php if (empty($recent_classes)): ?>
                        <div class="cls-empty">
                            <p style="font-size:2.5rem; margin-bottom:10px;"><svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' style='display:inline-block; vertical-align:middle; line-height:1;'><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg></p>
                            <p style="margin-bottom:8px;">Belum ada kelas yang dibuat.</p>
                            <a href="kelas.php">+ Buat Kelas Pertama</a>
                        </div>
                    <?php
else: ?>
                        <?php
    $avatarColors = ['#4f46e5', '#0891b2', '#d97706', '#059669', '#db2777'];
    foreach ($recent_classes as $i => $rc):
        $initials = strtoupper(mb_substr($rc['name'], 0, 2));
        $bg = $avatarColors[$i % count($avatarColors)];
?>
                        <a href="view_class.php?id=<?php echo $rc['id']; ?>" class="cls-row">
                            <div class="cls-av" style="background:<?php echo $bg; ?>"><?php echo $initials; ?></div>
                            <div style="flex:1; min-width:0;">
                                <div class="cls-name"><?php echo htmlspecialchars($rc['name']); ?></div>
                                <div class="cls-subj"><?php echo htmlspecialchars($rc['subject']); ?></div>
                            </div>
                            <div class="cls-badge"><svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' style='display:inline-block; vertical-align:middle; line-height:1;'><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg> <?php echo $rc['student_count']; ?> siswa</div>
                        </a>
                        <?php
    endforeach; ?>
                        <?php if ($my_classes_count > 5): ?>
                            <a href="kelas.php" class="view-all-link">Lihat Semua Kelas &rarr;</a>
                        <?php
    endif; ?>
                    <?php
endif; ?>
                </div>
            </div>

        </div>
        </div>
    </main>
</div>

<!-- ================= MODALS ================= -->

<!-- Add Assignment Modal -->
<div id="addAssignmentModal" class="modal">
    <div class="modal-content">
        <div class="close" onclick="closeModal('addAssignmentModal')">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:20px;height:20px;">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </div>
        <h2 style="margin-bottom: 0.5rem; color:#1e293b;"><svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' style='display:inline-block; vertical-align:middle; line-height:1;'><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg> Buat Tugas Baru</h2>
        <p style="color:#64748b; margin-bottom: 1.5rem;">Tugas akan muncul di halaman siswa.</p>
        
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="quick_add_assignment" value="1">
            
            <div class="form-group">
                <label>Pilih Kelas</label>
                <select name="class_id" required>
                    <option value="">-- Pilih Kelas Tujuan --</option>
                    <?php foreach ($dropdown_classes as $dc): ?>
                        <option value="<?php echo $dc['id']; ?>"><?php echo htmlspecialchars($dc['name']) . ' - ' . htmlspecialchars($dc['subject']); ?></option>
                    <?php
endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Judul Tugas</label>
                <input type="text" name="a_title" required placeholder="Contoh: Analisis Ekosistem">
            </div>

            <div class="form-group">
                <label>Deskripsi / Instruksi</label>
                <textarea name="a_description" rows="4" placeholder="Jelaskan detail tugas di sini..."></textarea>
            </div>

            <div class="form-group">
                <label>Tenggat Waktu (Deadline)</label>
                <input type="datetime-local" name="a_deadline" required>
            </div>

            <div class="form-group">
                <label>Lampiran File (Opsional)</label>
                <input type="file" name="a_attachment">
                <small style="color:#64748b;">PDF, Word, Excel, Gambar (Max 10MB)</small>
            </div>

            <div style="display: flex; gap: 12px; margin-top: 1.5rem;">
                <button type="button" class="btn" onclick="closeModal('addAssignmentModal')" style="flex: 1; background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0;">Batal</button>
                <button type="submit" class="btn" style="flex: 2;"><svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' style='display:inline-block; vertical-align:middle; line-height:1;'><path d='M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09z'/><path d='m12 15-3-3a22 22 0 0 1 2-3.95A12.88 12.88 0 0 1 22 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 0 1-4 2z'/><path d='M9 12H4s.55-3.03 2-4c1.62-1.08 5 0 5 0'/><path d='M12 15v5s3.03-.55 4-2c1.08-1.62 0-5 0-5'/></svg> Terbitkan Tugas</button>
            </div>
        </form>
    </div>
</div>

<!-- Add Material Modal -->
<div id="addMaterialModal" class="modal">
    <div class="modal-content">
        <div class="close" onclick="closeModal('addMaterialModal')">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:20px;height:20px;">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </div>
        <h2 style="margin-bottom: 0.5rem; color:#1e293b;"><svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' style='display:inline-block; vertical-align:middle; line-height:1;'><path d='M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z'/></svg> Upload Materi Belajar</h2>
        <p style="color:#64748b; margin-bottom: 1.5rem;">Bagikan bahan ajar ke kelas Anda.</p>
        
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="quick_add_material" value="1">
            
            <div class="form-group">
                <label>Pilih Kelas</label>
                <select name="class_id" required>
                    <option value="">-- Pilih Kelas Tujuan --</option>
                    <?php foreach ($dropdown_classes as $dc): ?>
                        <option value="<?php echo $dc['id']; ?>"><?php echo htmlspecialchars($dc['name']) . ' - ' . htmlspecialchars($dc['subject']); ?></option>
                    <?php
endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Judul Materi</label>
                <input type="text" name="m_title" required placeholder="Contoh: Modul Bab 1 - Pengantar">
            </div>

            <div class="form-group">
                <label>Deskripsi Singkat</label>
                <textarea name="m_description" rows="3" placeholder="Deskripsi materi..."></textarea>
            </div>

            <div class="form-group">
                <label>Tipe Materi</label>
                <select name="m_type" onchange="toggleMaterialInput(this.value)">
                    <option value="file"><svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' style='display:inline-block; vertical-align:middle; line-height:1;'><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg> Upload File (PDF/Word/PPT)</option>
                    <option value="link"><svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' style='display:inline-block; vertical-align:middle; line-height:1;'><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg> Link (Youtube/Drive/Zoom)</option>
                </select>
            </div>

            <div id="material-file-input" class="form-group">
                <label>Upload File</label>
                <input type="file" name="m_file">
                <small style="color:#64748b;">Max 20MB</small>
            </div>

            <div id="material-link-input" class="form-group" style="display:none;">
                <label>Paste Link URL</label>
                <input type="url" name="m_link_url" placeholder="https://...">
            </div>

            <div style="display: flex; gap: 12px; margin-top: 1.5rem;">
                <button type="button" class="btn" onclick="closeModal('addMaterialModal')" style="flex: 1; background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0;">Batal</button>
                <button type="submit" class="btn" style="flex: 2;"><svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' style='display:inline-block; vertical-align:middle; line-height:1;'><path d='M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4'/><polyline points='17 8 12 3 7 8'/><line x1='12' y1='3' x2='12' y2='15'/></svg> Upload Materi</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openModal(id) {
        document.getElementById(id).style.display = 'block';
    }
    function closeModal(id) {
        document.getElementById(id).style.display = 'none';
    }
    function toggleMaterialInput(val) {
        if(val === 'link') {
            document.getElementById('material-file-input').style.display = 'none';
            document.getElementById('material-link-input').style.display = 'block';
        } else {
            document.getElementById('material-file-input').style.display = 'block';
            document.getElementById('material-link-input').style.display = 'none';
        }
    }
    window.onclick = function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.style.display = "none";
        }
    }
</script>

</body>
</html>

