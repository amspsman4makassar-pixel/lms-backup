<?php
// src/guru/grades_import.php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guru') {
    header("Location: ../../login.php");
    exit;
}

$teacher_id = $_SESSION['user_id'];
$class_id = $_GET['class_id'] ?? ($_POST['class_id'] ?? null);

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

// Fetch Students
if ($class_info['is_special_class']) {
    $stmt = $pdo->prepare("SELECT u.id, u.full_name, u.nis FROM class_members cm JOIN users u ON cm.student_id = u.id WHERE cm.teacher_class_id = ? ORDER BY u.full_name ASC");
    $stmt->execute([$class_id]);
} else {
    $stmt = $pdo->prepare("SELECT id, full_name, nis FROM users WHERE class_id = ? AND role = 'siswa' ORDER BY full_name ASC");
    $stmt->execute([$class_info['class_id']]);
}
$students = $stmt->fetchAll();

$student_map = []; // Map NIS -> ID for easy lookup during import
foreach ($students as $s) {
    if (!empty($s['nis'])) {
        $student_map[$s['nis']] = $s['id'];
    }
}

// Handle Template Download
if (isset($_GET['action']) && $_GET['action'] === 'download_template') {
    $filename = "Template_Nilai_" . preg_replace('/[^A-Za-z0-9]/', '_', $class_info['name']) . ".csv";
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    // Write BOM for Excel UTF-8 support
    fputs($output, "\xEF\xBB\xBF");
    // Write headers
    fputcsv($output, ['NIS', 'Nama Siswa', 'Nilai Akhir (0-100)']);
    
    foreach ($students as $s) {
        fputcsv($output, [$s['nis'] ?? '-', $s['full_name'], '']);
    }
    fclose($output);
    exit;
}

$success = "";
$error = "";

