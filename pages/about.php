<?php
// ------------------------------------------------------------
// pages/about.php — À propos (About us)
//
// Presents Poilu & Co: our story, our values, how a personalised
// product is made, and a few key figures.
// The figures (number of products, categories, price range) are read
// from the database, so they stay correct when the admin adds products.
// If MySQL is stopped, the page still works and simply hides them.
// ------------------------------------------------------------

require '../includes/db.php';
require '../includes/functions.php';

$base_path        = '../';
$current_page     = 'about';
$page_css         = 'about';      // loads assets/css/about.css
$page_title       = 'À propos · Poilu & Co';
$page_description = 'Poilu & Co, boutique belge d\'accessoires personnalisés pour chiens et chats, fabriqués à la demande et livrés en 5 jours ouvrables.';


// ---------- 1. Key figures from the database ----------
$figures = null;

if ($pdo !== null) {
    $figures = $pdo->query("
        SELECT COUNT(*)                     AS product_count,
               COUNT(DISTINCT category_id)  AS category_count,
               MIN(price)                   AS min_price,
               MAX(price)                   AS max_price
        FROM products
        WHERE is_active = 1
    ")->fetch();
}


// ---------- 2. Content of the page ----------

// Our values: icon (see category_icon() in functions.php), title, text
$values = [
    ['paw',      'Fait pour lui',          'Chaque accessoire porte le prénom ou le portrait de votre compagnon. Jamais deux fois le même.'],
    ['coffrets', 'Fabriqué à la demande',  'Rien n\'est produit à l\'avance : pas de stock inutile, et un objet fait rien que pour vous.'],
    ['medailles','Vu avant d\'être acheté', 'L\'aperçu en direct montre le résultat pendant que vous personnalisez. Pas de mauvaise surprise.'],
    ['colliers', 'Belge et rapide',         'Nos ateliers partenaires gravent et impriment en Belgique. Livraison en ' . DELIVERY_WORKING_DAYS . ' jours ouvrables.'],
];

// How a product is made
$steps = [
    ['Vous personnalisez', 'Prénom, couleur, taille ou photo : l\'aperçu change en direct sur la fiche produit.'],
    ['Nos ateliers fabriquent', 'La commande part chez nos partenaires de gravure et d\'impression, qui la réalisent à la main.'],
    ['Vous recevez', 'Le colis arrive chez vous, partout en Belgique, en ' . DELIVERY_WORKING_DAYS . ' jours ouvrables.'],
];

// Three product photos for the story block (only shown if the files exist)
$story_images = ['medaille-os.jpg', 'plaid-portrait.jpg', 'mug-portrait.jpg'];


// ---------- 3. Page HTML ----------
require '../includes/header.php';
?>

<!-- ===================== PAGE TITLE ===================== -->
<section class="page-hero">
    <div class="container">
        <nav class="breadcrumb" aria-label="Fil d'Ariane">
            <a href="../index.php">Accueil</a> <span aria-hidden="true">/</span>
            <span>À propos</span>
        </nav>
        <h1>Parce qu'ils font <em>partie de la famille</em></h1>
        <p class="page-intro">Poilu &amp; Co crée des accessoires personnalisés pour les chiens, les chats et les humains qui les aiment.</p>
    </div>
</section>


<!-- ===================== OUR STORY ===================== -->
<section class="section">
    <div class="container about-story">
        <div class="about-story-text reveal">
            <p class="eyebrow">Notre histoire</p>
            <h2>Une idée née à Bruxelles</h2>
            <p>Tout a commencé avec une médaille. Celle d'un chien trouvé dans un parc d'Ixelles, sans aucun moyen de joindre sa famille. Nous nous sommes dit qu'un objet aussi utile pouvait aussi être beau, et surtout unique.</p>
            <p>Poilu &amp; Co propose aujourd'hui des médailles gravées, des colliers, des gamelles, des plaids, des mugs et des coffrets cadeaux. Chacun porte le prénom ou le portrait de votre compagnon, et chacun est fabriqué seulement quand vous le commandez.</p>
            <p>Nous pensons à ceux qui considèrent leur animal comme un membre de la famille. Et à ceux qui cherchent un cadeau qui fera vraiment plaisir.</p>
        </div>

        <div class="about-story-images reveal" aria-hidden="true">
            <?php foreach ($story_images as $index => $image): ?>
                <?php if (file_exists(__DIR__ . '/../assets/images/products/' . $image)): ?>
                    <img src="../assets/images/products/<?= e($image) ?>" alt="" class="about-image about-image-<?= $index + 1 ?>" loading="lazy">
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>


<!-- ===================== KEY FIGURES (from the database) ===================== -->
<?php if ($figures && $figures['product_count'] > 0): ?>
<section class="about-figures">
    <div class="container about-figures-grid">
        <div>
            <strong><?= (int) $figures['product_count'] ?></strong>
            <span>accessoires personnalisables</span>
        </div>
        <div>
            <strong><?= (int) $figures['category_count'] ?></strong>
            <span>catégories, pour chiens et chats</span>
        </div>
        <div>
            <strong><?= format_price($figures['min_price']) ?></strong>
            <span>à <?= format_price($figures['max_price']) ?>, TVA comprise</span>
        </div>
        <div>
            <strong><?= DELIVERY_WORKING_DAYS ?> jours</strong>
            <span>ouvrables pour être livré en Belgique</span>
        </div>
    </div>
</section>
<?php endif; ?>


<!-- ===================== OUR VALUES ===================== -->
<section class="section">
    <div class="container">
        <div class="section-head section-head-center">
            <p class="eyebrow">Ce qui nous guide</p>
            <h2>Nos engagements</h2>
        </div>

        <div class="about-values">
            <?php foreach ($values as [$icon, $title, $text]): ?>
                <article class="about-value reveal">
                    <span class="about-value-icon"><?= category_icon($icon) ?></span>
                    <h3><?= e($title) ?></h3>
                    <p><?= e($text) ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>


<!-- ===================== HOW IT IS MADE ===================== -->
<section class="section about-making">
    <div class="container">
        <div class="section-head section-head-center">
            <p class="eyebrow">De votre écran à sa gamelle</p>
            <h2>Comment naît un accessoire</h2>
        </div>

        <ol class="about-steps">
            <?php foreach ($steps as $index => [$title, $text]): ?>
                <li class="reveal">
                    <span class="about-step-number"><?= $index + 1 ?></span>
                    <h3><?= e($title) ?></h3>
                    <p><?= e($text) ?></p>
                </li>
            <?php endforeach; ?>
        </ol>

        <p class="about-note">Projet universitaire : la fabrication, le paiement et la livraison sont simulés.</p>
    </div>
</section>


<!-- ===================== CALL TO ACTION ===================== -->
<section class="section">
    <div class="container">
        <div class="about-cta">
            <div>
                <h2>Une question, une idée de cadeau ?</h2>
                <p>Écrivez-nous à <a href="mailto:bonjour@poilu-and-co.be">bonjour@poilu-and-co.be</a>, du lundi au vendredi de 9 h à 17 h.</p>
            </div>
            <div class="about-cta-buttons">
                <a href="products.php" class="button">Découvrir la boutique <span aria-hidden="true">→</span></a>
                <a href="../index.php#faq" class="button button-outline">Questions fréquentes</a>
            </div>
        </div>
    </div>
</section>

<?php require '../includes/footer.php'; ?>