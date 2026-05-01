<?php
ini_set('display_errors',1); error_reporting(E_ALL);
ini_set('memory_limit','512M'); set_time_limit(300);
session_start();
require_once '../../config/database.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    if (isset($_POST['ajax_batch'])||(isset($_SERVER['HTTP_X_REQUESTED_WITH'])&&strtolower($_SERVER['HTTP_X_REQUESTED_WITH'])==='xmlhttprequest')) { http_response_code(401); echo "AUTH_FAILED"; exit; }
    header("Location: ../../login.php"); exit;
}
$upload_dir = str_replace('\\','/',__DIR__.'/../../public/uploads/index_kelulusan_snbp/');
if (!is_dir($upload_dir)) mkdir($upload_dir,0777,true);

function compressImage($src,$dst,$q){
    if(!function_exists('imagecreatefromjpeg')||!file_exists($src)) return false;
    $info=getimagesize($src); if(!$info) return false;
    $img=null;
    if($info['mime']==='image/jpeg') $img=imagecreatefromjpeg($src);
    elseif($info['mime']==='image/gif')  $img=imagecreatefromgif($src);
    elseif($info['mime']==='image/png')  { $img=imagecreatefrompng($src); if($img){imagepalettetotruecolor($img);imagealphablending($img,true);imagesavealpha($img,true);} }
    elseif($info['mime']==='image/webp') $img=imagecreatefromwebp($src);
    if(!$img) return false;
    imagejpeg($img,$dst,$q); imagedestroy($img); return true;
}

if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='upload') {
    $ok=$fail=0; $allowed=['image/jpeg','image/png','image/gif','image/webp'];
    if(isset($_FILES['photos'])&&is_array($_FILES['photos']['name'])) {
        foreach($_FILES['photos']['name'] as $k=>$name) {
            if($_FILES['photos']['error'][$k]===UPLOAD_ERR_OK) {
                $tmp=$_FILES['photos']['tmp_name'][$k]; $type=$_FILES['photos']['type'][$k];
                if(in_array($type,$allowed)) {
                    $dest=$upload_dir.time().'_'.rand(1000,9999).'_'.preg_replace('/[^a-zA-Z0-9_.-]/','',pathinfo($name,PATHINFO_FILENAME)).'.jpg';
                    if(compressImage($tmp,$dest,60)||move_uploaded_file($tmp,$dest)) $ok++; else $fail++;
                } else $fail++;
            } elseif($_FILES['photos']['error'][$k]!==UPLOAD_ERR_NO_FILE) $fail++;
        }
        if(isset($_POST['ajax_batch'])) { echo $ok>0?'ok':'failed'; exit; }
        $_SESSION[$ok>0?'success_msg':'error_msg']=$ok>0?"$ok gambar berhasil diunggah".($fail>0?" ($fail gagal)":"").".":"Gagal mengunggah file.";
    } else { if(isset($_POST['ajax_batch'])){echo "No files.";exit;} $_SESSION['error_msg']="Gagal memproses form."; }
    header("Location: manage_snbp.php"); exit;
}
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='save_order') {
    $order=json_decode($_POST['order']??'',true);
    if(is_array($order)){ $clean=array_values(array_filter(array_map('basename',$order),fn($f)=>$f&&file_exists($upload_dir.$f))); file_put_contents($upload_dir.'order.json',json_encode($clean)); echo 'ok'; } else { http_response_code(400); echo 'invalid'; } exit;
}
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='delete') {
    $f=basename($_POST['filename']??''); $fp=$upload_dir.$f;
    if($f&&file_exists($fp)&&is_file($fp)) {
        unlink($fp);
        if(file_exists($upload_dir.'order.json')){ $ord=json_decode(file_get_contents($upload_dir.'order.json'),true)??[]; file_put_contents($upload_dir.'order.json',json_encode(array_values(array_filter($ord,fn($x)=>$x!==$f)))); }
        $_SESSION['success_msg']="Gambar berhasil dihapus.";
    } else $_SESSION['error_msg']="File tidak ditemukan.";
    header("Location: manage_snbp.php"); exit;
}

