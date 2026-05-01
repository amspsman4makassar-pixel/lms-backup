<?php
// src/guru/view_class.php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guru') {
    header("Location: ../../login.php");
    exit;
}

$teacher_id = $_SESSION['user_id'];
$class_id = $_GET['id'] ?? 0;
$success = "";
$error = "";
$active_meeting = isset($_GET['meeting']) ? intval($_GET['meeting']) : null;
$attendance_data = [];



// Fetch Teacher Class Details
$stmt = $pdo->prepare("
    SELECT tc.*, c.name as school_class_name 
    FROM teacher_classes tc
    LEFT JOIN classes c ON tc.class_id = c.id
    WHERE tc.id = ? AND tc.teacher_id = ?
");
$stmt->execute([$class_id, $teacher_id]);
$class = $stmt->fetch();

if (!$class) {
    die("Kelas tidak ditemukan atau Anda tidak memiliki akses.");
}

// Fetch Students
if ($class['is_special_class'] == 1) {
    // For special class, get from class_members
    $stmt = $pdo->prepare("
        SELECT u.* 
        FROM users u 
        JOIN class_members cm ON u.id = cm.student_id 
        WHERE cm.teacher_class_id = ? AND u.role = 'siswa' 
        ORDER BY u.nis ASC, u.full_name ASC
    ");
    $stmt->execute([$class_id]);
} else {
    // For regular class, get from physical class_id
    $stmt = $pdo->prepare("SELECT * FROM users WHERE class_id = ? AND role = 'siswa' ORDER BY nis ASC, full_name ASC");
    $stmt->execute([$class['class_id']]);
}
$students = $stmt->fetchAll();



// Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬ Handle Delete Attendance Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_attendance'])) {
    $meeting_num = intval($_POST['meeting_number']);

    $stmt = $pdo->prepare("SELECT id FROM assignments WHERE teacher_class_id = ? AND meeting_number = ? AND assignment_type = 'absensi'");
    $stmt->execute([$class_id, $meeting_num]);
    $assign = $stmt->fetch();

    if ($assign) {
        $assign_id = $assign['id'];
        $pdo->prepare("DELETE FROM submissions WHERE assignment_id = ?")->execute([$assign_id]);
        $pdo->prepare("DELETE FROM assignment_classes WHERE assignment_id = ?")->execute([$assign_id]);
        $pdo->prepare("DELETE FROM assignments WHERE id = ?")->execute([$assign_id]);

        $_SESSION['flash'] = ['type' => 'success', 'message' => "Data absensi pertemuan $meeting_num berhasil dihapus."];
    }
    else {
        $_SESSION['flash'] = ['type' => 'error', 'message' => "Data absensi tidak ditemukan."];
    }
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}

// Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬ Handle Edit Class Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_class'])) {
    $new_name = trim($_POST['class_name']);

    // Update teacher_classes
    $stmt = $pdo->prepare("UPDATE teacher_classes SET name = ? WHERE id = ? AND teacher_id = ?");
    if ($stmt->execute([$new_name, $class_id, $teacher_id])) {
        $_SESSION['flash'] = ['type' => 'success', 'message' => "Informasi kelas berhasil diperbarui."];
    }
    else {
        $_SESSION['flash'] = ['type' => 'error', 'message' => "Gagal memperbarui kelas."];
    }
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}

// Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬ Handle Remove Student Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_student_id'])) {
    $student_id = $_POST['remove_student_id'];
    
    if ($class['is_special_class'] == 1) {
        // Remove from special class only
        $stmt = $pdo->prepare("DELETE FROM class_members WHERE teacher_class_id = ? AND student_id = ?");
        if ($stmt->execute([$class_id, $student_id])) {
            $_SESSION['flash'] = ['type' => 'success', 'message' => "Siswa berhasil dikeluarkan dari kelas khusus."];
        } else {
            $_SESSION['flash'] = ['type' => 'error', 'message' => "Gagal mengeluarkan siswa."];
        }
    } else {
        // Remove student from the SCHOOL CLASS means setting class_id to NULL
        // This affects ALL subjects for this student in this class
        $stmt = $pdo->prepare("UPDATE users SET class_id = NULL WHERE id = ? AND role = 'siswa'");
        if ($stmt->execute([$student_id])) {
            $_SESSION['flash'] = ['type' => 'success', 'message' => "Siswa berhasil dikeluarkan dari kelas reguler."];
        } else {
            $_SESSION['flash'] = ['type' => 'error', 'message' => "Gagal mengeluarkan siswa."];
        }
    }
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}

// Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬ Handle Add Special Class Student Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_special_student_ids']) && $class['is_special_class'] == 1) {
    if (is_array($_POST['add_special_student_ids'])) {
        $student_ids = $_POST['add_special_student_ids'];
        $success_count = 0;
        $duplicate_count = 0;

        // INSERT IGNORE ensures duplicate errors are ignored
        $stmt = $pdo->prepare("INSERT IGNORE INTO class_members (teacher_class_id, student_id) VALUES (?, ?)");
        
        foreach ($student_ids as $student_id) {
            $stmt->execute([$class_id, $student_id]);
            if ($stmt->rowCount() > 0) {
                $success_count++;
            } else {
                $duplicate_count++;
            }
        }
        
        $msg = "$success_count siswa berhasil ditambahkan ke kelas khusus.";
        if ($duplicate_count > 0) {
            $msg .= " ($duplicate_count siswa diabaikan karena diproses dobel/sudah terdaftar).";
        }
        
        $_SESSION['flash'] = ['type' => 'success', 'message' => $msg];
    } else {
        $_SESSION['flash'] = ['type' => 'error', 'message' => "Format data siswa tidak valid."];
    }
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}

// Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬ Handle Add Material Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
// Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬ Handle Add Material Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_material'])) {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $type = $_POST['type'];

    $file_path = null;
    $material_type = 'pdf';
    $error_msg = "";

    if ($type === 'file') {
        if (isset($_FILES['file']) && $_FILES['file']['error'] == 0) {
            if ($_FILES['file']['size'] > 20 * 1024 * 1024) {
                $error_msg = "Ukuran file terlalu besar. Maksimal 20MB.";
            }
            else {
                // Determine Target Directory
                if (!empty($class['folder_name'])) {
                    $target_dir = "../../public/uploads/classes/" . $class['folder_name'] . "/materi/";
                    $rel_dir = "public/uploads/classes/" . $class['folder_name'] . "/materi/";
                }
                else {
                    $target_dir = "../../public/uploads/materials/";
                    $rel_dir = "public/uploads/materials/";
                }

                if (!file_exists($target_dir))
                    mkdir($target_dir, 0777, true);

                $file_name = time() . '_' . basename($_FILES["file"]["name"]);
                $target_file = $target_dir . $file_name;
                $ext = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

                if (in_array($ext, ['mp4', 'avi', 'mov']))
                    $material_type = 'video';
                elseif (in_array($ext, ['doc', 'docx']))
                    $material_type = 'word';
                elseif (in_array($ext, ['ppt', 'pptx']))
                    $material_type = 'ppt';
                elseif (in_array($ext, ['xls', 'xlsx']))
                    $material_type = 'excel';
                elseif ($ext === 'epub')
                    $material_type = 'epub';
                else
                    $material_type = 'pdf';

                if (move_uploaded_file($_FILES["file"]["tmp_name"], $target_file)) {
                    $file_path = $rel_dir . $file_name;
                }
                else {
                    $error_msg = "Gagal memindahkan file ke server. Periksa hak akses folder/direktori target.";
                }
            }
        }
        else {
            $error_code = $_FILES['file']['error'] ?? 'undefined';
            $error_msg = "Tidak ada file yang diunggah atau terjadi error (Kode: $error_code).";
        }
    }
    elseif ($type === 'link') {
        $link_url = trim($_POST['link_url']);
        if (!empty($link_url)) {
            $file_path = $link_url;
            $material_type = 'link';
        }
        else {
            $error_msg = "Masukkan link materi.";
        }
    }

    if (empty($error_msg) && $file_path) {
        $stmt = $pdo->prepare("INSERT INTO materials (title, description, type, file_path, teacher_id, teacher_class_id, class_id, subject_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$title, $description, $material_type, $file_path, $teacher_id, $class_id, $class['class_id'], $class['subject_id']])) {
            $_SESSION['flash'] = ['type' => 'success', 'message' => "Materi berhasil ditambahkan!"];
        }
        else {
            $_SESSION['flash'] = ['type' => 'error', 'message' => "Gagal menyimpan ke database."];
        }
    }
    else {
        $_SESSION['flash'] = ['type' => 'error', 'message' => $error_msg];
    }
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}

// Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬ Handle Delete Material Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
// Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬ Handle Delete Material Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
if (isset($_POST['delete_material_id'])) {
    $del_id = $_POST['delete_material_id'];
    $stmt = $pdo->prepare("SELECT file_path, type FROM materials WHERE id = ? AND teacher_class_id = ?");
    $stmt->execute([$del_id, $class_id]);
    $mat = $stmt->fetch();
    if ($mat) {
        if ($mat['type'] !== 'link' && file_exists("../../" . $mat['file_path'])) {
            unlink("../../" . $mat['file_path']);
        }
        $pdo->prepare("DELETE FROM materials WHERE id = ?")->execute([$del_id]);
        $_SESSION['flash'] = ['type' => 'success', 'message' => "Materi dihapus."];
    }
    else {
        $_SESSION['flash'] = ['type' => 'error', 'message' => "Materi tidak ditemukan."];
    }
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}

// Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬ Handle Add Assignment/Absensi Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_assignment'])) {
    $assignment_type = $_POST['assignment_type']; // 'tugas' or 'absensi'
    $title = trim($_POST['a_title'] ?? '');
    $description = trim($_POST['a_description'] ?? '');
    $meeting_number = null;

    $jam_start_val = null;
    $jam_end_val = null;

    if ($assignment_type === 'absensi') {
        $meeting_number = intval($_POST['meeting_number']);
        $jam_start_id = $_POST['jam_start'];
        $jam_end_time = (!empty($_POST['jam_end']) && $_POST['jam_end'] !== 'undefined') ? $_POST['jam_end'] : '23:59'; // e.g. '09:00'
        // jam_start_time berisi waktu mulai jam pelajaran (HH:MM) untuk pengecekan absensi siswa
        $jam_start_val = (!empty($_POST['jam_start_time']) && $_POST['jam_start_time'] !== 'undefined') ? $_POST['jam_start_time'] . ':00' : null;
        $jam_end_val = (!empty($_POST['jam_end_label']) && $_POST['jam_end_label'] !== 'undefined') ? $_POST['jam_end_label'] : null;

        // Check for duplicate meeting number
        $stmt = $pdo->prepare("SELECT id FROM assignments WHERE teacher_class_id = ? AND meeting_number = ? AND assignment_type = 'absensi'");
        $stmt->execute([$class_id, $meeting_number]);
        if ($stmt->fetch()) {
            $error = "Absensi untuk Pertemuan $meeting_number sudah ada. Harap hapus terlebih dahulu jika ingin membuat ulang.";
            // Prevent further execution for this POST
            $assignment_type = null;
        }
        else {
            // Build deadline from the end time of the selected lesson hour
            $deadline = date('Y-m-d') . ' ' . $jam_end_time . ':00';

            if (empty($title)) {
                $title = "Absensi Pertemuan " . $meeting_number;
            }
            if (empty($description)) {
                $description = "Absensi kehadiran pertemuan ke-" . $meeting_number . " (Jam ke-" . $_POST['jam_start_label'] . " s.d " . $_POST['jam_end_label'] . ")";
            }
        }
    }
    else {
        $deadline = $_POST['a_deadline'];
    }

    // Handle Multiple Attachments (TUGAS)
    if ($assignment_type === 'tugas') {
        $tugas_type = $_POST['a_type'] ?? 'file';
        $attachment_path = null;

        if (empty($error)) {
            $stmt = $pdo->prepare("INSERT INTO assignments (teacher_id, title, description, deadline, attachment_path, status, assignment_type, meeting_number, teacher_class_id, subject_id, jam_start, jam_end) VALUES (?, ?, ?, ?, ?, 'active', ?, ?, ?, ?, ?, ?)");
            if ($stmt->execute([$teacher_id, $title, $description, $deadline, $attachment_path, $assignment_type, $meeting_number, $class_id, $class['subject_id'], $jam_start_val, $jam_end_val])) {
                $assignment_id = $pdo->lastInsertId();
                if (!empty($class['class_id'])) {
                    $pdo->prepare("INSERT INTO assignment_classes (assignment_id, class_id) VALUES (?, ?)")->execute([$assignment_id, $class['class_id']]);
                }

                if ($tugas_type === 'file' && isset($_FILES['a_attachments'])) {
                    // Determine Target Directory for Assignments
                    if (!empty($class['folder_name'])) {
                        $upload_dir = "../../public/uploads/classes/" . $class['folder_name'] . "/tugas/";
                        $rel_upload_dir = "public/uploads/classes/" . $class['folder_name'] . "/tugas/";
                    }
                    else {
                        $upload_dir = '../../public/uploads/assignments/';
                        $rel_upload_dir = 'public/uploads/assignments/';
                    }

                    if (!file_exists($upload_dir))
                        mkdir($upload_dir, 0777, true);

                    $files = $_FILES['a_attachments'];
                    $file_count = count($files['name']);

                    for ($i = 0; $i < $file_count; $i++) {
                        if ($files['error'][$i] == 0) {
                            $file_name = time() . '_' . $i . '_' . basename($files['name'][$i]);
                            $target_file = $upload_dir . $file_name;
                            if (move_uploaded_file($files['tmp_name'][$i], $target_file)) {
                                $rel_path = $rel_upload_dir . $file_name;
                                $pdo->prepare("INSERT INTO assignment_attachments (assignment_id, file_path) VALUES (?, ?)")->execute([$assignment_id, $rel_path]);
                            }
                        }
                    }
                }
                elseif ($tugas_type === 'link') {
                    $link_url = trim($_POST['a_link_url']);
                    if (!empty($link_url)) {
                        $pdo->prepare("INSERT INTO assignment_attachments (assignment_id, file_path) VALUES (?, ?)")->execute([$assignment_id, $link_url]);
                    }
                }

                $_SESSION['flash'] = ['type' => 'success', 'message' => "Tugas berhasil dibuat!"];
                header("Location: " . $_SERVER['REQUEST_URI']);
                exit;
            }
            else {
                $error = "Gagal menyimpan tugas.";
            }
        }
    }
    elseif ($assignment_type === 'absensi') {
        // ... (absensi logic same as before, no files)
        $attachment_path = null;
        if (empty($error)) {
            $stmt = $pdo->prepare("INSERT INTO assignments (teacher_id, title, description, deadline, attachment_path, status, assignment_type, meeting_number, teacher_class_id, subject_id, jam_start, jam_end) VALUES (?, ?, ?, ?, ?, 'active', ?, ?, ?, ?, ?, ?)");
            if ($stmt->execute([$teacher_id, $title, $description, $deadline, $attachment_path, $assignment_type, $meeting_number, $class_id, $class['subject_id'], $jam_start_val, $jam_end_val])) {
                // Also insert into assignment_classes
                $assignment_id = $pdo->lastInsertId();
                if (!empty($class['class_id'])) {
                    $pdo->prepare("INSERT INTO assignment_classes (assignment_id, class_id) VALUES (?, ?)")->execute([$assignment_id, $class['class_id']]);
                }

                $_SESSION['flash'] = ['type' => 'success', 'message' => "Absensi berhasil dibuat!"];
                header("Location: " . $_SERVER['REQUEST_URI']);
                exit;
            }
            else {
                $error = "Gagal menyimpan absensi.";
            }
        }
    }

    if (!empty($error)) {
        $_SESSION['flash'] = ['type' => 'error', 'message' => $error];
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }
}

// Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬ Handle Delete Assignment Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
// Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬ Handle Delete Assignment Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
if (isset($_POST['delete_assignment_id'])) {
    $del_id = $_POST['delete_assignment_id'];
    $pdo->prepare("DELETE FROM assignment_classes WHERE assignment_id = ?")->execute([$del_id]);
    $pdo->prepare("DELETE FROM assignments WHERE id = ? AND teacher_id = ?")->execute([$del_id, $teacher_id]);
    $_SESSION['flash'] = ['type' => 'success', 'message' => "Berhasil dihapus."];
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}

// Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬ Handle Delete Class Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_class'])) {
    $pdo->prepare("DELETE FROM teacher_classes WHERE id = ?")->execute([$class_id]);
    header("Location: kelas.php");
    exit;
}

// Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬ Handle Submit Google Meet Link Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_meet_link'])) {
    $meet_link = trim($_POST['meet_link']);
    $start_time = $_POST['start_time'] ?? null;
    $end_time = $_POST['end_time'] ?? null;

    // Normalize: if teacher only typed the code like "uhw-agti-qry" &rarr; convert to full URL
    if (!empty($meet_link)) {
        // Plain code pattern: 3-4-3 letters (e.g. uhw-agti-qry)
        if (preg_match('/^[a-z]{3}-[a-z]{4}-[a-z]{3}$/i', $meet_link)) {
            $meet_link = 'https://meet.google.com/' . strtolower($meet_link);
        }
        // Missing protocol: meet.google.com/xxx &rarr; add https://
        elseif (preg_match('/^meet\.google\.com\//i', $meet_link)) {
            $meet_link = 'https://' . $meet_link;
        }
    }

    if (!empty($meet_link) && !empty($start_time) && !empty($end_time)) {
        $stmt = $pdo->prepare("INSERT INTO meet_links (teacher_id, teacher_class_id, class_id, subject_id, meet_link, start_time, end_time) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$teacher_id, $class_id, $class['class_id'], $class['subject_id'], $meet_link, $start_time, $end_time])) {
            $_SESSION['flash'] = ['type' => 'success', 'message' => "Link Google Meet berhasil dibagikan!"];
        }
        else {
            $_SESSION['flash'] = ['type' => 'error', 'message' => "Gagal menyimpan link Google Meet."];
        }
    }
    else {
        $_SESSION['flash'] = ['type' => 'error', 'message' => "Link Google Meet, Waktu Mulai, dan Waktu Selesai tidak boleh kosong."];
    }
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}

// Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬ Handle Delete Google Meet Link Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_meet_link'])) {
    $meet_id = $_POST['meet_id'];
    $stmt = $pdo->prepare("DELETE FROM meet_links WHERE id = ? AND teacher_id = ?");
    if ($stmt->execute([$meet_id, $teacher_id])) {
        $_SESSION['flash'] = ['type' => 'success', 'message' => "Link Google Meet berhasil dihapus."];
    }
    else {
        $_SESSION['flash'] = ['type' => 'error', 'message' => "Gagal menghapus link Google Meet."];
    }
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}

// Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬ Handle Save Attendance Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_attendance'])) {
    $meeting_num = intval($_POST['meeting_number']);
    $statuses = $_POST['status'] ?? [];

    // 1. Get or Create Assignment for this meeting
    // 1. Get or Create Assignment for this meeting
    $stmt = $pdo->prepare("SELECT id FROM assignments WHERE teacher_class_id = ? AND meeting_number = ? AND assignment_type = 'absensi' ORDER BY id DESC LIMIT 1");
    $stmt->execute([$class_id, $meeting_num]);
    $assign = $stmt->fetch();

    if (!$assign) {
        // Create new assignment if it doesn't exist
        $title = "Absensi Pertemuan $meeting_num";
        $desc = "Absensi pertemuan ke-$meeting_num";
        $deadline = date('Y-m-d 23:59:59'); // Default to end of today
        $target_class_id = $class['is_special_class'] ? null : $class['class_id'];

        $stmt = $pdo->prepare("INSERT INTO assignments (teacher_id, title, description, deadline, assignment_type, status, meeting_number, teacher_class_id, subject_id) VALUES (?, ?, ?, ?, 'absensi', 'active', ?, ?, ?)");
        $stmt->execute([$teacher_id, $title, $desc, $deadline, $meeting_num, $class_id, $class['subject_id']]);
        $assignment_id = $pdo->lastInsertId();

        // Link to class if regular class
        if ($target_class_id) {
            $pdo->prepare("INSERT INTO assignment_classes (assignment_id, class_id) VALUES (?, ?)")->execute([$assignment_id, $target_class_id]);
        }
    }
    else {
        $assignment_id = $assign['id'];
    }

    // 2. Save Submissions
    $cnt = 0;
    foreach ($statuses as $sid => $val) {
        // Allow alpha/terlambat/etc.
        if (!in_array($val, ['hadir', 'sakit', 'izin', 'alpha', 'terlambat']))
            continue;

        $chk = $pdo->prepare("SELECT id FROM submissions WHERE assignment_id = ? AND student_id = ?");
        $chk->execute([$assignment_id, $sid]);
        $exists = $chk->fetch();

        if ($exists) {
            $upd = $pdo->prepare("UPDATE submissions SET status = ?, submitted_at = NOW() WHERE id = ?");
            $upd->execute([$val, $exists['id']]);
        }
        else {
            // Note: file_path is nullable now
            $ins = $pdo->prepare("INSERT INTO submissions (assignment_id, student_id, status, submitted_at, file_path) VALUES (?, ?, ?, NOW(), NULL)");
            $ins->execute([$assignment_id, $sid, $val]);
        }
        $cnt++;
    }

    $success_msg = "Data absensi pertemuan $meeting_num berhasil disimpan.";
    $_SESSION['flash'] = ['type' => 'success', 'message' => $success_msg];
    // Redirect to same page but keep the active meeting visible if possible, or just standard reload
    header("Location: view_class.php?id=$class_id&meeting=$meeting_num");
    exit;

}

// Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬ Fetch Data Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
// Materials
$stmt = $pdo->prepare("SELECT * FROM materials WHERE teacher_class_id = ? ORDER BY created_at DESC");
$stmt->execute([$class_id]);
$materials = $stmt->fetchAll();

// Assignments (tugas only)
$stmt = $pdo->prepare("
    SELECT a.*, 
    (SELECT GROUP_CONCAT(file_path SEPARATOR ',') FROM assignment_attachments WHERE assignment_id = a.id) as attachments 
    FROM assignments a 
    WHERE a.teacher_class_id = ? AND a.assignment_type = 'tugas' AND a.teacher_id = ? 
    ORDER BY a.deadline DESC
");
$stmt->execute([$class_id, $teacher_id]);
$assignments = $stmt->fetchAll();

// Attendance (absensi only)
// Attendance List (for dropdown)
$stmt = $pdo->prepare("SELECT * FROM assignments WHERE teacher_class_id = ? AND assignment_type = 'absensi' AND teacher_id = ? ORDER BY meeting_number ASC");
$stmt->execute([$class_id, $teacher_id]);
$attendance_assignments = $stmt->fetchAll();
$existing_meetings = array_column($attendance_assignments, 'meeting_number');

// Fetch Attendance Data if Meeting Selected
if ($active_meeting) {
    // Get Assignment ID (Latest)
    $stmt = $pdo->prepare("SELECT id FROM assignments WHERE teacher_class_id = ? AND meeting_number = ? AND assignment_type = 'absensi' ORDER BY id DESC LIMIT 1");
    $stmt->execute([$class_id, $active_meeting]);
    $current_assign = $stmt->fetch();

    if ($current_assign) {
        $stmt = $pdo->prepare("SELECT student_id, status, submitted_at FROM submissions WHERE assignment_id = ?");
        $stmt->execute([$current_assign['id']]);
        $raw_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Re-map to student_id key
        $attendance_data = [];
        foreach ($raw_data as $row) {
            $attendance_data[$row['student_id']] = [
                'status' => $row['status'],
                'time' => $row['submitted_at']
            ];
        }
    }
}

// Fetch Meet Links
$stmt = $pdo->prepare("SELECT * FROM meet_links WHERE teacher_class_id = ? ORDER BY created_at DESC");
$stmt->execute([$class_id]);
$meet_links = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($class['name']); ?> - Kelas Saya</title>
    <link rel="stylesheet" href="/public/assets/css/style.css">

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
</head>
<body>

<div class="app-container">
    <?php include '../templates/sidebar.php'; ?>
    
    <main class="main-content">

        <!-- Page Toolbar -->
        <div class="page-toolbar">
            <div class="page-toolbar-left">
                <h1 class="page-title"><?php echo htmlspecialchars($class['name']); ?> <?php if($class['is_special_class']) echo '<span style="font-size:0.7rem;background:#fbbf24;color:#78350f;padding:3px 8px;border-radius:4px;vertical-align:middle;margin-left:6px;font-weight:700;">KHUSUS</span>'; ?></h1>
                <p class="page-subtitle"><?php echo htmlspecialchars($class['subject']); ?> &middot; <?php echo $class['is_special_class'] ? 'Lintas Kelas' : htmlspecialchars($class['school_class_name']); ?> &middot; <?php echo count($students); ?> siswa</p>
            </div>
            <div class="page-toolbar-right">
                <a href="kelas.php" class="btn btn-secondary btn-sm">&larr; Kembali</a>
            </div>
        </div>

        <div class="page-content">

            <?php
if (isset($_SESSION['flash'])) {
    $flash = $_SESSION['flash'];
    $bg = '#dcfce7';
    $color = '#166534';
    $border = '#bbf7d0'; // Success default

    if ($flash['type'] == 'error') {
        $bg = '#fee2e2';
        $color = '#991b1b';
        $border = '#fecaca';
    }

    echo "<div style='background:$bg; color:$color; padding:16px; border-radius:12px; margin-bottom:24px; border:1px solid $border;'>
                        " . ($flash['type'] == 'error' ? "<svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' style='display:inline-block; vertical-align:middle; line-height:1;'><path d=\"m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z\"/><line x1=\"12\" y1=\"9\" x2=\"12\" y2=\"13\"/><line x1=\"12\" y1=\"17\" x2=\"12.01\" y2=\"17\"/></svg> " : "<svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' style='display:inline-block; vertical-align:middle; line-height:1;'><polyline points=\"20 6 9 17 4 12\"/></svg> ") . htmlspecialchars($flash['message']) . "
                      </div>";
    unset($_SESSION['flash']);
}
?>

            <!-- Two columns: Materi + Tugas -->
            <div class="vc-grid">

                <!-- MATERI Panel -->
                <div class="vc-panel">
                    <div class="panel-header">
                        <h3><svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' style='display:inline-block; vertical-align:middle; line-height:1;'><path d='M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z'/></svg> Materi Pelajaran</h3>
                        <button class="btn-add" onclick="document.getElementById('materialModal').style.display='block'">+ Tambah</button>
                    </div>
                    <?php if (empty($materials)): ?>
                        <div class="empty-msg">
                            <p style="font-size:2rem; margin-bottom:6px;"><svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' style='display:inline-block; vertical-align:middle; line-height:1;'><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg></p>
                            <p>Belum ada materi.</p>
                        </div>
                    <?php
