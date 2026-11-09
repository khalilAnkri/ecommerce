<?php
// ------------------------------------------------------------
// pages/cart.php — Shopping cart
//
// The cart is NOT in the database: it lives in $_SESSION['cart']
// (filled by add_to_cart() on product.php) until checkout.
//
// This page:
//   - lists the items with their personalisation (name, colour, photo...)
//   - changes a quantity      (POST action=update)
//   - removes an item         (POST action=remove)
//   - shows subtotal, shipping and total
//   - leads to checkout.php
// ------------------------------------------------------------

require '../includes/db.php';
require '../includes/functions.php';

$base_path    = '../';
$current_page = 'cart';
$page_css     = 'cart';       // loads assets/css/cart.css
$page_title   = 'Mon panier · Poilu & Co';


// ---------- 1. Actions on the cart (POST) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action   = $_POST['action'] ?? '';
    $item_key = $_POST['item_key'] ?? '';

    if (!check_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Le formulaire a expiré. Merci de réessayer.');

    } elseif (!isset($_SESSION['cart'][$item_key])) {
        // The item does not exist (already removed, or a fake key)
        set_flash('error', 'Cet article n\'est plus dans votre panier.');

    } elseif ($action === 'update') {
        $quantity = (int) ($_POST['quantity'] ?? 1);

        if ($quantity <= 0) {
            unset($_SESSION['cart'][$item_key]);            // 0 = remove the item
            set_flash('success', 'Article retiré du panier.');
        } else {
            $_SESSION['cart'][$item_key]['quantity'] = min($quantity, 10);   // 10 maximum
            set_flash('success', 'Quantité mise à jour.');
        }

    } elseif ($action === 'remove') {
        unset($_SESSION['cart'][$item_key]);
        set_flash('success', 'Article retiré du panier.');
    }

    // Post/Redirect/Get: refreshing the page does not repeat the action
    header('Location: cart.php');
    exit;
}
$flash = get_flash();


// ---------- 2. Totals ----------
$cart_lines = $_SESSION['cart'] ?? [];
$subtotal   = cart_subtotal();
$shipping   = calculate_shipping($subtotal);
$total      = $subtotal + $shipping;

// How much is missing for free delivery (for the progress bar)
$missing_for_free = max(0, FREE_SHIPPING_FROM - $subtotal);
$progress_percent = min(100, round($subtotal / FREE_SHIPPING_FROM * 100));


// ---------- 3. Page HTML ----------
require '../includes/header.php';
?>

<section class="page-hero">
    <div class="container">
        <nav class="breadcrumb" aria-label="Fil d'Ariane">
            <a href="../index.php">Accueil</a> <span aria-hidden="true">/</span>
            <span>Panier</span>
        </nav>
        <h1>Mon panier</h1>
        <p class="page-intro">
            <?php if (count($cart_lines) > 0): ?>
                <?= cart_count() ?> article<?= cart_count() > 1 ? 's' : '' ?> fabriqué<?= cart_count() > 1 ? 's' : '' ?> rien que pour votre compagnon.
            <?php else: ?>
                Votre panier attend son premier article.
            <?php endif; ?>
        </p>
    </div>
</section>

