<?php
// src/admin/pengaduan.php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
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
    $id = intval($_GET['delete']);
    try {
        $stmt = $pdo->prepare("DELETE FROM pengaduan WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: pengaduan.php?msg=deleted");
        exit;
    } catch (PDOException $e) {
        $error = "Gagal menghapus tiket: " . $e->getMessage();
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
    <title>E-Counseling & Layanan BK - Admin</title>
    <link rel="stylesheet" href="/public/assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    </style>
</head>
<body>

<div class="app-container">
    <?php include '../templates/sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Dashboard Hero -->
        <div class="dashboard-hero">
            <div style="position: relative; z-index: 2;">
                <h1 style="color: white; margin-bottom: 0.5rem;"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;vertical-align:middle;margin-right:8px;"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg> Layanan E-Counseling BK</h1>
                <p style="color: rgba(255,255,255,0.8);">Kelola laporan dan pengaduan siswa</p>
            </div>
            <div style="position: absolute; right: -50px; top: -50px; width: 200px; height: 200px; background: rgba(255,255,255,0.1); border-radius: 50%;"></div>
        </div>

        <div class="content-overlap">
            <?php if (isset($_GET['msg'])): ?>
                <?php if ($_GET['msg'] === 'updated'): ?>
                    <div style="background: var(--primary-light); color: var(--primary); padding: 1rem; border-radius: 8px; margin-bottom: 2rem;">
                        âœ… Status tiket berhasil diperbarui.
                    </div>
                <?php elseif ($_GET['msg'] === 'deleted'): ?>
                    <div style="background: #fee2e2; color: #991b1b; padding: 1rem; border-radius: 8px; margin-bottom: 2rem;">
                        ðŸ—‘ï¸ Tiket pengaduan berhasil dihapus.
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div style="background: #fee2e2; color: #991b1b; padding: 1rem; border-radius: 8px; margin-bottom: 2rem;">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if (empty($tickets)): ?>
                <div class="page-section" style="text-align: center; padding: 3rem;">
                    <p style="color: var(--text-muted); font-size: 1.1rem;">Belum ada tiket pengaduan yang masuk.</p>
                </div>
            <?php else: ?>
                <div class="page-section">
                    <div class="panel-header">
                        <h2 style="margin: 0;">Semua Tiket</h2>
                    </div>
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
                                    <form method="POST" action="" style="display: flex; align-items: center; gap: 8px;">
                                        <input type="hidden" name="id" value="<?php echo $t['id']; ?>">
                                        <input type="hidden" name="update_status" value="1">
                                        <select name="status" style="width: auto; padding: 0.4rem 0.5rem; font-size: 0.85rem; border-radius: 6px; border: 1px solid #cbd5e1; background: #fff;">
                                            <option value="Pending" <?php echo $t['status'] === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="Diproses" <?php echo $t['status'] === 'Diproses' ? 'selected' : ''; ?>>Diproses</option>
                                            <option value="Selesai" <?php echo $t['status'] === 'Selesai' ? 'selected' : ''; ?>>Selesai</option>
                                        </select>
                                        <button type="submit" class="sub-btn" style="background: #e0e7ff; color: #4338ca;">Simpan</button>
                                        <a href="?delete=<?php echo $t['id']; ?>" class="sub-btn" style="background: #fee2e2; color: #991b1b; margin-left: auto;" onclick="return confirm('Hapus tiket ini secara permanen?');">Hapus</a>
                                    </form>
                                </td>
                            </tr>
                <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

</body>
</html>

