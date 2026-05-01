<?php
// src/admin/manage_backups.php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../login.php");
    exit;
}

$base_url = "/lms";
$backupDir = realpath(__DIR__ . '/../../backups');
if (!$backupDir) {
    if (!is_dir(__DIR__ . '/../../backups')) {
        mkdir(__DIR__ . '/../../backups', 0777, true);
    }
    $backupDir = realpath(__DIR__ . '/../../backups');
}

// Helper rekursif untuk membaca struktur tree folder
function getBackupStructure($dir) {
    if (!is_dir($dir)) return [];
    
    $directories = [];
    $filesList = [];
    
    $iterator = new DirectoryIterator($dir);
    foreach ($iterator as $fileinfo) {
        if ($fileinfo->isDot()) continue;
        
        if ($fileinfo->isDir()) {
            $strukturAnak = getBackupStructure($fileinfo->getPathname());
            if (!empty($strukturAnak)) {
                $directories[$fileinfo->getFilename()] = $strukturAnak;
            } else {
                // Folder kosong bisa kita tetapkan kosong
                $directories[$fileinfo->getFilename()] = [];
            }
        } else if ($fileinfo->getExtension() === 'zip' || $fileinfo->getExtension() === 'sql') {
            global $backupDir;
            $filesList[] = [
                'name' => $fileinfo->getFilename(),
                'path' => str_replace('\\', '/', substr($fileinfo->getRealPath(), strlen(dirname($backupDir)) + 1)),
                'size' => round($fileinfo->getSize() / 1024 / 1024, 2),
                'time' => filemtime($fileinfo->getRealPath())
            ];
        }
    }
    
    // Sort directories descending (tahun terbaru di atas, dsb)
    krsort($directories);
    
    // Sort files descending berdasarkan waktu dibuat
    usort($filesList, function($a, $b) { return $b['time'] - $a['time']; });
    
    if (!empty($directories)) return $directories;
    return $filesList;
}

$backupTree = getBackupStructure($backupDir);