$all_files=[];
foreach(glob($upload_dir.'*.{jpg,jpeg,png,gif,webp}',GLOB_BRACE) as $f) if(is_file($f)) $all_files[]=basename($f);
$images=[]; $order_file=$upload_dir.'order.json';
if(file_exists($order_file)){ $saved=json_decode(file_get_contents($order_file),true)??[]; foreach($saved as $f) if(in_array($f,$all_files)) $images[]=$f; foreach($all_files as $f) if(!in_array($f,$images)) $images[]=$f; }
else { usort($all_files,fn($a,$b)=>filemtime($upload_dir.$b)-filemtime($upload_dir.$a)); $images=$all_files; }
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Kelola SNBP — Admin</title>
<link rel="stylesheet" href="/public/assets/css/style.css">
<style>
.gallery-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(180px,1fr)); gap:16px; }
.gallery-item { background:var(--bg-surface); border:1px solid var(--border); border-radius:var(--radius-lg); padding:12px; text-align:center; position:relative; user-select:none; transition:border-color 0.15s; }
.gallery-item.drag-over { border-color:var(--primary); background:var(--primary-light); }
.gallery-img  { width:100%; height:130px; object-fit:contain; border-radius:var(--radius-sm); margin-bottom:10px; background:var(--bg-muted); }
.order-badge  { position:absolute; top:8px; left:8px; background:var(--bg-sidebar); color:#fff; font-size:0.625rem; font-weight:700; padding:2px 6px; border-radius:20px; }
.drag-handle  { color:var(--text-muted); font-size:1rem; margin-bottom:6px; cursor:grab; }
@keyframes spin { to { transform:rotate(360deg); } }
</style>
</head>
<body>
<div class="app-container">
<?php include '../templates/sidebar.php'; ?>
<main class="main-content">

    <div class="page-toolbar">
        <div class="page-toolbar-left">
            <h1 class="page-title">Kelulusan SNBP</h1>
            <p class="page-subtitle">Upload dan atur urutan foto siswa lulus SNBP untuk ditampilkan di halaman utama</p>
        </div>
    </div>

    <!-- Upload progress overlay -->
    <div id="uploadOverlay" style="display:none;position:fixed;inset:0;background:rgba(17,24,39,0.8);z-index:9999;align-items:center;justify-content:center;">
        <div style="background:#fff;border-radius:var(--radius-lg);padding:32px;max-width:440px;width:90%;text-align:center;box-shadow:0 25px 50px rgba(0,0,0,0.3);">
            <div style="width:44px;height:44px;border:4px solid var(--border);border-top-color:var(--primary);border-radius:50%;animation:spin 0.8s linear infinite;margin:0 auto 16px;"></div>
            <div id="overlayTitle" style="font-size:0.9375rem;font-weight:700;color:var(--text-primary);margin-bottom:6px;">Menyiapkan...</div>
            <div id="overlayDetail" style="font-size:0.8125rem;color:var(--text-muted);margin-bottom:14px;"><b>Jangan tutup</b> halaman ini.</div>
            <div style="height:8px;background:var(--border);border-radius:4px;overflow:hidden;margin-bottom:6px;">
                <div id="overlayBar" style="height:100%;width:0%;background:var(--primary);border-radius:4px;transition:width 0.3s;"></div>
            </div>
            <div id="overlayPercent" style="font-size:0.8125rem;font-weight:700;color:var(--primary);">0%</div>
            <div id="overlayCount"   style="font-size:0.75rem;color:var(--text-muted);margin-top:4px;"></div>
        </div>
    </div>

    <div class="page-content">

        <?php if(isset($_SESSION['success_msg'])): ?><div class="alert alert-success"><?php echo $_SESSION['success_msg']; unset($_SESSION['success_msg']); ?></div><?php endif; ?>
        <?php if(isset($_SESSION['error_msg'])):   ?><div class="alert alert-danger"><?php echo $_SESSION['error_msg'];   unset($_SESSION['error_msg']);   ?></div><?php endif; ?>

        <!-- Upload panel -->
        <div class="page-section">
            <div class="panel-header">
                <h3 class="panel-title">Unggah Foto Baru</h3>
            </div>
            <form id="uploadForm" action="manage_snbp.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload">
                <input type="hidden" name="ajax_batch" value="1">
                <div style="display:flex;gap:10px;align-items:center;">
                    <input type="file" id="photoInput" name="photos[]" accept="image/*" multiple required style="flex:1;">
                    <button type="submit" id="uploadBtn" class="btn">Unggah Foto</button>
                </div>
                <p style="font-size:0.75rem;color:var(--text-muted);margin-top:8px;">Pilih <b>banyak foto</b> sekaligus — sistem mengirim satu per satu otomatis. Format: JPG, PNG, WEBP.</p>
            </form>
        </div>

        <!-- Gallery panel -->
        <div class="page-section">
            <div class="panel-header">
                <h3 class="panel-title">Daftar Foto (<?php echo count($images); ?>)</h3>
                <div class="panel-meta">
                    <span style="font-size:0.75rem;color:var(--text-muted);">Geser untuk mengatur urutan</span>
                    <button id="saveOrderBtn" onclick="saveOrder()" style="display:none;" class="btn btn-success btn-sm">Simpan Urutan</button>
                </div>
            </div>

            <?php if(empty($images)): ?>
            <div class="empty-state">
                <div class="empty-icon">📷</div>
                <h4>Belum ada foto</h4>
                <p>Upload foto di panel di atas untuk memulai.</p>
            </div>
            <?php else: ?>
            <div id="galleryGrid" class="gallery-grid">
                <?php foreach($images as $idx=>$img): ?>
                <div class="gallery-item" draggable="true" data-filename="<?php echo htmlspecialchars($img); ?>">
                    <div class="order-badge">#<?php echo $idx+1; ?></div>
                    <div class="drag-handle">⠿</div>
                    <img src="/public/uploads/index_kelulusan_snbp/<?php echo htmlspecialchars($img); ?>" alt="SNBP" class="gallery-img">
                    <form action="manage_snbp.php" method="POST" onsubmit="return confirm('Hapus foto ini?');" style="margin:0;">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="filename" value="<?php echo htmlspecialchars($img); ?>">
                        <button type="submit" class="btn btn-danger btn-xs" style="width:100%;">Hapus</button>
                    </form>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

    </div>
</main>
</div>

<script>
// Upload batch
document.getElementById('uploadForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const files = document.getElementById('photoInput').files;
    if (!files.length) return;
    const overlay=document.getElementById('uploadOverlay'), title=document.getElementById('overlayTitle'),
          detail=document.getElementById('overlayDetail'), bar=document.getElementById('overlayBar'),
          pct=document.getElementById('overlayPercent'), cnt=document.getElementById('overlayCount');
    overlay.style.display='flex';
    let done=0, fail=0;
    const compress=(file)=>new Promise(res=>{
        if(!file.type.startsWith('image/')){res(file);return;}
        const r=new FileReader(); r.readAsDataURL(file);
        r.onload=ev=>{const img=new Image(); img.src=ev.target.result; img.onload=()=>{const MAX=1280;let w=img.width,h=img.height;if(w>MAX){h=h*MAX/w;w=MAX;}if(h>MAX){w=w*MAX/h;h=MAX;}const c=document.createElement('canvas');c.width=w;c.height=h;c.getContext('2d').drawImage(img,0,0,w,h);c.toBlob(b=>res(b?new File([b],file.name.replace(/\.[^.]+$/,'.jpg'),{type:'image/jpeg'}):file),'image/jpeg',0.82);};img.onerror=()=>res(file);};
        r.onerror=()=>res(file);
    });
    for(let i=0;i<files.length;i++){
        const p=Math.round(i/files.length*100);
        bar.style.width=p+'%'; pct.textContent=p+'%';
        title.textContent=`Mengunggah ${i+1} dari ${files.length}…`;
        cnt.textContent=`✓ ${done}   ✗ ${fail}`;
        try{
            const comp=await compress(files[i]), fd=new FormData();
            fd.append('action','upload'); fd.append('ajax_batch','1'); fd.append('photos[]',comp);
            const resp=await fetch('manage_snbp.php',{method:'POST',body:fd,credentials:'include'});
            const txt=await resp.text();
            if(resp.ok&&txt.trim()==='ok') done++; else { fail++; if(txt.trim()==='AUTH_FAILED'){title.textContent='Sesi habis — login ulang.';setTimeout(()=>location.reload(),2000);return;} }
        }catch{fail++;}
    }
    bar.style.width='100%'; pct.textContent='100%';
    bar.style.background=fail===0?'var(--success)':'var(--warning)';
    title.textContent=fail===0?'✅ Semua foto berhasil!':'⚠ Selesai: '+done+' berhasil, '+fail+' gagal';
    detail.textContent='Halaman akan dimuat ulang…'; cnt.textContent='';
    setTimeout(()=>location.reload(),1500);
});