else: ?>
                        <div style="max-height: 400px; overflow-y: auto; overflow-x: hidden; padding-right: 8px;">
                        <?php foreach ($materials as $m):
        $ico = "<svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' style='display:inline-block; vertical-align:middle; line-height:1;'><path d=\"M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z\"/><polyline points=\"14 2 14 8 20 8\"/><line x1=\"16\" y1=\"13\" x2=\"8\" y2=\"13\"/><line x1=\"16\" y1=\"17\" x2=\"8\" y2=\"17\"/><polyline points=\"10 9 9 9 8 9\"/></svg>";
        $icoClass = 'mat';
        if ($m['type'] == 'pdf')
            $ico = "<svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' style='display:inline-block; vertical-align:middle; line-height:1;'><path d=\"M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20\"/></svg>";
        elseif ($m['type'] == 'ppt')
            $ico = "<svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' style='display:inline-block; vertical-align:middle; line-height:1;'><line x1=\"18\" y1=\"20\" x2=\"18\" y2=\"10\"/><line x1=\"12\" y1=\"20\" x2=\"12\" y2=\"4\"/><line x1=\"6\" y1=\"20\" x2=\"6\" y2=\"14\"/></svg>";
        elseif ($m['type'] == 'video')
            $ico = "<svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' style='display:inline-block; vertical-align:middle; line-height:1;'><polygon points=\"23 7 16 12 23 17 23 7\"/><rect x=\"1\" y=\"5\" width=\"15\" height=\"14\" rx=\"2\" ry=\"2\"/></svg>";
        elseif ($m['type'] == 'link')
            $ico = "<svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' style='display:inline-block; vertical-align:middle; line-height:1;'><path d=\"M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71\"/><path d=\"M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71\"/></svg>";
        elseif ($m['type'] == 'excel')
            $ico = "<svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' style='display:inline-block; vertical-align:middle; line-height:1;'><path d=\"M14 2H6a2 2 0 0 0-2 2v16c0 1.1.9 2 2 2h12a2 2 0 0 0 2-2V8l-6-6z\"/><path d=\"M14 3v5h5M16 13H8M16 17H8M10 9H8\"/></svg>";
        elseif ($m['type'] == 'epub')
            $ico = "<svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' style='display:inline-block; vertical-align:middle; line-height:1;'><path d=\"M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z\"/><path d=\"M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z\"/></svg>";
        $href = $m['file_path'];
        if ($m['type'] !== 'link' && strpos($href, 'http') !== 0)
            $href = '/' . $href;
?>
                        <div class="item-row">
                            <div class="item-ico mat"><?php echo $ico; ?></div>
                            <div class="item-info">
                                <div class="item-title"><?php echo htmlspecialchars($m['title']); ?></div>
                                <div class="item-meta"><?php echo strtoupper($m['type']); ?> &middot; <?php echo date('d M Y', strtotime($m['created_at'])); ?></div>
                            </div>
                            <div class="item-actions">
                                <a href="<?php echo htmlspecialchars($href); ?>" target="_blank" class="btn-view">Buka</a>
                                <form method="POST" style="margin:0;" onsubmit="return confirm('Hapus materi ini?');">
                                    <input type="hidden" name="delete_material_id" value="<?php echo $m['id']; ?>">
                                    <button type="submit" class="btn-del"><svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' style='display:inline-block; vertical-align:middle; line-height:1;'><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg></button>
                                </form>
                            </div>
                        </div>
                        <?php
    endforeach; ?>
                        </div>
                    <?php
endif; ?>
                </div>

                <!-- TUGAS Panel -->
                <div class="vc-panel">
                    <div class="panel-header">
                        <h3><svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' style='display:inline-block; vertical-align:middle; line-height:1;'><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg> Tugas & Meet</h3>
                        <div style="display: flex; gap: 10px;">
                            <a href="manage_assignments.php?from_class=<?php echo $class_id; ?>" class="btn" style="background: #475569; padding: 8px 14px; font-size: 0.85rem; text-decoration: none; display: inline-flex; align-items: center; border-radius: 8px;"><svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' style='display:inline-block; vertical-align:middle; line-height:1;'><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg> Kelola</a>
                            <button class="btn-add" onclick="document.getElementById('meetModal').style.display='block'" style="background: #10b981; color: white;">+ Meet</button>
                            <button class="btn-add" onclick="document.getElementById('assignmentModal').style.display='block'">+ Tambah</button>
                        </div>
                    </div>
                    <?php if (empty($assignments) && empty($meet_links)): ?>
                        <div class="empty-msg">
                            <p style="font-size:2rem; margin-bottom:6px;"><svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' style='display:inline-block; vertical-align:middle; line-height:1;'><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg></p>
                            <p>Belum ada tugas atau jadwal Meet.</p>
                        </div>
                    <?php
else: ?>
                        <div style="max-height: 400px; overflow-y: auto; overflow-x: hidden; padding-right: 8px;">
                        <?php foreach ($meet_links as $ml): ?>
                        <div class="item-row">
                            <div class="item-ico att" style="background: #d1fae5; color: #059669;"><svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' style='display:inline-block; vertical-align:middle; line-height:1;'><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg></div>
                            <div class="item-info">
                                <div class="item-title">Google Meet</div>
                                <div class="item-meta">
                                    <?php if ($ml['start_time'] && $ml['end_time']): ?>
                                        Jadwal: <?php echo date('d M Y, H:i', strtotime($ml['start_time'])); ?> s/d <?php echo date('H:i', strtotime($ml['end_time'])); ?>
                                    <?php
        else: ?>
                                        Dibuat: <?php echo date('d M Y, H:i', strtotime($ml['created_at'])); ?>
                                    <?php
        endif; ?>
                                </div>
                                <div style="font-size: 0.75rem; margin-top: 4px;">
                                    <a href="<?php echo htmlspecialchars($ml['meet_link']); ?>" target="_blank" style="color: #10b981; text-decoration: none; font-weight: bold;"><svg width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' style='display:inline-block; vertical-align:middle; line-height:1;'><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg> Buka Meet</a>
                                </div>
                            </div>
                            <div class="item-actions">
                                <form method="POST" style="margin:0;" onsubmit="return confirm('Hapus link Meet ini?');">
                                    <input type="hidden" name="delete_meet_link" value="1">
                                    <input type="hidden" name="meet_id" value="<?php echo $ml['id']; ?>">
                                    <button type="submit" class="btn-del" title="Hapus"><svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' style='display:inline-block; vertical-align:middle; line-height:1;'><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg></button>
                                </form>
                            </div>
                        </div>
                        <?php
    endforeach; ?>
                        <?php foreach ($assignments as $a): ?>
                        <div class="item-row">
                            <div class="item-ico task"><svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' style='display:inline-block; vertical-align:middle; line-height:1;'><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg></div>
                            <div class="item-info">
                                <div class="item-title"><?php echo htmlspecialchars($a['title']); ?></div>
                                <div class="item-meta">Deadline: <?php echo date('d M Y, H:i', strtotime($a['deadline'])); ?></div>
                                <?php if (!empty($a['attachments'])):
            $files = explode(',', $a['attachments']);
            foreach ($files as $f):
                $is_link = (strpos($f, 'http') === 0);
                $href = $is_link ? $f : '../../' . $f;
                $label = $is_link ? "Buka Link Tugas / Google Form" : "Download Lampiran";
?>
                                    <div style="font-size: 0.75rem; margin-top: 4px;">
                                        <a href="<?php echo htmlspecialchars($href); ?>" target="_blank" style="color: #4f46e5; text-decoration: none;"><svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' style='display:inline-block; vertical-align:middle; line-height:1;'><path d='m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48'/></svg> <?php echo $label; ?></a>
                                    </div>
                                <?php
            endforeach;
        endif; ?>
                            </div>
                            <div class="item-actions">
                                <a href="view_submissions.php?assignment_id=<?php echo $a['id']; ?>" class="btn-grade">Nilai</a>
                                <form method="POST" style="margin:0;" onsubmit="return confirm('Hapus tugas ini?');">
                                    <input type="hidden" name="delete_assignment_id" value="<?php echo $a['id']; ?>">
                                    <button type="submit" class="btn-del"><svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' style='display:inline-block; vertical-align:middle; line-height:1;'><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg></button>
                                </form>
                            </div>
                        </div>
                        <?php
    endforeach; ?>
                        </div>
                    <?php
