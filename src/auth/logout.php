<?php
// src/auth/logout.php
session_start();
session_destroy();

$redirect = isset($_GET['redirect']) ? $_GET['redirect'] : '/index.php';
// Prevent open redirect by ensuring it's local or a known relative path
if (strpos($redirect, 'http') === 0) {
    $redirect = '/index.php';
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="refresh" content="0;url=<?php echo htmlspecialchars($redirect); ?>">
</head>
<body>
    Logging out...
    <script>window.location.href = '<?php echo addslashes($redirect); ?>';</script>
</body>
</html>


