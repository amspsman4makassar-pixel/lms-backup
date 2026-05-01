<?php
// src/guru/view_submissions.php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guru') {
    header("Location: ../../login.php");
    exit;
}

$assignment_id = $_GET['assignment_id'] ?? 0;

// Handle Grading
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_grade'])) {
    $submission_id = $_POST['submission_id'];
    $grade = $_POST['grade'] === '' ? null : $_POST['grade'];
    $feedback = $_POST['feedback'];

    $stmt = $pdo->prepare("UPDATE submissions SET grade = ?, feedback = ? WHERE id = ?");
    if ($stmt->execute([$grade, $feedback, $submission_id])) {
        $success = "Nilai berhasil disimpan.";
    }
}

$stmt = $pdo->prepare("SELECT * FROM assignments WHERE id = ? AND teacher_id = ?");
$stmt->execute([$assignment_id, $_SESSION['user_id']]);
$assignment = $stmt->fetch();

if (!$assignment) {
    echo "Tugas tidak ditemukan.";
    exit;
}

$stmt = $pdo->prepare("
    SELECT s.*, u.full_name, u.username,
    (SELECT GROUP_CONCAT(file_path SEPARATOR ',') FROM submission_attachments WHERE submission_id = s.id) as submitted_files
    FROM submissions s 
    JOIN users u ON s.student_id = u.id 
    WHERE s.assignment_id = ? 
    ORDER BY s.submitted_at ASC
");
$stmt->execute([$assignment_id]);
$submissions = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta charset="UTF-8">
    <title>Penilaian Tugas</title>
    <link rel="stylesheet" href="/public/assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="unified-layout">

<div class="app-container">
    <?php include '../templates/sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Dashboard Hero -->
        <div class="dashboard-hero">
            <div style="position: relative; z-index: 2;">
                <div style="margin-bottom: 1rem;">
                    <a href="manage_assignments.php" style="color: rgba(255,255,255,0.8); text-decoration: none; font-size: 0.9rem; display: flex; align-items: center; gap: 6px; background: rgba(255,255,255,0.1); padding: 6px 12px; border-radius: 20px; width: fit-content; backdrop-filter: blur(4px);">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5"/><path d="M12 19l-7-7 7-7"/></svg>
                        Kembali ke Kelola Tugas
                    </a>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem;">
                    <div>
                        <h1 style="color: white; margin-bottom: 0.5rem;"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;vertical-align:middle;margin-right:8px;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg> Penilaian: <?php echo htmlspecialchars($assignment['title']); ?></h1>
                        <p style="color: rgba(255,255,255,0.8);">Evaluasi dan berikan umpan balik kepada siswa.</p>
                    </div>
                </div>
            </div>
            <div style="position: absolute; right: -50px; top: -50px; width: 200px; height: 200px; background: rgba(255,255,255,0.1); border-radius: 50%;"></div>
        </div>

        <div class="content-overlap">
            <?php if (isset($success)): ?>
                <div style="background:#dcfce7; color:#166534; padding:15px; border-radius:8px; margin-bottom:20px;">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <div class="page-section">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; flex-wrap:wrap; gap:10px;">
                <h3 style="margin:0; font-size:1rem; color:#475569;">
                    Total mengumpulkan: <strong style="color:#1e293b;"><?php echo count($submissions); ?> siswa</strong>
                    <?php
                    $graded_count = count(array_filter($submissions, fn($s) => $s['grade'] !== null));
                    $grade_values = array_filter(array_column($submissions, 'grade'), fn($g) => $g !== null);
                    $avg_grade = count($grade_values) ? round(array_sum($grade_values) / count($grade_values), 1) : '-';
                    ?>
                    &nbsp;|&nbsp; Sudah dinilai: <strong style="color:#16a34a;"><?php echo $graded_count; ?></strong>
                    &nbsp;|&nbsp; Rata-rata: <strong style="color:#4f46e5;"><?php echo $avg_grade; ?></strong>
                </h3>
                <div style="display:flex; gap:6px;">
                    <a href="export_grades.php?assignment_id=<?php echo intval($assignment_id); ?>" class="btn" style="background:#16a34a; color:#fff; display:inline-flex; align-items:center; gap:5px; font-size:0.8rem; padding:5px 12px;">
                        <svg xmlns='http://www.w3.org/2000/svg' width='13' height='13' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><path d='M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4'/><polyline points='7 10 12 15 17 10'/><line x1='12' y1='15' x2='12' y2='3'/></svg>
                        CSV
                    </a>
                    <a href="export_grades_print.php?assignment_id=<?php echo intval($assignment_id); ?>" target="_blank" class="btn" style="background:#dc2626; color:#fff; display:inline-flex; align-items:center; gap:5px; font-size:0.8rem; padding:5px 12px;">
                        <svg xmlns='http://www.w3.org/2000/svg' width='13' height='13' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><polyline points='6 9 6 2 18 2 18 9'/><path d='M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2'/><rect x='6' y='14' width='12' height='8'/></svg>
                        PDF
                    </a>
                </div>
            </div>
            <div class="page-table-wrap">
                <table class="page-table">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Siswa</th>
                        <th>File Tugas</th>
                        <th>Nilai (0-100)</th>
                        <th>Umpan Balik (Feedback)</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($submissions) == 0): ?>
                    <tr>
                        <td colspan="6" style="text-align:center; padding: 2rem; color: #94a3b8;">Belum ada siswa yang mengumpulkan.</td>
                    </tr>
                    <?php else: ?>
                        <?php $row_no = 1; foreach ($submissions as $sub): ?>
                        <tr>
                            <form method="POST">
                                <input type="hidden" name="save_grade" value="1">
                                <input type="hidden" name="submission_id" value="<?php echo $sub['id']; ?>">
                                
                                <td style="font-size:0.85rem; color:#94a3b8; text-align:center;"><?php echo $row_no++; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($sub['full_name']); ?></strong>
                                    <div style="font-size: 12px; color: #64748b; margin-top: 2px;">
                                        <svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' style='display:inline-block; vertical-align:middle; line-height:1;'><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg> <?php echo date('d M Y, H:i', strtotime($sub['submitted_at'])); ?>
                                        <?php
        $is_late = false;
        if ($sub['status'] === 'terlambat') {
            $is_late = true;
        }
        elseif (strtotime($sub['submitted_at']) > strtotime($assignment['deadline'])) {
            $is_late = true;
        }

        if ($is_late): ?>
                                            <span style="background: #fee2e2; color: #991b1b; padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: 700; text-transform: uppercase; margin-left: 5px;">Terlambat</span>
                                        <?php
        endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($sub['submitted_files']):
            $files = explode(',', $sub['submitted_files']);
            foreach ($files as $idx => $f):
