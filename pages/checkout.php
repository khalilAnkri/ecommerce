<?php
// ------------------------------------------------------------
// pages/checkout.php — Checkout and order confirmation
//
// Two views in one file:
//   checkout.php                  -> order form + summary
//   checkout.php?confirmation=12  -> "thank you" page of order 12
//
// Steps when the customer clicks "Payer":
//   1. check the form (PHP, server side)
//   2. read prices again from the DATABASE (never trust the session)
//   3. save orders + order_items + personalizations in ONE transaction
//   4. SIMULATED payment: the order is directly marked as 'paid'
//   5. empty the cart, show the confirmation
//
// No account needed (guest order: user_id = NULL). If the customer is
// logged in ($_SESSION['user_id'], set by login.php), the order is
// linked to their account and the form is pre-filled.
// ------------------------------------------------------------

require '../includes/db.php';
require '../includes/functions.php';

$base_path    = '../';
$current_page = 'checkout';
$page_css     = 'checkout';    // loads assets/css/checkout.css

// Simulated payment methods (value saved in orders.payment_method)
$payment_methods = [
    'bancontact' => 'Bancontact',
    'card'       => 'Carte de crédit (Visa, Mastercard)',
    'paypal'     => 'PayPal',
];

// Prints the error message of a field, if there is one.
function field_error($errors, $field)
{
    if (isset($errors[$field])) {
        echo '<p class="field-error">' . e($errors[$field]) . '</p>';
    }
}


// ============================================================
// VIEW 1: CONFIRMATION (checkout.php?confirmation=ID)
// ============================================================
if (isset($_GET['confirmation'])) {
    $order_id = (int) $_GET['confirmation'];

    // Only the person who just placed this order may see it.
    if ($pdo === null || $order_id !== ($_SESSION['last_order_id'] ?? 0)) {
        header('Location: ../index.php');
        exit;
    }

    $statement = $pdo->prepare('SELECT * FROM orders WHERE id = :id');
    $statement->execute(['id' => $order_id]);
    $order = $statement->fetch();

    $statement = $pdo->prepare('SELECT * FROM order_items WHERE order_id = :id');
    $statement->execute(['id' => $order_id]);
    $order_items = $statement->fetchAll();

    $delivery_date = add_working_days($order['created_at'], DELIVERY_WORKING_DAYS);
    $page_title    = 'Commande confirmée · Poilu & Co';

    require '../includes/header.php';
    ?>
    <section class="section confirmation">
        <div class="container confirmation-inner">
            <div class="confirmation-icon" aria-hidden="true">✓</div>
            <p class="eyebrow">Commande n° <?= str_pad($order['id'], 5, '0', STR_PAD_LEFT) ?></p>
            <h1>Merci <?= e($order['first_name']) ?> !</h1>
            <p class="confirmation-lead">
                Votre paiement (simulé) a été accepté. Un récapitulatif a été envoyé à
                <strong><?= e($order['email']) ?></strong> (simulation).
            </p>

            <!-- Simulated life of the order -->
            <ol class="order-steps">
                <li class="is-done">Payée</li>
                <li>En production</li>
                <li>Expédiée</li>
                <li>Livrée</li>
            </ol>

            <div class="confirmation-box">
                <p class="confirmation-delivery">
                    Livraison estimée : <strong><?= e(format_date_fr($delivery_date)) ?></strong><br>
                    <?= e($order['address']) ?>, <?= e($order['postal_code']) ?> <?= e($order['city']) ?>
                </p>

                <ul class="confirmation-items">
                    <?php foreach ($order_items as $item): ?>
                        <li>
                            <span><?= (int) $item['quantity'] ?> × <?= e($item['product_name']) ?></span>
                            <span><?= format_price($item['unit_price'] * $item['quantity']) ?></span>
                        </li>
                    <?php endforeach; ?>
                    <li><span>Livraison</span><span><?= $order['shipping_cost'] > 0 ? format_price($order['shipping_cost']) : 'Offerte' ?></span></li>
                    <li class="confirmation-total"><span>Total payé</span><span><?= format_price($order['total']) ?></span></li>
                </ul>
            </div>

            <a href="products.php" class="button">Continuer mes achats <span aria-hidden="true">→</span></a>
        </div>
    </section>
    <?php
    require '../includes/footer.php';
    exit;
}


