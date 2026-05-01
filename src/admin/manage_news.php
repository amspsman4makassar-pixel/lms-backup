<?php
// src/admin/manage_news.php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../login.php");
    exit;
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    try {
        $stmt = $pdo->prepare("DELETE FROM news WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['flash'] = ['type' => 'success', 'message' => "Berita berhasil dihapus."];
    }
    catch (PDOException $e) {
        $_SESSION['flash'] = ['type' => 'error', 'message' => "Gagal menghapus berita."];
    }
    header("Location: manage_news.php");
    exit;
}

// Handle Edit Fetch
$edit_mode = false;
$edit_item = null;
if (isset($_GET['edit'])) {
    $edit_mode = true;
    $id = $_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM news WHERE id = ?");
    $stmt->execute([$id]);
    $edit_item = $stmt->fetch();
    if ($edit_item) {
        // Fetch images
        $stmt = $pdo->prepare("SELECT * FROM news_images WHERE news_id = ?");
        $stmt->execute([$id]);
        $edit_item['images_list'] = $stmt->fetchAll();
    }
    if (!$edit_item) {
        header("Location: manage_news.php");
        exit;
    }
}

// Handle Create / Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $content = $_POST['content'];
    $author_id = $_SESSION['user_id'];

    // Handle Create / Update
    if (isset($_POST['create_news']) || isset($_POST['update_news'])) {

        // Basic Insert/Update of News Text
        if (isset($_POST['update_news'])) {
            $id = $_POST['news_id'];
            $stmt = $pdo->prepare("UPDATE news SET title=?, content=? WHERE id=?");
            $stmt->execute([$title, $content, $id]);
            $news_id = $id; // For image linkage
            $flash_message = "<svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' style='display:inline-block; vertical-align:middle; line-height:1;'><polyline points='20 6 9 17 4 12'/></svg> Berita berhasil diupdate!";
        }
        else {
            $stmt = $pdo->prepare("INSERT INTO news (title, content, author_id) VALUES (?, ?, ?)");
            $stmt->execute([$title, $content, $author_id]);
            $news_id = $pdo->lastInsertId();
            $flash_message = "<svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' style='display:inline-block; vertical-align:middle; line-height:1;'><polyline points='20 6 9 17 4 12'/></svg> Berita berhasil diterbitkan!";
        }

        // Handle Image Uploads (Multi)
        if (isset($_FILES['images'])) {
            $target_dir = "../../public/uploads/news/";
            if (!file_exists($target_dir))
                mkdir($target_dir, 0777, true);

            $files = $_FILES['images'];
            $count = count($files['name']);

            for ($i = 0; $i < $count; $i++) {
                if ($files['error'][$i] == 0) {
                    $filename = time() . "_" . $i . "_" . basename($files['name'][$i]);
                    if (move_uploaded_file($files['tmp_name'][$i], $target_dir . $filename)) {
                        $pdo->prepare("INSERT INTO news_images (news_id, image_path) VALUES (?, ?)")
                            ->execute([$news_id, $filename]);

                        // Update legacy 'image' column for backward compatibility with other pages if needed
                        // Just set the first image as the 'main' image if it's currently null
                        $stmt = $pdo->prepare("UPDATE news SET image = ? WHERE id = ? AND (image IS NULL OR image = '')");
                        $stmt->execute([$filename, $news_id]);
                    }
                }
            }
        }

        $_SESSION['flash'] = ['type' => 'success', 'message' => $flash_message];
        header("Location: manage_news.php");
        exit;
    }
}