<section class="section cart-section">
    <div class="container">

        <?php if ($flash): ?>
            <p class="flash flash-<?= e($flash['type']) ?> cart-flash" role="status"><?= e($flash['message']) ?></p>
        <?php endif; ?>

        <?php if (count($cart_lines) === 0): ?>
            <!-- ===================== EMPTY CART ===================== -->
            <div class="empty-state">
                <h2>Votre panier est vide</h2>
                <p>Médailles, colliers, gamelles… trouvez l'accessoire qui portera son prénom.</p>
                <a href="products.php" class="button">Découvrir la boutique <span aria-hidden="true">→</span></a>
            </div>

        <?php else: ?>
        <div class="cart-layout">

            <!-- ===================== ITEMS (left) ===================== -->
            <ul class="cart-list">
                <?php foreach ($cart_lines as $item_key => $item): ?>
                    <li class="cart-item">

                        <a href="product.php?id=<?= (int) $item['product_id'] ?>" class="cart-item-image">
                            <?php if (!empty($item['personalization']['photo'])): ?>
                                <!-- The customer's pet photo -->
                                <img src="../assets/images/uploads/<?= e($item['personalization']['photo']) ?>" alt="Photo de votre animal">
                            <?php elseif (!empty($item['image']) && file_exists(__DIR__ . '/../assets/images/products/' . $item['image'])): ?>
                                <img src="../assets/images/products/<?= e($item['image']) ?>" alt="<?= e($item['name']) ?>">
                            <?php else: ?>
                                <span class="product-placeholder product-placeholder-<?= e($item['category_slug']) ?>">
                                    <?= category_icon($item['category_slug']) ?>
                                </span>
                            <?php endif; ?>
                        </a>

                        <div class="cart-item-info">
                            <h2><a href="product.php?id=<?= (int) $item['product_id'] ?>"><?= e($item['name']) ?></a></h2>
                            <p class="cart-item-unit"><?= format_price($item['price']) ?> / pièce</p>

                            <!-- Personalisation chosen on the product page -->
                            <?php if (!empty($item['personalization'])): ?>
                                <dl class="cart-personalization">
                                    <?php foreach ($item['personalization'] as $field => $value): ?>
                                        <div>
                                            <dt><?= e(personalization_label($field)) ?></dt>
                                            <dd><?= $field === 'photo' ? 'Photo ajoutée ✓' : e($value) ?></dd>
                                        </div>
                                    <?php endforeach; ?>
                                </dl>
                            <?php endif; ?>

                            <div class="cart-item-actions">
                                <!-- Change the quantity -->
                                <form method="post" action="cart.php" class="cart-quantity-form">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="item_key" value="<?= e($item_key) ?>">
                                    <label for="quantity-<?= e($item_key) ?>" class="visually-hidden">Quantité</label>
                                    <input type="number" id="quantity-<?= e($item_key) ?>" name="quantity"
                                           min="0" max="10" value="<?= (int) $item['quantity'] ?>" class="quantity-input" data-auto-submit>
                                    <!-- Without JavaScript, this button saves the new quantity -->
                                    <button type="submit" class="cart-update-button">OK</button>
                                </form>

                                <!-- Remove the item -->
                                <form method="post" action="cart.php">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="remove">
                                    <input type="hidden" name="item_key" value="<?= e($item_key) ?>">
                                    <button type="submit" class="cart-remove-button">Retirer</button>
                                </form>
                            </div>
                        </div>

                        <p class="cart-item-total"><?= format_price($item['price'] * $item['quantity']) ?></p>
                    </li>
                <?php endforeach; ?>
            </ul>


            <!-- ===================== SUMMARY (right) ===================== -->
            <aside class="cart-summary">
                <h2>Récapitulatif</h2>

                <!-- Free shipping progress bar -->
                <div class="shipping-progress">
                    <?php if ($missing_for_free > 0): ?>
                        <p>Plus que <strong><?= format_price($missing_for_free) ?></strong> pour la livraison offerte.</p>
                    <?php else: ?>
                        <p><strong>La livraison est offerte !</strong></p>
                    <?php endif; ?>
                    <div class="progress-bar"><span style="width: <?= (int) $progress_percent ?>%"></span></div>
                </div>

                <dl class="summary-lines">
                    <div>
                        <dt>Sous-total</dt>
                        <dd><?= format_price($subtotal) ?></dd>
                    </div>
                    <div>
                        <dt>Livraison en Belgique</dt>
                        <dd><?= $shipping > 0 ? format_price($shipping) : 'Offerte' ?></dd>
                    </div>
                    <div class="summary-total">
                        <dt>Total <small>TVA comprise</small></dt>
                        <dd><?= format_price($total) ?></dd>
                    </div>
                </dl>

                <a href="checkout.php" class="button summary-button">Passer la commande <span aria-hidden="true">→</span></a>
                <a href="products.php" class="summary-continue">Continuer mes achats</a>

                <ul class="summary-notes">
                    <li>🚚 Livré en <?= DELIVERY_WORKING_DAYS ?> jours ouvrables</li>
                    <li>✦ Chaque article est fabriqué à la demande</li>
                </ul>
            </aside>

        </div>
        <?php endif; ?>

    </div>
</section>

<?php require '../includes/footer.php'; ?>