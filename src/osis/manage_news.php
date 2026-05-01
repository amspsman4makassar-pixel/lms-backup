<?php
// src/osis/manage_news.php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'osis') {
    header("Location: ../../login.php");
    exit;
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    try {
        // Optional: Check if OSIS owns it? Requirement says "can be deleted by OSIS AND Admin", implying full power.
        // We will allow deleting ANY news for now to match the user request.
        $stmt = $pdo->prepare("DELETE FROM news WHERE id = ?");
        $stmt->execute([$id]);
        $success = "Berita berhasil dihapus.";
    }
    catch (PDOException $e) {
        $error = "Gagal menghapus berita.";
    }
}

// Handle Create
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_news'])) {
    $title = $_POST['title'];
    $content = $_POST['content'];
    $author_id = $_SESSION['user_id'];
    $image_path = null;

    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $target_dir = "../../public/uploads/news/";
        if (!file_exists($target_dir))
            mkdir($target_dir, 0777, true);
        $filename = time() . "_" . basename($_FILES["image"]["name"]);
        if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_dir . $filename)) {
            $image_path = $filename;
        }
    }

    $stmt = $pdo->prepare("INSERT INTO news (title, content, image, author_id) VALUES (?, ?, ?, ?)");
    if ($stmt->execute([$title, $content, $image_path, $author_id])) {
        $success = "Berita berhasil diterbitkan!";
    }
}

$news_items = $pdo->query("SELECT * FROM news ORDER BY created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta charset="UTF-8">
    <title>Kelola Berita - OSIS</title>
    <link rel="stylesheet" href="/public/assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>

<div class="app-container">
    <?php include '../templates/sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Page Toolbar -->
        <div class="page-toolbar">
            <div class="page-toolbar-left">
                <h1 class="page-title">Kelola Berita Sekolah</h1>
                <p class="page-subtitle">OSIS SMAN 4 - Suara Siswa.</p>
            </div>
        </div>
        
        <div class="page-content">
            <div style="display: grid; grid-template-columns: 1fr 1.5fr; gap: 2rem; align-items: start;">
            <!-- Form Card -->
            <div class="page-section">
                <h3><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block; vertical-align:middle; line-height:1;"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg> Tulis Berita Baru</h3>
                <?php if (isset($success))
    echo "<div class='badge badge-success' style='display:block; padding:10px; margin-bottom:15px; text-align:center;'>$success</div>"; ?>
                <?php if (isset($error))
    echo "<div class='badge badge-danger' style='display:block; padding:10px; margin-bottom:15px; text-align:center;'>$error</div>"; ?>
                
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="create_news" value="1">
                    <div class="form-group">
                        <label>Judul Berita</label>
                        <input type="text" name="title" required placeholder="Contoh: Class Meeting Semester Ganjil">
                    </div>
                    <div class="form-group">
                        <label>Konten</label>
                        <textarea name="content" rows="6" required placeholder="Tulis rincian kegiatan..."></textarea>
                    </div>
                    <div class="form-group">
                        <label>Gambar Kegiatan (Opsional)</label>
                        <input type="file" name="image" accept="image/*">
                    </div>
                    <button type="submit" class="btn" style="width: 100%; background: #ea580c;">Terbitkan Berita</button>
                </form>
            </div>
            
            <!-- List Card -->
            <div class="page-section">
                <h3><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block; vertical-align:middle; line-height:1;"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg> Daftar Berita</h3>
                <div class="page-table-wrap">
                    <table class="page-table">
                        <thead>
                            <tr>
                                <th>Gambar</th>
                                <th>Judul & Tanggal</th>
                                <th style="text-align: right;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($news_items as $item): ?>
                            <tr>
                                <td width="80">
                                    <?php if ($item['image']): ?>
                                        <img src="../../public/uploads/news/<?php echo htmlspecialchars($item['image']); ?>" style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px; box-shadow: var(--shadow-sm);">
                                    <?php
    else: ?>
                                        <div style="width: 60px; height: 60px; background: #f1f5f9; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block; vertical-align:middle; line-height:1;"><path d="M4 22h16a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v16a2 2 0 0 1-2 2Zm0 0a2 2 0 0 1-2-2v-9c0-1.1.9-2 2-2h2"/><path d="M18 14h-8"/><path d="M15 18h-5"/><path d="M10 6h8v4h-8V6Z"/></svg></div>
                                    <?php
    endif; ?>
                                </td>
                                <td>
                                    <strong style="display: block; font-size: 1rem; color: var(--secondary); margin-bottom: 4px;"><?php echo htmlspecialchars($item['title']); ?></strong>
                                    <span style="font-size: 0.85rem; color: var(--text-muted);"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block; vertical-align:middle; line-height:1;"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg> <?php echo date('d M Y, H:i', strtotime($item['created_at'])); ?></span>
                                </td>
                                <td style="text-align: right;">
                                    <a href="manage_news.php?delete=<?php echo $item['id']; ?>" class="btn btn-danger" style="padding: 6px 12px; font-size: 0.8rem;" onclick="return confirm('Yakin ingin menghapus berita ini?')">Hapus</a>
                                </td>
                            </tr>
                            <?php
endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            </div>
        </div>
    </main>
</div>

</body>
</html>


