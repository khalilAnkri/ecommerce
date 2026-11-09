<?php


require 'includes/db.php';
require 'includes/functions.php';

// Settings read by includes/header.php
$base_path        = '';
$page_title       = 'Poilu & Co · Accessoires personnalisés pour chiens et chats';
$page_description = 'Médailles gravées, colliers, gamelles, plaids et mugs personnalisés au nom de votre animal. Aperçu en direct et livraison en Belgique en 5 jours ouvrables.';
$current_page     = 'home';


// ---------- 1. Newsletter form (POST) ----------
// We handle the form BEFORE any HTML is sent, then redirect
// (Post/Redirect/Get) so a page refresh does not resend the form.
// The newsletter is simulated: the email is validated but not stored.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['newsletter_email'])) {
    $email = trim($_POST['newsletter_email']);

    if (!check_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Le formulaire a expiré. Merci de réessayer.');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        set_flash('error', 'Merci d\'entrer une adresse email valide.');
    } else {
        set_flash('success', 'Merci ! Votre inscription est enregistrée (simulation).');
    }

    header('Location: index.php#newsletter');
    exit;
}
$flash = get_flash();


// ---------- 2. Content of the page ----------

$hero_slides = [
    [
        'eyebrow'   => 'Gravure offerte sur toutes les médailles',
        'title'     => 'Un accessoire',
        'highlight' => 'à son nom',
        'text'      => 'Médailles, colliers, gamelles et plaids personnalisés pour chiens et chats. Vous voyez le résultat avant de commander.',
        'button'    => 'Personnaliser maintenant',
        'link'      => 'pages/products.php',
        'visual'    => 'tag',
        'image'     => 'hero-1.jpg',
    ],
    [
        'eyebrow'   => 'Nouveau : mugs et plaids portrait',
        'title'     => 'Son portrait,',
        'highlight' => 'partout avec vous',
        'text'      => 'Envoyez une photo de votre compagnon : nous l\'imprimons sur un mug, un coussin ou un plaid tout doux.',
        'button'    => 'Voir les plaids & mugs',
        'link'      => 'pages/products.php?category=plaids',
        'visual'    => 'mug',
        'image'     => 'hero-2.jpg',
    ],
    [
        'eyebrow'   => 'Une nouvelle boule de poils à la maison ?',
        'title'     => 'Le coffret',
        'highlight' => 'adoption',
        'text'      => 'Médaille, gamelle et plaid réunis dans une jolie boîte : le cadeau parfait pour une adoption.',
        'button'    => 'Découvrir les coffrets',
        'link'      => 'pages/products.php?category=coffrets',
        'visual'    => 'gift',
        'image'     => 'hero-3.jpg',
    ],
];

// Categories shown under the hero (slug => name).
$categories = [
    'medailles' => 'Médailles',
    'colliers'  => 'Colliers',
    'gamelles'  => 'Gamelles',
    'plaids'    => 'Plaids & coussins',
    'mugs'      => 'Mugs',
    'coffrets'  => 'Coffrets',
];

// Best-sellers.

$featured_products = [
    ['id' => 1, 'name' => 'Médaille os gravée',          'category_slug' => 'medailles', 'category_name' => 'Médailles', 'price' => 19.90, 'old_price' => null,  'badge' => 'Nouveau',     'image' => 'medaille-os.jpg'],
    ['id' => 2, 'name' => 'Collier cuir brodé au prénom', 'category_slug' => 'colliers',  'category_name' => 'Colliers',  'price' => 34.90, 'old_price' => null,  'badge' => 'Best-seller', 'image' => 'collier-cuir.jpg'],
    ['id' => 3, 'name' => 'Gamelle céramique prénom',    'category_slug' => 'gamelles',  'category_name' => 'Gamelles',  'price' => 29.90, 'old_price' => 34.90, 'badge' => '-15 %',       'image' => 'gamelle-ceramique.jpg'],
    ['id' => 4, 'name' => 'Plaid portrait de votre animal', 'category_slug' => 'plaids', 'category_name' => 'Plaids',    'price' => 54.90, 'old_price' => null,  'badge' => null,          'image' => 'plaid-portrait.jpg'],
];

