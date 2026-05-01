<?php
// src/bk/pengaduan.php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'bk') {
    header("Location: ../../login.php");
    exit;
}

// Update status if requested
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $id = $_POST['id'];
    $status = $_POST['status'];
    try {
        $stmt = $pdo->prepare("UPDATE pengaduan SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);
        header("Location: pengaduan.php?msg=updated");
        exit;
    } catch (PDOException $e) {
        $error = "Error updating status: " . $e->getMessage();
    }
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    try {
        $stmt = $pdo->prepare("DELETE FROM pengaduan WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: pengaduan.php?msg=deleted");
        exit;
    } catch (PDOException $e) {
        $error = "Error delete ticket: " . $e->getMessage();
    }
}

// Fetch all tickets
try {
    $stmt = $pdo->query("SELECT * FROM pengaduan ORDER BY created_at DESC");
    $tickets = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Counseling & Layanan BK - Guru BK</title>
    <link rel="stylesheet" href="/public/assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .badge-kategori {
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 700;
            background: #e0e7ff;
            color: #4338ca;
            display: inline-block;
        }
        .badge-bullying { background: #fee2e2; color: #991b1b; }
        
        .status-form {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .status-form select {
            width: auto;
            padding: 0.4rem 0.5rem;
            font-size: 0.85rem;
            border-radius: 6px;
            border: 1px solid #cbd5e1;
            background: #fff;
        }
        .status-form button {
            padding: 0.4rem 0.8rem;
            font-size: 0.85rem;
            border-radius: 6px;
            cursor: pointer;
            border: none;
            font-weight: 600;
        }
        .btn-save {
            background: #4f46e5;
            color: white;
        }
        .btn-save:hover { background: #4338ca; }
        .btn-del {
            background: #fef2f2;
            color: #ef4444;
            border: 1px solid #fca5a5;
            margin-left: auto;
            text-decoration: none;
            padding: 0.4rem 0.8rem;
            font-size: 0.85rem;
            border-radius: 6px;
            display: inline-block;
            font-weight: 600;
        }
        .btn-del:hover { background: #fee2e2; }
        .tr-urgent {
            border-left: 4px solid #ef4444 !important;
        }
    </style>
</head>
<body>

<div class="app-container">
    <?php include '../templates/sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Page Toolbar -->
        <div class="page-toolbar">
            <div class="page-toolbar-left">
                <h1 class="page-title">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;vertical-align:middle;margin-right:6px;"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    Layanan E-Counseling BK
                </h1>
                <p class="page-subtitle">Kelola laporan dan pengaduan siswa</p>
            </div>
        </div>

        <div class="page-content">
            <?php if (isset($_GET['msg'])): ?>
                <?php if ($_GET['msg'] === 'updated'): ?>
                    <div style="background: var(--primary-light); color: var(--primary); padding: 1rem; border-radius: 8px; margin-bottom: 2rem;">
                        Status tiket berhasil diperbarui.
                    </div>
                <?php elseif ($_GET['msg'] === 'deleted'): ?>
                    <div style="background: #fee2e2; color: #991b1b; padding: 1rem; border-radius: 8px; margin-bottom: 2rem;">
                        Tiket pengaduan berhasil dihapus.
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div style="background: #fee2e2; color: #991b1b; padding: 1rem; border-radius: 8px; margin-bottom: 2rem;">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if (empty($tickets)): ?>
                <div class="card" style="text-align: center; padding: 3rem;">
                    <p style="color: var(--text-muted); font-size: 1.1rem;">Belum ada tiket pengaduan yang masuk.</p>
                </div>
            <?php else: ?>
                <div class="page-table-wrap">
                    <table class="page-table">
                        <thead>
                            <tr>
                                <th>Pelapor</th>
                                <th>Kategori</th>
                                <th style="width: 35%;">Isi Laporan</th>
                                <th>Status / Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                <?php foreach ($tickets as $t): ?>
                    <?php 
                        $isBullying = ($t['kategori'] === 'Bullying');
                        $statusColor = '';
                        if ($t['status'] === 'Pending') $statusColor = '#f59e0b';
                        if ($t['status'] === 'Diproses') $statusColor = '#3b82f6';
                        if ($t['status'] === 'Selesai') $statusColor = '#10b981';
                    ?>
                            <tr class="<?php echo $isBullying ? 'tr-urgent' : ''; ?>">
                                <td>
                                    <div style="font-weight: 700; color: #1e293b;"><?php echo htmlspecialchars($t['nama_siswa']); ?></div>
                                    <div style="font-size: 0.85rem; color: #64748b; margin-top: 4px;">Kelas <?php echo htmlspecialchars($t['kelas']); ?></div>
                                    <div style="font-size: 0.8rem; color: #94a3b8; margin-top: 4px;"><?php echo date('d M Y, H:i', strtotime($t['created_at'])); ?></div>
                                </td>
                                <td>
                                    <span class="badge-kategori <?php echo $isBullying ? 'badge-bullying' : ''; ?>"><?php echo htmlspecialchars($t['kategori']); ?></span>
                                </td>
                                <td>
                                    <div style="background: #f8fafc; padding: 12px; border-radius: 8px; font-size: 0.9rem; color: #334155; line-height: 1.5;">
                                        <?php echo nl2br(htmlspecialchars($t['pesan'])); ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="margin-bottom: 8px; font-size: 0.85rem; font-weight: 700; color: <?php echo $statusColor; ?>; display: flex; align-items: center; gap: 5px;">
                                        <span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:<?php echo $statusColor; ?>;"></span>
                                        <?php echo htmlspecialchars($t['status']); ?>
                                    </div>
                                    <form method="POST" action="" class="status-form">
                                        <input type="hidden" name="id" value="<?php echo $t['id']; ?>">
                                        <input type="hidden" name="update_status" value="1">
                                        <select name="status">
                                            <option value="Pending" <?php echo $t['status'] === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="Diproses" <?php echo $t['status'] === 'Diproses' ? 'selected' : ''; ?>>Diproses</option>
                                            <option value="Selesai" <?php echo $t['status'] === 'Selesai' ? 'selected' : ''; ?>>Selesai</option>
                                        </select>
                                        <button type="submit" class="btn-save">Simpan</button>
                                        <a href="?delete=<?php echo $t['id']; ?>" class="btn-del" onclick="return confirm('Hapus tiket pengaduan ini secara permanen?');">Hapus</a>
                                    </form>
                                </td>
                            </tr>
                <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

</body>
</html>

