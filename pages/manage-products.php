<?php
// ------------------------------------------------------------
//
//   manage-products.php                  -> list of products + categories
//   manage-products.php?action=new       -> form to add a product
//   manage-products.php?action=edit&id=3 -> form to edit product 3
//
// POST actions (all with CSRF token):
//   save_product     add or update a product (with optional photo)
//   toggle_active    show / hide a product in the shop
//   delete_product   only if it was never ordered (otherwise: hide it)
//   add_category     add a category
//   delete_category  only if it has no products
// ------------------------------------------------------------

require '../includes/db.php';
require '../includes/functions.php';

require_admin($pdo);

$base_path    = '../';
$current_page = 'admin';
$page_css     = 'admin';
$page_title   = 'Produits · Admin Poilu & Co';

$product_image_folder = __DIR__ . '/../assets/images/products/';

// Allowed values (same as the ENUM columns of the products table)
$animal_options = ['both' => 'Chien & chat', 'dog' => 'Chien', 'cat' => 'Chat'];
$type_options   = [
    'tag'     => 'Médaille (prénom, téléphone, couleur, taille)',
    'collar'  => 'Collier (prénom, couleur, taille)',
    'bowl'    => 'Gamelle (prénom, couleur, taille)',
    'blanket' => 'Plaid / coussin (prénom, photo, couleur)',
    'mug'     => 'Mug (prénom, texte, photo)',
    'gift'    => 'Coffret (prénom, message)',
];

// Turns a name into a URL slug: "Plaids & coussins" -> "plaids-coussins"
function make_slug($text)
{
    $text = mb_strtolower($text);
    $text = strtr($text, ['à' => 'a', 'â' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
                          'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ö' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
                          'ç' => 'c', 'œ' => 'oe', 'æ' => 'ae']);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);   // everything else becomes "-"
    return trim($text, '-');
}

function field_error($errors, $field)
{
    if (isset($errors[$field])) {
        echo '<p class="field-error">' . e($errors[$field]) . '</p>';
    }
}

$categories = $pdo->query("SELECT * FROM categories ORDER BY sort_order, name")->fetchAll();
$category_ids = array_column($categories, 'id');

$action = $_GET['action'] ?? 'list';
$errors = [];

// Values of the product form (empty = new product)
$values = [
    'id' => 0, 'name' => '', 'category_id' => $categories[0]['id'] ?? 0, 'description' => '',
    'price' => '', 'old_price' => '', 'animal' => 'both', 'personalization_type' => 'tag',
    'badge' => '', 'is_featured' => 0, 'is_active' => 1, 'image' => '',
];

// Edit: load the product
if ($action === 'edit') {
    $statement = $pdo->prepare('SELECT * FROM products WHERE id = :id');
    $statement->execute(['id' => (int) ($_GET['id'] ?? 0)]);
    $product = $statement->fetch();
    if (!$product) {
        set_flash('error', 'Produit introuvable.');
        header('Location: manage-products.php');
        exit;
    }
    $values = array_merge($values, $product);
}