$testimonials = [
    ['name' => 'Camille, Ixelles',  'pet' => 'maman de Moka 🐶',   'text' => 'La médaille est magnifique et la gravure très nette. L\'aperçu correspondait exactement au résultat.'],
    ['name' => 'Thomas, Gand',      'pet' => 'papa de Pixel 🐱',   'text' => 'J\'ai offert le mug avec la photo de Pixel à ma sœur. Reçu en 4 jours, elle a adoré !'],
    ['name' => 'Sarah, Namur',      'pet' => 'maman de Lou 🐶',    'text' => 'Le coffret adoption est super bien présenté. Parfait pour accueillir notre petite chienne.'],
];

// FAQ: the practical information every customer needs.
$faq = [
    ['question' => 'En combien de temps vais-je recevoir ma commande ?',
     'answer'   => 'Chaque article est fabriqué à la demande puis expédié. Comptez ' . DELIVERY_WORKING_DAYS . ' jours ouvrables maximum pour une livraison partout en Belgique.'],
    ['question' => 'Combien coûte la livraison ?',
     'answer'   => 'La livraison coûte ' . format_price(SHIPPING_COST) . ' et elle est offerte dès ' . format_price(FREE_SHIPPING_FROM) . ' d\'achat. Nous livrons uniquement en Belgique pour le moment.'],
    ['question' => 'Puis-je voir mon produit avant de commander ?',
     'answer'   => 'Oui ! Sur chaque fiche produit, l\'aperçu se met à jour en direct quand vous tapez le prénom, choisissez la couleur ou ajoutez une photo.'],
    ['question' => 'Quelle photo dois-je envoyer pour un portrait ?',
     'answer'   => 'Une photo nette et bien éclairée de votre animal, au format JPG, PNG ou WEBP, de 5 Mo maximum.'],
    ['question' => 'Puis-je retourner un article personnalisé ?',
     'answer'   => 'Un produit personnalisé est fabriqué rien que pour vous : il ne peut pas être échangé. En cas de défaut ou d\'erreur de notre part, nous le refaisons gratuitement.'],
    ['question' => 'Comment suivre ma commande ?',
     'answer'   => 'Dans votre espace client, chaque commande affiche son statut : payée, en production, expédiée puis livrée.'],
];


// ---------- 3. Page HTML ----------
require 'includes/header.php';
?>