// Handle image deletion
if (isset($_POST['delete_image'])) {
    $image_id = $_POST['delete_image_id'];
    $news_id = $_POST['news_id_for_image_delete'];

    $stmt = $pdo->prepare("SELECT image_path FROM news_images WHERE id = ?");
    $stmt->execute([$image_id]);
    $image_to_delete = $stmt->fetchColumn();

    if ($image_to_delete) {
        $file_path = "../../public/uploads/news/" . $image_to_delete;
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        $pdo->prepare("DELETE FROM news_images WHERE id = ?")->execute([$image_id]);

        // If the deleted image was the main 'image' in the news table, update it
        $stmt_check_main = $pdo->prepare("SELECT image FROM news WHERE id = ?");
        $stmt_check_main->execute([$news_id]);
        $main_image = $stmt_check_main->fetchColumn();

        if ($main_image == $image_to_delete) {
            // Find a new main image
            $stmt_new_main = $pdo->prepare("SELECT image_path FROM news_images WHERE news_id = ? ORDER BY id ASC LIMIT 1");
            $stmt_new_main->execute([$news_id]);
            $new_main_image = $stmt_new_main->fetchColumn();
            $pdo->prepare("UPDATE news SET image = ? WHERE id = ?")->execute([$new_main_image, $news_id]);
        }
        $_SESSION['flash'] = ['type' => 'success', 'message' => "Gambar berhasil dihapus."];
        // Redirect back to edit mode to refresh images
        header("Location: manage_news.php?edit=" . $news_id);
        exit;
    }
    else {
        $_SESSION['flash'] = ['type' => 'error', 'message' => "Gagal menghapus gambar."];
        header("Location: manage_news.php?edit=" . $news_id);
    }
}


