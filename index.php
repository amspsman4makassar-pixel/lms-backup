<?php
session_start();
// index.php
require_once 'config/database.php';

$user_status = null;
$user_role = null;
if (isset($_SESSION['user_id'])) {
    try {
        $stmt_user = $pdo->prepare("SELECT role, status FROM users WHERE id = ?");
        $stmt_user->execute([$_SESSION['user_id']]);
        $u_data = $stmt_user->fetch();
        if ($u_data) {
            $user_role = $u_data['role'];
            $user_status  = $u_data['status'] ?? 'active';
        }
    } catch (PDOException $e) {}
}

// Logic untuk URL Tracer Alumni
$tracer_link = "login.php?portal=tracer";
$tracer_onclick = "";

if (isset($_SESSION['user_id'])) {
    if ($user_role === 'siswa') {
        if ($user_status === 'graduated') {
            $tracer_link = "tracer_form.php";
        } else {
            $tracer_link = "javascript:void(0)";
            $tracer_onclick = "onclick=\"alert('Layanan ini khusus diperuntukkan bagi Alumni (Siswa yang telah lulus).')\"";
        }
    } else {
        $tracer_link = "javascript:void(0)";
        $tracer_onclick = "onclick=\"alert('Layanan Portal Tracer Alumni dikhususkan bagi Siswa Alumni SMA Negeri 4 Makassar.')\"";
    }
}

// Quotes
$quotes = [
    ["text" => "Pendidikan adalah senjata paling mematikan di dunia, karena dengan pendidikan, Anda dapat mengubah dunia.", "author" => "Nelson Mandela"],
    ["text" => "Hiduplah seolah engkau mati besok. Belajarlah seolah engkau hidup selamanya.", "author" => "Mahatma Gandhi"],
    ["text" => "Tujuan pendidikan itu untuk mempertajam kecerdasan, memperkukuh kemauan serta memperhalus perasaan.", "author" => "Tan Malaka"],
    ["text" => "Hanya ada satu kegelapan, yaitu kebodohan.", "author" => "William Shakespeare"],
    ["text" => "Jangan biarkan sekolah mengganggu pendidikanmu.", "author" => "Mark Twain"],
    ["text" => "Akar pendidikan itu pahit, tapi buahnya manis.", "author" => "Aristoteles"],
    ["text" => "Investasi dalam pengetahuan memberikan bunga terbaik.", "author" => "Benjamin Franklin"],
    ["text" => "Orang yang berhenti belajar akan menjadi tua, entah di usia 20 atau 80 tahun.", "author" => "Henry Ford"]
];

$todays_quote = $quotes[date('z') % count($quotes)];