<!-- ===================== HERO SLIDER ===================== -->
<section class="hero" data-slider>
    <div class="container hero-inner">

        <?php foreach ($hero_slides as $index => $slide): ?>
            <div class="hero-slide <?= $index === 0 ? 'is-active' : '' ?>" data-slide>

                <div class="hero-visual">
                    <div class="hero-circle"></div>

                    <?php if (file_exists('assets/images/banners/' . $slide['image'])): ?>
                        <img src="assets/images/banners/<?= e($slide['image']) ?>" alt="" class="hero-photo">

                    <?php elseif ($slide['visual'] === 'tag'): ?>
                        <!-- Illustration: engraved ID tag -->
                        <svg class="hero-illustration" viewBox="0 0 320 360" aria-hidden="true">
                            <defs>
                                <linearGradient id="brass" x1="0" y1="0" x2="1" y2="1">
                                    <stop offset="0" stop-color="#E9C894"/><stop offset="1" stop-color="#C08A50"/>
                                </linearGradient>
                            </defs>
                            <path d="M40 40 Q160 150 280 40" fill="none" stroke="#9B6B43" stroke-width="16" stroke-linecap="round"/>
                            <circle cx="160" cy="106" r="16" fill="none" stroke="#B88A5C" stroke-width="6"/>
                            <circle cx="160" cy="226" r="104" fill="url(#brass)"/>
                            <circle cx="160" cy="226" r="90" fill="none" stroke="#FFF3DE" stroke-opacity=".55" stroke-width="2"/>
                            <circle cx="160" cy="140" r="8" fill="#F6EEE4"/>
                            <text x="160" y="232" text-anchor="middle" font-family="Fraunces, serif" font-size="52" font-style="italic" fill="#5A3B22">Moka</text>
                            <text x="160" y="268" text-anchor="middle" font-family="Nunito, sans-serif" font-size="17" letter-spacing="1" fill="#5A3B22">+32 470 12 34 56</text>
                        </svg>

                    <?php elseif ($slide['visual'] === 'mug'): ?>
                        <!-- Illustration: personalised mug -->
                        <svg class="hero-illustration" viewBox="0 0 320 360" aria-hidden="true">
                            <path d="M232 150h22a40 40 0 0 1 0 80h-22" fill="none" stroke="#E7DDD0" stroke-width="22"/>
                            <rect x="64" y="104" width="180" height="200" rx="26" fill="#FFFDF9"/>
                            <ellipse cx="154" cy="106" rx="90" ry="14" fill="#E7DDD0"/>
                            <circle cx="154" cy="190" r="50" fill="#F3DCD4"/>
                            <g fill="#9B6B43" transform="translate(130 165) scale(1)">
                                <ellipse cx="24" cy="31" rx="9" ry="7.5"/><ellipse cx="13" cy="20" rx="3.6" ry="4.6"/><ellipse cx="20" cy="12.5" rx="3.6" ry="4.8"/><ellipse cx="28" cy="12.5" rx="3.6" ry="4.8"/><ellipse cx="35" cy="20" rx="3.6" ry="4.6"/>
                            </g>
                            <text x="154" y="276" text-anchor="middle" font-family="Fraunces, serif" font-size="30" font-style="italic" fill="#5A3B22">Pixel</text>
                            <path d="M126 70c-10 12 10 16 0 28M154 64c-10 12 10 16 0 28M182 70c-10 12 10 16 0 28" fill="none" stroke="#C8915A" stroke-width="5" stroke-linecap="round" opacity=".6"/>
                        </svg>

                    <?php else: ?>
                        <!-- Illustration: adoption gift box -->
                        <svg class="hero-illustration" viewBox="0 0 320 360" aria-hidden="true">
                            <rect x="58" y="170" width="204" height="150" rx="12" fill="#DDE5D6"/>
                            <rect x="44" y="134" width="232" height="48" rx="12" fill="#C9D6BF"/>
                            <rect x="146" y="134" width="28" height="186" fill="#C8915A"/>
                            <path d="M160 134c-20-44-76-50-70-18 5 24 50 18 70 18zM160 134c20-44 76-50 70-18-5 24-50 18-70 18z" fill="none" stroke="#C8915A" stroke-width="14" stroke-linejoin="round"/>
                            <rect x="84" y="226" width="54" height="34" rx="8" fill="#FFFDF9"/>
                            <text x="111" y="249" text-anchor="middle" font-family="Fraunces, serif" font-size="15" font-style="italic" fill="#5A3B22">Lou</text>
                        </svg>
                    <?php endif; ?>

                    <span class="hero-chip hero-chip-top">✦ Aperçu en direct</span>
                    <span class="hero-chip hero-chip-bottom">🚚 Livré en <?= DELIVERY_WORKING_DAYS ?> jours</span>
                </div>

                <div class="hero-text">
                    <p class="eyebrow"><?= e($slide['eyebrow']) ?></p>
                    <h1><?= e($slide['title']) ?> <em><?= e($slide['highlight']) ?></em></h1>
                    <p class="hero-lead"><?= e($slide['text']) ?></p>
                    <a href="<?= e($slide['link']) ?>" class="button button-outline">
                        <?= e($slide['button']) ?> <span aria-hidden="true">→</span>
                    </a>
                </div>

            </div>
        <?php endforeach; ?>

        <div class="hero-dots" role="tablist" aria-label="Choisir une diapositive">
            <?php foreach ($hero_slides as $index => $slide): ?>
                <button type="button" class="<?= $index === 0 ? 'is-active' : '' ?>" data-dot="<?= $index ?>" aria-label="Diapositive <?= $index + 1 ?>"></button>
            <?php endforeach; ?>
        </div>

    </div>