// Drag & drop reorder
(function(){
    const grid=document.getElementById('galleryGrid'); if(!grid) return;
    const saveBtn=document.getElementById('saveOrderBtn'); let dragSrc=null;
    grid.addEventListener('dragstart',e=>{dragSrc=e.target.closest('.gallery-item');if(!dragSrc)return;dragSrc.style.opacity='0.4';e.dataTransfer.effectAllowed='move';});
    grid.addEventListener('dragend',e=>{const it=e.target.closest('.gallery-item');if(it)it.style.opacity='1';grid.querySelectorAll('.gallery-item').forEach(el=>el.classList.remove('drag-over'));});
    grid.addEventListener('dragover',e=>{e.preventDefault();const t=e.target.closest('.gallery-item');if(!t||t===dragSrc)return;grid.querySelectorAll('.gallery-item').forEach(el=>el.classList.remove('drag-over'));t.classList.add('drag-over');});
    grid.addEventListener('drop',e=>{e.preventDefault();const t=e.target.closest('.gallery-item');if(!t||t===dragSrc)return;const all=[...grid.querySelectorAll('.gallery-item')];all.indexOf(dragSrc)<all.indexOf(t)?grid.insertBefore(dragSrc,t.nextSibling):grid.insertBefore(dragSrc,t);t.classList.remove('drag-over');saveBtn.style.display='inline-flex';updateBadges();});
    function updateBadges(){grid.querySelectorAll('.gallery-item').forEach((el,i)=>{const b=el.querySelector('.order-badge');if(b)b.textContent='#'+(i+1);});}
    window.saveOrder=async function(){
        const order=[...grid.querySelectorAll('.gallery-item')].map(el=>el.dataset.filename);
        saveBtn.textContent='Menyimpan…'; saveBtn.disabled=true;
        const fd=new FormData(); fd.append('action','save_order'); fd.append('order',JSON.stringify(order));
        try{const r=await fetch('manage_snbp.php',{method:'POST',body:fd,credentials:'include'}), t=await r.text();
            if(r.ok&&t.trim()==='ok'){saveBtn.textContent='✓ Tersimpan';saveBtn.style.background='var(--success)';setTimeout(()=>{saveBtn.textContent='Simpan Urutan';saveBtn.style.background='';saveBtn.disabled=false;saveBtn.style.display='none';},2000);}
            else{saveBtn.textContent='✗ Gagal';saveBtn.disabled=false;}
        }catch{saveBtn.textContent='✗ Error';saveBtn.disabled=false;}
    };
})();
</script>
</body>
</html>