// ============================================================
// VIEW 2: CHECKOUT FORM
// ============================================================

// Nothing to order: back to the cart.
if (empty($_SESSION['cart'])) {
    header('Location: cart.php');
    exit;
}


// ---------- 1. Refresh the cart with the database ----------
// A price may have changed or a product may have been removed since
// it was added to the cart. The database is always right.
$removed_products = [];

if ($pdo !== null) {
    $product_ids  = array_unique(array_column($_SESSION['cart'], 'product_id'));
    $placeholders = implode(',', array_fill(0, count($product_ids), '?'));   // "?,?,?"

    $statement = $pdo->prepare("SELECT id, name, price FROM products WHERE is_active = 1 AND id IN ($placeholders)");
    $statement->execute(array_values($product_ids));

    $products_in_db = [];
    foreach ($statement->fetchAll() as $row) {
        $products_in_db[$row['id']] = $row;        // index by id to find them easily
    }

    foreach ($_SESSION['cart'] as $item_key => $item) {
        if (!isset($products_in_db[$item['product_id']])) {
            $removed_products[] = $item['name'];
            unset($_SESSION['cart'][$item_key]);
        } else {
            $_SESSION['cart'][$item_key]['price'] = (float) $products_in_db[$item['product_id']]['price'];
            $_SESSION['cart'][$item_key]['name']  = $products_in_db[$item['product_id']]['name'];
        }
    }

    if (empty($_SESSION['cart'])) {
        set_flash('error', 'Les articles de votre panier ne sont plus disponibles.');
        header('Location: cart.php');
        exit;
    }
}

$subtotal      = cart_subtotal();
$shipping      = calculate_shipping($subtotal);
$total         = $subtotal + $shipping;
$delivery_date = add_working_days(date('Y-m-d'), DELIVERY_WORKING_DAYS);


// ---------- 2. Default values (pre-filled if the customer is logged in) ----------
$values = [
    'first_name' => '', 'last_name' => '', 'email' => '', 'phone' => '',
    'address' => '', 'postal_code' => '', 'city' => '',
    'payment_method' => 'bancontact', 'accept_terms' => false,
];

if ($pdo !== null && is_logged_in()) {
    $statement = $pdo->prepare('SELECT * FROM users WHERE id = :id');
    $statement->execute(['id' => $_SESSION['user_id']]);
    $user = $statement->fetch();
    if ($user) {
        foreach (['first_name', 'last_name', 'email', 'phone', 'address', 'postal_code', 'city'] as $field) {
            $values[$field] = $user[$field] ?? '';
        }
    }
}

$errors = [];