</section>


<!-- ===================== CATEGORIES ===================== -->
<section class="section categories" aria-label="Catégories">
    <div class="container categories-grid">
        <?php foreach ($categories as $slug => $name): ?>
            <a href="pages/products.php?category=<?= e($slug) ?>" class="category-item reveal">
                <span class="category-icon"><?= category_icon($slug) ?></span>
                <span><?= e($name) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</section>


<!-- ===================== BEST-SELLERS ===================== -->
<section class="section">
    <div class="container">
        <div class="section-head">
            <div>
                <p class="eyebrow">Les préférés de nos clients</p>
                <h2>Nos best-sellers</h2>
            </div>
            <a href="pages/products.php" class="button button-outline">Voir la boutique <span aria-hidden="true">→</span></a>
        </div>

        <div class="product-grid">
            <?php foreach ($featured_products as $product): ?>
                <article class="product-card reveal">
                    <a href="pages/product.php?id=<?= (int) $product['id'] ?>" class="product-image">
                        <?php if ($product['badge']): ?>
                            <span class="product-badge"><?= e($product['badge']) ?></span>
                        <?php endif; ?>

                        <?php if (file_exists('assets/images/products/' . $product['image'])): ?>
                            <img src="assets/images/products/<?= e($product['image']) ?>" alt="<?= e($product['name']) ?>" loading="lazy">
                        <?php else: ?>
                            <!-- No photo yet: show the category icon instead -->
                            <span class="product-placeholder product-placeholder-<?= e($product['category_slug']) ?>">
                                <?= category_icon($product['category_slug']) ?>
                            </span>
                        <?php endif; ?>
                    </a>

                    <div class="product-info">
                        <p class="product-category"><?= e($product['category_name']) ?></p>
                        <h3><a href="pages/product.php?id=<?= (int) $product['id'] ?>"><?= e($product['name']) ?></a></h3>
                        <p class="product-price">
                            <?= format_price($product['price']) ?>
                            <?php if ($product['old_price']): ?>
                                <del><?= format_price($product['old_price']) ?></del>
                            <?php endif; ?>
                        </p>
                        <a href="pages/product.php?id=<?= (int) $product['id'] ?>" class="button button-small">Personnaliser</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>


<!-- ===================== OUR PROMISES ===================== -->
<section class="promises" aria-label="Nos engagements">
    <div class="container promises-grid">
        <div class="promise">
            <strong><?= DELIVERY_WORKING_DAYS ?> jours ouvrables</strong>
            <span>Livraison partout en Belgique</span>
        </div>
        <div class="promise">
            <strong>Offerte dès <?= format_price(FREE_SHIPPING_FROM) ?></strong>
            <span>Sinon <?= format_price(SHIPPING_COST) ?> seulement</span>
        </div>
        <div class="promise">
            <strong>Aperçu en direct</strong>
            <span>Voyez le résultat avant d'acheter</span>
        </div>
        <div class="promise">
            <strong>Refait gratuitement</strong>
            <span>En cas de défaut de fabrication</span>
        </div>
    </div>
</section>


<!-- ===================== HOW IT WORKS ===================== -->
<section class="section" id="how-it-works">
    <div class="container">
        <div class="section-head section-head-center">
            <p class="eyebrow">Simple comme une caresse</p>
            <h2>Comment ça marche ?</h2>
        </div>

        <ol class="steps">
            <li class="step reveal">
                <span class="step-number">1</span>
                <h3>Choisissez</h3>
                <p>Une médaille, un collier, une gamelle, un plaid ou un mug, pour chien ou pour chat.</p>
            </li>
            <li class="step reveal">
                <span class="step-number">2</span>
                <h3>Personnalisez</h3>
                <p>Ajoutez le prénom, votre numéro, une couleur ou une photo. L'aperçu change en direct.</p>
            </li>
            <li class="step reveal">
                <span class="step-number">3</span>
                <h3>Recevez</h3>
                <p>Nos artisans partenaires fabriquent votre article. Il arrive chez vous en <?= DELIVERY_WORKING_DAYS ?> jours ouvrables.</p>
            </li>
        </ol>
    </div>
