<?php
// src/guru/class_materials.php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guru') {
    header("Location: ../../login.php");
    exit;
}

$teacher_id = $_SESSION['user_id'];
$class_id = $_GET['id'] ?? 0;


// Verify Class Ownership or Legacy
if ($class_id === 'legacy') {
    $class = [
        'name' => 'Materi Lama / Uncategorized',
        'subject' => 'Materi yang belum dikelompokkan ke kelas baru',
        'is_legacy' => true
    ];
}
else {
    $stmt = $pdo->prepare("SELECT * FROM teacher_classes WHERE id = ? AND teacher_id = ?");
    $stmt->execute([$class_id, $teacher_id]);
    $class = $stmt->fetch();

    if (!$class) {
        die("Kelas tidak ditemukan atau Anda tidak memiliki akses.");
    }
}

// Handle Add Material
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_material'])) {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $type = $_POST['type']; // 'file' or 'link'

    $file_path = null;
    $material_type = 'pdf'; // Default

    // Logic for File vs Link
    if ($type === 'file') {
        if (isset($_FILES['file']) && $_FILES['file']['error'] == 0) {
            // Check file size (20MB Limit)
            if ($_FILES['file']['size'] > 20 * 1024 * 1024) {
                $error = "Ukuran file terlalu besar. Maksimal 20MB.";
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

                // Detect Type
                if (in_array($ext, ['mp4', 'avi', 'mov']))
                    $material_type = 'video';
                elseif (in_array($ext, ['doc', 'docx']))
                    $material_type = 'word';
                elseif (in_array($ext, ['ppt', 'pptx']))
                    $material_type = 'ppt';
                else
                    $material_type = 'pdf';

                if (move_uploaded_file($_FILES["file"]["tmp_name"], $target_file)) {
                    $file_path = $rel_dir . $file_name;
                }
                else {
                    $error = "Gagal upload file.";
                }
            }
        }
        else {
            $error = "Pilih file materi.";
        }
    }
    elseif ($type === 'link') {
        $link_url = trim($_POST['link_url']);
        if (!empty($link_url)) {
            $file_path = $link_url;
            $material_type = 'link';
        }
        else {
            $error = "Masukkan link materi.";
        }
    }

    if (empty($error) && $file_path) {
        // Updated to include class_id and subject_id
        $stmt = $pdo->prepare("INSERT INTO materials (title, description, type, file_path, teacher_id, teacher_class_id, class_id, subject_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$title, $description, $material_type, $file_path, $teacher_id, $class_id, $class['class_id'], $class['subject_id']])) {
            $_SESSION['flash'] = ['type' => 'success', 'message' => "Materi berhasil ditambahkan!"];
        }
        else {
            $_SESSION['flash'] = ['type' => 'error', 'message' => "Gagal menyimpan ke database."];
        }
    }
    else if (!empty($error)) {
        $_SESSION['flash'] = ['type' => 'error', 'message' => $error];
    }

    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}

// Handle Delete
if (isset($_POST['delete_id'])) {
    $del_id = $_POST['delete_id'];
    // Get file path to delete file if it's local
    $stmt = $pdo->prepare("SELECT file_path, type FROM materials WHERE id = ? AND teacher_class_id = ?");
    $stmt->execute([$del_id, $class_id]);
    $mat = $stmt->fetch();

    if ($mat) {
        if ($mat['type'] !== 'link' && file_exists("../../" . $mat['file_path'])) {
            unlink("../../" . $mat['file_path']);
        }
        $stmt = $pdo->prepare("DELETE FROM materials WHERE id = ?");
        $stmt->execute([$del_id]);
        $_SESSION['flash'] = ['type' => 'success', 'message' => "Materi dihapus."];
    }
    else {
        $_SESSION['flash'] = ['type' => 'error', 'message' => "Materi tidak ditemukan."];
    }
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}