endif; ?>
                </div>
            </div>

            <!-- ABSENSI Panel (Revised) -->
            <div class="vc-full">
                <div class="vc-panel">
                    <div class="card-header" style="background: linear-gradient(135deg, #4f46e5 0%, #3730a3 100%); padding: 1.5rem 2rem; border-radius: 18px 18px 0 0; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <div>
                                <h3 style="color: white; margin: 0; font-size: 1.25rem; font-weight: 700; letter-spacing: 0.5px;">REKAP ABSENSI</h3>
                                <p style="margin: 0; color: rgba(255,255,255,0.8); font-size: 0.85rem;">Kelola kehadiran siswa</p>
                            </div>
                        </div>
                        
                        <div style="flex: 1; display: flex; justify-content: flex-end; align-items: center; gap: 12px;">
                     <!-- Meeting Selector Form -->
                             <form method="GET" style="display: flex; gap: 10px; align-items: center; margin: 0; background: rgba(255,255,255,0.1); padding: 6px 10px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.1);">
                                <input type="hidden" name="id" value="<?php echo $class_id; ?>">
                                <select name="meeting" onchange="this.form.submit()" style="padding: 8px 14px; border-radius: 8px; border: none; font-size: 0.9rem; background: #fff; color: #1e293b; font-weight: 500; cursor: pointer; outline: none;">
                                    <option value="">-- Pilih Pertemuan --</option>
                                    <?php for ($i = 1; $i <= 16; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo($active_meeting == $i) ? 'selected' : ''; ?>>
                                        Pertemuan <?php echo $i; ?> <?php echo in_array($i, $existing_meetings) ? "<svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' style='display:inline-block; vertical-align:middle; line-height:1;'><polyline points=\"20 6 9 17 4 12\"/></svg>" : ''; ?>
                                    </option>
                                    <?php
endfor; ?>
                                </select>
                                <button type="submit" class="btn-view" style="border:none; background: #fff; color: #4f46e5; padding: 8px 16px; border-radius: 8px; font-weight: 700; font-size: 0.85rem; cursor: pointer; transition: all 0.2s;">Tampilkan</button>
                             </form>

                             <!-- Absensi Button -->
                             <button class="btn-add" onclick="document.getElementById('attendanceModal').style.display='block'" title="Buat Absensi" style="background: rgba(255,255,255,0.2); color: #fff; border: 1px solid rgba(255,255,255,0.3); padding: 0 16px; height: 42px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s;">
                                <svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' style='display:inline-block; vertical-align:middle; line-height:1;'><path d='M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2'/><rect x='8' y='2' width='8' height='4' rx='1' ry='1'/></svg> + Absensi
                             </button>
                        </div>
                    </div>


                    <?php
// Determine active meeting to show (default to next available)
if (!$active_meeting) {
    $next = 1;
    if (!empty($existing_meetings)) {
        $next = max($existing_meetings) + 1;
    }
    if ($next > 16)
        $next = 16;
    $active_meeting = $next;
}

// Reload data for this active meeting if not already loaded
if (empty($attendance_data) && $active_meeting) {
    $stmt = $pdo->prepare("SELECT id FROM assignments WHERE teacher_class_id = ? AND meeting_number = ? AND assignment_type = 'absensi'");
    $stmt->execute([$class_id, $active_meeting]);
    $current_assign = $stmt->fetch();
    if ($current_assign) {
        $stmt = $pdo->prepare("SELECT student_id, status, submitted_at FROM submissions WHERE assignment_id = ?");
        $stmt->execute([$current_assign['id']]);
        $raw_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $attendance_data = [];
        foreach ($raw_data as $row) {
            $attendance_data[$row['student_id']] = [
                'status' => $row['status'],
                'time' => $row['submitted_at']
            ];
        }
    }
}
?>

                    <!-- Attendance Workspace (Always Visible) -->
                    <div class="attendance-workspace" style="margin-top: 20px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 12px;">
                            <h4 style="margin:0; font-size: 1rem;">
                                Data Absensi Pertemuan Ke-<?php echo $active_meeting; ?>
                                <?php if (!in_array($active_meeting, $existing_meetings)): ?>
                                    <span style="font-size:0.75rem; color:#f59e0b; background:#fffbeb; padding:2px 8px; border-radius:4px; margin-left:8px;">(Baru)</span>
                                <?php
endif; ?>
                            </h4>
                            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                                <?php if (in_array($active_meeting, $existing_meetings)): ?>
                                <a href="view_attendance.php?class_id=<?php echo $class_id; ?>&meeting=<?php echo $active_meeting; ?>&print=true" target="_blank" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 22px; background: linear-gradient(135deg, #3b82f6, #1d4ed8); color: #fff; border-radius: 12px; font-size: 0.95rem; font-weight: 700; text-decoration: none; box-shadow: 0 4px 12px rgba(29,78,216,0.35); transition: all 0.2s ease; border: 2px solid transparent;"><svg width='18' height='18' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><polyline points='6 9 6 2 18 2 18 9'></polyline><path d='M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2'></path><rect x='6' y='14' width='12' height='8'></rect></svg> Cetak / Download</a>
                                <form method="POST" onsubmit="return confirm('Yakin ingin menghapus seluruh data absensi pertemuan ini? Data yang sudah diisi akan hilang.');" style="margin:0;">
                                    <input type="hidden" name="delete_attendance" value="1">
                                    <input type="hidden" name="meeting_number" value="<?php echo $active_meeting; ?>">
                                    <button type="submit" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 22px; background: linear-gradient(135deg, #ef4444, #b91c1c); color: #fff; border: 2px solid transparent; border-radius: 12px; font-size: 0.95rem; font-weight: 700; cursor: pointer; box-shadow: 0 4px 12px rgba(185,28,28,0.35); transition: all 0.2s ease;"><svg width='18' height='18' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><polyline points='3 6 5 6 21 6'></polyline><path d='M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2'></path><line x1='10' y1='11' x2='10' y2='17'></line><line x1='14' y1='11' x2='14' y2='17'></line></svg> Hapus Data</button>
                                </form>
                                <?php
endif; ?>
                            </div>
                        </div>

                        <form method="POST">
                            <input type="hidden" name="save_attendance" value="1">
                            <input type="hidden" name="meeting_number" value="<?php echo $active_meeting; ?>">
                            
                            <div style="overflow-x: auto; border-radius: 12px; border: 1px solid #e2e8f0;">
                                <table class="att-input-table">
                                    <thead>
                                        <tr>
                                            <th style="width: 50px;">No</th>
                                            <th style="width: 100px; text-align: left;">NIS</th>
                                            <th style="text-align: left;">Nama Siswa</th>
                                            <th style="width: 80px;">Hadir</th>
                                            <th style="width: 80px;">Sakit</th>
                                            <th style="width: 80px;">Izin</th>
                                            <th style="width: 80px;">Alpha</th>
                                            <th style="width: 80px;">Terlambat</th>
                                            <th style="width: 120px;">Waktu Absen</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
foreach ($students as $idx => $s):
    $s_data = $attendance_data[$s['id']] ?? null;
    $status = $s_data ? $s_data['status'] : 'alpha';
    $time = $s_data ? $s_data['time'] : null;
?>
                                        <tr>
                                            <td style="text-align: center; color: #94a3b8; font-weight: 600;"><?php echo $idx + 1; ?></td>
                                            <td style="font-family: monospace; color: #64748b;"><?php echo htmlspecialchars($s['nis'] ?? '-'); ?></td>
                                            <td class="name-cell">
                                                <div style="font-weight: 600; color: #1e293b;"><?php echo htmlspecialchars($s['full_name']); ?></div>
                                                <div style="font-size: 0.75rem; color: #64748b;"><?php echo htmlspecialchars($s['username']); ?></div>
                                            </td>
                                            
                                            <!-- Hadir -->
                                            <td class="radio-cell <?php echo($status == 'hadir') ? 'selected-hadir' : ''; ?>">
                                                <label style="display: block; width: 100%; height: 100%; cursor: pointer;">
                                                    <input type="radio" name="status[<?php echo $s['id']; ?>]" value="hadir" <?php echo($status == 'hadir') ? 'checked' : ''; ?>>
                                                </label>
                                            </td>
                                            
                                            <!-- Sakit -->
                                            <td class="radio-cell <?php echo($status == 'sakit') ? 'selected-sakit' : ''; ?>">
                                                <label style="display: block; width: 100%; height: 100%; cursor: pointer;">
                                                    <input type="radio" name="status[<?php echo $s['id']; ?>]" value="sakit" <?php echo($status == 'sakit') ? 'checked' : ''; ?>>
                                                </label>
                                            </td>

                                            <!-- Izin -->
                                            <td class="radio-cell <?php echo($status == 'izin') ? 'selected-izin' : ''; ?>">
                                                <label style="display: block; width: 100%; height: 100%; cursor: pointer;">
                                                    <input type="radio" name="status[<?php echo $s['id']; ?>]" value="izin" <?php echo($status == 'izin') ? 'checked' : ''; ?>>
                                                </label>
                                            </td>

                                            <!-- Alpha -->
                                            <td class="radio-cell <?php echo($status == 'alpha') ? 'selected-alpha' : ''; ?>">
                                                <label style="display: block; width: 100%; height: 100%; cursor: pointer;">
                                                    <input type="radio" name="status[<?php echo $s['id']; ?>]" value="alpha" <?php echo($status == 'alpha') ? 'checked' : ''; ?>>
                                                </label>
                                            </td>

                                            <!-- Terlambat -->
                                            <td class="radio-cell <?php echo($status == 'terlambat') ? 'selected-terlambat' : ''; ?>">
                                                <label style="display: block; width: 100%; height: 100%; cursor: pointer;">
                                                    <input type="radio" name="status[<?php echo $s['id']; ?>]" value="terlambat" <?php echo($status == 'terlambat') ? 'checked' : ''; ?>>
                                                </label>
                                            </td>

                                            <!-- Waktu -->
                                            <td style="text-align: center;">
                                                <?php if ($time): ?>
                                                    <span class="time-badge"><?php echo date('H:i', strtotime($time)); ?></span>
                                                    <div style="font-size: 0.65rem; color: #94a3b8; margin-top: 2px;"><?php echo date('d/m', strtotime($time)); ?></div>
                                                <?php
    else: ?>
                                                    <span style="color: #cbd5e1;">-</span>
                                                <?php
    endif; ?>
                                            </td>
                                        </tr>
                                        <?php
endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <div style="margin-top: 20px; text-align: right;">
                                <button type="submit" class="btn-submit" style="width: auto; padding: 10px 24px;"><svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' style='display:inline-block; vertical-align:middle; line-height:1;'><path d='M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z'/><polyline points='17 21 17 13 7 13 7 21'/><polyline points='7 3 7 8 15 8'/></svg> Simpan Absensi</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        </div>
    </main>
</div>

<!-- Ã¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢Â Material Modal Ã¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢Â -->
<div id="materialModal" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-top">
            <h2>Tambah Materi</h2>
            <button class="modal-close" onclick="document.getElementById('materialModal').style.display='none'">&times;</button>
        </div>
        <form method="POST" enctype="multipart/form-data" onsubmit="return validateMaterialUpload(this)">
            <input type="hidden" name="add_material" value="1">
            <div class="form-group">
                <label>Judul Materi</label>
                <input type="text" name="title" required placeholder="Contoh: Slide Presentasi Bab 1">
            </div>
            <div class="form-group">
                <label>Deskripsi</label>
                <textarea name="description" rows="3" required></textarea>
            </div>
            <div class="form-group">
                <label>Tipe Materi</label>
                <div class="radio-group">
                    <label><input type="radio" name="type" value="file" checked onclick="toggleMatInput('file')"><span> Upload File</span></label>
                    <label><input type="radio" name="type" value="link" onclick="toggleMatInput('link')"><span> Link</span></label>
                </div>
            </div>
            <div id="mat-input-file" class="form-group">
                <label>Pilih File (Max 20MB) - PDF, Word, PPT, Excel, EPUB, Video</label>
                <input type="file" name="file" accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.epub,.mp4,.avi,.mov">
            </div>
            <div id="mat-input-link" class="form-group" style="display:none;">
                <label>URL Link (Youtube, Zoom, GMeet, dll)</label>
                <input type="url" name="link_url" placeholder="https://zoom.us/..., https://meet.google.com/...">
            </div>
            <button type="submit" class="btn-submit">Simpan Materi</button>
        </form>
    </div>
</div>

<!-- Ã¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢Â Assignment Modal (TUGAS ONLY) Ã¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢Â -->
<div id="assignmentModal" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-top">
            <h2> Tambah Tugas</h2>
            <button class="modal-close" onclick="document.getElementById('assignmentModal').style.display='none'">&times;</button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="add_assignment" value="1">
            <input type="hidden" name="assignment_type" value="tugas">

            <div class="form-group">
                <label>Judul Tugas</label>
                <input type="text" name="a_title" required placeholder="Contoh: Latihan Soal Bab 1">
            </div>
            <div class="form-group">
                <label>Deskripsi / Instruksi</label>
                <textarea name="a_description" rows="3" placeholder="Jelaskan detail tugas..."></textarea>
            </div>
            <div class="form-group">
                <label>Tipe Lampiran</label>
                <div class="radio-group">
                    <label><input type="radio" name="a_type" value="file" checked onclick="toggleAssignmentInput('file')"><span> Upload File</span></label>
                    <label><input type="radio" name="a_type" value="link" onclick="toggleAssignmentInput('link')"><span> Link (Google Form, dll)</span></label>
                </div>
            </div>
            <div id="assign-input-file" class="form-group">
                <label>Lampiran (Bisa lebih dari satu)</label>
                <input type="file" name="a_attachments[]" multiple accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.epub,.mp4,.avi,.mov">
                <small style="color: grey; display: block; margin-top: 5px;">Tekan Ctrl (Windows) atau Command (Mac) untuk memilih banyak file.</small>
            </div>
            <div id="assign-input-link" class="form-group" style="display:none;">
                <label>URL Link Pengumpulan / Tugas (Contoh: Google Form)</label>
                <input type="url" name="a_link_url" placeholder="https://forms.gle/...">
            </div>
            <div class="form-group">
                <label>Deadline</label>
                <input type="datetime-local" name="a_deadline" required>
            </div>

            <button type="submit" class="btn-submit">Terbitkan Tugas</button>
        </form>
    </div>
</div>

<!-- Ã¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢Â Attendance Modal (ABSENSI ONLY) Ã¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢Â -->
<div id="attendanceModal" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-top">
            <h2> Buat Jadwal Absensi</h2>
            <button class="modal-close" onclick="document.getElementById('attendanceModal').style.display='none'">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="add_assignment" value="1">
            <input type="hidden" name="assignment_type" value="absensi">

            <div class="form-group">
                <label>Pertemuan Ke</label>
                <select name="meeting_number" id="meetingNumber">
                    <?php for ($i = 1; $i <= 16; $i++): ?>
                    <option value="<?php echo $i; ?>">Pertemuan <?php echo $i; ?></option>
                    <?php
endfor; ?>
                </select>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label>Dari Jam</label>
                    <select name="jam_start" id="jamStart" onchange="updateJamLabels()">
                        <!-- JS populated -->
                    </select>
                    <input type="hidden" name="jam_start_label" id="jamStartLabel">
                    <input type="hidden" name="jam_start_time" id="jamStartTime">
                </div>
                <div class="form-group">
                    <label>Sampai Jam</label>
                    <select name="jam_end" id="jamEnd" onchange="updateJamLabels()">
                        <!-- JS populated -->
                    </select>
                    <input type="hidden" name="jam_end_label" id="jamEndLabel">
                </div>
            </div>

            <button type="submit" class="btn-submit">Buat Absensi</button>
        </form>
    </div>
</div>

<!-- Ã¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢Â Edit Class Modal Ã¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢Â -->
<div id="editClassModal" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-top">
            <h2>Edit Kelas</h2>
            <button class="modal-close" onclick="document.getElementById('editClassModal').style.display='none'">&times;</button>
        </div>
        <form method="POST" style="margin-bottom: 2rem;">
            <input type="hidden" name="edit_class" value="1">
            <div class="form-group">
                <label>Nama Kelas (Custom)</label>
                <input type="text" name="class_name" value="<?php echo htmlspecialchars($class['name']); ?>" required>
            </div>
            <button type="submit" class="btn-submit">Simpan Perubahan</button>
        </form>

        <h3 style="font-size: 1rem; margin-bottom: 10px; color: #334155; display: flex; justify-content: space-between; align-items: center;">
            Daftar Siswa
            <?php if ($class['is_special_class'] == 1): ?>
                <button type="button" class="btn-add" onclick="document.getElementById('addStudentSection').style.display='block'">+ Tambah Siswa</button>
            <?php endif; ?>
        </h3>

        <?php if ($class['is_special_class'] == 1): ?>
        <div id="addStudentSection" style="display: none; padding: 15px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 15px;">
            <h4 style="margin-top: 0; margin-bottom: 10px; font-size: 0.9rem;">Pilih Siswa untuk Ditambahkan</h4>
            <div style="display: flex; gap: 10px; margin-bottom: 15px;">
                <div style="flex: 1;">
                    <select id="studentSelect2" style="width: 100%;" class="form-control">
                        <option value="">-- Cari NIS atau Nama Siswa --</option>
                        <?php
                        // Fetch ALL ACTIVE students to pick from (including username as fallback for empty NIS)
                        $all_stu_stmt = $pdo->query("SELECT id, full_name, nis, username, (SELECT name FROM classes c WHERE c.id = u.class_id) as class_name FROM users u WHERE role = 'siswa' AND status = 'active' ORDER BY class_name ASC, full_name ASC");
                        while ($stu = $all_stu_stmt->fetch()) {
                            // Banyak data siswa memiliki field 'nis' = null, tapi NIS/NISN aslinya disimpan sebagai 'username'.
                            $rawNis = (trim($stu['nis']) !== '' && $stu['nis'] !== null) ? trim($stu['nis']) : trim($stu['username']);
                            $nisText = htmlspecialchars($rawNis);
                            $nameText = htmlspecialchars($stu['full_name']);
                            $classText = htmlspecialchars($stu['class_name'] ?? 'Tanpa Kelas');
                            
                            $optionText = $nisText ? "($nisText) $nameText" : "$nameText - $classText";
                            
                            // A dedicated string containing all searchable text ensuring no nulls break JS
                            $searchStr = htmlspecialchars(strtolower($nisText . ' ' . $nameText . ' ' . $classText));
                            
                            echo '<option value="'.$stu['id'].'" data-search="'.$searchStr.'" data-nis="'.$nisText.'" data-name="'.$nameText.'" data-class="'.$classText.'">'.$optionText.'</option>';
                        }
                        ?>
                    </select>
                </div>
                <button type="button" class="btn-add" id="btnAddToTemp" style="width: auto; padding: 8px 16px;">Tambah ke Daftar</button>
            </div>

            <form method="POST" id="formSaveStudents">
                <div style="border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; display: none; background: #fff;" id="tempStudentContainer">
                    <table class="att-input-table" style="margin: 0; width: 100%;">
                        <thead style="background: #f1f5f9;">
                            <tr>
                                <th style="padding: 8px 12px; text-align: left; font-size: 0.85rem; color: #475569;">NIS</th>
                                <th style="padding: 8px 12px; text-align: left; font-size: 0.85rem; color: #475569;">Nama Siswa</th>
                                <th style="padding: 8px 12px; text-align: left; font-size: 0.85rem; color: #475569;">Kelas Asal</th>
                                <th style="padding: 8px 12px; text-align: center; font-size: 0.85rem; color: #475569; width: 60px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="tempStudentBody">
                            <!-- JS akan mengisi ini -->
                        </tbody>
                    </table>
                    <div style="padding: 10px; text-align: right; border-top: 1px solid #e2e8f0;">
                        <button type="submit" class="btn-submit" style="padding: 8px 16px; width: auto; display: inline-block;">Simpan Perubahan</button>
                    </div>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <div style="max-height: 200px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 8px;">
            <table class="att-input-table">
                <tbody>
                    <?php foreach ($students as $s): ?>
                    <tr>
                        <td style="padding: 8px 12px; font-size: 0.9rem;">
                            <strong><?php echo htmlspecialchars($s['full_name']); ?></strong>
                            <br><small style="color:#64748b"><?php echo htmlspecialchars($s['username']); ?></small>
                        </td>
                        <td style="text-align: right; padding: 8px 12px;">
                            <form method="POST" onsubmit="return confirm('Keluarkan siswa ini dari kelas? Siswa akan kehilangan akses ke SEMUA mata pelajaran di kelas ini.');" style="margin:0;">
                                <input type="hidden" name="remove_student_id" value="<?php echo $s['id']; ?>">
                                <button type="submit" style="background:none; border:none; cursor:pointer;" title="Keluarkan Siswa"><svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' style='display:inline-block; vertical-align:middle; line-height:1;'><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
                            </form>
                        </td>
                    </tr>
                    <?php
endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Ã¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢Â Modal Add Google Meet Ã¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢Â -->
<div id="meetModal" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-top">
            <h2>Bagikan Link Google Meet</h2>
            <button class="modal-close" onclick="document.getElementById('meetModal').style.display='none'">&times;</button>
        </div>
        <form method="POST" id="formMeet" onsubmit="return normalizeMeetLink() && validateMeetTime()">
            <input type="hidden" name="submit_meet_link" value="1">
            <input type="hidden" name="start_time" id="meet_start_time">
            <input type="hidden" name="end_time" id="meet_end_time">
            
            <div class="form-group">
                <label>Link / URL Google Meet</label>
                <input type="text" name="meet_link" id="meet_link_input"
                    placeholder="Contoh: uhw-agti-qry atau https://meet.google.com/uhw-agti-qry"
                    required autocomplete="off" style="font-family: monospace;">
                <small style="color:#64748b; font-size:0.78rem; margin-top:4px; display:block;">
                    &#128161; Boleh tempel kode singkat (cth: <strong>uhw-agti-qry</strong>) atau link lengkap.
                </small>
            </div>
            <div class="form-group">
                <label>Tanggal Pelaksanaan</label>
                <input type="date" id="meet_date" required onchange="populateMeetJam()">
            </div>
            <div class="form-group" style="display:flex; gap:10px;">
                <div style="flex:1;">
                    <label>Mulai Jam Ke-</label>
                    <select id="meet_jam_start" required onchange="updateMeetTimeHidden()">
                        <option value="">-- Pilih Hari Dulu --</option>
                    </select>
                </div>
                <div style="flex:1;">
                    <label>Selesai Jam Ke-</label>
                    <select id="meet_jam_end" required onchange="updateMeetTimeHidden()">
                        <option value="">-- Pilih Hari Dulu --</option>
                    </select>
                </div>
            </div>
            <div id="meet_time_desc" style="font-size: 0.85rem; color: #64748b; margin-bottom: 1rem; text-align: center; font-weight: 500;">
                Mulai: - | Selesai: -
            </div>
            <button type="submit" class="btn-submit" style="background: #10b981;">Bagikan Link Meet</button>
        </form>
    </div>
</div>

<!-- Ã¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢Â Statistics Modal Ã¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢Â -->
<div id="statisticsModal" class="modal-overlay">
    <div class="modal-box" style="max-width: 700px;">
        <div class="modal-top">
            <h2>Statistik Kinerja Kelas</h2>
            <button class="modal-close" onclick="document.getElementById('statisticsModal').style.display='none'">&times;</button>
        </div>
        <div style="height: 300px;">
            <canvas id="classChart"></canvas>
        </div>
        <p style="text-align: center; color: #64748b; font-size: 0.85rem; margin-top: 10px;">
            Grafik menunjukkan rata-rata nilai kelas pada setiap tugas.
        </p>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
let statsLoaded = false;
let myChart = null;

$(document).ready(function() {
    function formatStudentOption (student) {
        if (!student.id) {
            return student.text;
        }
        var nis = $(student.element).data('nis');
        var name = $(student.element).data('name');
        var className = $(student.element).data('class');
        
        var $studentHtml = $(
            '<div style="display:flex; justify-content:space-between; align-items:center;">' +
                '<div>' +
                    '<div style="font-weight:600; color:#334155;">' + name + '</div>' +
                    '<div style="font-size:0.8rem; color:#64748b;">' + (nis ? 'NIS: ' + nis : 'NIS: -') + '</div>' +
                '</div>' +
                '<span style="font-size:0.75rem; background:#f1f5f9; padding:2px 6px; border-radius:4px; color:#475569;">' + className + '</span>' +
            '</div>'
        );
        return $studentHtml;
    }

    function formatStudentSelection (student) {
        if (!student.id) return student.text;
        var nis = $(student.element).data('nis');
        var name = $(student.element).data('name');
        return nis ? name + " (" + nis + ")" : name;
    }

    function customMatcher(params, data) {
        if ($.trim(params.term) === '') {
            return data;
        }

        if (typeof data.text === 'undefined') {
            return null;
        }

        var term = params.term.toLowerCase();
        
        var searchString = data.text.toLowerCase();
        if (data.element) {
            var ds = $(data.element).data('search');
            if (ds) {
                searchString += ' ' + ds;
            }
        }

        if (searchString.indexOf(term) > -1) {
            return data;
        }

        return null;
    }

    $('#studentSelect2').select2({
        placeholder: "Cari berdasarkan NIS atau Nama Siswa...",
        allowClear: true,
        width: '100%',
        templateResult: formatStudentOption,
        templateSelection: formatStudentSelection,
        matcher: customMatcher
    });

    $('#btnAddToTemp').click(function() {
        const select = document.getElementById('studentSelect2');
        if (!select.value) return;

        const option = select.options[select.selectedIndex];
        const id = option.value;
        const nis = option.getAttribute('data-nis') || '-';
        const name = option.getAttribute('data-name');
        const className = option.getAttribute('data-class');

        if (tempSelectedStudents.has(id)) {
            alert("Siswa ini sudah ada di daftar sementara.");
            return;
        }

        // Add to map
        tempSelectedStudents.set(id, { nis, name, class: className });
        
        // Reset select2
        $('#studentSelect2').val(null).trigger('change');
        
        renderTempTable();
    });
});

let tempSelectedStudents = new Map();

function removeTempStudent(id) {
    tempSelectedStudents.delete(id.toString());
    renderTempTable();
}

function renderTempTable() {
    const tbody = document.getElementById('tempStudentBody');
    const container = document.getElementById('tempStudentContainer');
    
    tbody.innerHTML = '';
    
    if (tempSelectedStudents.size === 0) {
        container.style.display = 'none';
        return;
    }
    
    container.style.display = 'block';
    
    tempSelectedStudents.forEach((student, id) => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td style="padding: 8px 12px; font-size: 0.9rem; border-bottom: 1px solid #e2e8f0;">${student.nis}</td>
            <td style="padding: 8px 12px; font-size: 0.9rem; border-bottom: 1px solid #e2e8f0;"><strong>${student.name}</strong></td>
            <td style="padding: 8px 12px; font-size: 0.9rem; border-bottom: 1px solid #e2e8f0;">${student.class}</td>
            <td style="padding: 8px 12px; text-align: center; border-bottom: 1px solid #e2e8f0;">
                <input type="hidden" name="add_special_student_ids[]" value="${id}">
                <button type="button" onclick="removeTempStudent('${id}')" style="background: none; border: none; color: #ef4444; cursor: pointer; padding: 4px;" title="Hapus"><svg width='18' height='18' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg></button>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

function loadChart() {
    if (statsLoaded) return;

    fetch('get_class_stats.php?class_id=<?php echo $class_id; ?>')
        .then(response => response.json())
        .then(data => {
            const ctx = document.getElementById('classChart').getContext('2d');
            
            const labels = data.map(item => item.title);
            const values = data.map(item => item.avg_grade);

            if (myChart) myChart.destroy();

            myChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Rata-rata Nilai Kelas',
                        data: values,
                        borderColor: '#4f46e5',
                        backgroundColor: 'rgba(79, 70, 229, 0.1)',
                        borderWidth: 2,
                        tension: 0.3,
                        fill: true,
                        pointBackgroundColor: '#fff',
                        pointBorderColor: '#4f46e5',
                        pointBorderWidth: 2,
                        pointRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            grid: { color: '#f1f5f9' }
                        },
                        x: {
                            grid: { display: false }
                        }
                    },
                    plugins: {
                        legend: { display: false }
                    }
                }
            });
            statsLoaded = true;
        })
        .catch(err => console.error('Error loading stats:', err));
}