// ---------- 3. Place the order (POST) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo !== null) {

    if (!check_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors['form'] = 'Le formulaire a expiré. Merci de réessayer.';
    }

    foreach (['first_name', 'last_name', 'email', 'phone', 'address', 'postal_code', 'city'] as $field) {
        $values[$field] = trim($_POST[$field] ?? '');
    }
    $values['payment_method'] = $_POST['payment_method'] ?? '';
    $values['accept_terms']   = isset($_POST['accept_terms']);

    // --- Server-side validation ---
    $name_pattern = "/^[\p{L} '\-]{2,50}$/u";   // letters (with accents), spaces, - and '

    if (!preg_match($name_pattern, $values['first_name'])) {
        $errors['first_name'] = 'Indiquez votre prénom.';
    }
    if (!preg_match($name_pattern, $values['last_name'])) {
        $errors['last_name'] = 'Indiquez votre nom.';
    }
    if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($values['email']) > 120) {
        $errors['email'] = 'Adresse email invalide.';
    }
    if ($values['phone'] !== '' && !preg_match('/^\+?[0-9 .\/]{8,18}$/', $values['phone'])) {
        $errors['phone'] = 'Numéro invalide. Exemple : +32 470 12 34 56';
    }
    if (mb_strlen($values['address']) < 5 || mb_strlen($values['address']) > 150) {
        $errors['address'] = 'Indiquez la rue et le numéro.';
    }
    if (!preg_match('/^[1-9][0-9]{3}$/', $values['postal_code'])) {   // Belgium: 1000 to 9999
        $errors['postal_code'] = 'Code postal belge à 4 chiffres (ex. 1050).';
    }
    if (!preg_match("/^[\p{L} '\-]{2,80}$/u", $values['city'])) {
        $errors['city'] = 'Indiquez votre commune.';
    }
    if (!array_key_exists($values['payment_method'], $payment_methods)) {
        $errors['payment_method'] = 'Choisissez un moyen de paiement.';
    }
    if (!$values['accept_terms']) {
        $errors['accept_terms'] = 'Merci d\'accepter les conditions.';
    }

    // --- Everything is correct: save the order ---
    if (empty($errors)) {
        try {
            // Transaction: either ALL the inserts succeed, or NONE is kept.
            $pdo->beginTransaction();

            // a) the order
            $statement = $pdo->prepare("
                INSERT INTO orders (user_id, first_name, last_name, email, phone, address, postal_code, city,
                                    subtotal, shipping_cost, total, payment_method, status)
                VALUES (:user_id, :first_name, :last_name, :email, :phone, :address, :postal_code, :city,
                        :subtotal, :shipping_cost, :total, :payment_method, 'paid')
            ");
            $statement->execute([
                'user_id'        => is_logged_in() ? $_SESSION['user_id'] : null,   // NULL = guest
                'first_name'     => $values['first_name'],
                'last_name'      => $values['last_name'],
                'email'          => $values['email'],
                'phone'          => $values['phone'] !== '' ? $values['phone'] : null,
                'address'        => $values['address'],
                'postal_code'    => $values['postal_code'],
                'city'           => $values['city'],
                'subtotal'       => $subtotal,
                'shipping_cost'  => $shipping,
                'total'          => $total,
                'payment_method' => $values['payment_method'],
            ]);
            $order_id = (int) $pdo->lastInsertId();

            // Prepared once, executed for each item
            $insert_item = $pdo->prepare("
                INSERT INTO order_items (order_id, product_id, product_name, unit_price, quantity)
                VALUES (:order_id, :product_id, :product_name, :unit_price, :quantity)
            ");
            $insert_personalization = $pdo->prepare("
                INSERT INTO personalizations (order_item_id, pet_name, phone, color, size, message, photo)
                VALUES (:order_item_id, :pet_name, :phone, :color, :size, :message, :photo)
            ");

            foreach ($_SESSION['cart'] as $item) {
                // b) one line per cart item
                $insert_item->execute([
                    'order_id'     => $order_id,
                    'product_id'   => $item['product_id'],
                    'product_name' => $item['name'],
                    'unit_price'   => $item['price'],
                    'quantity'     => $item['quantity'],
                ]);
                $order_item_id = (int) $pdo->lastInsertId();

                // c) its personalisation, if the customer personalised it
                if (!empty($item['personalization'])) {
                    $p = $item['personalization'];
                    $insert_personalization->execute([
                        'order_item_id' => $order_item_id,
                        'pet_name'      => $p['pet_name'] ?? null,
                        'phone'         => $p['phone'] ?? null,
                        'color'         => $p['color'] ?? null,
                        'size'          => $p['size'] ?? null,
                        'message'       => $p['message'] ?? null,
                        'photo'         => $p['photo'] ?? null,
                    ]);
                }
            }

            $pdo->commit();

            // d) SIMULATED payment accepted: empty the cart, show the confirmation
            unset($_SESSION['cart']);
            $_SESSION['last_order_id'] = $order_id;
            header('Location: checkout.php?confirmation=' . $order_id);
            exit;

        } catch (PDOException $error) {
            $pdo->rollBack();   // cancel everything that was inserted
            error_log('Checkout failed: ' . $error->getMessage());
            $errors['form'] = 'Une erreur est survenue, votre commande n\'a pas été enregistrée. Réessayez.';
        }
    }
}


// ---------- 4. Page HTML ----------
$page_title = 'Commande · Poilu & Co';
require '../includes/header.php';
?>

<section class="page-hero">
    <div class="container">
        <nav class="breadcrumb" aria-label="Fil d'Ariane">
            <a href="../index.php">Accueil</a> <span aria-hidden="true">/</span>
            <a href="cart.php">Panier</a> <span aria-hidden="true">/</span>
            <span>Commande</span>
        </nav>
        <h1>Finaliser ma commande</h1>
        <p class="page-intro">Livraison partout en Belgique en <?= DELIVERY_WORKING_DAYS ?> jours ouvrables.</p>
    </div>
</section>

<section class="section checkout-section">
    <div class="container">

        <?php if ($pdo === null): ?>
            <div class="empty-state">
                <h2>La commande est indisponible</h2>
                <p>La base de données n'est pas connectée. Démarrez MySQL dans XAMPP/WAMP.</p>
            </div>
        <?php else: ?>

        <?php if (!empty($removed_products)): ?>
            <p class="flash flash-error checkout-flash">
                Retiré de votre panier (plus disponible) : <?= e(implode(', ', $removed_products)) ?>
            </p>
        <?php endif; ?>
        <?php if (isset($errors['form'])): ?>
            <p class="flash flash-error checkout-flash"><?= e($errors['form']) ?></p>
        <?php elseif (!empty($errors)): ?>
            <p class="flash flash-error checkout-flash">Merci de corriger les champs en rouge.</p>
        <?php endif; ?>

        <form method="post" action="checkout.php" class="checkout-layout" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

            <!-- ===================== FORM (left) ===================== -->
            <div class="checkout-form">

                <?php if (!is_logged_in()): ?>
                    <p class="checkout-login-note">
                        Vous avez un compte ? <a href="login.php">Connectez-vous</a> pour retrouver cette commande dans votre historique.
                        Sinon, commandez simplement en tant qu'invité.
                    </p>
                <?php endif; ?>

                <!-- 1. Contact -->
                <fieldset class="checkout-block">
                    <legend><span>1</span> Vos coordonnées</legend>
                    <div class="form-row">
                        <div class="form-field">
                            <label for="first-name">Prénom *</label>
                            <input type="text" id="first-name" name="first_name" maxlength="50" autocomplete="given-name" value="<?= e($values['first_name']) ?>" required>
                            <?php field_error($errors, 'first_name'); ?>
                        </div>
                        <div class="form-field">
                            <label for="last-name">Nom *</label>
                            <input type="text" id="last-name" name="last_name" maxlength="50" autocomplete="family-name" value="<?= e($values['last_name']) ?>" required>
                            <?php field_error($errors, 'last_name'); ?>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-field">
                            <label for="email">Email *</label>
                            <input type="email" id="email" name="email" maxlength="120" autocomplete="email" value="<?= e($values['email']) ?>" required>
                            <?php field_error($errors, 'email'); ?>
                        </div>
                        <div class="form-field">
                            <label for="phone">Téléphone <small>(facultatif)</small></label>
                            <input type="tel" id="phone" name="phone" maxlength="18" autocomplete="tel" value="<?= e($values['phone']) ?>" placeholder="+32 470 12 34 56">
                            <?php field_error($errors, 'phone'); ?>
                        </div>
                    </div>
                </fieldset>

                <!-- 2. Address -->
                <fieldset class="checkout-block">
                    <legend><span>2</span> Adresse de livraison</legend>
                    <div class="form-field">
                        <label for="address">Rue et numéro *</label>
                        <input type="text" id="address" name="address" maxlength="150" autocomplete="street-address" value="<?= e($values['address']) ?>" placeholder="Rue du Bailli 12" required>
                        <?php field_error($errors, 'address'); ?>
                    </div>
                    <div class="form-row form-row-postal">
                        <div class="form-field">
                            <label for="postal-code">Code postal *</label>
                            <input type="text" id="postal-code" name="postal_code" maxlength="4" inputmode="numeric" autocomplete="postal-code" value="<?= e($values['postal_code']) ?>" placeholder="1050" required>
                            <?php field_error($errors, 'postal_code'); ?>
                        </div>
                        <div class="form-field">
                            <label for="city">Commune *</label>
                            <input type="text" id="city" name="city" maxlength="80" autocomplete="address-level2" value="<?= e($values['city']) ?>" placeholder="Ixelles" required>
                            <?php field_error($errors, 'city'); ?>
                        </div>
                    </div>
                    <p class="field-help">Pays : Belgique (nous ne livrons qu'en Belgique pour le moment).</p>
                </fieldset>

                <!-- 3. Payment (simulated) -->
                <fieldset class="checkout-block">
                    <legend><span>3</span> Paiement</legend>
                    <p class="simulation-note">
                        Projet universitaire : le paiement est <strong>simulé</strong>. Aucune donnée bancaire n'est demandée ni enregistrée.
                    </p>
                    <div class="payment-options">
                        <?php foreach ($payment_methods as $method_value => $method_label): ?>
                            <label class="payment-option">
                                <input type="radio" name="payment_method" value="<?= e($method_value) ?>" <?= $values['payment_method'] === $method_value ? 'checked' : '' ?>>
                                <span><?= e($method_label) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <?php field_error($errors, 'payment_method'); ?>

                    <label class="terms-check">
                        <input type="checkbox" name="accept_terms" value="1" <?= $values['accept_terms'] ? 'checked' : '' ?>>
                        <span>J'ai vérifié mes personnalisations et j'accepte qu'un article personnalisé ne soit ni échangé ni remboursé (sauf défaut de fabrication).</span>
                    </label>
                    <?php field_error($errors, 'accept_terms'); ?>
                </fieldset>
            </div>


            <!-- ===================== ORDER SUMMARY (right) ===================== -->
            <aside class="checkout-summary">
                <h2>Votre commande</h2>

                <ul class="checkout-items">
                    <?php foreach ($_SESSION['cart'] as $item): ?>
                        <li>
                            <div>
                                <strong><?= (int) $item['quantity'] ?> × <?= e($item['name']) ?></strong>
                                <?php if (!empty($item['personalization']['pet_name'])): ?>
                                    <small>Pour <?= e($item['personalization']['pet_name']) ?></small>
                                <?php endif; ?>
                            </div>
                            <span><?= format_price($item['price'] * $item['quantity']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <a href="cart.php" class="checkout-edit">Modifier le panier</a>

                <dl class="checkout-totals">
                    <div><dt>Sous-total</dt><dd><?= format_price($subtotal) ?></dd></div>
                    <div><dt>Livraison</dt><dd><?= $shipping > 0 ? format_price($shipping) : 'Offerte' ?></dd></div>
                    <div class="checkout-total"><dt>Total <small>TVA comprise</small></dt><dd><?= format_price($total) ?></dd></div>
                </dl>

                <p class="checkout-delivery">🚚 Livraison estimée : <strong><?= e(format_date_fr($delivery_date)) ?></strong></p>

                <button type="submit" class="button checkout-button">Payer <?= format_price($total) ?> <small>(simulation)</small></button>
            </aside>
        </form>
        <?php endif; ?>

    </div>
</section>

<?php require '../includes/footer.php'; ?>