// Fetch Materials for this Class
if ($class_id === 'legacy') {
    $stmt = $pdo->prepare("SELECT * FROM materials WHERE teacher_id = ? AND teacher_class_id IS NULL ORDER BY created_at DESC");
    $stmt->execute([$teacher_id]);
}
else {
    $stmt = $pdo->prepare("SELECT * FROM materials WHERE teacher_class_id = ? ORDER BY created_at DESC");
    $stmt->execute([$class_id]);
}
$materials = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta charset="UTF-8">
    <title>Materi <?php echo htmlspecialchars($class['name']); ?></title>
    <link rel="stylesheet" href="/public/assets/css/style.css">
    <script>
        function toggleInput(type) {
            document.getElementById('input-file').style.display = type === 'file' ? 'block' : 'none';
            document.getElementById('input-link').style.display = type === 'link' ? 'block' : 'none';
        }
    </script>
</head>
<body class="unified-layout">

<div class="app-container">
    <?php include '../templates/sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Dashboard Hero -->
        <div class="dashboard-hero">
            <div style="position: relative; z-index: 2;">
                <div style="margin-bottom: 1rem;">
                    <a href="manage_materials.php" style="color: rgba(255,255,255,0.8); text-decoration: none; font-size: 0.9rem; display: flex; align-items: center; gap: 6px; background: rgba(255,255,255,0.1); padding: 6px 12px; border-radius: 20px; width: fit-content; backdrop-filter: blur(4px);">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5"/><path d="M12 19l-7-7 7-7"/></svg>
                        Kembali ke Daftar Kelas
                    </a>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem;">
                    <div>
                        <h1 style="color: white; margin-bottom: 0.5rem;"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;vertical-align:middle;margin-right:8px;"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg> Materi <?php echo htmlspecialchars($class['name']); ?></h1>
                        <p style="color: rgba(255,255,255,0.8);"><?php echo htmlspecialchars($class['subject']); ?></p>
                    </div>
                    <?php if (!isset($class['is_legacy'])): ?>
                    <button onclick="document.getElementById('addMaterialModal').style.display='block'" class="btn" style="background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.3); backdrop-filter: blur(5px); display: flex; align-items: center; gap: 8px;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        Tambah Materi
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <div style="position: absolute; right: -50px; top: -50px; width: 200px; height: 200px; background: rgba(255,255,255,0.1); border-radius: 50%;"></div>
        </div>

        <div class="content-overlap">
        <?php
if (isset($_SESSION['flash'])) {
    $flash = $_SESSION['flash'];
    $bg = '#dcfce7';
    $color = '#166534';
    if ($flash['type'] == 'error') {
        $bg = '#fee2e2';
        $color = '#991b1b';
    }
    echo "<div style='background:$bg; color:$color; padding:15px; border-radius:8px; margin-bottom:20px;'>" . htmlspecialchars($flash['message']) . "</div>";
    unset($_SESSION['flash']);
}
?>

        <div class="page-section">
            <?php if (empty($materials)): ?>
                <div style="text-align: center; padding: 3rem; color: #94a3b8;">
                    <p>Belum ada materi di kelas ini.</p>
                </div>
            <?php
else: ?>
                <div style="display: flex; flex-direction: column; gap: 1rem;">
                    <?php foreach ($materials as $m): ?>
                        <div style="border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.5rem; display: flex; justify-content: space-between; align-items: flex-start;">
                            <div style="display: flex; gap: 15px; align-items: flex-start;">
                                <div style="font-size: 2.5rem;">
                                    <?php
        if ($m['type'] == 'pdf')
            echo "<svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' style='display:inline-block; vertical-align:middle; line-height:1;'><path d=\"M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20\"/></svg>";
        elseif ($m['type'] == 'word')
            echo "<svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' style='display:inline-block; vertical-align:middle; line-height:1;'><path d='M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20'/></svg>";
        elseif ($m['type'] == 'ppt')
            echo "<svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' style='display:inline-block; vertical-align:middle; line-height:1;'><path d='M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20'/></svg>";
        elseif ($m['type'] == 'video')
            echo "<svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' style='display:inline-block; vertical-align:middle; line-height:1;'><polygon points='23 7 16 12 23 17 23 7'/><rect x='1' y='5' width='15' height='14' rx='2' ry='2'/></svg>";
        elseif ($m['type'] == 'link')
            echo "<svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' style='display:inline-block; vertical-align:middle; line-height:1;'><path d=\"M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71\"/><path d=\"M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71\"/></svg>";
        else
            echo "<svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' style='display:inline-block; vertical-align:middle; line-height:1;'><path d=\"M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z\"/><polyline points=\"14 2 14 8 20 8\"/><line x1=\"16\" y1=\"13\" x2=\"8\" y2=\"13\"/><line x1=\"16\" y1=\"17\" x2=\"8\" y2=\"17\"/><polyline points=\"10 9 9 9 8 9\"/></svg>";