// Fetch News
try {
    $stmt = $pdo->query("SELECT news.*, users.full_name as author_name FROM news JOIN users ON news.author_id = users.id ORDER BY created_at DESC LIMIT 3");
    $news_items = $stmt->fetchAll();
}
catch (PDOException $e) {
    $news_items = [];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMA Negeri 4 Makassar - Digital School</title>
    <link rel="stylesheet" href="/public/assets/css/style.css?v=<?php echo time(); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* Responsive adjustments are maintained in style.css */
    </style>
</head>
<body>

    <!-- Mobile Nav Overlay -->
    <div id="mobileNavOverlay" onclick="closeMobileNav()" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:98; backdrop-filter:blur(3px);"></div>

    <nav class="landing-nav" id="landingNav">
        <a href="#" class="nav-logo">SMA Negeri 4 Makassar</a>
        <button class="hamburger-btn" id="hamburgerBtn" onclick="toggleMobileNav()" aria-label="Menu">
            <span></span><span></span><span></span>
        </button>
        <div class="nav-links" id="navLinks">
            <a href="#">Beranda</a>
            <a href="#profil-sekolah">Profil Sekolah</a>
            <a href="#akses-layanan">Akses Layanan</a>
            <a href="#news">Kabar &amp; Info</a>
            <a href="login.php" class="nav-btn">Masuk LMS</a>
        </div>
    </nav>

    <!-- Mobile Drawer -->
    <div class="mobile-nav-drawer" id="mobileNavDrawer">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:2rem;">
            <span style="font-weight:800; font-size:1rem; color:white;">Menu Navigasi</span>
            <button onclick="closeMobileNav()" style="background:rgba(255,255,255,0.1); border:none; color:white; cursor:pointer; font-size:1.25rem; width:32px; height:32px; border-radius:8px; line-height:1;">&times;</button>
        </div>
        <a href="#" onclick="closeMobileNav()" class="mobile-nav-link">Beranda</a>
        <a href="#profil-sekolah" onclick="closeMobileNav()" class="mobile-nav-link">Profil Sekolah</a>
        <a href="#akses-layanan" onclick="closeMobileNav()" class="mobile-nav-link">Akses Layanan</a>
        <a href="#news" onclick="closeMobileNav()" class="mobile-nav-link">Kabar &amp; Info</a>
        <a href="login.php" class="mobile-nav-link mobile-nav-cta">Masuk LMS &rarr;</a>
    </div>

    <header class="hero-section">
        <div class="hero-content">
            <span class="hero-tag">Smart School Portal</span>
            <h1 class="hero-title">SMA Negeri 4 Makassar</h1>
            <p class="hero-subtitle">Membangun generasi cerdas berkarakter melalui akselerasi digital, pelayanan terpadu, dan pembelajaran terintegrasi.</p>
            <div class="hero-buttons">
                <a href="#akses-layanan" class="btn btn-hero-primary">Jelajahi Layanan</a>
                <a href="#news" class="btn btn-hero-secondary">Kabar Sekolah</a>
            </div>
        </div>
        
        <!-- School Background Image -->
        <img src="public/assets/images/sekolah.png" 
             alt="Background Sekolah" class="hero-bg-img">

        <!-- Principal Image -->
        <img src="public/assets/images/kepsek.png" 
             alt="Kepala Sekolah" class="hero-kepsek-img">

        <!-- Decoration Circle -->
        <div class="hero-circle"></div>
    </header>

    <section class="quote-box">
        <p style="font-size: 1.5rem; font-style: italic; font-weight: 300;">"<?php echo $todays_quote['text']; ?>"</p>
        <p style="margin-top: 1rem; font-weight: 700; opacity: 0.8;">— <?php echo $todays_quote['author']; ?></p>
    </section>

    <!-- AKSES LAYANAN SECTION -->
    <section id="akses-layanan" class="portal-section">
        <div class="section-header">
            <h2>Akses Layanan Utama</h2>
            <p>Pilih layanan digital yang ingin Anda akses</p>
        </div>
        <div class="layanan-grid">
            <a href="login.php" class="layanan-card">
                <div class="layanan-icon" style="background: rgba(79, 70, 229, 0.1); color: var(--primary);">
                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
                </div>
                <h3>Portal eLearning (LMS)</h3>
                <p>Akses materi, tugas, dan ujian untuk Siswa dan Guru.</p>
                <span class="layanan-link">Masuk ke LMS &rarr;</span>
            </a>
            
            <a href="e_counseling.php" class="layanan-card">
                <div class="layanan-icon" style="background: rgba(16, 185, 129, 0.1); color: var(--success);">
                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 9a2 2 0 0 1-2 2H6l-4 4V4c0-1.1.9-2 2-2h8a2 2 0 0 1 2 2v5Z"/><path d="M18 9h2a2 2 0 0 1 2 2v11l-4-4h-6a2 2 0 0 1-2-2v-1"/></svg>
                </div>
                <h3>E-Counseling & Layanan Pengaduan</h3>
                <p>Layanan BK online untuk siswa menyampaikan kendala atau pesan secara privat.</p>
                <span class="layanan-link">Buat Tiket Baru &rarr;</span>
            </a>

            <a href="pantauan_nilai.php" class="layanan-card">
                <div class="layanan-icon" style="background: rgba(245, 158, 11, 0.1); color: #f59e0b;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                </div>
                <h3>Pantauan Nilai Siswa</h3>
                <p>Bagi siswa, pantau hasil belajar dan nilai akhir mata pelajaran secara real-time.</p>
                <span class="layanan-link">Pantau Nilai &rarr;</span>
            </a>

            <a href="<?php echo $tracer_link; ?>" <?php echo $tracer_onclick; ?> class="layanan-card">
                <div class="layanan-icon" style="background: rgba(236, 72, 153, 0.1); color: #ec4899;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
                </div>
                <h3>Portal Tracer Alumni</h3>
                <p>Pendataan lulusan untuk pemetaan karir: Kuliah, Kerja, atau Wirausaha.</p>
                <span class="layanan-link">Isi Data Lulusan &rarr;</span>
            </a>
        </div>
    </section>

    <!-- PROFIL SEKOLAH SECTION -->
    <section id="profil-sekolah" class="portal-section" style="background: #f8fafc;">
        <div class="section-header">
            <h2>Profil Sekolah</h2>
            <p>Mengenal lebih dekat SMA Negeri 4 Makassar</p>
        </div>
        
        <div class="profil-container">
            <div class="profil-tabs">
                <button class="tab-btn active" onclick="showProfilTab('sejarah', this)">Sejarah Singkat</button>
                <button class="tab-btn" onclick="showProfilTab('visimisi', this)">Visi & Misi</button>
                <button class="tab-btn" onclick="showProfilTab('fasilitas', this)">Fasilitas</button>
                <button class="tab-btn" onclick="showProfilTab('struktur', this)">Struktur Organisasi</button>
                <button class="tab-btn" onclick="showProfilTab('identitas', this)">Identitas Sekolah</button>
                <button class="tab-btn" onclick="showProfilTab('pelengkap', this)">Data Pelengkap</button>
                <button class="tab-btn" onclick="showProfilTab('rincian', this)">Data Rincian</button>
                <button class="tab-btn" onclick="showProfilTab('lokasi', this)">Lokasi</button>

            </div>
            
            <div class="profil-content">
                <div id="tab-sejarah" class="profil-pane active">
                    <h3>Tentang SMAN 4 Makassar</h3>
                    <p>SMA Negeri 4 Makassar merupakan salah satu Sekolah Menengah Atas Negeri yang berada di Jl. Cakalang No. 3 Makassar, Provinsi Sulawesi Selatan, Indonesia, kode pos 90165. Masa pendidikan ditempuh selama tiga tahun pelajaran, mulai dari Kelas X hingga Kelas XII.</p>
                    <p>Sekolah ini didirikan pada tanggal 5 Februari 1964. Dalam perjalanan sejarahnya, SMAN 4 Makassar sebelumnya merupakan lahan sekolah Tionghoa, kemudian dijadikan sekolah oleh pemerintah setempat pada tahun 1964. Pada awalnya, sekolah ini merupakan kelas jauh dari SMA Negeri 1 Makassar sebelum berkembang menjadi SMA Negeri 4 Makassar.</p>
                    <p>Pada tahun 2007, sekolah ini menggunakan Kurikulum Tingkat Satuan Pendidikan (sebelumnya dengan KBK). Pada tahun 2013, sekolah ini memperoleh akreditasi BAN-SM dengan nilai 93,37 dan terakreditasi A.</p>
                </div>
                
                <div id="tab-visimisi" class="profil-pane" style="display: none;">
                    <h3>Visi & Misi</h3>
                    <h4>Visi</h4>
                    <p style="font-style: italic; font-weight: 500;">Misi dan Tujuan SMA Negeri 4 Makassar adalah:</p>

                    <h4 style="margin-top: 1.5rem;">Misi</h4>
                    <ul style="padding-left: 1.5rem; line-height: 1.8;">
                        <li>Meningkatkan mutu akademik dan non akademik.</li>
                        <li>Mengembangkan kreatifitas dan motivasi belajar.</li>
                        <li>Mengembangkan delapan Standar Pendidikan Nasional.</li>
                        <li>Membina dan mengembangkan English Club dan TIK.</li>
                        <li>Mengupayakan lulusan yang dapat bersaing secara nasional dan global.</li>
                        <li>Menciptakan lingkungan sekolah yang berahlakul karimah.</li>
                        <li>Membina dan mengembangkan disiplin dan ketertiban.</li>
                        <li>Membina dan mengembangkan budaya daerah dan nasional.</li>
                        <li>Mengembangkan sikap nasionalisme dan patriotisme.</li>
                        <li>Mewujudkan lingkungan sekolah yang sehat, bersih, rindang, asri sebagai upaya dalam pelestarian dan pengelolaan lingkungan hidup.</li>
                    </ul>

                    <h4 style="margin-top: 1.5rem;">Tujuan Sekolah</h4>
                    <ul style="padding-left: 1.5rem; line-height: 1.8;">
                        <li>Mampu meraih Nilai Ujian Akhir rata-rata 6,5 keatas minimal 80% siswa.</li>
                        <li>Dapat lulus SNMPTN sebanyak minimal 30%.</li>
                        <li>Tersedia bahan ajar berbasis life skill bagi setiap mata pelajaran.</li>
                        <li>Memiliki Kelompok KIR di sekolah.</li>
                        <li>Memiliki kelompok pencipta mata pelajaran sains dan matematika yang berprestasi dalam Olimpiade Sains dan Matematika.</li>
                        <li>Memiliki Kelompok Olah Raga, kelompok seni yang berprestasi di Tk. Kota.</li>
                        <li>Memiliki kelompok debat Bhs. Inggris yang handal di Tk. Kota.</li>
                        <li>Meningkatkan ketaatan beribadah sesuai ajaran agamanya.</li>
                        <li>Memanfaatkan teknologi informatika yang efektif.</li>
                        <li>Meningkatkan sikap cinta tanah air dan jiwa kepahlawanan.</li>
                        <li>Terciptanya sekolah adiwiyata.</li>
                    </ul>
                </div>
                
                <div id="tab-fasilitas" class="profil-pane" style="display: none;">
                    <h3>Fasilitas Unggulan</h3>
                    <div class="fasilitas-grid">
                        <div class="fasilitas-item">Kelas</div>
                        <div class="fasilitas-item">Ruang OSIS</div>
                        <div class="fasilitas-item">Masjid Nur Delima</div>
                        <div class="fasilitas-item">Perpustakaan</div>
                        <div class="fasilitas-item">Laboratorium Biologi</div>
                        <div class="fasilitas-item">Laboratorium Fisika</div>
                        <div class="fasilitas-item">Laboratorium Kimia</div>
                        <div class="fasilitas-item">Laboratorium Komputer (2 buah)</div>
                        <div class="fasilitas-item">Laboratorium Bahasa</div>
                        <div class="fasilitas-item">Lapangan Basket</div>
                        <div class="fasilitas-item">Lapangan Bola Volly</div>
                        <div class="fasilitas-item">Lapangan Sepak Takraw</div>
                        <div class="fasilitas-item">Lapangan Futsal</div>
                        <div class="fasilitas-item">Aula Mini</div>
                        <div class="fasilitas-item">Outdoor</div>
                        <div class="fasilitas-item">Smart Class</div>
                        <div class="fasilitas-item">Ruang BK</div>
                        <div class="fasilitas-item">Kantin Sehat</div>
                        <div class="fasilitas-item">KPN SMAN 4 Makassar</div>
                        <div class="fasilitas-item">Parkiran</div>
                        <div class="fasilitas-item">Lapangan Upacara</div>
                        <div class="fasilitas-item">Ruang UKS</div>
                        <div class="fasilitas-item">Ruang PMR</div>
                        <div class="fasilitas-item">Ruang Pramuka</div>
                        <div class="fasilitas-item">Gudang</div>
                        <div class="fasilitas-item">Toilet</div>
                        <div class="fasilitas-item">Sekretariat IKA</div>
                        <div class="fasilitas-item">Taman/Sudut Baca</div>
                    </div>
                </div>
                
                <div id="tab-struktur" class="profil-pane" style="display: none;">
                    <h3>Organisasi & Kegiatan Siswa</h3>
                    <h4>Organisasi</h4>
                    <ul style="padding-left: 1.5rem; line-height: 1.8;">
                        <li>Majelis Permusyawaratan Kelas (MPK) SMA Negeri 4 Makassar</li>
                        <li>OSIS SMA Negeri 4 Makassar</li>
                        <li>Bina Damping (BINDAP)</li>
                    </ul>

                    <h4 style="margin-top: 1.5rem;">Ekstrakurikuler</h4>
                    <ul style="padding-left: 1.5rem; line-height: 1.8;">
                        <li>Pramuka Mirae Scout 04</li>
                        <li>Paskibra Unit 104 Makassar</li>
                        <li>PMR Madya 204 Makassar</li>
                        <li>Futsal Smapat 04</li>
                        <li>Basket</li>
                        <li>Bahasa Inggris E-Club</li>
                        <li>Kerohanian Islam (Rohis) Irmuhumah</li>
                        <li>Karya Ilmiah Remaja (KIR)</li>
                        <li>TIK Club</li>
                        <li>Perkusi</li>
                        <li>Supporter</li>
                    </ul>

                    <h4 style="margin-top: 1.5rem;">Organisasi Siswa Intra Sekolah (OSIS)</h4>
                    <p>OSIS merupakan organisasi wajib yang menjadi satu-satunya wadah induk kesiswaan di SMA Negeri 4 Makassar. OSIS membawahi organisasi dan ekstrakurikuler sekolah, berada di bawah naungan kesiswaan dan Dewan Pembina OSIS, serta mendapatkan arahan dan pembinaan untuk seluruh program kerjanya.</p>
                    <p>Kepengurusan OSIS diperbarui setiap tahun untuk menjaga regenerasi dan kapabilitas kerja. Proses kaderisasi dilakukan menjelang akhir masa jabatan melalui seleksi dan pelatihan kepemimpinan. Proses penerimaan anggota biasanya berlangsung antara Oktober hingga November, dan serah terima dilakukan setelah pemilihan ketua OSIS.</p>

                    <h4 style="margin-top: 1.5rem;">Majelis Permusyawaratan Kelas (MPK)</h4>
                    <p>MPK merupakan lembaga legislatif yang bekerja berdampingan dengan OSIS. Keanggotaan MPK berjumlah enam belas orang, terdiri dari enam pengurus inti dan sepuluh anggota komisi. MPK bertugas mengawasi dan memberi pengarahan terhadap bidang kerja OSIS melalui komisi-komisi yang dibentuk.</p>
                    <p>Selain itu, MPK memiliki tugas otonom seperti penyelenggaraan pemilihan ketua OSIS, pengarahan dan penilaian kinerja OSIS, hingga pemberian sanksi terhadap pelanggaran kode etik. Pengurus MPK berasal dari siswa kelas X dan XI yang lolos seleksi kepengurusan, dengan pelaksanaan seleksi oleh pengurus periode sebelumnya di bawah arahan kesiswaan dan pembina MPK.</p>
                </div>

                <div id="tab-identitas" class="profil-pane" style="display: none;">
                    <h3>Identitas Sekolah</h3>
                    <ul style="padding-left: 1.5rem; line-height: 1.8;">
                        <li>NPSN: 40311892</li>
                        <li>Status: Negeri</li>
                        <li>Bentuk Pendidikan: SMA</li>
                        <li>Status Kepemilikan: Pemerintah Pusat</li>
                        <li>SK Pendirian Sekolah: 79/B-IV-64</li>
                        <li>Tanggal SK Pendirian: 1964-07-30</li>
                        <li>SK Izin Operasional: KEP-97/Team/1966</li>
                        <li>Tanggal SK Izin Operasional: 1966-07-01</li>
                    </ul>
                </div>

                <div id="tab-pelengkap" class="profil-pane" style="display: none;">
                    <h3>Data Pelengkap</h3>
                    <ul style="padding-left: 1.5rem; line-height: 1.8;">
                        <li>Kebutuhan Khusus Dilayani: Tidak ada</li>
                        <li>Nama Bank: BNI</li>
                        <li>Cabang KCP/Unit: Makassar</li>
                        <li>Rekening Atas Nama: SMA Negeri 4 Makassar</li>
                        <li>Luas Tanah Milik: 7.840 M2</li>
                    </ul>
                </div>

                <div id="tab-rincian" class="profil-pane" style="display: none;">
                    <h3>Data Rincian</h3>
                    <ul style="padding-left: 1.5rem; line-height: 1.8;">
                        <li>Status BOS: Bersedia Menerima</li>
                        <li>Waktu Penyelenggaraan: Sehari penuh (5 h/m)</li>
                        <li>Sertifikasi ISO: Belum Bersertifikat</li>
                        <li>Sumber Listrik: PLN</li>
                        <li>Daya Listrik: 15000 watt</li>
                        <li>Akses Internet: Telkom Speedy/Indihome</li>
                    </ul>
                </div>

                <div id="tab-lokasi" class="profil-pane" style="display: none;">
                    <h3>Lokasi</h3>
                    <p style="margin-bottom: 1rem;">
                        Jl. Cakalang No.3, Totaka, Kec. Ujung Tanah, Kota Makassar, Sulawesi Selatan 90164
                    </p>
                    <div style="border-radius: 12px; overflow: hidden; box-shadow: 0 10px 20px rgba(15, 23, 42, 0.12); border: 1px solid #e2e8f0;">
                        <iframe
                            src="https://www.google.com/maps?q=Jl.%20Cakalang%20No.3%2C%20Totaka%2C%20Kec.%20Ujung%20Tanah%2C%20Kota%20Makassar%2C%20Sulawesi%20Selatan%2090164&output=embed"
                            width="100%"
                            height="320"
                            style="border:0;"
                            allowfullscreen=""
                            loading="lazy"
                            referrerpolicy="no-referrer-when-downgrade"
                            title="Lokasi SMA Negeri 4 Makassar">
                        </iframe>
                    </div>
                </div>



            </div>
        </div>
    </section>
    <?php
    $snbp_dir = 'public/uploads/index_kelulusan_snbp/';
    $snbp_images = [];
    if (is_dir($snbp_dir)) {
        $all_snbp = [];
        $pattern = $snbp_dir . '*.{jpg,jpeg,png,gif,webp}';
        foreach (glob($pattern, GLOB_BRACE) as $file) {
            if (is_file($file)) $all_snbp[] = basename($file);
        }

        $order_file = $snbp_dir . 'order.json';
        if (file_exists($order_file)) {
            $saved = json_decode(file_get_contents($order_file), true) ?? [];
            foreach ($saved as $f) {
                if (in_array($f, $all_snbp)) $snbp_images[] = $f;
            }
            foreach ($all_snbp as $f) {
                if (!in_array($f, $snbp_images)) $snbp_images[] = $f;
            }
        } else {
            usort($all_snbp, fn($a, $b) => filemtime($snbp_dir . $b) - filemtime($snbp_dir . $a));
            $snbp_images = $all_snbp;
        }
    }
    ?>
    <?php if (!empty($snbp_images)): ?>
    <section id="snbp-index" class="portal-section" style="background: #fffff;">
        <div class="section-header">
            <h2>Selamat kepada Siswa yang Telah Lulus SNBP</h2>
            <p>Prestasi Gemilang Siswa/Siswi SMA Negeri 4 Makassar</p>
        </div>
        
        <div class="snbp-carousel-container" style="max-width: 800px; margin: 0 auto; position: relative; overflow: hidden; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1);">
            <div class="snbp-carousel-track" id="snbpTrack" style="display: flex; transition: transform 0.5s ease-in-out;">
                <?php foreach ($snbp_images as $img): ?>
                    <div class="snbp-carousel-slide" style="min-width: 100%; box-sizing: border-box;">
                        <img src="<?php echo $snbp_dir . htmlspecialchars($img); ?>" alt="SNBP Graduate" style="width: 100%; height: auto; display: block; max-height: 500px; object-fit: contain; background: #fff;">
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Controls -->
            <button aria-label="Previous" onclick="moveSnbp(-1)" style="position: absolute; top: 50%; left: 10px; transform: translateY(-50%); background: rgba(0,0,0,0.5); color: #fff; border: none; width: 40px; height: 40px; border-radius: 50%; cursor: pointer; font-size: 1.5rem; display: flex; align-items: center; justify-content: center;">&#10094;</button>
            <button aria-label="Next" onclick="moveSnbp(1)" style="position: absolute; top: 50%; right: 10px; transform: translateY(-50%); background: rgba(0,0,0,0.5); color: #fff; border: none; width: 40px; height: 40px; border-radius: 50%; cursor: pointer; font-size: 1.5rem; display: flex; align-items: center; justify-content: center;">&#10095;</button>
        </div>

        <script>
            let currentSnbpSlide = 0;
            const snbpTrack = document.getElementById('snbpTrack');
            const totalSnbpSlides = <?php echo count($snbp_images); ?>;
            
            function moveSnbp(direction) {
                currentSnbpSlide = (currentSnbpSlide + direction + totalSnbpSlides) % totalSnbpSlides;
                updateSnbpCarousel();
            }
            
            function updateSnbpCarousel() {
                if (snbpTrack) {
                    snbpTrack.style.transform = `translateX(-${currentSnbpSlide * 100}%)`;
                }
            }

            // Auto slide every 4 seconds
            if (totalSnbpSlides > 1) {
                setInterval(() => {
                    moveSnbp(1);
                }, 4000);
            }
        </script>
    </section>
    <?php endif; ?>
    <section id="news" class="portal-section">
        <div class="section-header">
            <h2>Kabar Sekolah</h2>
            <p>Informasi terkini kegiatan dan prestasi siswa</p>
        </div>

        <div class="news-grid">
            <?php foreach ($news_items as $news): ?>
            <article class="news-card">
                <?php if ($news['image']): ?>
                    <img src="public/uploads/news/<?php echo htmlspecialchars($news['image']); ?>" class="news-img">
                <?php
    else: ?>
                    <div class="news-img" style="background: #f1f5f9; display: flex; align-items: center; justify-content: center; color: #94a3b8;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg>
                    </div>
                <?php
    endif; ?>
                <div class="news-body">
                    <h3 class="news-title"><?php echo htmlspecialchars($news['title']); ?></h3>
                    <p style="color: var(--text-muted); margin-bottom: 1.5rem;">
                        <?php echo substr(strip_tags($news['content']), 0, 100); ?>...
                    </p>
                    <a href="news_detail.php?id=<?php echo $news['id']; ?>" class="read-btn">Baca Selengkapnya &rarr;</a>
                </div>
            </article>
            <?php
endforeach; ?>
        </div>
        
        <div style="text-align: center; margin-top: 3rem;">
            <a href="news.php" class="btn" style="border-radius: 50px; padding: 1rem 2.5rem; font-size: 1.1rem; background: var(--primary); color: white; display: inline-block;">Lihat Semua Berita</a>
        </div>
    </section>



    <footer style="background: var(--bg-sidebar); color: #94a3b8; padding: 4rem 5%; margin-top: 0;">
        <div style="max-width: 1400px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 2rem;">
            <div>
                <h3 style="color: white; font-size: 1.5rem; font-weight: 800; margin-bottom: 0.5rem;">SMA Negeri 4 Makassar</h3>
                <p>&copy; <?php echo date('Y'); ?> SMA Negeri 4 Makassar - Digital School</p>
            </div>
            <div style="display: flex; gap: 2rem;">
                <a href="#" style="color: white; text-decoration: none;">Instagram</a>
                <a href="#" style="color: white; text-decoration: none;">Youtube</a>
                <a href="#" style="color: white; text-decoration: none;">Facebook</a>
            </div>
        </div>
    </footer>

    <script>
        function toggleMobileNav() {
            const drawer = document.getElementById('mobileNavDrawer');
            const overlay = document.getElementById('mobileNavOverlay');
            if (drawer.classList.contains('open')) {
                closeMobileNav();
            } else {
                drawer.classList.add('open');
                overlay.style.display = 'block';
                document.body.style.overflow = 'hidden';
            }
        }
        function closeMobileNav() {
            document.getElementById('mobileNavDrawer').classList.remove('open');
            document.getElementById('mobileNavOverlay').style.display = 'none';
            document.body.style.overflow = '';
        }
        function showProfilTab(tabId, btn) {
            document.querySelectorAll('.profil-pane').forEach(el => {
                el.style.display = 'none';
                el.classList.remove('active');
            });
            const activePane = document.getElementById('tab-' + tabId);
            activePane.style.display = 'block';
            setTimeout(() => activePane.classList.add('active'), 10);
            document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
            if (btn) btn.classList.add('active');
        }
    </script>
</body>
</html>
