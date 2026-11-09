<?php
// ------------------------------------------------------------
// pages/product.php — Product detail + personalisation
//
// URL: product.php?id=3
//   - shows the product (name, price, description, delivery info)
//   - personalisation form: pet name, phone, colour, size,
//     message, pet photo (depends on the product type)
//   - LIVE PREVIEW: script.js updates the drawing while typing
//   - "Ajouter au panier": checked in PHP, then saved in
//     $_SESSION['cart'] with add_to_cart() (functions.php)
//   - "Vous aimerez aussi": other products of the same category
// ------------------------------------------------------------

require '../includes/db.php';
require '../includes/functions.php';

$base_path    = '../';
$current_page = 'products';
$page_css     = 'product';    // loads assets/css/product.css

// Folder where pet photos are saved (random file names).
$upload_folder = __DIR__ . '/../assets/images/uploads/';


// ---------- 1. Load the product ----------
$product_id = (int) ($_GET['id'] ?? 0);   // (int) turns anything that is not a number into 0
$product    = false;

if ($pdo !== null && $product_id > 0) {
    $statement = $pdo->prepare("
        SELECT p.*, c.name AS category_name, c.slug AS category_slug
        FROM products p
        JOIN categories c ON c.id = p.category_id
        WHERE p.id = :id AND p.is_active = 1
    ");
    $statement->execute(['id' => $product_id]);
    $product = $statement->fetch();
}

// Unknown product: show a "not found" page with the right HTTP code.
if (!$product) {
    http_response_code(404);
    $page_title = 'Produit introuvable · Poilu & Co';
    require '../includes/header.php';
    ?>
    <section class="section">
        <div class="container">
            <div class="empty-state">
                <h2>Ce produit n'existe pas (ou plus)</h2>
                <p>Il a peut-être été retiré de la boutique.</p>
                <a href="products.php" class="button button-outline">Retour à la boutique</a>
            </div>
        </div>
    </section>
    <?php
    require '../includes/footer.php';
    exit;
}

// What can be personalised for this product (see functions.php)
$type        = $product['personalization_type'];
$options     = personalization_options($type);
$fields      = $options['fields'];
$message_max = ($type === 'mug') ? 20 : 40;      // short text on a mug, longer on a gift card


// ---------- 2. Default values of the form ----------
// After a failed submit, the form is shown again with what the user typed.
$values = [
    'pet_name' => '',
    'phone'    => '',
    'color'    => array_key_first($options['colors']) ?? '',
    'size'     => array_key_exists('M', $options['sizes']) ? 'M' : (array_key_first($options['sizes']) ?? ''),
    'message'  => ($type === 'mug') ? 'Maman de' : '',
    'quantity' => 1,
];
$errors = [];   // field name => error message


// ---------- 3. "Ajouter au panier" (POST) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!check_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors['form'] = 'Le formulaire a expiré. Merci de réessayer.';
    }

    // Read what was sent (trim = remove spaces at the start and the end)
    $values['pet_name'] = trim($_POST['pet_name'] ?? '');
    $values['phone']    = trim($_POST['phone'] ?? '');
    $values['color']    = $_POST['color'] ?? '';
    $values['size']     = $_POST['size'] ?? '';
    $values['message']  = trim($_POST['message'] ?? '');
    $values['quantity'] = (int) ($_POST['quantity'] ?? 1);

    // Check each field used by this product. Never trust the browser:
    // these checks are the real protection (JavaScript only helps the user).
    $personalization = [];

    if (in_array('pet_name', $fields, true)) {
        if ($values['pet_name'] === '') {
            $errors['pet_name'] = 'Indiquez le prénom de votre animal.';
        } elseif (mb_strlen($values['pet_name']) > 15) {
            $errors['pet_name'] = '15 caractères maximum.';
        } elseif (!preg_match("/^[\p{L} '\-]+$/u", $values['pet_name'])) {
            $errors['pet_name'] = 'Lettres, espaces, tirets et apostrophes uniquement.';
        }
        $personalization['pet_name'] = $values['pet_name'];
    }

    if (in_array('phone', $fields, true) && $values['phone'] !== '') {   // the phone is optional
        if (!preg_match('/^\+?[0-9 .\/]{8,18}$/', $values['phone'])) {
            $errors['phone'] = 'Numéro invalide. Exemple : +32 470 12 34 56';
        }
        $personalization['phone'] = $values['phone'];
    }

    if (in_array('color', $fields, true)) {
        if (!array_key_exists($values['color'], $options['colors'])) {
            $errors['color'] = 'Choisissez une couleur.';
        }
        $personalization['color'] = $values['color'];
    }

    if (in_array('size', $fields, true)) {
        if (!array_key_exists($values['size'], $options['sizes'])) {
            $errors['size'] = 'Choisissez une taille.';
        }
        $personalization['size'] = $values['size'];
    }

    if (in_array('message', $fields, true) && $values['message'] !== '') {
        if (mb_strlen($values['message']) > $message_max) {
            $errors['message'] = $message_max . ' caractères maximum.';
        }
        $personalization['message'] = $values['message'];
    }

    if ($values['quantity'] < 1 || $values['quantity'] > 10) {
        $errors['quantity'] = 'Quantité entre 1 et 10.';
    }

    // The photo is saved only when everything else is correct,
    // so we never keep photos of a form that failed.
    if (in_array('photo', $fields, true) && empty($errors)) {
        $upload = save_uploaded_image($_FILES['photo'] ?? [], $upload_folder);

        if ($upload['error'] !== null) {
            $errors['photo'] = $upload['error'];
        } elseif ($upload['file_name'] === null && $type === 'blanket') {
            $errors['photo'] = 'Ajoutez une photo de votre animal pour le portrait.';
        } elseif ($upload['file_name'] !== null) {
            $personalization['photo'] = $upload['file_name'];
        }
    }

    // Everything is fine: add to the cart, then redirect (Post/Redirect/Get).
    if (empty($errors)) {
        add_to_cart($product, $values['quantity'], $personalization);
        set_flash('success', '« ' . $product['name'] . ' » a été ajouté à votre panier.');
        header('Location: product.php?id=' . $product['id']);
        exit;
    }
}
$flash = get_flash();