// Handle Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $academic_year = $_POST['academic_year'];
    $semester = $_POST['semester'];
    $file = $_FILES['csv_file'];

    if ($file['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'csv') {
            $error = "Hanya file berformat CSV yang didukung.";
        } else {
            $handle = fopen($file['tmp_name'], "r");
            if ($handle !== FALSE) {
                // Read BOM if present
                $bom = fread($handle, 3);
                if ($bom !== "\xEF\xBB\xBF") {
                    rewind($handle); // Not a BOM, go back to start
                }

                $header = fgetcsv($handle, 1000, ","); // Skip header
                
                $stmt = $pdo->prepare("
                    INSERT INTO student_grades (student_id, teacher_id, subject_id, class_id, academic_year, semester, grade) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE grade = VALUES(grade), updated_at = NOW()
                ");

                $pdo->beginTransaction();
                $imported_count = 0;
                $real_class_id = $class_info['class_id'] ? $class_info['class_id'] : 0;

                try {
                    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                        $nis = trim($data[0] ?? '');
                        $grade = trim($data[2] ?? '');

                        if ($grade === '' || !isset($student_map[$nis])) continue;
                        
                        $student_id = $student_map[$nis];
                        
                        $stmt->execute([
                            $student_id, 
                            $teacher_id, 
                            $class_info['subject_id'], 
                            $real_class_id, 
                            $academic_year, 
                            $semester, 
                            $grade
                        ]);
                        $imported_count++;
                    }
                    $pdo->commit();
                    $success = "Berhasil mengimpor $imported_count nilai siswa!";
                } catch(Exception $e) {
                    $pdo->rollBack();
                    $error = "Gagal memproses file: " . $e->getMessage();
                }
                fclose($handle);
            } else {
                $error = "Gagal membaca file CSV.";
            }
        }
    } else {
        $error = "Gagal mengupload file.";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta charset="UTF-8">
    <title>Import Nilai - <?php echo htmlspecialchars($class_info['name']); ?></title>
    <link rel="stylesheet" href="/public/assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .page-header {
            background: white; padding: 24px 32px; border-radius: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 24px;
            display: flex; justify-content: space-between; align-items: center;
        }
        .class-title { font-size: 1.5rem; font-weight: 800; color: #1e293b; margin-bottom: 4px; }
        .badge { display: inline-block; padding: 4px 10px; border-radius: 20px; background: #eef2ff; color: #4338ca; font-size: 0.8rem; font-weight: 700; margin-bottom: 8px; }
        
        .import-card {
            background: white; padding: 32px; border-radius: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); max-width: 600px; margin: 0 auto;
        }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-weight: 700; color: #334155; margin-bottom: 8px; }
        .form-group select, .form-group input[type="file"] {
            width: 100%; border: 2px solid #e2e8f0; border-radius: 10px; padding: 12px 14px; font-weight: 500; font-family: inherit;
        }
        .form-group input[type="file"] { padding: 9px 14px; background: #f8fafc; }
        .btn-outline {
            background: transparent; border: 2px solid #e2e8f0; color: #475569; padding: 10px 20px; border-radius: 10px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s;
        }
        .btn-outline:hover { border-color: #cbd5e1; background: #f8fafc; }
        .help-box { background: #f0fdf4; border: 1px solid #bbf7d0; padding: 16px; border-radius: 12px; margin-bottom: 24px; color: #166534; font-size: 0.9rem; }
    </style>
</head>
<body>

<div class="app-container">
    <?php include '../templates/sidebar.php'; ?>
    
    <main class="main-content">
        <?php if ($success): ?>
            <div style="background: #dcfce7; border: 1px solid #bbf7d0; color: #166534; padding: 16px; border-radius: 12px; margin-bottom: 24px; font-weight: 600;">&#10003; <?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div style="background: #fee2e2; border: 1px solid #fecaca; color: #991b1b; padding: 16px; border-radius: 12px; margin-bottom: 24px; font-weight: 600;">&#9888;ï¸ <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="page-header">
            <div>
                <span class="badge"><?php echo htmlspecialchars($class_info['subject']); ?></span>
                <div class="class-title">Import Nilai: <?php echo htmlspecialchars($class_info['name']); ?></div>
            </div>
            <a href="grades_input.php?class_id=<?php echo $class_id; ?>" class="btn-outline">&larr; Kembali ke Input Manual</a>
        </div>

        <div class="import-card">
            <div class="help-box">
                <strong>Cara Import Nilai:</strong>
                <ol style="margin-top: 8px; margin-bottom: 0; padding-left: 20px; line-height: 1.6;">
                    <li>Unduh template CSV yang sudah berisi daftar siswa di kelas ini.</li>
                    <li>Buka file CSV (bisa menggunakan Excel atau Google Sheets).</li>
                    <li>Isi nilai akhir pada kolom <strong>Nilai Akhir</strong> tanpa mengubah NIS.</li>
                    <li>Simpan ulang file (pastikan format tetap CSV).</li>
                    <li>Upload file CSV yang sudah diisi pada form di bawah.</li>
                </ol>
                <div style="margin-top: 15px;">
                    <a href="?class_id=<?php echo $class_id; ?>&action=download_template" class="btn" style="background: #10b981; text-decoration: none; padding: 8px 16px; font-size: 0.9rem; display: inline-block;">&darr;ï¸ Unduh Template CSV</a>
                </div>
            </div>

            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="class_id" value="<?php echo $class_id; ?>">
                
                <div style="display: flex; gap: 16px; margin-bottom: 20px;">
                    <div class="form-group" style="flex: 1; margin-bottom: 0;">
                        <label>Tahun Ajaran</label>
                        <select name="academic_year" required>
                            <option value="2023-2024">2023-2024</option>
                            <option value="2024-2025">2024-2025</option>
                            <option value="2025-2026" selected>2025-2026</option>
                            <option value="2026-2027">2026-2027</option>
                        </select>
                    </div>
                    <div class="form-group" style="flex: 1; margin-bottom: 0;">
                        <label>Semester</label>
                        <select name="semester" required>
                            <option value="Ganjil" selected>Ganjil</option>
                            <option value="Genap">Genap</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>File CSV Nilai</label>
                    <input type="file" name="csv_file" accept=".csv" required>
                </div>

                <button type="submit" class="btn" style="width: 100%; padding: 14px; font-size: 1.05rem; background: #4f46e5;">ðŸš€ Upload & Proses Nilai</button>
            </form>
        </div>

    </main>
</div>

</body>
</html>


