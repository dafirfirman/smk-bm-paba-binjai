<?php
function getPageTitle($page_title = '') {
    $base_title = "Sistem Pembayaran Uang Sekolah - SMK BM PABA BINJAI";
    return $page_title ? "$page_title | $base_title" : $base_title;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= getPageTitle($page_title) ?></title>
    <link rel="stylesheet" href="../assets/css/style_<?= $_SESSION['user']['role'] ?>.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>