?>
                                </div>
                                <div>
                                    <h3 style="margin: 0 0 5px; font-size: 1.1rem;"><?php echo htmlspecialchars($m['title']); ?></h3>
                                    <p style="color: #64748b; font-size: 0.9rem; margin-bottom: 10px;"><?php echo htmlspecialchars($m['description']); ?></p>
                                    
                                    <?php if ($m['type'] == 'link'): ?>
                                        <a href="<?php echo htmlspecialchars($m['file_path']); ?>" target="_blank" class="btn btn-secondary" style="padding: 5px 15px; font-size: 0.85rem;">
                                            Buka Link Ã¢â€ &mdash;
                                        </a>
                                    <?php
        else: ?>
                                        <a href="../../<?php echo htmlspecialchars($m['file_path']); ?>" target="_blank" class="btn btn-secondary" style="padding: 5px 15px; font-size: 0.85rem;">
                                            Download File
                                        </a>
                                    <?php
        endif; ?>
                                </div>
                            </div>
                            <form method="POST" onsubmit="return confirm('Hapus materi ini?');">
                                <input type="hidden" name="delete_id" value="<?php echo $m['id']; ?>">
                                <button type="submit" style="background: none; border: none; cursor: pointer; color: #ef4444; font-size: 1.2rem;"><svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' style='display:inline-block; vertical-align:middle; line-height:1;'><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg></button>
                            </form>
                        </div>
                    <?php
    endforeach; ?>
                </div>
            <?php
endif; ?>
        </div>
        </div>

        <!-- Add Material Modal -->
        <div id="addMaterialModal" style="display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; overflow:auto; background-color:rgba(0,0,0,0.5); backdrop-filter:blur(4px);">
            <div style="background-color:#fff; margin:5% auto; padding:2rem; border-radius:16px; width:100%; max-width:600px; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25);">
                <div style="display:flex; justify-content:space-between; margin-bottom:1.5rem;">
                    <h2>Tambah Materi Baru</h2>
                    <span onclick="document.getElementById('addMaterialModal').style.display='none'" style="cursor:pointer; font-size:1.5rem;">&times;</span>
                </div>
                
                <form method="POST" enctype="multipart/form-data">
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
                        <div style="display: flex; gap: 15px; margin-top: 5px;">
                            <label style="cursor: pointer; display: flex; align-items: center; gap: 5px;">
                                <input type="radio" name="type" value="file" checked onclick="toggleInput('file')"> Upload File
                            </label>
                            <label style="cursor: pointer; display: flex; align-items: center; gap: 5px;">
                                <input type="radio" name="type" value="link" onclick="toggleInput('link')"> Link (YouTube/GDrive)
                            </label>
                        </div>
                    </div>

                    <div id="input-file" class="form-group" style="background: #f8fafc; padding: 15px; border-radius: 8px; border: 1px dashed #cbd5e1;">
                        <label>Pilih File (Max 20MB)</label>
                        <input type="file" name="file">
                        <small style="color: #64748b; display: block; margin-top: 5px;">Format: PDF, Word, PPT, Video</small>
                    </div>

                    <div id="input-link" class="form-group" style="display: none; background: #f8fafc; padding: 15px; border-radius: 8px; border: 1px dashed #cbd5e1;">
                        <label>URL Link</label>
                        <input type="url" name="link_url" placeholder="https://youtube.com/..." style="width: 100%;">
                    </div>

                    <button type="submit" class="btn" style="width: 100%; margin-top: 1rem;">Simpan Materi</button>
                    <button type="button" class="btn btn-secondary" onclick="document.getElementById('addMaterialModal').style.display='none'" style="width: 100%; margin-top: 10px;">Batal</button>
                </form>
            </div>
        </div>

    </main>
</div>

</body>
</html>