// Material modal toggle
function toggleMatInput(type) {
    document.getElementById('mat-input-file').style.display = type === 'file' ? 'block' : 'none';
    document.getElementById('mat-input-link').style.display = type === 'link' ? 'block' : 'none';
}

function validateMaterialUpload(form) {
    const typeRadios = form.querySelectorAll('input[name="type"]');
    let selectedType = 'file';
    for (const radio of typeRadios) {
        if (radio.checked) {
            selectedType = radio.value;
            break;
        }
    }

    if (selectedType === 'file') {
        const fileInput = form.querySelector('input[type="file"]');
        if (fileInput && fileInput.files.length > 0) {
            const fileSize = fileInput.files[0].size; // in bytes
            const maxSize = 20 * 1024 * 1024; // 20MB
            
            if (fileSize > maxSize) {
                alert("Peringatan: Ukuran file materi terlalu besar!\nMaksimal ukuran yang diizinkan adalah 20MB.");
                fileInput.value = ''; // Reset file input
                return false; // Prevent form submission
            }
        }
    }
    return true; // Allow form submission
}

// Assignment modal toggle
function toggleAssignmentInput(type) {
    document.getElementById('assign-input-file').style.display = type === 'file' ? 'block' : 'none';
    document.getElementById('assign-input-link').style.display = type === 'link' ? 'block' : 'none';
}