// Fetch News with Images
$news_items = $pdo->query("
    SELECT n.*, 
    (SELECT GROUP_CONCAT(image_path SEPARATOR ',') FROM news_images WHERE news_id = n.id) as images
    FROM news n 
    ORDER BY created_at DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta charset="UTF-8">
    <title>Kelola Berita - Admin</title>
    <link rel="stylesheet" href="/public/assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>

<div class="app-container">
    <?php include '../templates/sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Unified Hero Header -->
        <div class="page-toolbar">
            <div class="page-toolbar-left">
                <h1 class="page-title">Kelola Berita</h1>
                <p class="page-subtitle">Buat, edit, dan publikasikan berita sekolah</p>
            </div>
        </div>
        <div class="page-content">
            <div class="two-col-layout-equal">
            <!-- Form Panel -->
            <div class="page-section">
                <div class="section-title"><?= $edit_mode ? 'Edit Berita' : 'Tulis Berita Baru' ?></div>

                <?php if (isset($_SESSION['flash'])): $flash = $_SESSION['flash']; unset($_SESSION['flash']); ?>
                    <div class="<?= $flash['type'] === 'error' ? 'flash-error' : 'flash-success' ?>">
                        <?= $flash['type'] === 'error'
                            ? '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/></svg>'
                            : '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>'; ?>
                        <?= htmlspecialchars($flash['message']) ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" enctype="multipart/form-data">
                    <?php if ($edit_mode): ?>
                        <input type="hidden" name="update_news" value="1">
                        <input type="hidden" name="news_id" value="<?php echo $edit_item['id']; ?>">
                    <?php
else: ?>
                        <input type="hidden" name="create_news" value="1">
                    <?php
endif; ?>

                    <div class="form-group">
                        <label>Judul Berita</label>
                        <input type="text" name="title" required placeholder="Contoh: Juara 1 Lomba Matematika" value="<?php echo $edit_mode ? htmlspecialchars($edit_item['title']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>Konten</label>
                        <textarea name="content" rows="6" required placeholder="Tulis isi berita di sini..."><?php echo $edit_mode ? htmlspecialchars($edit_item['content']) : ''; ?></textarea>
                    </div>
                    
                    <?php if ($edit_mode && !empty($edit_item['images_list'])): ?>
                        <div style="margin-bottom: 15px;">
                            <label>Gambar Saat Ini:</label>
                            <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-top: 5px;">
                                <?php foreach ($edit_item['images_list'] as $img): ?>
                                    <div style="position: relative; width: 100px; height: 100px;">
                                        <img src="../../public/uploads/news/<?php echo htmlspecialchars($img['image_path']); ?>" style="width: 100%; height: 100%; object-fit: cover; border-radius: 8px;">
                                        <form method="POST" style="position: absolute; top: -5px; right: -5px; margin: 0;" onsubmit="return confirm('Hapus gambar ini?');">
                                            <input type="hidden" name="delete_image" value="1">
                                            <input type="hidden" name="delete_image_id" value="<?php echo $img['id']; ?>">
                                            <input type="hidden" name="news_id_for_image_delete" value="<?php echo $edit_item['id']; ?>">
                                            <button type="submit" style="background: red; color: white; border: none; border-radius: 50%; width: 20px; height: 20px; cursor: pointer; font-size: 12px; display: flex; align-items: center; justify-content: center;">&times;</button>
                                        </form>
                                    </div>
                                <?php
    endforeach; ?>
                            </div>
                        </div>
                    <?php
endif; ?>

                    <div class="form-group">
                        <label><?php echo $edit_mode ? "Tambah Gambar Lagi (Opsional)" : "Upload Gambar (Bisa lebih dari satu)"; ?></label>
                        <input type="file" name="images[]" multiple accept="image/*">
                        <small style="color: grey; display: block; margin-top: 5px;">Tekan Ctrl (Windows) atau Command (Mac) untuk memilih banyak gambar.</small>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width:100%;"><?= $edit_mode ? 'Simpan Perubahan' : 'Terbitkan Berita' ?></button>
                    <?php if ($edit_mode): ?>
                        <a href="manage_news.php" class="btn btn-ghost" style="display:block;width:100%;text-align:center;margin-top:8px;">Batal Edit</a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- List Panel -->
            <div class="page-section">
                <div class="panel-header">
                    <h3 class="panel-title">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg>
                        Daftar Berita
                    </h3>
                    <span style="background:#dbeafe;color:#1e40af;padding:3px 10px;border-radius:20px;font-size:0.75rem;font-weight:700;"><?= count($news_items) ?> artikel</span>
                </div>
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
                                <td width="120">
                                    <?php if ($item['images']):
        $imgs = explode(',', $item['images']);
        $main_img = $imgs[0];
        $count = count($imgs);
?>
                                        <div style="position: relative; width: 80px; height: 60px;">
                                            <img src="../../public/uploads/news/<?php echo htmlspecialchars($main_img); ?>" style="width: 100%; height: 100%; object-fit: cover; border-radius: 8px; box-shadow: var(--shadow-sm);">
                                            <?php if ($count > 1): ?>
                                                <div style="position: absolute; bottom: 0; right: 0; background: rgba(0,0,0,0.6); color: white; font-size: 10px; padding: 2px 5px; border-radius: 4px;">+<?php echo $count - 1; ?></div>
                                            <?php
        endif; ?>
                                        </div>
                                    <?php
    else: ?>
                                        <div style="width: 80px; height: 60px; background: #f1f5f9; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block; vertical-align:middle; line-height:1;"><path d="M4 22h16a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v16a2 2 0 0 1-2 2Zm0 0a2 2 0 0 1-2-2v-9c0-1.1.9-2 2-2h2"/><path d="M18 14h-8"/><path d="M15 18h-5"/><path d="M10 6h8v4h-8V6Z"/></svg></div>
                                    <?php
    endif; ?>
                                </td>
                                <td>
                                    <strong style="display: block; font-size: 1rem; color: var(--secondary); margin-bottom: 4px;"><?php echo htmlspecialchars($item['title']); ?></strong>
                                    <span style="font-size: 0.85rem; color: var(--text-muted);"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block; vertical-align:middle; line-height:1;"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg> <?php echo date('d M Y, H:i', strtotime($item['created_at'])); ?></span>
                                </td>
                                <td style="text-align:right;">
                                    <div style="display:flex;gap:5px;justify-content:flex-end;">
                                        <a href="manage_news.php?edit=<?= $item['id'] ?>" class="sub-btn">Edit</a>
                                        <a href="manage_news.php?delete=<?= $item['id'] ?>" class="sub-btn danger" onclick="return confirm('Yakin hapus berita ini?')">Hapus</a>
                                    </div>
                                </td>
                            </tr>
                            <?php
endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            </div><!-- /.two-col-layout-equal -->
        </div><!-- /.content-overlap -->
    </main>
</div>

</body>
</html>