?>
                                        <div style="margin-bottom: 4px;">
                                            <a href="../../<?php echo htmlspecialchars($f); ?>" target="_blank" class="btn btn-secondary" style="font-size:11px; padding: 4px 8px;">
                                                <svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' style='display:inline-block; vertical-align:middle; line-height:1;'><path d='m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48'/></svg> File <?php echo $idx + 1; ?>
                                            </a>
                                        </div>
                                    <?php
            endforeach;
        elseif ($sub['file_path']): // Backward compatibility
?>
                                        <a href="../../<?php echo htmlspecialchars($sub['file_path']); ?>" target="_blank" class="btn btn-secondary" style="font-size:12px; padding: 5px 10px;">Download</a>
                                    <?php
        else: ?>
                                        <span style="color:red; font-size: 12px;">Tidak ada file</span>
                                    <?php
        endif; ?>
                                </td>
                                <td>
                                    <input type="number" name="grade" value="<?php echo $sub['grade']; ?>" min="0" max="100" style="width: 80px;" placeholder="0">
                                </td>
                                <td>
                                    <input type="text" name="feedback" value="<?php echo htmlspecialchars($sub['feedback'] ?? ''); ?>" placeholder="Tulis komentar..." style="font-size: 0.9rem;">
                                </td>
                                <td>
                                    <button type="submit" class="btn" style="padding: 6px 12px; font-size: 12px;">Simpan</button>
                                </td>
                            </form>
                        </tr>
                        <?php
    endforeach; ?>
                    <?php
endif; ?>
                </tbody>
            </table>
            </div>
        </div>
        </div>
    </main>
</div>

</body>
</html>


