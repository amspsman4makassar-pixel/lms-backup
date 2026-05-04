<?php
// src/templates/sidebar.php
$role = $_SESSION['role'] ?? 'guest';
$current_page = basename($_SERVER['PHP_SELF']);
$base_url = "";

// Set data-lms-role on <html> for legacy role-specific hero colors
if ($role !== 'guest') {
    echo '<script>document.documentElement.setAttribute("data-lms-role",' . json_encode($role, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ');</script>' . "\n";
}

// Role labels for the badge
$role_labels = [
    'admin'   => 'Administrator',
    'guru'    => 'Guru',
    'siswa'   => 'Siswa',
    'osis'    => 'OSIS',
    'kepsek'  => 'Kepala Sekolah',
    'wakasek' => 'Wakil Kepsek',
    'bk'      => 'Bimbingan Konseling',
];
$role_label = $role_labels[$role] ?? 'Guest';

// Dashboard link per role
$dashboard_link = "#";
if ($role === 'admin')                       $dashboard_link = "$base_url/src/admin/dashboard.php";
if ($role === 'guru')                        $dashboard_link = "$base_url/src/guru/dashboard.php";
if ($role === 'siswa')                       $dashboard_link = "$base_url/src/siswa/dashboard.php";
if ($role === 'osis')                        $dashboard_link = "$base_url/src/osis/dashboard.php";
if ($role === 'kepsek' || $role === 'wakasek') $dashboard_link = "$base_url/src/pimpinan/dashboard.php";
if ($role === 'bk')                          $dashboard_link = "$base_url/src/bk/dashboard.php";

// Helper: is current page active?
function nav_active(string|array $pages): string {
    global $current_page;
    $pages = is_array($pages) ? $pages : [$pages];
    return in_array($current_page, $pages, true) ? 'active' : '';
}
?>

<!-- Mobile Toggle Button -->
<button id="sidebarToggle" class="mobile-toggle-btn" aria-label="Buka Menu">
    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <line x1="3" y1="6"  x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>
    </svg>
</button>

<!-- Sidebar Overlay (mobile) -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">

    <!-- Header -->
    <div class="sidebar-header">
        <div>
            <div class="sidebar-brand-name">SMA Negeri 4</div>
            <div class="sidebar-brand-sub">Makassar</div>
        </div>
        <button id="sidebarClose" class="mobile-close-btn" aria-label="Tutup Menu">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
        </button>
    </div>

    <!-- Role badge -->
    <span class="sidebar-role-badge"><?php echo htmlspecialchars($role_label); ?></span>

    <!-- Navigation -->
    <nav class="sidebar-nav">

        <!-- Dashboard (all roles) -->
        <a href="<?php echo $dashboard_link; ?>" class="nav-link <?php echo nav_active('dashboard.php'); ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
            Dashboard
        </a>

        <?php if ($role === 'admin'): ?>
            <span class="nav-label">Manajemen</span>
            <a href="<?php echo $base_url; ?>/src/admin/manage_users.php" class="nav-link <?php echo nav_active('manage_users.php'); ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                Kelola Pengguna
            </a>
            <a href="<?php echo $base_url; ?>/src/admin/manage_master_classes.php" class="nav-link <?php echo nav_active('manage_master_classes.php'); ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg>
                Master Kelas
            </a>
            <a href="<?php echo $base_url; ?>/src/admin/manage_classes.php" class="nav-link <?php echo nav_active('manage_classes.php'); ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                Kelas Guru
            </a>
            <a href="<?php echo $base_url; ?>/src/admin/manage_schedules.php" class="nav-link <?php echo nav_active('manage_schedules.php'); ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                Kelola Jadwal
            </a>
            <a href="<?php echo $base_url; ?>/src/admin/promote_class.php" class="nav-link <?php echo nav_active('promote_class.php'); ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h6"/><path d="M12 18V6a2 2 0 0 1 2-2h1a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2z"/></svg>
                Promosi Kelas
            </a>
            <a href="<?php echo $base_url; ?>/src/admin/manage_snbp.php" class="nav-link <?php echo nav_active('manage_snbp.php'); ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                Kelulusan SNBP
            </a>
            <a href="<?php echo $base_url; ?>/src/admin/manage_kelulusan.php" class="nav-link <?php echo nav_active('manage_kelulusan.php'); ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                Data Kelulusan
            </a>

            <span class="nav-label">Konten</span>
            <a href="<?php echo $base_url; ?>/src/admin/manage_news.php" class="nav-link <?php echo nav_active('manage_news.php'); ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 22h16a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v16a2 2 0 0 1-2 2Zm0 0a2 2 0 0 1-2-2v-9c0-1.1.9-2 2-2h2"/><path d="M18 14h-8"/><path d="M15 18h-5"/><path d="M10 6h8v4h-8V6Z"/></svg>
                Kelola Berita
            </a>

        <?php endif; ?>

        <?php if ($role === 'guru'): ?>
            <span class="nav-label">Akademik</span>
            <a href="<?php echo $base_url; ?>/src/guru/kelas.php" class="nav-link <?php echo nav_active(['kelas.php','view_class.php']); ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                Kelas &amp; Materi
            </a>
            <a href="<?php echo $base_url; ?>/src/guru/jadwal_mengajar.php" class="nav-link <?php echo nav_active('jadwal_mengajar.php'); ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                Jadwal Mengajar
            </a>
            <a href="<?php echo $base_url; ?>/src/guru/grades.php" class="nav-link <?php echo nav_active(['grades.php','grades_input.php','grades_import.php']); ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                Input Nilai
            </a>
        <?php endif; ?>

        <?php if ($role === 'siswa'): ?>
            <span class="nav-label">Akademik</span>
            <a href="<?php echo $base_url; ?>/src/siswa/kelas_siswa.php" class="nav-link <?php echo nav_active(['kelas_siswa.php','kelas_detail_siswa.php']); ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                Kelas Saya
            </a>
            <a href="<?php echo $base_url; ?>/src/siswa/jadwal.php" class="nav-link <?php echo nav_active('jadwal.php'); ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                Jadwal Pelajaran
            </a>
        <?php endif; ?>

        <?php if ($role === 'osis'): ?>
            <span class="nav-label">OSIS</span>
            <a href="<?php echo $base_url; ?>/src/osis/manage_news.php" class="nav-link <?php echo nav_active('manage_news.php'); ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 15-3-3a22 22 0 0 1 2-3.95A12.88 12.88 0 0 1 22 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 0 1-4 2z"/><path d="M9 12H4s.55-3.03 2-4c1.62-1.08 5 0 5 0"/><path d="M12 15v5s3.03-.55 4-2c1.08-1.62 0-5 0-5"/></svg>
                Berita Sekolah
            </a>
        <?php endif; ?>

        <?php if ($role === 'kepsek' || $role === 'wakasek'): ?>
            <span class="nav-label">Pemantauan</span>
            <a href="<?php echo $base_url; ?>/src/pimpinan/guru_list.php" class="nav-link <?php echo nav_active('guru_list.php'); ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                Data Guru
            </a>
            <a href="<?php echo $base_url; ?>/src/pimpinan/siswa_list.php" class="nav-link <?php echo nav_active('siswa_list.php'); ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                Data Siswa
            </a>
            <a href="<?php echo $base_url; ?>/src/pimpinan/jadwal_sekolah.php" class="nav-link <?php echo nav_active('jadwal_sekolah.php'); ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                Jadwal Mengajar
            </a>
            <a href="<?php echo $base_url; ?>/src/pimpinan/pemantauan_meet.php" class="nav-link <?php echo nav_active('pemantauan_meet.php'); ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 7l-7 5 7 5V7z"/><rect x="1" y="5" width="15" height="14" rx="2" ry="2"/></svg>
                Pemantauan Meet
            </a>
        <?php endif; ?>

        <?php if ($role === 'bk'): ?>
            <span class="nav-label">Konseling</span>
            <a href="<?php echo $base_url; ?>/src/bk/pengaduan.php" class="nav-link <?php echo nav_active('pengaduan.php'); ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 9a2 2 0 0 1-2 2H6l-4 4V4c0-1.1.9-2 2-2h8a2 2 0 0 1 2 2v5Z"/><path d="M18 9h2a2 2 0 0 1 2 2v11l-4-4h-6a2 2 0 0 1-2-2v-1"/></svg>
                E-Counseling
            </a>
        <?php endif; ?>

        <hr class="nav-divider">

        <!-- Common links (all roles) -->
        <a href="<?php echo $base_url; ?>/src/profile.php" class="nav-link <?php echo nav_active('profile.php'); ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            Profil Saya
        </a>
        <a href="<?php echo $base_url; ?>/src/auth/logout.php"
           class="nav-link"
           style="color: #F87171;"
           onclick="return confirm('Yakin ingin logout?');">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            Logout
        </a>

    </nav>
</aside>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const toggle  = document.getElementById('sidebarToggle');
    const close   = document.getElementById('sidebarClose');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const main    = document.querySelector('.main-content');

    // Restore desktop collapse state
    if (window.innerWidth > 768 && localStorage.getItem('sidebar-collapsed') === 'true') {
        sidebar.classList.add('collapsed');
        if (main) main.classList.add('expanded');
        if (toggle) toggle.classList.add('sidebar-hidden');
    }

    function openSidebar() {
        sidebar.classList.add('active');
        overlay.classList.add('active');
    }
    function closeSidebar() {
        sidebar.classList.remove('active');
        overlay.classList.remove('active');
    }
    function toggleDesktop() {
        const collapsed = sidebar.classList.toggle('collapsed');
        if (main)   main.classList.toggle('expanded', collapsed);
        if (toggle) toggle.classList.toggle('sidebar-hidden', collapsed);
        localStorage.setItem('sidebar-collapsed', collapsed);
    }

    if (toggle) toggle.addEventListener('click', () => {
        window.innerWidth <= 768 ? openSidebar() : toggleDesktop();
    });
    if (close)   close.addEventListener('click',   closeSidebar);
    if (overlay) overlay.addEventListener('click', closeSidebar);
});
</script>