function renderTree($node, $path = 'backups', $level = 0) {
    if (empty($node)) {
        echo '<p style="color:#94a3b8; font-style:italic; font-size:0.9rem;">Belum ada arsip pada direktori ini.</p>';
        return;
    }
    
    $isList = isset($node[0]) && is_array($node[0]) && isset($node[0]['name']);
    
    if ($isList) {
        echo '<div style="display:grid; gap:12px; margin-top:8px;">';
        foreach ($node as $file) {
            echo '<div style="display:flex; justify-content:space-between; align-items:center; padding:12px 18px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; transition:transform 0.2s; box-shadow:0 1px 2px rgba(0,0,0,0.02);">';
            echo '<div style="display:flex; align-items:center; gap:16px;">';
            echo '<div style="font-size:1.6rem; background:#dbeafe; padding:10px; border-radius:10px; display:flex; align-items:center; justify-content:center; color:#2563eb;">';
            echo '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>';
            echo '</div>';
            echo '<div><div style="font-weight:700; font-size:0.95rem; color:#0f172a; margin-bottom:4px;">' . htmlspecialchars($file['name']) . '</div>';
            echo '<div style="font-size:0.8rem; color:#64748b; display:flex; gap:12px;">';
            echo '<span><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline; vertical-align:-2px;"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg> ' . date('d M Y, H:i', $file['time']) . '</span>';
            echo '<span><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline; vertical-align:-2px;"><rect x="2" y="2" width="20" height="8" rx="2" ry="2"/><rect x="2" y="14" width="20" height="8" rx="2" ry="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/></svg> ' . $file['size'] . ' MB</span>';
            echo '</div></div>';
            echo '</div>';
            
            echo '<a href="download_backup.php?path=' . urlencode($file['path']) . '" title="Unduh File Berkas" class="btn" style="padding:8px 16px; font-size:0.85rem; display:flex; gap:6px; align-items:center;">';
            echo '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg> Unduh';
            echo '</a>';
            echo '</div>';
        }
        echo '</div>';
    } else {
        foreach ($node as $dirName => $children) {
            $currentPath = $path . '/' . $dirName;
            echo '<details style="margin-bottom:14px; background:white; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden;" ' . ($level < 2 ? 'open' : '') . '>';
            
            echo '<summary style="padding:18px 20px; background:#f4f7fb; cursor:pointer; font-weight:600; display:flex; justify-content:space-between; align-items:center; list-style:none; outline:none;">';
            echo '<div style="display:flex; gap:12px; align-items:center;">';
            echo '<div style="color:#fbbf24; font-size:1.4rem; display:flex; align-items:center;"><svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor" stroke="none"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg></div>';
            echo '<span style="font-size:1.05rem; color:#1e293b; letter-spacing:-0.01em;">' . htmlspecialchars($dirName) . '</span>';
            echo '</div>';
            
            // Disable detail toggle when clicking download
            echo '<a href="download_backup.php?path=' . urlencode($currentPath) . '" onclick="event.stopPropagation();" style="display:flex; align-items:center; gap:8px; padding:6px 14px; background:white; color:#0f172a; border:1px solid #cbd5e1; border-radius:8px; text-decoration:none; font-size:0.8rem; font-weight:600; box-shadow:0 1px 2px rgba(0,0,0,0.05); transition:all 0.2s;" onmouseover="this.style.background=\'#0f172a\'; this.style.color=\'white\'; this.style.borderColor=\'#0f172a\';" onmouseout="this.style.background=\'white\'; this.style.color=\'#0f172a\'; this.style.borderColor=\'#cbd5e1\';">';
            echo '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>';
            echo 'Bundling ZIP</a>';
            
            echo '</summary>';
            
            echo '<div style="padding:20px; border-top:1px solid #e2e8f0; background:#ffffff;">';
            renderTree($children, $currentPath, $level + 1);
            echo '</div>';
            echo '</details>';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Backup - Admin</title>
    <link rel="stylesheet" href="/public/assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .main-content { background: #f5f7fb !important; padding: 0 !important; }
        .page-hero {
            background: linear-gradient(135deg, #1e1b4b 0%, #312e81 50%, #4338ca 100%);
            padding: 2.5rem 3rem 5rem; position: relative; overflow: hidden;
        }
        .page-hero::before { content:''; position:absolute; right:-60px; top:-60px; width:250px; height:250px; background:rgba(255,255,255,0.07); border-radius:50%; }
        .page-hero h1 { color:#fff; font-size:1.6rem; font-weight:700; margin:0 0 0.4rem; }
        .page-hero p  { color:rgba(255,255,255,0.8); margin:0; font-size:0.95rem; }
        .page-content { position:relative; margin-top:-2.5rem; padding:0 3rem 3rem; z-index:10; }
        .btn-backup-now {
            position: absolute; right: 3rem; top: 50%; transform: translateY(-50%); z-index:10;
            background:rgba(255,255,255,0.18); border:1px solid rgba(255,255,255,0.3); color:#fff;
            padding:10px 22px; border-radius:10px; font-size:0.9rem; font-weight:700;
            cursor:pointer; backdrop-filter:blur(5px); display:flex; align-items:center; gap:8px;
            font-family:inherit;
        }
        .btn-backup-now:hover { background:rgba(255,255,255,0.28); }
        details > summary::-webkit-details-marker { display: none; }
        details[open] > summary { border-bottom-left-radius: 0; border-bottom-right-radius: 0; border-bottom: 1px solid #e2e8f0; }
    </style>
</head>
<body>

<div class="app-container">
    <?php include '../templates/sidebar.php'; ?>
    
    <main class="main-content">
        <div class="page-hero">
            <div>
                <h1>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;vertical-align:middle;margin-right:8px;"><path d="M21 2v6h-6"/><path d="M3 12a9 9 0 0 1 15-6.7L21 8"/><path d="M3 22v-6h6"/><path d="M21 12a9 9 0 0 1-15 6.7L3 16"/></svg>
                    Sistem Backup Terpadu
                </h1>
                <p>Arsip sistem harian (Database SQL &amp; Uploads Files) dikelompokkan secara otomatis.</p>
            </div>
            <button onclick="triggerManualBackup()" id="btn-backup-manual" class="btn-backup-now">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 2v6h-6"/><path d="M3 12a9 9 0 0 1 15-6.7L21 8"/><path d="M3 22v-6h6"/><path d="M21 12a9 9 0 0 1-15 6.7L3 16"/></svg>
                Backup Sekarang
            </button>
        </div>

        <div class="page-content">
            <div id="alert-container"></div>
            <div class="tree-container">
                <?php renderTree($backupTree); ?>
            </div>
        </div>
    </main>
</div>

<script>
function triggerManualBackup() {
    const btn = document.getElementById('btn-backup-manual');
    const oriText = btn.innerHTML;
    
    if(!confirm("Buat arsip backup untuk hari ini sekarang?\n\nJika backup harian sudah ada, sistem hanya akan memberitahu Anda.")) return;
    
    btn.innerHTML = `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="animate-spin"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg> Memproses...`;
    btn.disabled = true;
    btn.style.opacity = '0.7';
    
    fetch('backup_engine.php')
      .then(r => r.json())
      .then(data => {
          btn.innerHTML = oriText;
          btn.disabled = false;
          btn.style.opacity = '1';
          
          let color = data.status === 'success' ? '#059669' : '#dc2626';
          let bg = data.status === 'success' ? '#d1fae5' : '#fee2e2';
          
          document.getElementById('alert-container').innerHTML = `
              <div style="background:${bg}; color:${color}; padding:14px 20px; border-radius:10px; margin-bottom:20px; font-weight:500; display:flex; justify-content:space-between; align-items:center;">
                  <span>${data.message}</span>
                  <button onclick="window.location.reload();" style="background:transparent; border:1px solid ${color}; color:${color}; padding:4px 12px; border-radius:6px; cursor:pointer; font-weight:600; font-size:0.8rem;">Muat Ulang Laman</button>
              </div>
          `;
      })
      .catch(e => {
          btn.innerHTML = oriText;
          btn.disabled = false;
          btn.style.opacity = '1';
          alert('Terjadi kesalahan jaringan.');
      });
}

// Add CSS keyframes for rotation spin icon
const style = document.createElement('style');
style.innerHTML = `
@keyframes spin { 100% { transform: rotate(360deg); } }
.animate-spin { animation: spin 1s linear infinite; }
`;
document.head.appendChild(style);
</script>

</body>
</html>