// Jam pelajaran schedules
const jamBiasa = [
    { id: 1, label: 'Jam 1 (07:30 - 08:15)', end: '08:15' },
    { id: 2, label: 'Jam 2 (08:15 - 09:00)', end: '09:00' },
    { id: 3, label: 'Jam 3 (09:00 - 09:45)', end: '09:45' },
    { id: 4, label: 'Jam 4 (09:45 - 10:30)', end: '10:30' },
    { id: 5, label: 'Jam 5 (10:45 - 11:30)', end: '11:30' },
    { id: 6, label: 'Jam 6 (11:30 - 12:15)', end: '12:15' },
    { id: 7, label: 'Jam 7 (12:50 - 13:30)', end: '13:30' },
    { id: 8, label: 'Jam 8 (13:30 - 14:10)', end: '14:10' },
    { id: 9, label: 'Jam 9 (14:10 - 14:50)', end: '14:50' },
    { id: 10, label: 'Jam 10 (14:50 - 15:30)', end: '15:30' },
];

const jamJumat = [
    { id: 1, label: 'Jam 1 (07:15 - 08:00)', end: '08:00' },
    { id: 2, label: 'Jam 2 (08:00 - 08:45)', end: '08:45' },
    { id: 3, label: 'Jam 3 (08:45 - 09:30)', end: '09:30' },
    { id: 4, label: 'Jam 4 (09:30 - 10:15)', end: '10:15' },
    { id: 5, label: 'Jam 5 (10:25 - 11:10)', end: '11:10' },
    { id: 6, label: 'Jam 6 (11:10 - 11:55)', end: '11:55' },
    { id: 7, label: 'Jam 7 (13:15 - 14:00)', end: '14:00' },
    { id: 8, label: 'Jam 8 (14:00 - 14:45)', end: '14:45' },
];