// ---------- 4. Other products of the same category ----------
$statement = $pdo->prepare("
    SELECT p.*, c.name AS category_name, c.slug AS category_slug
    FROM products p
    JOIN categories c ON c.id = p.category_id
    WHERE p.category_id = :category_id AND p.id != :id AND p.is_active = 1
    ORDER BY p.created_at DESC
    LIMIT 4
");
$statement->execute(['category_id' => $product['category_id'], 'id' => $product['id']]);
$related_products = $statement->fetchAll();

// Later (step 12): "Fréquemment achetés ensemble" from analytics/output/ (Apriori).


// ---------- 5. Page HTML ----------
$page_title       = $product['name'] . ' · Poilu & Co';
$page_description = mb_substr($product['description'], 0, 155);
$badge            = product_badge($product);
$current_color    = $options['colors'][$values['color']] ?? '#E9DCC6';

require '../includes/header.php';

// Small helper for this page: prints the error of a field, if any.
function field_error($errors, $field)
{
    if (isset($errors[$field])) {
        echo '<p class="field-error">' . e($errors[$field]) . '</p>';
    }
}
?>

<section class="product-page">
    <div class="container">

        <nav class="breadcrumb" aria-label="Fil d'Ariane">
            <a href="../index.php">Accueil</a> <span aria-hidden="true">/</span>
            <a href="products.php">Boutique</a> <span aria-hidden="true">/</span>
            <a href="products.php?category=<?= e($product['category_slug']) ?>"><?= e($product['category_name']) ?></a>
            <span aria-hidden="true">/</span>
            <span><?= e($product['name']) ?></span>
        </nav>

        <div class="product-layout">

            <!-- ===================== LIVE PREVIEW (left) ===================== -->
            <div class="product-preview">
                <!-- data-preview: script.js updates everything inside this box -->
                <div class="preview preview-<?= e($type) ?>" data-preview
                     data-size="<?= e($values['size']) ?>"
                     style="--preview-color: <?= e($current_color) ?>">

                    <?php if ($badge !== ''): ?>
                        <span class="product-badge"><?= e($badge) ?></span>
                    <?php endif; ?>

                    <svg viewBox="0 0 400 400" class="preview-svg" aria-hidden="true">
                        <defs>
                            <clipPath id="photo-circle"><circle cx="200" cy="170" r="78"/></clipPath>
                            <clipPath id="mug-circle"><circle cx="185" cy="205" r="62"/></clipPath>
                        </defs>

                        <?php if ($type === 'tag'): ?>
                            <g class="preview-shape">
                                <circle cx="200" cy="72" r="20" fill="none" stroke="#B8B0A6" stroke-width="7"/>
                                <circle cx="200" cy="215" r="120" style="fill: var(--preview-color)"/>
                                <circle cx="200" cy="215" r="104" fill="none" stroke="#fff" stroke-opacity=".45" stroke-width="2"/>
                                <circle cx="200" cy="118" r="9" fill="#FBF7F1"/>
                                <text x="200" y="222" class="preview-name" font-size="54" data-preview-text="pet_name" data-default="Moka" data-max-width="190">Moka</text>
                                <text x="200" y="266" class="preview-small" font-size="19" data-preview-text="phone" data-default="+32 470 12 34 56" data-max-width="180">+32 470 12 34 56</text>
                            </g>

                        <?php elseif ($type === 'collar'): ?>
                            <g class="preview-shape">
                                <rect x="20" y="168" width="360" height="64" rx="32" style="fill: var(--preview-color)"/>
                                <rect x="20" y="168" width="360" height="64" rx="32" fill="none" stroke="#000" stroke-opacity=".08" stroke-width="2"/>
                                <rect x="44" y="156" width="46" height="88" rx="12" fill="none" stroke="#C9A36A" stroke-width="10"/>
                                <circle cx="330" cy="200" r="5" fill="#000" fill-opacity=".18"/>
                                <circle cx="352" cy="200" r="5" fill="#000" fill-opacity=".18"/>
                                <circle cx="200" cy="262" r="14" fill="none" stroke="#C9A36A" stroke-width="6"/>
                                <text x="210" y="213" class="preview-name preview-name-light" font-size="38" data-preview-text="pet_name" data-default="Moka" data-max-width="200">Moka</text>
                            </g>

                        <?php elseif ($type === 'bowl'): ?>
                            <g class="preview-shape">
                                <ellipse cx="200" cy="150" rx="150" ry="34" fill="#E2D8CA"/>
                                <path d="M50 150 L80 290 Q86 310 110 310 L290 310 Q314 310 320 290 L350 150 Q200 200 50 150 Z" style="fill: var(--preview-color)"/>
                                <ellipse cx="200" cy="150" rx="150" ry="34" fill="none" stroke="#000" stroke-opacity=".08" stroke-width="2"/>
                                <text x="200" y="258" class="preview-name" font-size="48" data-preview-text="pet_name" data-default="Moka" data-max-width="210">Moka</text>
                            </g>

                        <?php elseif ($type === 'blanket'): ?>
                            <g class="preview-shape">
                                <rect x="40" y="40" width="320" height="320" rx="26" style="fill: var(--preview-color)"/>
                                <rect x="56" y="56" width="288" height="288" rx="18" fill="none" stroke="#fff" stroke-opacity=".7" stroke-width="3" stroke-dasharray="8 8"/>
                                <circle cx="200" cy="170" r="82" fill="#FFFDF9"/>
                                <g data-photo-placeholder fill="#C8915A" transform="translate(162 132) scale(1.6)">
                                    <ellipse cx="24" cy="31" rx="9" ry="7.5"/><ellipse cx="13" cy="20" rx="3.6" ry="4.6"/><ellipse cx="20" cy="12.5" rx="3.6" ry="4.8"/><ellipse cx="28" cy="12.5" rx="3.6" ry="4.8"/><ellipse cx="35" cy="20" rx="3.6" ry="4.6"/>
                                </g>
                                <image data-preview-photo x="122" y="92" width="156" height="156" clip-path="url(#photo-circle)" preserveAspectRatio="xMidYMid slice" href=""/>
                                <text x="200" y="310" class="preview-name" font-size="44" data-preview-text="pet_name" data-default="Moka" data-max-width="240">Moka</text>
                            </g>

                        <?php elseif ($type === 'mug'): ?>
                            <g class="preview-shape">
                                <path d="M300 150h20a50 50 0 0 1 0 100h-20" fill="none" stroke="#E2D8CA" stroke-width="24"/>
                                <rect x="60" y="80" width="250" height="270" rx="30" fill="#FFFDF9" stroke="#E2D8CA" stroke-width="3"/>
                                <ellipse cx="185" cy="82" rx="125" ry="16" fill="#E2D8CA"/>
                                <text x="185" y="128" class="preview-small" font-size="24" data-preview-text="message" data-default="Maman de" data-max-width="210">Maman de</text>
                                <circle cx="185" cy="205" r="64" fill="#F3DCD4"/>
                                <g data-photo-placeholder fill="#C8915A" transform="translate(155 172) scale(1.25)">
                                    <ellipse cx="24" cy="31" rx="9" ry="7.5"/><ellipse cx="13" cy="20" rx="3.6" ry="4.6"/><ellipse cx="20" cy="12.5" rx="3.6" ry="4.8"/><ellipse cx="28" cy="12.5" rx="3.6" ry="4.8"/><ellipse cx="35" cy="20" rx="3.6" ry="4.6"/>
                                </g>
                                <image data-preview-photo x="123" y="143" width="124" height="124" clip-path="url(#mug-circle)" preserveAspectRatio="xMidYMid slice" href=""/>
                                <text x="185" y="318" class="preview-name" font-size="40" data-preview-text="pet_name" data-default="Pixel" data-max-width="210">Pixel</text>
                            </g>

                        <?php else: /* gift */ ?>
                            <g class="preview-shape">
                                <rect x="70" y="170" width="260" height="190" rx="14" fill="#DDE5D6"/>
                                <rect x="54" y="128" width="292" height="56" rx="14" fill="#C9D6BF"/>
                                <rect x="186" y="128" width="28" height="232" fill="#C8915A"/>
                                <path d="M200 128c-24-50-88-56-80-20 6 28 56 20 80 20zM200 128c24-50 88-56 80-20-6 28-56 20-80 20z" fill="none" stroke="#C8915A" stroke-width="14" stroke-linejoin="round"/>
                                <rect x="88" y="232" width="224" height="96" rx="12" fill="#FFFDF9"/>
                                <text x="200" y="276" class="preview-name" font-size="34" data-preview-text="pet_name" data-default="Lou" data-max-width="196">Lou</text>
                                <text x="200" y="308" class="preview-small" font-size="15" data-preview-text="message" data-default="Bienvenue à la maison !" data-max-width="196">Bienvenue à la maison !</text>
                            </g>
                        <?php endif; ?>
                    </svg>
                </div>
                <p class="preview-note">✦ Aperçu en direct : il change pendant que vous personnalisez.</p>
            </div>


            <!-- ===================== PRODUCT INFO + FORM (right) ===================== -->
            <div class="product-details">
                <p class="product-category"><?= e($product['category_name']) ?> · <?= e(animal_label($product['animal'])) ?></p>
                <h1><?= e($product['name']) ?></h1>

                <p class="product-detail-price">
                    <?= format_price($product['price']) ?>
                    <?php if (!empty($product['old_price'])): ?>
                        <del><?= format_price($product['old_price']) ?></del>
                    <?php endif; ?>
                    <small>TVA comprise</small>
                </p>

                <p class="product-description"><?= e($product['description']) ?></p>

                <?php if ($flash): ?>
                    <div class="flash flash-<?= e($flash['type']) ?>" role="status">
                        <?= e($flash['message']) ?>
                        <a href="cart.php">Voir mon panier →</a>
                    </div>
                <?php endif; ?>
                <?php if (isset($errors['form'])): ?>
                    <p class="flash flash-error"><?= e($errors['form']) ?></p>
                <?php endif; ?>

                <!-- enctype="multipart/form-data" is required to send a file -->
                <form method="post" action="product.php?id=<?= (int) $product['id'] ?>" enctype="multipart/form-data"
                      class="personalize-form" id="personalize" novalidate data-personalize-form>
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

                    <h2 class="form-title">Personnalisez-le</h2>

                    <?php if (in_array('pet_name', $fields, true)): ?>
                        <div class="form-field">
                            <label for="pet-name">Prénom de votre animal <span class="required">*</span></label>
                            <input type="text" id="pet-name" name="pet_name" maxlength="15" required
                                   value="<?= e($values['pet_name']) ?>" placeholder="Ex. Moka" data-preview-input="pet_name">
                            <?php field_error($errors, 'pet_name'); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (in_array('phone', $fields, true)): ?>
                        <div class="form-field">
                            <label for="phone">Votre téléphone <small>(facultatif, gravé au dos)</small></label>
                            <input type="tel" id="phone" name="phone" maxlength="18"
                                   value="<?= e($values['phone']) ?>" placeholder="+32 470 12 34 56" data-preview-input="phone">
                            <?php field_error($errors, 'phone'); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (in_array('message', $fields, true)): ?>
                        <div class="form-field">
                            <label for="message"><?= $type === 'mug' ? 'Texte au-dessus de la photo' : 'Message sur la carte' ?> <small>(facultatif)</small></label>
                            <input type="text" id="message" name="message" maxlength="<?= $message_max ?>"
                                   value="<?= e($values['message']) ?>" data-preview-input="message">
                            <?php field_error($errors, 'message'); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (in_array('color', $fields, true)): ?>
                        <fieldset class="form-field">
                            <legend>Couleur : <strong data-color-name><?= e($values['color']) ?></strong></legend>
                            <div class="swatches">
                                <?php foreach ($options['colors'] as $color_name => $color_hex): ?>
                                    <label class="swatch" title="<?= e($color_name) ?>">
                                        <input type="radio" name="color" value="<?= e($color_name) ?>"
                                               data-color="<?= e($color_hex) ?>" <?= $values['color'] === $color_name ? 'checked' : '' ?>>
                                        <span style="background: <?= e($color_hex) ?>"></span>
                                        <span class="visually-hidden"><?= e($color_name) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <?php field_error($errors, 'color'); ?>
                        </fieldset>
                    <?php endif; ?>

                    <?php if (in_array('size', $fields, true)): ?>
                        <fieldset class="form-field">
                            <legend>Taille</legend>
                            <div class="size-options">
                                <?php foreach ($options['sizes'] as $size_value => $size_label): ?>
                                    <label class="size-option">
                                        <input type="radio" name="size" value="<?= e($size_value) ?>" data-size-input <?= $values['size'] === $size_value ? 'checked' : '' ?>>
                                        <span><?= e($size_label) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <?php field_error($errors, 'size'); ?>
                        </fieldset>
                    <?php endif; ?>

                    <?php if (in_array('photo', $fields, true)): ?>
                        <div class="form-field">
                            <label for="photo">
                                Photo de votre animal
                                <?php if ($type === 'blanket'): ?><span class="required">*</span><?php else: ?><small>(facultatif)</small><?php endif; ?>
                            </label>
                            <input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp" data-preview-photo-input>
                            <p class="field-help">JPG, PNG ou WEBP · 5 Mo maximum · une photo nette et bien éclairée</p>
                            <?php field_error($errors, 'photo'); ?>
                            <?php if (!empty($errors) && !isset($errors['photo'])): ?>
                                <p class="field-help">Pour votre sécurité, merci de choisir à nouveau la photo.</p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="add-to-cart">
                        <label for="quantity" class="visually-hidden">Quantité</label>
                        <input type="number" id="quantity" name="quantity" min="1" max="10" value="<?= (int) $values['quantity'] ?>" class="quantity-input">
                        <button type="submit" class="button add-to-cart-button">Ajouter au panier · <?= format_price($product['price']) ?></button>
                    </div>
                    <?php field_error($errors, 'quantity'); ?>
                </form>

                <ul class="product-promises">
                    <li>🚚 Livré en Belgique en <?= DELIVERY_WORKING_DAYS ?> jours ouvrables</li>
                    <li>📦 Livraison <?= format_price(SHIPPING_COST) ?>, offerte dès <?= format_price(FREE_SHIPPING_FROM) ?></li>
                    <li>✦ Fabriqué à la demande, rien que pour votre compagnon</li>
                    <li>↺ Article personnalisé : non échangeable, refait gratuitement en cas de défaut</li>
                </ul>
            </div>

        </div>
    </div>
</section>


<!-- ===================== RELATED PRODUCTS ===================== -->
<?php if (count($related_products) > 0): ?>
<section class="section related-products">
    <div class="container">
        <div class="section-head">
            <div>
                <p class="eyebrow">Dans la même catégorie</p>
                <h2>Vous aimerez aussi</h2>
            </div>
            <a href="products.php?category=<?= e($product['category_slug']) ?>" class="button button-outline">Tout voir <span aria-hidden="true">→</span></a>
        </div>

        <div class="product-grid">
            <?php foreach ($related_products as $related): ?>
                <?php $related_badge = product_badge($related); ?>
                <article class="product-card reveal">
                    <a href="product.php?id=<?= (int) $related['id'] ?>" class="product-image">
                        <?php if ($related_badge !== ''): ?>
                            <span class="product-badge"><?= e($related_badge) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($related['image']) && file_exists(__DIR__ . '/../assets/images/products/' . $related['image'])): ?>
                            <img src="../assets/images/products/<?= e($related['image']) ?>" alt="<?= e($related['name']) ?>" loading="lazy">
                        <?php else: ?>
                            <span class="product-placeholder product-placeholder-<?= e($related['category_slug']) ?>">
                                <?= category_icon($related['category_slug']) ?>
                            </span>
                        <?php endif; ?>
                    </a>
                    <div class="product-info">
                        <p class="product-category"><?= e($related['category_name']) ?> · <?= e(animal_label($related['animal'])) ?></p>
                        <h3><a href="product.php?id=<?= (int) $related['id'] ?>"><?= e($related['name']) ?></a></h3>
                        <p class="product-price">
                            <?= format_price($related['price']) ?>
                            <?php if (!empty($related['old_price'])): ?>
                                <del><?= format_price($related['old_price']) ?></del>
                            <?php endif; ?>
                        </p>
                        <a href="product.php?id=<?= (int) $related['id'] ?>" class="button button-small">Personnaliser</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php require '../includes/footer.php'; ?>