</section>


<!-- ===================== ABOUT ===================== -->
<section class="section about" id="about">
    <div class="container about-inner">
        <div class="about-text reveal">
            <p class="eyebrow">Notre histoire</p>
            <h2>Parce qu'ils font <em>partie de la famille</em></h2>
            <p>Poilu &amp; Co est née à Bruxelles d'une idée simple : nos chiens et nos chats méritent des objets aussi uniques qu'eux.</p>
            <p>Nous travaillons avec des ateliers de gravure et d'impression à la demande. Chaque article est fabriqué seulement quand vous le commandez : pas de stock inutile, et un objet fait rien que pour votre compagnon.</p>
        </div>
        <ul class="about-facts reveal">
            <li><strong>19 – 59 €</strong><span>des prix doux, TVA comprise</span></li>
            <li><strong>100 %</strong><span>fabriqué à la demande</span></li>
            <li><strong>🐶 + 🐱</strong><span>pour chiens et chats</span></li>
        </ul>
    </div>
</section>


<!-- ===================== TESTIMONIALS ===================== -->
<section class="section">
    <div class="container">
        <div class="section-head section-head-center">
            <p class="eyebrow">Ils nous font confiance</p>
            <h2>Avis de nos clients</h2>
        </div>

        <div class="testimonials">
            <?php foreach ($testimonials as $review): ?>
                <figure class="testimonial reveal">
                    <p class="stars" aria-label="5 étoiles sur 5">★★★★★</p>
                    <blockquote><?= e($review['text']) ?></blockquote>
                    <figcaption>
                        <strong><?= e($review['name']) ?></strong>
                        <span><?= e($review['pet']) ?></span>
                    </figcaption>
                </figure>
            <?php endforeach; ?>
        </div>
    </div>
</section>


<!-- ===================== FAQ ===================== -->
<section class="section faq-section" id="faq">
    <div class="container faq-inner">
        <div class="section-head">
            <div>
                <p class="eyebrow">Bon à savoir</p>
                <h2>Questions fréquentes</h2>
                <p class="section-intro">Une autre question ? Écrivez-nous à <a href="mailto:bonjour@poilu-and-co.be">bonjour@poilu-and-co.be</a>.</p>
            </div>
        </div>

        <div class="faq-list">
            <?php foreach ($faq as $item): ?>
                <!-- <details> opens and closes without any JavaScript -->
                <details class="faq-item">
                    <summary><?= e($item['question']) ?></summary>
                    <p><?= e($item['answer']) ?></p>
                </details>
            <?php endforeach; ?>
        </div>
    </div>
</section>


<!-- ===================== NEWSLETTER ===================== -->
<section class="section" id="newsletter">
    <div class="container">
        <div class="newsletter">
            <div>
                <h2>Des nouvelles de la meute</h2>
                <p>Nouveautés, idées cadeaux et -10 % sur votre première commande.</p>
            </div>

            <form method="post" action="index.php#newsletter" class="newsletter-form" novalidate data-newsletter-form>
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <label for="newsletter-email" class="visually-hidden">Votre adresse email</label>
                <input type="email" id="newsletter-email" name="newsletter_email" placeholder="votre@email.be" required>
                <button type="submit" class="button">S'inscrire</button>
            </form>

            <?php if ($flash): ?>
                <p class="flash flash-<?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></p>
            <?php endif; ?>
            <p class="flash flash-error" data-newsletter-error hidden></p>
        </div>
    </div>
</section>

<?php require 'includes/footer.php'; ?>
