<?php
// src/guru/manage_materials.php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guru') {
    header("Location: ../../login.php");
    exit;
}

$teacher_id = $_SESSION['user_id'];

// Fetch Teacher's Classes
$stmt = $pdo->prepare("
    SELECT tc.*, c.name as school_class_name,
    (SELECT COUNT(*) FROM materials m WHERE m.teacher_class_id = tc.id) as material_count
    FROM teacher_classes tc
    JOIN classes c ON tc.class_id = c.id
    WHERE tc.teacher_id = ?
    ORDER BY tc.created_at DESC
");
$stmt->execute([$teacher_id]);
$classes = $stmt->fetchAll();

// Check for Legacy Materials (Null Class)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM materials WHERE teacher_id = ? AND teacher_class_id IS NULL");
$stmt->execute([$teacher_id]);
$legacy_count = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta charset="UTF-8">
    <title>Kelola Materi - Guru</title>
    <link rel="stylesheet" href="/public/assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .folder-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .folder-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            text-decoration: none;
            color: inherit;
            transition: all 0.2s;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }
        .folder-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            border-color: #3b82f6;
        }
        .folder-icon {
            font-size: 4rem;
            color: #fbbf24;
            margin-bottom: 10px;
        }
        .folder-name {
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 5px;
            color: #1e293b;
        }
        .folder-meta {
            font-size: 0.85rem;
            color: #64748b;
        }
    </style>
</head>
<body>

<div class="app-container">
    <?php include '../templates/sidebar.php'; ?>
    
    <main class="main-content">
        <header style="margin-bottom: 2rem;">
            <h1>Materi Pelajaran</h1>
            <p style="color: #64748b;">Pilih kelas untuk mengelola materi pelajaran.</p>
        </header>

        <?php if (empty($classes)): ?>
            <div style="text-align: center; padding: 4rem; background: white; border-radius: 16px; border: 2px dashed #e2e8f0;">
                <div style="font-size: 3rem; margin-bottom: 1rem;"><svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' style='display:inline-block; vertical-align:middle; line-height:1;'><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg></div>
                <h3 style="color: #1e293b; margin-bottom: 0.5rem;">Belum ada kelas</h3>
                <p style="color: #64748b; margin-bottom: 1.5rem;">Anda belum membuat kelas. Silakan buat kelas terlebih dahulu.</p>
                <a href="kelas.php" class="btn">Buat Kelas Sekarang</a>
            </div>
        <?php
else: ?>
            <div class="folder-grid">
                <?php foreach ($classes as $class): ?>
                    <a href="class_materials.php?id=<?php echo $class['id']; ?>" class="folder-card">
                        <div class="folder-icon"><svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' style='display:inline-block; vertical-align:middle; line-height:1;'><path d='M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z'/></svg></div>
                        <div class="folder-name"><?php echo htmlspecialchars($class['name']); ?></div>
                        <div class="folder-meta">
                            <?php echo htmlspecialchars($class['subject']); ?> &bull; <?php echo $class['material_count']; ?> File
                        </div>
                    </a>
                <?php
    endforeach; ?>
                
                <?php if ($legacy_count > 0): ?>
                    <a href="class_materials.php?id=legacy" class="folder-card" style="border-style: dashed; background: #fafafa;">
                        <div class="folder-icon" style="color: #94a3b8;"><svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' style='display:inline-block; vertical-align:middle; line-height:1;'><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg></div>
                        <div class="folder-name" style="color: #64748b;">Materi Lama</div>
                        <div class="folder-meta">
                            Belum dikelompokkan &bull; <?php echo $legacy_count; ?> File
                        </div>
                    </a>
                <?php
    endif; ?>
            </div>
        <?php
endif; ?>

    </main>
</div>

</body>
</html>