// ============================================================
// POST ACTIONS
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!check_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Le formulaire a expiré. Merci de réessayer.');
        header('Location: manage-products.php');
        exit;
    }

    $post_action = $_POST['action'] ?? '';
    $product_id  = (int) ($_POST['product_id'] ?? 0);


    // ---------- Add / update a product ----------
    if ($post_action === 'save_product') {
        $values['id']                   = $product_id;
        $values['name']                 = trim($_POST['name'] ?? '');
        $values['category_id']          = (int) ($_POST['category_id'] ?? 0);
        $values['description']          = trim($_POST['description'] ?? '');
        $values['price']                = str_replace(',', '.', trim($_POST['price'] ?? ''));       // accept "19,90"
        $values['old_price']            = str_replace(',', '.', trim($_POST['old_price'] ?? ''));
        $values['animal']               = $_POST['animal'] ?? '';
        $values['personalization_type'] = $_POST['personalization_type'] ?? '';
        $values['badge']                = trim($_POST['badge'] ?? '');
        $values['is_featured']          = isset($_POST['is_featured']) ? 1 : 0;
        $values['is_active']            = isset($_POST['is_active']) ? 1 : 0;

        // Current image of the product (kept when no new photo is sent)
        if ($product_id > 0) {
            $statement = $pdo->prepare('SELECT image FROM products WHERE id = :id');
            $statement->execute(['id' => $product_id]);
            $values['image'] = $statement->fetchColumn() ?: '';
        }

        // Validation
        if (mb_strlen($values['name']) < 3 || mb_strlen($values['name']) > 120) {
            $errors['name'] = 'Entre 3 et 120 caractères.';
        }
        if (!in_array($values['category_id'], $category_ids)) {
            $errors['category_id'] = 'Choisissez une catégorie.';
        }
        if (mb_strlen($values['description']) < 10) {
            $errors['description'] = 'Décrivez le produit (10 caractères minimum).';
        }
        if (!is_numeric($values['price']) || $values['price'] <= 0 || $values['price'] > 999) {
            $errors['price'] = 'Prix invalide (ex. 19.90).';
        }
        if ($values['old_price'] !== '' && (!is_numeric($values['old_price']) || $values['old_price'] <= $values['price'])) {
            $errors['old_price'] = 'L\'ancien prix doit être plus élevé que le prix (ou vide).';
        }
        if (!array_key_exists($values['animal'], $animal_options)) {
            $errors['animal'] = 'Choix invalide.';
        }
        if (!array_key_exists($values['personalization_type'], $type_options)) {
            $errors['personalization_type'] = 'Choix invalide.';
        }
        if (mb_strlen($values['badge']) > 30) {
            $errors['badge'] = '30 caractères maximum.';
        }

        // New photo (optional), saved only if the rest is valid
        if (empty($errors)) {
            $upload = save_uploaded_image($_FILES['image'] ?? [], $product_image_folder);
            if ($upload['error'] !== null) {
                $errors['image'] = $upload['error'];
            } elseif ($upload['file_name'] !== null) {
                $values['image'] = $upload['file_name'];
            }
        }

        if (empty($errors)) {
            $data = [
                'category_id'          => $values['category_id'],
                'name'                 => $values['name'],
                'description'          => $values['description'],
                'price'                => $values['price'],
                'old_price'            => $values['old_price'] !== '' ? $values['old_price'] : null,
                'image'                => $values['image'] !== '' ? $values['image'] : null,
                'animal'               => $values['animal'],
                'personalization_type' => $values['personalization_type'],
                'badge'                => $values['badge'] !== '' ? $values['badge'] : null,
                'is_featured'          => $values['is_featured'],
                'is_active'            => $values['is_active'],
            ];

            if ($product_id > 0) {
                $data['id'] = $product_id;
                $statement = $pdo->prepare("
                    UPDATE products SET category_id = :category_id, name = :name, description = :description,
                        price = :price, old_price = :old_price, image = :image, animal = :animal,
                        personalization_type = :personalization_type, badge = :badge,
                        is_featured = :is_featured, is_active = :is_active
                    WHERE id = :id
                ");
                $statement->execute($data);
                set_flash('success', 'Produit « ' . $values['name'] . ' » mis à jour.');
            } else {
                $statement = $pdo->prepare("
                    INSERT INTO products (category_id, name, description, price, old_price, image, animal,
                                          personalization_type, badge, is_featured, is_active)
                    VALUES (:category_id, :name, :description, :price, :old_price, :image, :animal,
                            :personalization_type, :badge, :is_featured, :is_active)
                ");
                $statement->execute($data);
                set_flash('success', 'Produit « ' . $values['name'] . ' » ajouté.');
            }
            header('Location: manage-products.php');
            exit;
        }

        // Errors: show the form again with what was typed
        $action = $product_id > 0 ? 'edit' : 'new';
    }


    // ---------- Show / hide a product ----------
    elseif ($post_action === 'toggle_active') {
        $statement = $pdo->prepare('UPDATE products SET is_active = 1 - is_active WHERE id = :id');
        $statement->execute(['id' => $product_id]);
        set_flash('success', 'Visibilité du produit modifiée.');
        header('Location: manage-products.php');
        exit;
    }


    // ---------- Delete a product ----------
    elseif ($post_action === 'delete_product') {
        // A product that was ordered must stay (old orders point to it): hide it instead.
        $statement = $pdo->prepare('SELECT COUNT(*) FROM order_items WHERE product_id = :id');
        $statement->execute(['id' => $product_id]);

        if ($statement->fetchColumn() > 0) {
            set_flash('error', 'Ce produit a déjà été commandé : il ne peut pas être supprimé. Masquez-le plutôt.');
        } else {
            $statement = $pdo->prepare('DELETE FROM products WHERE id = :id');
            $statement->execute(['id' => $product_id]);
            set_flash('success', 'Produit supprimé.');
        }
        header('Location: manage-products.php');
        exit;
    }


    // ---------- Add a category ----------
    elseif ($post_action === 'add_category') {
        $category_name = trim($_POST['category_name'] ?? '');
        $category_desc = trim($_POST['category_description'] ?? '');
        $slug          = make_slug($category_name);

        $statement = $pdo->prepare('SELECT COUNT(*) FROM categories WHERE slug = :slug');
        $statement->execute(['slug' => $slug]);

        if (mb_strlen($category_name) < 2 || mb_strlen($category_name) > 80 || $slug === '') {
            set_flash('error', 'Nom de catégorie invalide (2 à 80 caractères).');
        } elseif ($statement->fetchColumn() > 0) {
            set_flash('error', 'Cette catégorie existe déjà.');
        } else {
            // The new category goes at the end of the menu
            $next_order = $pdo->query('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM categories')->fetchColumn();

            $statement = $pdo->prepare("
                INSERT INTO categories (name, slug, description, sort_order)
                VALUES (:name, :slug, :description, :sort_order)
            ");
            $statement->execute([
                'name'        => $category_name,
                'slug'        => $slug,
                'description' => $category_desc !== '' ? mb_substr($category_desc, 0, 255) : null,
                'sort_order'  => $next_order,
            ]);
            set_flash('success', 'Catégorie « ' . $category_name . ' » ajoutée.');
        }
        header('Location: manage-products.php#categories');
        exit;
    }


    // ---------- Delete a category ----------
    elseif ($post_action === 'delete_category') {
        $category_id = (int) ($_POST['category_id'] ?? 0);
        $statement = $pdo->prepare('SELECT COUNT(*) FROM products WHERE category_id = :id');
        $statement->execute(['id' => $category_id]);

        if ($statement->fetchColumn() > 0) {
            set_flash('error', 'Cette catégorie contient des produits : déplacez-les ou supprimez-les d\'abord.');
        } else {
            $statement = $pdo->prepare('DELETE FROM categories WHERE id = :id');
            $statement->execute(['id' => $category_id]);
            set_flash('success', 'Catégorie supprimée.');
        }
        header('Location: manage-products.php#categories');
        exit;
    }
}

$flash = get_flash();


// ---------- Data for the list ----------
if ($action === 'list') {
    $products = $pdo->query("
        SELECT p.*, c.name AS category_name, c.slug AS category_slug,
               (SELECT COALESCE(SUM(oi.quantity), 0) FROM order_items oi WHERE oi.product_id = p.id) AS sold
        FROM products p
        JOIN categories c ON c.id = p.category_id
        ORDER BY c.sort_order, p.name
    ")->fetchAll();

    $products_per_category = $pdo->query("SELECT category_id, COUNT(*) FROM products GROUP BY category_id")->fetchAll(PDO::FETCH_KEY_PAIR);
}


// ============================================================
// PAGE HTML
// ============================================================
require '../includes/header.php';
?>

<section class="admin-hero">
    <div class="container">
        <p class="eyebrow">Espace administrateur</p>
        <h1><?= $action === 'list' ? 'Produits' : ($action === 'edit' ? 'Modifier un produit' : 'Nouveau produit') ?></h1>
        <nav class="admin-nav" aria-label="Menu admin">
            <a href="admin.php">Tableau de bord</a>
            <a href="manage-products.php" class="is-active">Produits</a>
            <a href="manage-orders.php">Commandes</a>
        </nav>
    </div>
</section>

<section class="admin-section">
    <div class="container">

        <?php if ($flash): ?>
            <p class="flash flash-<?= e($flash['type']) ?> admin-flash" role="status"><?= e($flash['message']) ?></p>
        <?php endif; ?>


        <?php if ($action === 'new' || $action === 'edit'): ?>
            <!-- ===================== PRODUCT FORM ===================== -->
            <form method="post" action="manage-products.php" enctype="multipart/form-data" class="admin-card admin-form" novalidate>
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="save_product">
                <input type="hidden" name="product_id" value="<?= (int) $values['id'] ?>">

                <div class="form-field">
                    <label for="name">Nom du produit *</label>
                    <input type="text" id="name" name="name" maxlength="120" value="<?= e($values['name']) ?>">
                    <?php field_error($errors, 'name'); ?>
                </div>

                <div class="form-row">
                    <div class="form-field">
                        <label for="category-id">Catégorie *</label>
                        <select id="category-id" name="category_id">
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= (int) $category['id'] ?>" <?= (int) $values['category_id'] === (int) $category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php field_error($errors, 'category_id'); ?>
                    </div>
                    <div class="form-field">
                        <label for="animal">Pour</label>
                        <select id="animal" name="animal">
                            <?php foreach ($animal_options as $value => $label): ?>
                                <option value="<?= e($value) ?>" <?= $values['animal'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php field_error($errors, 'animal'); ?>
                    </div>
                </div>

                <div class="form-field">
                    <label for="description">Description *</label>
                    <textarea id="description" name="description" rows="4"><?= e($values['description']) ?></textarea>
                    <?php field_error($errors, 'description'); ?>
                </div>

                <div class="form-row">
                    <div class="form-field">
                        <label for="price">Prix (€, TVA comprise) *</label>
                        <input type="text" id="price" name="price" inputmode="decimal" value="<?= e($values['price']) ?>" placeholder="29.90">
                        <?php field_error($errors, 'price'); ?>
                    </div>
                    <div class="form-field">
                        <label for="old-price">Ancien prix <small>(si promotion)</small></label>
                        <input type="text" id="old-price" name="old_price" inputmode="decimal" value="<?= e($values['old_price'] ?? '') ?>" placeholder="34.90">
                        <?php field_error($errors, 'old_price'); ?>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-field">
                        <label for="personalization-type">Personnalisation proposée</label>
                        <select id="personalization-type" name="personalization_type">
                            <?php foreach ($type_options as $value => $label): ?>
                                <option value="<?= e($value) ?>" <?= $values['personalization_type'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php field_error($errors, 'personalization_type'); ?>
                    </div>
                    <div class="form-field">
                        <label for="badge">Badge <small>(ex. Nouveau, Best-seller)</small></label>
                        <input type="text" id="badge" name="badge" maxlength="30" value="<?= e($values['badge'] ?? '') ?>">
                        <?php field_error($errors, 'badge'); ?>
                    </div>
                </div>

                <div class="form-field">
                    <label for="image">Photo du produit <small>(JPG, PNG, WEBP · 5 Mo max)</small></label>
                    <?php if (!empty($values['image']) && file_exists($product_image_folder . $values['image'])): ?>
                        <img src="../assets/images/products/<?= e($values['image']) ?>" alt="" class="admin-current-image">
                        <p class="field-help">Laissez vide pour garder la photo actuelle.</p>
                    <?php endif; ?>
                    <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp">
                    <?php field_error($errors, 'image'); ?>
                </div>

                <div class="admin-checks">
                    <label><input type="checkbox" name="is_active" value="1" <?= $values['is_active'] ? 'checked' : '' ?>> Visible dans la boutique</label>
                    <label><input type="checkbox" name="is_featured" value="1" <?= $values['is_featured'] ? 'checked' : '' ?>> Mis en avant sur l'accueil</label>
                </div>

                <div class="admin-form-actions">
                    <button type="submit" class="button"><?= $action === 'edit' ? 'Enregistrer les modifications' : 'Ajouter le produit' ?></button>
                    <a href="manage-products.php" class="admin-link">Annuler</a>
                </div>
            </form>


        <?php else: ?>
            <!-- ===================== PRODUCT LIST ===================== -->
            <div class="admin-card">
                <div class="admin-card-head">
                    <h2><?= count($products) ?> produits</h2>
                    <a href="manage-products.php?action=new" class="button button-small">+ Nouveau produit</a>
                </div>

                <div class="table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr><th></th><th>Produit</th><th>Catégorie</th><th>Prix</th><th>Vendus</th><th>Statut</th><th>Actions</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $product): ?>
                                <tr class="<?= $product['is_active'] ? '' : 'is-hidden-row' ?>">
                                    <td>
                                        <?php if (!empty($product['image']) && file_exists($product_image_folder . $product['image'])): ?>
                                            <img src="../assets/images/products/<?= e($product['image']) ?>" alt="" class="admin-thumb">
                                        <?php else: ?>
                                            <span class="admin-thumb product-placeholder-<?= e($product['category_slug']) ?>"></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?= e($product['name']) ?></strong>
                                        <?php if ($product['is_featured']): ?><span class="admin-tag">Accueil</span><?php endif; ?>
                                        <?php if ($product['badge']): ?><span class="admin-tag"><?= e($product['badge']) ?></span><?php endif; ?>
                                        <br><small><?= e(animal_label($product['animal'])) ?></small>
                                    </td>
                                    <td><?= e($product['category_name']) ?></td>
                                    <td>
                                        <?= format_price($product['price']) ?>
                                        <?php if ($product['old_price']): ?><br><del><?= format_price($product['old_price']) ?></del><?php endif; ?>
                                    </td>
                                    <td><?= (int) $product['sold'] ?></td>
                                    <td>
                                        <span class="status-badge <?= $product['is_active'] ? 'status-delivered' : 'status-cancelled' ?>">
                                            <?= $product['is_active'] ? 'Visible' : 'Masqué' ?>
                                        </span>
                                    </td>
                                    <td class="admin-actions">
                                        <a href="manage-products.php?action=edit&amp;id=<?= (int) $product['id'] ?>" class="admin-link">Modifier</a>

                                        <form method="post" action="manage-products.php">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="action" value="toggle_active">
                                            <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                                            <button type="submit" class="admin-link"><?= $product['is_active'] ? 'Masquer' : 'Afficher' ?></button>
                                        </form>

                                        <?php if ((int) $product['sold'] === 0): ?>
                                            <!-- data-confirm: script.js asks "are you sure?" before sending -->
                                            <form method="post" action="manage-products.php" data-confirm="Supprimer définitivement « <?= e($product['name']) ?> » ?">
                                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                                <input type="hidden" name="action" value="delete_product">
                                                <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                                                <button type="submit" class="admin-link admin-link-danger">Supprimer</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>


            <!-- ===================== CATEGORIES ===================== -->
            <div class="admin-grid" id="categories">
                <div class="admin-card">
                    <h2>Catégories</h2>
                    <ul class="admin-category-list">
                        <?php foreach ($categories as $category): ?>
                            <?php $count = (int) ($products_per_category[$category['id']] ?? 0); ?>
                            <li>
                                <div>
                                    <strong><?= e($category['name']) ?></strong>
                                    <small><?= $count ?> produit<?= $count > 1 ? 's' : '' ?> · /<?= e($category['slug']) ?></small>
                                </div>
                                <?php if ($count === 0): ?>
                                    <form method="post" action="manage-products.php" data-confirm="Supprimer la catégorie « <?= e($category['name']) ?> » ?">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="delete_category">
                                        <input type="hidden" name="category_id" value="<?= (int) $category['id'] ?>">
                                        <button type="submit" class="admin-link admin-link-danger">Supprimer</button>
                                    </form>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <form method="post" action="manage-products.php" class="admin-card">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="add_category">
                    <h2>Nouvelle catégorie</h2>
                    <div class="form-field">
                        <label for="category-name">Nom *</label>
                        <input type="text" id="category-name" name="category_name" maxlength="80" placeholder="Ex. Bandanas">
                    </div>
                    <div class="form-field">
                        <label for="category-description">Description</label>
                        <input type="text" id="category-description" name="category_description" maxlength="255">
                    </div>
                    <button type="submit" class="button button-small">Ajouter la catégorie</button>
                </form>
            </div>
        <?php endif; ?>

    </div>
</section>

<?php require '../includes/footer.php'; ?>