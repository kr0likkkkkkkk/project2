<?php
session_start();
header('HTTP/1.1 401 Unauthorized');
header('Location: admin.php');
exit();
?>