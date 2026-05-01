<?php
// src/osis/dashboard.php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'osis') {
    header("Location: ../../login.php");
    exit;
}

$hour = (int)date('H');
if ($hour < 11)
    $greeting = "Selamat Pagi";
elseif ($hour < 15)
    $greeting = "Selamat Siang";
elseif ($hour < 18)
    $greeting = "Selamat Sore";
else
    $greeting = "Selamat Malam";

// Stats
$news_count = $pdo->query("SELECT COUNT(*) FROM news")->fetchColumn();
$news_published = $news_count;

// Recent News
$recent_news = $pdo->query("SELECT title, created_at FROM news ORDER BY created_at DESC LIMIT 5")->fetchAll();

// Indonesian Date
$days = ['Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'];
$months = ['January' => 'Januari', 'February' => 'Februari', 'March' => 'Maret', 'April' => 'April', 'May' => 'Mei', 'June' => 'Juni', 'July' => 'Juli', 'August' => 'Agustus', 'September' => 'September', 'October' => 'Oktober', 'November' => 'November', 'December' => 'Desember'];
$dayName = $days[date('l')] ?? date('l');
$monthName = $months[date('F')] ?? date('F');
$dateStr = $dayName . ', ' . date('d') . ' ' . $monthName . ' ' . date('Y');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta charset="UTF-8">
    <title>Dashboard OSIS</title>
    <link rel="stylesheet" href="/public/assets/css/style.css">
    <!-- <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet"> -->
    <style>
        /* * { font-family: 'Inter', system-ui, -apple-system, sans-serif; } */
        /* Quick Actions */
        .qa-list { display: flex; flex-direction: column; gap: 8px; }
        .qa-item {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 14px 16px;
            border-radius: 12px;
            background: #f8fafc;
            border: 1px solid #eef2f7;
            text-decoration: none;
            color: inherit;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .qa-item:hover {
            background: #fffbeb;
            border-color: #fde68a;
            transform: translateX(4px);
            box-shadow: 0 2px 12px rgba(245,158,11,0.06);
        }
        .qa-ico {
            width: 42px; height: 42px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
            flex-shrink: 0;
        }
        .qa-item:nth-child(1) .qa-ico { background: #fef3c7; }
        .qa-item:nth-child(2) .qa-ico { background: #d1fae5; }
        .qa-title { font-size: 0.88rem; font-weight: 600; color: #1e293b; }
        .qa-desc { font-size: 0.75rem; color: #94a3b8; margin-top: 2px; }
        .qa-arrow {
            margin-left: auto;
            color: #cbd5e1;
            font-size: 1.2rem;
            transition: all 0.2s;
        }
        .qa-item:hover .qa-arrow { color: #f59e0b; transform: translateX(3px); }

        /* News List */
        .news-row {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 13px 0;
            border-bottom: 1px solid #f1f5f9;
        }
        .news-row:last-child { border-bottom: none; }
        .news-dot {
            width: 10px; height: 10px;
            border-radius: 50%;
            flex-shrink: 0;
        }
        .news-info { flex: 1; min-width: 0; }
        .news-title {
            font-size: 0.88rem;
            font-weight: 600;
            color: #1e293b;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .news-date { font-size: 0.75rem; color: #94a3b8; margin-top: 2px; }


    </style>
</head>
<body>

<div class="app-container">
    <?php include '../templates/sidebar.php'; ?>
    
    <main class="main-content">

        <div class="page-toolbar">
            <div class="page-toolbar-left">
                <h1 class="page-title"><?php echo $greeting; ?>, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</h1>
                <p class="page-subtitle">Portal informasi dan berita sekolah OSIS</p>
            </div>
            <div class="page-toolbar-right" style="text-align: right;">
                <p class="page-subtitle"><?php echo $dateStr; ?></p>
            </div>
        </div>

        <div class="page-content">

            <div class="db-stats">
                <div class="db-stat c-amber">
                    <div class="num"><?php echo $news_count; ?></div>
                    <div class="lbl">Total Berita</div>
                </div>
                <div class="db-stat c-green">
                    <div class="num"><?php echo $news_published; ?></div>
                    <div class="lbl">Berita Terbit</div>
                </div>
            </div>

            <div class="db-grid">

                <!-- Quick Actions -->
                <div class="page-section">
                    <h3>Menu Cepat</h3>
                    <div class="qa-list">
                        <a href="manage_news.php" class="qa-item">
                            <div class="qa-ico"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block; vertical-align:middle; line-height:1;"><path d="M4 22h16a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v16a2 2 0 0 1-2 2Zm0 0a2 2 0 0 1-2-2v-9c0-1.1.9-2 2-2h2"/><path d="M18 14h-8"/><path d="M15 18h-5"/><path d="M10 6h8v4h-8V6Z"/></svg></div>
                            <div>
                                <div class="qa-title">Kelola Berita</div>
                                <div class="qa-desc">Tulis, edit, dan publikasi berita sekolah</div>
                            </div>
                            <span class="qa-arrow">&rsaquo;</span>
                        </a>
                        <a href="../profile.php" class="qa-item">
                            <div class="qa-ico"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block; vertical-align:middle; line-height:1;"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></div>
                            <div>
                                <div class="qa-title">Profil Saya</div>
                                <div class="qa-desc">Ubah data diri dan password</div>
                            </div>
                            <span class="qa-arrow">&rsaquo;</span>
                        </a>
                    </div>
                </div>

                <!-- Recent News -->
                <div class="page-section">
                    <h3>Berita Terbaru</h3>
                    <?php if (empty($recent_news)): ?>
                        <div style="text-align:center; padding:2.5rem 1rem; color:#94a3b8;">
                            <p style="font-size:2.5rem; margin-bottom:10px;"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block; vertical-align:middle; line-height:1;"><path d="M4 22h16a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v16a2 2 0 0 1-2 2Zm0 0a2 2 0 0 1-2-2v-9c0-1.1.9-2 2-2h2"/><path d="M18 14h-8"/><path d="M15 18h-5"/><path d="M10 6h8v4h-8V6Z"/></svg></p>
                            <p style="margin-bottom:8px;">Belum ada berita.</p>
                            <a href="manage_news.php" style="color:#f59e0b; font-weight:600; text-decoration:none;">+ Tulis Berita Pertama</a>
                        </div>
                    <?php
else: ?>
                        <?php foreach ($recent_news as $n): ?>
                        <div class="news-row">
                            <div class="news-dot" style="background: #f59e0b;"></div>
                            <div class="news-info">
                                <div class="news-title"><?php echo htmlspecialchars($n['title']); ?></div>
                                <div class="news-date"><?php echo date('d M Y', strtotime($n['created_at'])); ?></div>
                            </div>
                        </div>
                        <?php
    endforeach; ?>
                    <?php
endif; ?>
                </div>
            </div>

        </div>
    </main>
</div>

</body>
</html>

