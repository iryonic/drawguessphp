<?php
/**
 * Global SEO & Favicon Head Partial
 * Include in every view to maintain consistent SEO metadata.
 * 
 * Available variables (passed from the view):
 *  $seo_title       - Page-specific title (without site name)
 *  $seo_description - Page-specific description
 *  $seo_keywords    - Comma-separated keywords
 *  $seo_og_type     - Open Graph type (default: website)
 *  $seo_canonical   - Canonical URL (full URL)
 */

$site_name    = 'Draw & Guess Royale';
$default_desc = 'Play Draw & Guess Royale — the free, real-time multiplayer drawing and guessing game. Create a room, invite friends, and race to guess the word!';
$default_keys = 'draw and guess, drawing game, multiplayer drawing game, skribbl alternative, online guessing game, free drawing game, drawguess , irfan manzoor , iry , irony , itachi uchiha , scribble , scribblr by irfan ';

$title       = isset($seo_title)       ? "{$seo_title} — {$site_name}" : $site_name;
$description = isset($seo_description) ? $seo_description               : $default_desc;
$keywords    = isset($seo_keywords)    ? $seo_keywords                  : $default_keys;
$og_type     = isset($seo_og_type)     ? $seo_og_type                   : 'website';
$canonical   = isset($seo_canonical)   ? $seo_canonical : 'https://drawguess.irfanmanzoor.in/';

$icon_base = (isset($base_path) ? $base_path : '/drawguess/') . 'assets/pwa/';
?>
<!-- ======= SEO Meta Tags ======= -->
<title><?= htmlspecialchars($title) ?></title>
<meta name="description" content="<?= htmlspecialchars($description) ?>">
<meta name="keywords" content="<?= htmlspecialchars($keywords) ?>">
<meta name="author" content="Irfan Manzoor">
<meta name="robots" content="index, follow">
<link rel="canonical" href="<?= htmlspecialchars($canonical) ?>">

<!-- ======= Open Graph (Facebook, WhatsApp, etc.) ======= -->
<meta property="og:type"        content="<?= $og_type ?>">
<meta property="og:title"       content="<?= htmlspecialchars($title) ?>">
<meta property="og:description" content="<?= htmlspecialchars($description) ?>">
<meta property="og:image"       content="<?= $icon_base ?>icon-512.png">
<meta property="og:site_name"   content="<?= $site_name ?>">
<meta property="og:url"         content="<?= htmlspecialchars($canonical) ?>">
<meta property="og:locale"      content="en_US">

<!-- ======= Twitter Card ======= -->
<meta name="twitter:card"        content="summary">
<meta name="twitter:title"       content="<?= htmlspecialchars($title) ?>">
<meta name="twitter:description" content="<?= htmlspecialchars($description) ?>">
<meta name="twitter:image"       content="<?= $icon_base ?>icon-512.png">

<!-- ======= Favicons ======= -->
<link rel="icon"             href="<?= $icon_base ?>favicon.png" type="image/png">
<link rel="shortcut icon"    href="<?= $icon_base ?>favicon.png" type="image/png">
<link rel="apple-touch-icon" href="<?= $icon_base ?>icon-512.png">

<!-- ======= PWA / Theme ======= -->
<link rel="manifest"         href="<?= isset($base_path) ? $base_path : '/drawguess/' ?>manifest.json">
<meta name="theme-color"     content="#ffeb3b">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="DrawGuess">