function populateJam() {
    const sStart = document.getElementById('jamStart');
    const sEnd = document.getElementById('jamEnd');
    if(!sStart || !sEnd) return;

    sStart.innerHTML = '';
    sEnd.innerHTML = '';
    
    const today = new Date();
    const isFriday = today.getDay() === 5;
    const schedule = isFriday ? jamJumat : jamBiasa;

    schedule.forEach(j => {
        const optS = document.createElement('option');
        optS.value = j.start;  // simpan waktu mulai (HH:MM) sebagai value
        optS.textContent = j.label;
        sStart.appendChild(optS);

        const optE = document.createElement('option');
        optE.value = j.end;
        optE.textContent = j.label;
        sEnd.appendChild(optE);
    });
    
    updateJamLabels();
}

function updateJamLabels() {
    const sStart = document.getElementById('jamStart');
    const sEnd = document.getElementById('jamEnd');
    
    if (sStart && sEnd && sStart.options.length > 0) {
        const startText = sStart.options[sStart.selectedIndex].text;
        const endText = sEnd.options[sEnd.selectedIndex].text;
        document.getElementById('jamStartLabel').value = startText.split(' ')[1];
        document.getElementById('jamEndLabel').value = endText.split(' ')[1];

        // Value select jamStart sekarang langsung berisi HH:MM (misal "08:00")
        const startVal = sStart.value; // e.g. "08:00"
        if (startVal) {
            document.getElementById('jamStartTime').value = startVal;
        }
    }
}

// MEET JAM LOGIC
function populateMeetJam() {
    const sDate = document.getElementById('meet_date');
    const sStart = document.getElementById('meet_jam_start');
    const sEnd = document.getElementById('meet_jam_end');
    if(!sDate || !sStart || !sEnd) return;

    if(!sDate.value) {
        sStart.innerHTML = '<option value="">-- Pilih Hari Dulu --</option>';
        sEnd.innerHTML = '<option value="">-- Pilih Hari Dulu --</option>';
        updateMeetTimeHidden();
        return;
    }

    const d = new Date(sDate.value);
    const isFriday = d.getDay() === 5; // 5 is Friday
    const schedule = isFriday ? jamJumat : jamBiasa;

    // Save previous
    const prevStart = sStart.value;
    const prevEnd = sEnd.value;

    sStart.innerHTML = '';
    sEnd.innerHTML = '';

    schedule.forEach(j => {
        // e.g. "Jam 3 (09:00 - 09:45)"
        const match = j.label.match(/\((.*?)\s*-/);
        const startTime = match ? match[1].trim() : j.end;
        
        const optS = document.createElement('option');
        optS.value = startTime; 
        optS.textContent = j.label;
        sStart.appendChild(optS);

        const optE = document.createElement('option');
        optE.value = j.end;
        optE.textContent = j.label;
        sEnd.appendChild(optE);
    });

    sStart.value = prevStart; // Restore if valid
    sEnd.value = prevEnd;     // Restore if valid
    
    if(!sStart.value) sStart.selectedIndex = 0;
    if(!sEnd.value) sEnd.selectedIndex = schedule.length - 1; 

    updateMeetTimeHidden();
}

function updateMeetTimeHidden() {
    const sDate = document.getElementById('meet_date');
    const sStart = document.getElementById('meet_jam_start');
    const sEnd = document.getElementById('meet_jam_end');
    const hStart = document.getElementById('meet_start_time');
    const hEnd = document.getElementById('meet_end_time');
    const desc = document.getElementById('meet_time_desc');

    if (sDate && sStart && sEnd && hStart && hEnd) {
        if (!sDate.value || !sStart.value || !sEnd.value) {
            hStart.value = ""; hEnd.value = "";
            if (desc) desc.textContent = "Mulai: - | Selesai: -";
            return;
        }

        hStart.value = sDate.value + ' ' + sStart.value + ':00';
        hEnd.value = sDate.value + ' ' + sEnd.value + ':00';
        
        if (desc) {
            desc.innerHTML = `Mulai: <strong>${sStart.value}</strong> | Selesai: <strong>${sEnd.value}</strong>`;
        }
    }
}

function normalizeMeetLink() {
    const input = document.getElementById('meet_link_input');
    if (!input) return true;

    let val = input.value.trim();

    // Pattern: plain code like "abc-defg-hij" (Google Meet room code format)
    // Google Meet codes are 3 groups: 3-4-3 lowercase letters separated by hyphens
    const codePattern = /^[a-z]{3}-[a-z]{4}-[a-z]{3}$/i;

    if (codePattern.test(val)) {
        // It's a bare code Ã¢â‚¬â€œ make a full URL
        val = 'https://meet.google.com/' + val.toLowerCase();
        input.value = val;
    } else if (/^meet\.google\.com\//i.test(val)) {
        // Missing protocol
        val = 'https://' + val;
        input.value = val;
    }

    // Final sanity check Ã¢â‚¬â€œ must look like a valid URL now
    try {
        const u = new URL(val);
        if (!u.hostname.includes('google.com')) {
            alert('Link yang dimasukkan bukan link Google Meet yang valid.\nContoh kode: uhw-agti-qry');
            return false;
        }
    } catch (e) {
        alert('Format link tidak valid.\nMasukkan kode Meet (cth: uhw-agti-qry) atau link lengkap.');
        return false;
    }

    return true;
}

function validateMeetTime() {
    const sStart = document.getElementById('meet_start_time').value;
    const sEnd = document.getElementById('meet_end_time').value;
    if (!sStart || !sEnd) {
        alert("Pilih tanggal dan jam pelajaran terlebih dahulu.");
        return false;
    }
    
    // Ensure end time is greater than start time
    if (new Date(sEnd) <= new Date(sStart)) {
        alert("Jam Selesai harus lebih besar dari Jam Mulai.");
        return false;
    }
    return true;
}

// Init
document.addEventListener('DOMContentLoaded', function() {
    populateJam();
});

// Close modals on clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('modal-overlay')) {
        event.target.style.display = "none";
    }
}
</script>

</body>
</html>


