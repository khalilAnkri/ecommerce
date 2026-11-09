<?php
// ------------------------------------------------------------
// includes/header.php
// Top of every page: <head>, logo, search bar, menu, cart icon.
//
// Before including this file, each page sets:
//   $base_path        ''    in index.php   |   '../'  in pages/*.php
//   $page_title       text in the browser tab
//   $page_description short text for Google (SEO)
//   $current_page     'home', 'products', ... to highlight the menu link
// ------------------------------------------------------------

$base_path        = $base_path        ?? '';
$page_title       = $page_title       ?? 'Poilu & Co';
$page_description = $page_description ?? 'Accessoires personnalisés pour chiens et chats, livrés en Belgique.';
$current_page     = $current_page     ?? '';

// Where the account icon goes: login page, or account page if logged in.
$account_link = is_logged_in() ? 'pages/account.php' : 'pages/login.php';
$cart_items   = cart_count();

// The categories shown in the "Nos catégories" drop-down menu.
$menu_categories = [
    'medailles' => 'Médailles gravées',
    'colliers'  => 'Colliers',
    'gamelles'  => 'Gamelles',
    'plaids'    => 'Plaids & coussins',
    'mugs'      => 'Mugs',
    'coffrets'  => 'Coffrets cadeaux',
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title) ?></title>
    <meta name="description" content="<?= e($page_description) ?>">

    <!-- Fonts: Fraunces (titles) and Nunito (text) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,600;1,9..144,400;1,9..144,600&family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="<?= $base_path ?>assets/css/style.css">
</head>
<body>

<header class="site-header">

    <!-- Row 1: logo, search, contact -->
    <div class="header-top container">
        <a href="<?= $base_path ?>index.php" class="logo" aria-label="Poilu & Co, retour à l'accueil">
          <img src="<?= $base_path ?>assets/images/logo/poilu.png" alt="Poilu & Co" style="height: 50px; width: auto;">
        </a>

        <form class="search-form" action="<?= $base_path ?>pages/products.php" method="get" role="search">
            <input type="search" name="search" placeholder="Rechercher une médaille, un mug…" aria-label="Rechercher un produit">
            <button type="submit" aria-label="Lancer la recherche">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            </button>
        </form>

        <div class="header-contact">
            <div>
                <span>Livraison</span>
                <strong>Belgique · <?= DELIVERY_WORKING_DAYS ?> jours ouvrables</strong>
            </div>
            <div>
                <span>Email</span>
                <a href="mailto:bonjour@poilu-and-co.be">bonjour@poilu-and-co.be</a>
            </div>
        </div>

        <!-- Burger button: only visible on mobile -->
        <button class="menu-toggle" type="button" aria-label="Ouvrir le menu" aria-expanded="false" data-menu-toggle>
            <span></span><span></span><span></span>
        </button>
    </div>

    <!-- Row 2: categories, menu links, icons -->
    <div class="header-nav">
        <div class="container header-nav-inner">

            <div class="category-menu">
                <button type="button" class="category-menu-button" aria-expanded="false" data-category-toggle>
                    Nos catégories
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="m6 9 6 6 6-6"/></svg>
                </button>
                <ul class="category-menu-list">
                    <?php foreach ($menu_categories as $slug => $label): ?>
                        <li><a href="<?= $base_path ?>pages/products.php?category=<?= e($slug) ?>"><?= e($label) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <nav class="main-nav" data-main-nav aria-label="Menu principal">
                <a href="<?= $base_path ?>index.php" class="<?= $current_page === 'home' ? 'is-active' : '' ?>">Accueil</a>
                <a href="<?= $base_path ?>pages/products.php" class="<?= $current_page === 'products' ? 'is-active' : '' ?>">Boutique</a>
                <a href="<?= $base_path ?>index.php#how-it-works">Comment ça marche</a>
                <a href="<?= $base_path ?>index.php#about">À propos</a>
                <a href="<?= $base_path ?>index.php#faq">FAQ</a>
                <a href="<?= $base_path ?>index.php#contact">Contact</a>
            </nav>

            <div class="header-icons">
                <a href="<?= $base_path . $account_link ?>" aria-label="Mon compte">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6"/></svg>
                </a>
                <a href="<?= $base_path ?>pages/cart.php" class="cart-link" aria-label="Mon panier (<?= $cart_items ?> articles)">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 8h14l-1.2 11.1a2 2 0 0 1-2 1.9H8.2a2 2 0 0 1-2-1.9z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/></svg>
                    <span class="cart-badge"><?= $cart_items ?></span>
                </a>
            </div>

        </div>
    </div>
</header>

<main>
