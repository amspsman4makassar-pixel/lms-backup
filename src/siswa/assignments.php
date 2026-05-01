<?php
// src/siswa/assignments.php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'siswa') {
    header("Location: ../../login.php");
    exit;
}

$student_id    = $_SESSION['user_id'];
$assignment_id = intval($_GET['assignment_id'] ?? 0);

if (!$assignment_id) {
    header("Location: kelas_siswa.php");
    exit;
}

// Fetch assignment detail
$stmt = $pdo->prepare("
    SELECT a.*,
           u.full_name as teacher_name,
           COALESCE(s_a.name, s_u.name, 'Umum') as subject_name,
           c.name as class_name,
           (SELECT GROUP_CONCAT(file_path SEPARATOR ',') FROM assignment_attachments WHERE assignment_id = a.id) as attachments
    FROM assignments a
    JOIN users u ON a.teacher_id = u.id
    LEFT JOIN subjects s_u ON u.subject_id = s_u.id
    LEFT JOIN subjects s_a ON a.subject_id = s_a.id
    JOIN assignment_classes ac ON a.id = ac.assignment_id
    JOIN classes c ON ac.class_id = c.id
    JOIN users stu ON stu.class_id = c.id
    WHERE a.id = ? AND stu.id = ? AND a.status = 'active'
    LIMIT 1
");
$stmt->execute([$assignment_id, $student_id]);
$assignment = $stmt->fetch();

if (!$assignment) {
    echo "<p style='padding:2rem;color:red;'>Tugas tidak ditemukan atau Anda tidak memiliki akses.</p>";
    exit;
}

// Check existing submission
$sub_stmt = $pdo->prepare("SELECT * FROM submissions WHERE assignment_id = ? AND student_id = ?");
$sub_stmt->execute([$assignment_id, $student_id]);
$existing_submission = $sub_stmt->fetch();

$deadline_ts = strtotime($assignment['deadline']);
$is_overdue  = $deadline_ts < time();
$class_id    = null;

// Get class_id for back link
$cl_stmt = $pdo->prepare("SELECT class_id FROM users WHERE id = ?");
$cl_stmt->execute([$student_id]);
$class_id = $cl_stmt->fetchColumn();

// Handle submission
$success_msg = '';
$error_msg   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$existing_submission) {
    if (($assignment['assignment_type'] ?? 'tugas') === 'absensi') {
        $status = $_POST['status'] ?? 'hadir';
        if (!in_array($status, ['hadir', 'sakit', 'izin'])) {
            $status = 'hadir';
        }
        
        $current_time = date('H:i');
        if ($current_time < '07:00' || $current_time > '16:00') {
            $error_msg = "Absensi hanya dapat dilakukan antara pukul 07:00 - 16:00 WITA.";
        } else {
            $ins = $pdo->prepare("
                INSERT INTO submissions (assignment_id, student_id, status, submitted_at)
                VALUES (?, ?, ?, NOW())
            ");
            $ins->execute([$assignment_id, $student_id, $status]);
            $success_msg = "Kehadiran berhasil dicatat!";
            
            $sub_stmt->execute([$assignment_id, $student_id]);
            $existing_submission = $sub_stmt->fetch();
        }
    } else {
        $file_path    = null;

        // Handle file upload
        if (!empty($_FILES['submission_file']['name'])) {
            $upload_dir = '../../public/uploads/submissions/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

            $orig_name  = basename($_FILES['submission_file']['name']);
            $ext        = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
            $allowed    = ['pdf','doc','docx','ppt','pptx','xls','xlsx','jpg','jpeg','png','zip','rar'];

            if (!in_array($ext, $allowed)) {
                $error_msg = "Format file tidak didukung. Gunakan: " . implode(', ', $allowed);
            } elseif ($_FILES['submission_file']['size'] > 20 * 1024 * 1024) {
                $error_msg = "Ukuran file maksimal 20MB.";
            } else {
                $new_name  = 'sub_' . $student_id . '_' . $assignment_id . '_' . time() . '.' . $ext;
                $dest      = $upload_dir . $new_name;
                if (move_uploaded_file($_FILES['submission_file']['tmp_name'], $dest)) {
                    $file_path = 'public/uploads/submissions/' . $new_name;
                } else {
                    $error_msg = "Gagal mengupload file.";
                }
            }
        }

        if (!$error_msg) {
            if (!$file_path) {
                $error_msg = "Harap upload file tugas.";
            } else {
                $sub_status = $is_overdue ? 'terlambat' : 'menunggu_nilai';
                $ins = $pdo->prepare("
                    INSERT INTO submissions (assignment_id, student_id, file_path, status, submitted_at)
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $ins->execute([$assignment_id, $student_id, $file_path, $sub_status]);
                $success_msg = "Tugas berhasil dikumpulkan!" . ($is_overdue ? " (Terlambat)" : "");
                // Refresh submission
                $sub_stmt->execute([$assignment_id, $student_id]);
                $existing_submission = $sub_stmt->fetch();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($assignment['title']); ?> - SIAKAD</title>
    <link rel="stylesheet" href="/public/assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .assign-wrap {
            max-width: 780px;
            margin: 0 auto;
            padding: 24px 16px;
        }
        .assign-header {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 24px 28px;
            margin-bottom: 20px;
        }
        .assign-subject-badge {
            display: inline-block;
            background: #ede9fe;
            color: #5b21b6;
            font-size: 0.72rem;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 20px;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .assign-title {
            font-size: 1.4rem;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 12px;
            line-height: 1.35;
        }
        .assign-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            font-size: 0.82rem;
            color: #64748b;
        }
        .assign-meta span {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .overdue-banner {
            background: #fef3c7;
            border: 1px solid #fcd34d;
            border-radius: 10px;
            padding: 10px 16px;
            color: #92400e;
            font-size: 0.85rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 14px;
        }
        .assign-desc {
            background: #f8fafc;
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 20px 24px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            color: #334155;
            line-height: 1.7;
            white-space: pre-line;
        }
        .assign-form-card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 24px 28px;
        }
        .assign-form-card h3 {
            font-size: 0.95rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 18px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border);
        }
        .form-group { margin-bottom: 18px; }
        .form-group label {
            display: block;
            font-size: 0.82rem;
            font-weight: 600;
            color: #475569;
            margin-bottom: 6px;
        }
        .form-group textarea {
            width: 100%;
            border: 1.5px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px 14px;
            font-family: inherit;
            font-size: 0.9rem;
            resize: vertical;
            min-height: 120px;
            transition: border-color 0.2s;
            box-sizing: border-box;
        }
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary);
        }
        .file-upload-area {
            border: 2px dashed #cbd5e1;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        .file-upload-area:hover {
            border-color: var(--primary);
            background: #f0f9ff;
        }
        .file-upload-area input[type="file"] {
            display: none;
        }
        .file-upload-area label {
            cursor: pointer;
            font-size: 0.85rem;
            color: #64748b;
            margin: 0;
        }
        .submit-btn {
            width: 100%;
            padding: 13px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 0.95rem;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: opacity 0.2s;
        }
        .submit-btn:hover { opacity: 0.9; }
        .submit-btn.late { background: #f59e0b; }
        .done-card {
            background: #f0fdf4;
            border: 1px solid #86efac;
            border-radius: var(--radius-lg);
            padding: 28px;
            text-align: center;
        }
        .done-card .done-icon { margin-bottom: 12px; }
        .done-card h3 { color: #166534; font-size: 1.1rem; margin-bottom: 8px; }
        .done-card p { color: #15803d; font-size: 0.88rem; }
        .alert-success {
            background: #d1fae5; border: 1px solid #6ee7b7; color: #065f46;
            padding: 12px 16px; border-radius: 8px; margin-bottom: 16px;
            font-size: 0.88rem; font-weight: 600;
        }
        .alert-error {
            background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b;
            padding: 12px 16px; border-radius: 8px; margin-bottom: 16px;
            font-size: 0.88rem; font-weight: 600;
        }
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #64748b;
            font-size: 0.85rem;
            font-weight: 600;
            text-decoration: none;
            margin-bottom: 16px;
            padding: 6px 12px;
            border-radius: 8px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
        }
        .back-link:hover { background: #e0f2fe; color: #0284c7; }
    </style>
</head>
<body>
<div class="app-container">
    <?php include '../templates/sidebar.php'; ?>
    <main class="main-content">
        <div class="page-toolbar">
            <div class="page-toolbar-left">
                <h1 class="page-title">Kerjakan Tugas</h1>
                <p class="page-subtitle"><?php echo htmlspecialchars($assignment['subject_name']); ?> &middot; <?php echo htmlspecialchars($assignment['class_name']); ?></p>
            </div>
        </div>
        <div class="page-content">
            <div class="assign-wrap">

                <!-- Back -->
                <a href="kelas_detail_siswa.php?class_id=<?php echo $class_id; ?>&tab=tugas" class="back-link">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
                    Kembali ke Daftar Tugas
                </a>

                <!-- Header -->
                <div class="assign-header">
                    <div class="assign-subject-badge"><?php echo htmlspecialchars($assignment['subject_name']); ?></div>
                    <div class="assign-title"><?php echo htmlspecialchars($assignment['title']); ?></div>
                    <div class="assign-meta">
                        <span>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            <?php echo htmlspecialchars($assignment['teacher_name']); ?>
                        </span>
                        <span>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            Deadline: <?php echo date('d M Y, H:i', $deadline_ts); ?>
                        </span>
                        <span>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                            <?php echo ucfirst($assignment['assignment_type'] ?? 'Tugas'); ?>
                        </span>
                    </div>
                    <?php if ($is_overdue): ?>
                    <div class="overdue-banner">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        Deadline sudah lewat - pengumpulan masih diperbolehkan namun akan ditandai <strong>Terlambat</strong>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Description -->
                <?php if (!empty($assignment['description'])): ?>
                <div class="assign-desc"><?php echo htmlspecialchars($assignment['description']); ?></div>
                <?php endif; ?>

                <!-- Attachment -->
                <?php 
                $all_attachments = [];
                if (!empty($assignment['attachment_path'])) {
                    $all_attachments[] = $assignment['attachment_path'];
                }
                if (!empty($assignment['attachments'])) {
                    $files = explode(',', $assignment['attachments']);
                    foreach ($files as $f) {
                        $f = trim($f);
                        if (!empty($f) && !in_array($f, $all_attachments)) {
                            $all_attachments[] = $f;
                        }
                    }
                }
                ?>
                
                <?php if (!empty($all_attachments)): ?>
                <div style="margin-bottom: 24px; display: flex; flex-direction: column; gap: 10px;">
                    <?php foreach ($all_attachments as $att): 
                        $is_link = (strpos($att, 'http') === 0);
                        $href = $is_link ? $att : '/' . $att;
                        
                        // Extract filename from path for better display
                        $display_name = $is_link ? "Buka Link / Form Lampiran" : "Lihat Lampiran: " . basename($att);
                        if (!$is_link && strlen($display_name) > 60) {
                            $display_name = substr($display_name, 0, 57) . '...';
                        }
                    ?>
                    <a href="<?php echo htmlspecialchars($href); ?>" target="_blank" style="display:inline-flex;align-items:center;gap:8px;padding:12px 20px;background:#f8fafc;color:#0f172a;border:1px solid var(--border);border-radius:10px;font-size:0.9rem;font-weight:600;text-decoration:none;transition:all 0.2s; width: fit-content;" onmouseover="this.style.background='#f1f5f9'; this.style.borderColor='#cbd5e1';" onmouseout="this.style.background='#f8fafc'; this.style.borderColor='var(--border)';">
                        <?php if ($is_link): ?>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:#3b82f6;"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                        <?php else: ?>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:#ef4444;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                        <?php endif; ?>
                        <?php echo htmlspecialchars($display_name); ?>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Alerts -->
                <?php if ($success_msg): ?>
                    <div class="alert-success">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:middle;margin-right:6px;"><polyline points="20 6 9 17 4 12"/></svg>
                        <?php echo htmlspecialchars($success_msg); ?>
                    </div>
                <?php endif; ?>
                <?php if ($error_msg): ?>
                    <div class="alert-error">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:middle;margin-right:6px;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        <?php echo htmlspecialchars($error_msg); ?>
                    </div>
                <?php endif; ?>

                <!-- Already submitted -->
                <?php if ($existing_submission): ?>
                <div class="done-card">
                    <div class="done-icon">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    </div>
                    <?php if (($assignment['assignment_type'] ?? 'tugas') === 'absensi'): ?>
                        <h3>Kehadiran Tercatat: <?php echo ucfirst($existing_submission['status']); ?></h3>
                        <p>Dicatat pada <?php echo date('d M Y, H:i', strtotime($existing_submission['submitted_at'])); ?></p>
                    <?php else: ?>
                        <h3>Tugas Sudah Dikumpulkan</h3>
                        <p>Dikumpulkan pada <?php echo date('d M Y, H:i', strtotime($existing_submission['submitted_at'])); ?>
                        <?php if ($existing_submission['status'] === 'terlambat'): ?>
                            &middot; <span style="color:#d97706;font-weight:700;">Terlambat</span>
                        <?php endif; ?>
                        </p>
                        <?php if ($existing_submission['grade'] !== null): ?>
                            <p style="margin-top:10px;font-size:1.1rem;font-weight:800;color:#0f172a;">
                                Nilai: <?php echo htmlspecialchars($existing_submission['grade']); ?>
                            </p>
                        <?php else: ?>
                            <p style="margin-top:6px;color:#64748b;">Menunggu penilaian dari guru.</p>
                        <?php endif; ?>
                        <?php if ($existing_submission['file_path']): ?>
                            <a href="/<?php echo htmlspecialchars($existing_submission['file_path']); ?>" target="_blank" style="display:inline-flex;align-items:center;gap:6px;margin-top:12px;padding:8px 16px;background:#dbeafe;color:#1d4ed8;border-radius:8px;font-size:0.85rem;font-weight:600;text-decoration:none;">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                Lihat File yang Dikumpulkan
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <?php else: ?>
                <!-- Submission Form -->
                <div class="assign-form-card">
                    <h3>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-right:6px;"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                        <?php echo (($assignment['assignment_type'] ?? 'tugas') === 'absensi') ? 'Form Kehadiran' : 'Form Pengumpulan Tugas'; ?>
                    </h3>
                    <form method="POST" enctype="multipart/form-data">
                        <?php if (($assignment['assignment_type'] ?? 'tugas') === 'absensi'): ?>
                            <div class="form-group">
                                <label style="display: block; font-size: 0.85rem; font-weight: 600; color: #475569; margin-bottom: 10px;">Pilih Status Kehadiran Anda:</label>
                                <div style="display: flex; gap: 16px; align-items: center;">
                                    <label style="cursor: pointer; display: flex; align-items: center; gap: 6px;"><input type="radio" name="status" value="hadir" checked> Hadir</label>
                                    <label style="cursor: pointer; display: flex; align-items: center; gap: 6px;"><input type="radio" name="status" value="sakit"> Sakit</label>
                                    <label style="cursor: pointer; display: flex; align-items: center; gap: 6px;"><input type="radio" name="status" value="izin"> Izin</label>
                                </div>
                                <p style="font-size: 0.8rem; color: #64748b; margin-top: 10px;">Catatan: Absensi hanya dapat diisi pada pukul 07:00 - 16:00 WITA.</p>
                            </div>
                            <button type="submit" class="submit-btn" style="background: #10b981;">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                Simpan Kehadiran
                            </button>
                        <?php else: ?>
                            <div class="form-group">
                                <label>Upload File (PDF, Word, PPT, gambar, ZIP - maks. 20MB)</label>
                                <div class="file-upload-area" onclick="document.getElementById('sub_file').click()">
                                    <input type="file" id="sub_file" name="submission_file" accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.jpg,.jpeg,.png,.zip,.rar,application/pdf,image/jpeg,image/png,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document" onchange="document.getElementById('file_label').textContent = this.files[0]?.name || 'Klik untuk pilih file'">
                                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom:8px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                    <label id="file_label">Klik untuk pilih file</label>
                                </div>
                                <div style="margin-top: 10px; padding: 10px 12px; background-color: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; font-size: 0.8rem; color: #92400e; line-height: 1.5;">
                                    <strong>&#128161; Tips jika gagal upload:</strong> Jika Anda mengalami error seperti <em>"ERR_UPLOAD_FILE_CHANGED"</em>, silakan pilih file melalui menu <strong>File Manager / Penyimpanan Internal</strong> HP Anda, hindari memilih dari tab "Terbaru" (Recent) atau "Galeri".
                                </div>
                            </div>
                            <button type="submit" class="submit-btn <?php echo $is_overdue ? 'late' : ''; ?>">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="22 2 11 13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                                <?php echo $is_overdue ? 'Kumpulkan Tugas (Terlambat)' : 'Kumpulkan Tugas'; ?>
                            </button>
                        <?php endif; ?>
                    </form>
                </div>
                <?php endif; ?>

            </div>
        </div>
    </main>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const fileInput = document.getElementById('sub_file');
    const form = fileInput ? fileInput.closest('form') : null;
    let fileBlob = null;
    let fileName = '';

    if (fileInput && form) {
        // Remove standard onchange so it doesn't conflict
        fileInput.removeAttribute('onchange');
        
        fileInput.addEventListener('change', function(e) {
            const file = this.files[0];
            if (!file) return;
            
            fileName = file.name;
            const label = document.getElementById('file_label');
            if (label) label.textContent = fileName;
            
            // Baca file ke RAM segera setelah dipilih untuk mencegah ERR_UPLOAD_FILE_CHANGED
            // bug bawaan Google Chrome di Android (terutama Xiaomi/Samsung).
            const reader = new FileReader();
            reader.onload = function(evt) {
                fileBlob = new Blob([evt.target.result], { type: file.type });
            };
            reader.readAsArrayBuffer(file);
        });

        form.addEventListener('submit', function(e) {
            if (fileBlob) {
                e.preventDefault();
                const submitBtn = form.querySelector('.submit-btn');
                if (submitBtn) {
                    submitBtn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg> Sedang Mengupload...';
                    submitBtn.disabled = true;
                    submitBtn.style.opacity = '0.7';
                }

                const formData = new FormData(form);
                // Timpa file asli dengan Blob yang sudah ada di RAM
                formData.set('submission_file', fileBlob, fileName);

                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(html => {
                    document.open();
                    document.write(html);
                    document.close();
                })
                .catch(err => {
                    alert('Gagal mengupload tugas, silakan coba lagi: ' + err.message);
                    if (submitBtn) {
                        submitBtn.innerHTML = 'Coba Lagi';
                        submitBtn.disabled = false;
                        submitBtn.style.opacity = '1';
                    }
                });
            }
        });
    }
});
</script>
</body>
</html>
