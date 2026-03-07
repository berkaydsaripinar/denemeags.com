<?php
session_start();
unset($_SESSION['yazar_id']);
unset($_SESSION['yazar_name']);
header("Location: login.php");
exit;