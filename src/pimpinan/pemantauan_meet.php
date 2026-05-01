<?php
session_start();
require_once "../../config/database.php";

if (!isset($_SESSION["user_id"]) || !in_array($_SESSION["role"], ["kepsek", "wakasek"])) {
    header("Location: ../../login.php");
    exit;
}

date_default_timezone_set("Asia/Makassar");
$now = new DateTime();

try {
    $sql = "SELECT 
                m.id, 
                m.meet_link, 
                m.created_at,
                m.start_time,
                m.end_time,
                u.full_name AS teacher_name,
                c.name AS class_name,
                COALESCE(s.name, 'Umum') AS subject_name
            FROM meet_links m
            JOIN users u ON m.teacher_id = u.id
            JOIN classes c ON m.class_id = c.id
            LEFT JOIN subjects s ON m.subject_id = s.id
            ORDER BY COALESCE(m.start_time, m.created_at) ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $meet_links = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $grouped_meets = [];
    foreach ($meet_links as $link) {
        $raw_date = $link["start_time"] ? $link["start_time"] : $link["created_at"];
        $date_key = date("Y-m-d", strtotime($raw_date));
        $grouped_meets[$date_key][] = $link;
    }
    krsort($grouped_meets);

} catch (PDOException $e) {
    die("Error fetching Google Meet links: " . $e->getMessage());
}

// Determine selected date
$available_dates = array_keys($grouped_meets);
$selected_date = isset($_GET["tanggal"]) && isset($grouped_meets[$_GET["tanggal"]]) 
    ? $_GET["tanggal"] 
    : ($available_dates[0] ?? null);

$page_title = "Pemantauan Google Meet";

$hari_array  = ["Sunday"=>"Minggu","Monday"=>"Senin","Tuesday"=>"Selasa","Wednesday"=>"Rabu","Thursday"=>"Kamis","Friday"=>"Jumat","Saturday"=>"Sabtu"];
$bulan_array = ["01"=>"Januari","02"=>"Februari","03"=>"Maret","04"=>"April","05"=>"Mei","06"=>"Juni","07"=>"Juli","08"=>"Agustus","09"=>"September","10"=>"Oktober","11"=>"November","12"=>"Desember"];

