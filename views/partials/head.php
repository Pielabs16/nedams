<?php
// ============================================================
// views/partials/head.php
// HTML head — include before <body>
// ============================================================
$pageTitle = $pageTitle ?? 'NEDAMS';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="NEDAMS — Digital Addressing & Community Mapping System">
    <title><?= htmlspecialchars($pageTitle) ?> — <?= htmlspecialchars(appName()) ?></title>

    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- IBM Plex fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@300;400;500;600;700&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
    <!-- Main CSS -->
    <link rel="stylesheet" href="<?= appUrl() ?>/assets/css/style.css">
    <!-- Favicon -->
    <link rel="icon" href="<?= appUrl() ?>/assets/img/favicon.svg" type="image/svg+xml">
</head>
<body>
