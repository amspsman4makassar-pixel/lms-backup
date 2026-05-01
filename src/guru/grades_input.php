<?php
// src/guru/grades_input.php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guru') {
    header("Location: ../../login.php");
    exit;
}

$teacher_id = $_SESSION['user_id'];
$class_id = $_GET['class_id'] ?? null;

if (!$class_id) {
    header("Location: grades.php");
    exit;
}

// Fetch class info
$stmt = $pdo->prepare("SELECT tc.*, c.name as school_class_name FROM teacher_classes tc LEFT JOIN classes c ON tc.class_id = c.id WHERE tc.id = ? AND tc.teacher_id = ?");
$stmt->execute([$class_id, $teacher_id]);
$class_info = $stmt->fetch();

if (!$class_info) {
    die("Akses ditolak atau kelas tidak ditemukan.");
}

$success = "";
$error = "";

$academic_year = $_GET['academic_year'] ?? '2025-2026';
$semester = $_GET['semester'] ?? 'Ganjil';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_grades'])) {
    $academic_year = $_POST['academic_year'];
    $semester = $_POST['semester'];
    $grades = $_POST['grades'] ?? [];
    
    $stmt = $pdo->prepare("
        INSERT INTO student_grades (student_id, teacher_id, subject_id, class_id, academic_year, semester, grade) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE grade = VALUES(grade), updated_at = NOW()
    ");

    $pdo->beginTransaction();
    try {
        $real_class_id = $class_info['class_id'] ? $class_info['class_id'] : 0;
        foreach ($grades as $sid => $val) {
            if ($val === '') continue; 
            $stmt->execute([
                $sid, 
                $teacher_id, 
                $class_info['subject_id'], 
                $real_class_id, 
                $academic_year, 
                $semester, 
                $val
            ]);
        }
        $pdo->commit();
        $success = "Data nilai berhasil disimpan!";
    } catch(Exception $e) {
        $pdo->rollBack();
        $error = "Terjadi kesalahan sistem: " . $e->getMessage();
    }
}

// Fetch Students
if ($class_info['is_special_class']) {
    $stmt = $pdo->prepare("SELECT u.id, u.full_name, u.nis FROM class_members cm JOIN users u ON cm.student_id = u.id WHERE cm.teacher_class_id = ? ORDER BY u.full_name ASC");
    $stmt->execute([$class_id]);
} else {
    $stmt = $pdo->prepare("SELECT id, full_name, nis FROM users WHERE class_id = ? AND role = 'siswa' ORDER BY full_name ASC");
    $stmt->execute([$class_info['class_id']]);
}
$students = $stmt->fetchAll();

// Fetch existing grades
$stmt = $pdo->prepare("SELECT student_id, grade FROM student_grades WHERE teacher_id = ? AND subject_id = ? AND academic_year = ? AND semester = ?");
$stmt->execute([$teacher_id, $class_info['subject_id'], $academic_year, $semester]);
$existing_grades = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta charset="UTF-8">
    <title>Input Nilai - <?php echo htmlspecialchars($class_info['name']); ?></title>
    <link rel="stylesheet" href="/public/assets/css/style.css">

</head>
<body>

<div class="app-container">
    <?php include '../templates/sidebar.php'; ?>
    
    <main class="main-content">
        <?php if ($success): ?>
            <div style="background: #dcfce7; border: 1px solid #bbf7d0; color: #166534; padding: 16px; border-radius: 12px; margin-bottom: 24px; font-weight: 600;">
                &#10003; <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div style="background: #fee2e2; border: 1px solid #fecaca; color: #991b1b; padding: 16px; border-radius: 12px; margin-bottom: 24px; font-weight: 600;">
                &#9888;ï¸ <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="page-toolbar">
            <div class="page-toolbar-left">
                <h1 class="page-title">Input Nilai - <?php echo htmlspecialchars($class_info['name']); ?></h1>
                <p class="page-subtitle"><?php echo htmlspecialchars($class_info['subject']); ?> &middot; <?php echo $class_info['is_special_class'] ? 'Siswa Gabungan (Lintas Kelas)' : htmlspecialchars($class_info['school_class_name']); ?> &middot; <?php echo count($students); ?> Siswa</p>
            </div>
            <div class="page-toolbar-right">
                <a href="grades.php" class="btn btn-secondary btn-sm">&larr; Kembali</a>
                <a href="grades_import.php?class_id=<?php echo $class_id; ?>" class="btn btn-sm" style="background: #10b981; color: white; border: none;">&darr; Import Excel / CSV</a>
            </div>
        </div>

        <div class="page-content">
        
        <div class="page-section" style="margin-bottom: 20px;">
            <form method="GET" action="" id="filterForm" class="filter-bar" style="margin:0;">
                <input type="hidden" name="class_id" value="<?php echo $class_id; ?>">
                <span style="font-size: 0.85rem; font-weight: 600; color: var(--text-secondary);">Tahun Ajaran:</span>
                <select name="academic_year" class="filter-input" onchange="document.getElementById('filterForm').submit()">
                    <option value="2023-2024" <?php if($academic_year=='2023-2024') echo 'selected'; ?>>2023-2024</option>
                    <option value="2024-2025" <?php if($academic_year=='2024-2025') echo 'selected'; ?>>2024-2025</option>
                    <option value="2025-2026" <?php if($academic_year=='2025-2026') echo 'selected'; ?>>2025-2026</option>
                    <option value="2026-2027" <?php if($academic_year=='2026-2027') echo 'selected'; ?>>2026-2027</option>
                </select>
                <span style="font-size: 0.85rem; font-weight: 600; color: var(--text-secondary); margin-left: 10px;">Semester:</span>
                <select name="semester" class="filter-input" onchange="document.getElementById('filterForm').submit()">
                    <option value="Ganjil" <?php if($semester=='Ganjil') echo 'selected'; ?>>Ganjil</option>
                    <option value="Genap" <?php if($semester=='Genap') echo 'selected'; ?>>Genap</option>
                </select>
            </form>
        </div>

        <form method="POST" action="">
            <input type="hidden" name="academic_year" value="<?php echo htmlspecialchars($academic_year); ?>">
            <input type="hidden" name="semester" value="<?php echo htmlspecialchars($semester); ?>">
            
            <div class="page-table-wrap">
                <table class="page-table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>NIS</th>
                            <th>Nama Siswa</th>
                            <th>Nilai Akhir</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($students)): ?>
                            <tr><td colspan="4" style="text-align: center; padding: 40px; color: #64748b;">Belum ada siswa terdaftar di kelas ini.</td></tr>
                        <?php else: ?>
                            <?php foreach ($students as $index => $s): 
                                $current_val = $existing_grades[$s['id']] ?? '';
                            ?>
                            <tr>
                                <td style="text-align: center; color: #94a3b8;"><?php echo $index + 1; ?></td>
                                <td style="color: #64748b;"><?php echo htmlspecialchars($s['nis'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($s['full_name']); ?></td>
                                <td style="text-align: center;">
                                    <input type="number" step="0.01" min="0" max="100" name="grades[<?php echo $s['id']; ?>]" value="<?php echo htmlspecialchars((string)$current_val); ?>" class="filter-input" style="width:100px; text-align:center;" placeholder="0-100">
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if (!empty($students)): ?>
            <div class="table-footer" style="justify-content: flex-end; margin-top: 20px;">
                <button type="submit" name="save_grades" class="btn">
                    Simpan Nilai
                </button>
            </div>
            <?php endif; ?>
        </form>
        </div>

    </main>
</div>

</body>
</html>