function formatTanggal($date, $hari_array, $bulan_array) {
    $d   = strtotime($date);
    $hari = $hari_array[date("l", $d)];
    $tgl  = date("d", $d);
    $bln  = $bulan_array[date("m", $d)];
    $thn  = date("Y", $d);
    return "$hari, $tgl $bln $thn";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - LMS SMAN 4 Makassar</title>
    <link rel="stylesheet" href="/public/assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .pm-layout { display: flex; gap: 1.5rem; align-items: flex-start; }
        .pm-main   { flex: 1; min-width: 0; }
        .pm-sidebar { width: 250px; flex-shrink: 0; background: #fff; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.08); overflow: hidden; position: sticky; top: 80px; }
        .pm-sidebar-header { background: #4f46e5; color: #fff; padding: 1rem 1.25rem; font-weight: 700; font-size: 0.9rem; display: flex; align-items: center; gap: 8px; }
        .pm-sidebar-dates  { padding: 0.5rem; }
        .pm-date-btn {
            display: block; width: 100%; text-align: left; padding: 0.65rem 1rem; border-radius: 8px;
            border: none; background: transparent; cursor: pointer; font-family: inherit; font-size: 0.85rem;
            color: #334155; transition: background 0.15s; text-decoration: none; margin-bottom: 2px;
        }
        .pm-date-btn:hover { background: #f1f5f9; }
        .pm-date-btn.active { background: #eef2ff; color: #4f46e5; font-weight: 700; }
        .pm-date-btn .dot { width: 8px; height: 8px; border-radius: 50%; background: #4f46e5; display: inline-block; margin-right: 8px; }
        .pm-date-btn .badge-today { font-size: 0.65rem; background: #4f46e5; color: #fff; border-radius: 4px; padding: 0 5px; margin-left: 6px; vertical-align: middle; }
        .pm-date-btn.active .dot { background: #4f46e5; }
        .pm-selected-header { display: flex; align-items: center; gap: 10px; margin-bottom: 1.5rem; }
        .pm-selected-header h2 { font-size: 1.3rem; color: #1e293b; margin: 0; font-weight: 700; }
        .meet-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(290px,1fr)); gap: 1.2rem; }
        .meet-card { background: #fff; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.08); padding: 1.25rem; position: relative; overflow: hidden; transition: transform 0.2s, box-shadow 0.2s; }
        .meet-card:hover { transform: translateY(-2px); box-shadow: 0 10px 20px -4px rgba(0,0,0,0.1); }
        .status-bar { position: absolute; top: 0; left: 0; right: 0; height: 5px; }
        .bg-green { background: #10b981; } .bg-red { background: #ef4444; } .bg-gray { background: #94a3b8; } .bg-slate { background: #cbd5e1; }
        .status-badge { display: inline-block; padding: 3px 9px; border-radius: 5px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; margin-bottom: 0.75rem; }
        .badge-green { background: #d1fae5; color: #059669; } .badge-red { background: #fee2e2; color: #b91c1c; } .badge-gray { background: #f1f5f9; color: #475569; } .badge-slate { background: #f8fafc; color: #64748b; }
        .card-title { font-size: 1rem; font-weight: 700; color: #1e293b; margin: 0 0 0.5rem; }
        .card-info { display: flex; align-items: center; gap: 6px; color: #475569; font-size: 0.82rem; margin-bottom: 0.3rem; }
        .card-time { display: flex; align-items: center; gap: 6px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 7px; padding: 7px 10px; margin: 0.75rem 0; font-size: 0.82rem; font-weight: 500; color: #334155; }
        .btn-meet { display: flex; align-items: center; justify-content: center; gap: 6px; background: #10b981; color: #fff; padding: 9px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 0.85rem; transition: background 0.2s; }
        .btn-meet:hover { background: #059669; }
        .btn-meet.disabled { background: #cbd5e1; color: #64748b; pointer-events: none; }
        .empty-state { text-align: center; padding: 4rem 2rem; background: #fff; border-radius: 12px; border: 1px dashed #cbd5e1; color: #64748b; }
        @media (max-width: 768px) { .pm-layout { flex-direction: column-reverse; } .pm-sidebar { width: 100%; position: static; } }
    </style>
</head>
<body>
<div class="app-container">
    <?php include "../templates/sidebar.php"; ?>
    <main class="main-content">
        <!-- Page Toolbar -->
        <div class="page-toolbar">
            <div class="page-toolbar-left">
                <h1 class="page-title">Pemantauan Google Meet</h1>
                <p class="page-subtitle">Pilih tanggal di sebelah kanan untuk melihat meet yang dijadwalkan.</p>
            </div>
        </div>

        <div class="page-content">
        <?php if (empty($grouped_meets)): ?>
            <div class="empty-state">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom:1rem;color:#94a3b8;"><path d="M15.6 11.6L22 7v10l-6.4-4.5v-1zM0 5h9a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V7c0-1.1.9-2 2-2z"/></svg>
                <h3 style="color:#1e293b;margin-bottom:0.5rem;">Belum Ada Meet</h3>
                <p>Saat ini belum ada tautan Google Meet yang tersimpan.</p>
            </div>
        <?php else: ?>
        <div class="pm-layout">
            <!-- Left: Meet cards for selected date -->
            <div class="pm-main">
                <?php if ($selected_date): ?>
                    <div class="pm-selected-header">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#4f46e5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        <h2><?= formatTanggal($selected_date, $hari_array, $bulan_array) ?></h2>
                        <?php if ($selected_date === $now->format("Y-m-d")): ?>
                            <span style="background:#4f46e5;color:#fff;font-size:0.7rem;padding:3px 8px;border-radius:10px;">Hari Ini</span>
                        <?php endif; ?>
                    </div>
                    <div class="meet-grid">
                        <?php foreach ($grouped_meets[$selected_date] as $link):
                            $start_dt = $link["start_time"] ? new DateTime($link["start_time"]) : null;
                            $end_dt   = $link["end_time"]   ? new DateTime($link["end_time"])   : null;
                            $bar = "bg-slate"; $badge = "badge-slate"; $status = "Tidak Ada Waktu"; $active = true;
                            if ($start_dt && $end_dt) {
                                if ($now < $start_dt)      { $bar="bg-gray";  $badge="badge-gray";  $status="Belum Mulai";       $active=false; }
                                elseif ($now > $end_dt)    { $bar="bg-red";   $badge="badge-red";   $status="Selesai";           $active=false; }
                                else                       { $bar="bg-green"; $badge="badge-green"; $status="Sedang Berlangsung"; }
                            }
                        ?>
                        <div class="meet-card">
                            <div class="status-bar <?= $bar ?>"></div>
                            <span class="status-badge <?= $badge ?>"><?= $status ?></span>
                            <div class="card-title"><?= htmlspecialchars($link["class_name"]) ?></div>
                            <div class="card-info">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                <?= htmlspecialchars($link["teacher_name"]) ?>
                            </div>
                            <div class="card-info">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                                <?= htmlspecialchars($link["subject_name"]) ?>
                            </div>
                            <?php if ($start_dt && $end_dt): ?>
                            <div class="card-time">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                <?= $start_dt->format("H:i") ?> &ndash; <?= $end_dt->format("H:i") ?>
                            </div>
                            <?php else: ?>
                            <div class="card-time">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                Dibuat: <?= date("H:i", strtotime($link["created_at"])) ?>
                            </div>
                            <?php endif; ?>
                            <a href="<?= htmlspecialchars($link["meet_link"]) ?>" target="_blank" class="btn-meet <?= !$active ? "disabled" : "" ?>">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                                <?= $status === "Selesai" ? "Ruangan Berakhir" : "Buka Ruangan" ?>
                            </a>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Right: Date archive sidebar -->
            <div class="pm-sidebar">
                <div class="pm-sidebar-header">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    Arsip Tanggal
                </div>
                <div class="pm-sidebar-dates">
                    <?php foreach ($grouped_meets as $date => $lnks):
                        $isToday  = ($date === $now->format("Y-m-d"));
                        $isActive = ($date === $selected_date);
                        $count    = count($lnks);
                        $d = strtotime($date);
                        $hari = $hari_array[date("l", $d)];
                        $tgl  = date("d", $d);
                        $bln  = $bulan_array[date("m", $d)];
                    ?>
                    <a href="?tanggal=<?= $date ?>" class="pm-date-btn <?= $isActive ? "active" : "" ?>">
                        <span class="dot"></span>
                        <?= "$hari, $tgl $bln" ?>
                        <?php if ($isToday): ?><span class="badge-today">Hari Ini</span><?php endif; ?>
                        <span style="float:right;background:#e2e8f0;border-radius:10px;padding:1px 7px;font-size:0.7rem;color:#475569;"><?= $count ?></span>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        </div>
    </main>
</div>
</body>
</html>
