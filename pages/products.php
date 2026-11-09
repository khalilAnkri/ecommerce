<?php


require '../includes/db.php';
require '../includes/functions.php';

$base_path    = '../';
$current_page = 'products';
$page_css     = 'products';   // loads assets/css/products.css


// ---------- 1. Read and check the filters from the URL ----------


$search        = trim($_GET['search'] ?? '');
$search        = mb_substr($search, 0, 60);          // limit the length
$category_slug = $_GET['category'] ?? '';
$animal        = $_GET['animal'] ?? '';
$sort          = $_GET['sort'] ?? 'newest';

// Allowed animal filters
$animal_options = ['dog' => 'Chien', 'cat' => 'Chat'];
if (!array_key_exists($animal, $animal_options)) {
    $animal = '';
}

// Allowed sorting options. The SQL part comes from THIS list,
// never from the URL, so nobody can inject SQL through "sort".
$sort_options = [
    'newest'     => ['label' => 'Nouveautés',       'sql' => 'p.created_at DESC'],
    'price_asc'  => ['label' => 'Prix croissant',   'sql' => 'p.price ASC'],
    'price_desc' => ['label' => 'Prix décroissant', 'sql' => 'p.price DESC'],
    'name'       => ['label' => 'Nom (A → Z)',      'sql' => 'p.name ASC'],
];
if (!array_key_exists($sort, $sort_options)) {
    $sort = 'newest';
}


// ---------- 2. Load categories and products from the database ----------

$categories       = [];
$products         = [];
$current_category = null;   // the selected category (array) or null = all

if ($pdo !== null) {

    // All categories with the number of active products in each one.
    $categories = $pdo->query("
        SELECT c.id, c.name, c.slug, c.description, COUNT(p.id) AS product_count
        FROM categories c
        LEFT JOIN products p ON p.category_id = c.id AND p.is_active = 1
        GROUP BY c.id, c.name, c.slug, c.description, c.sort_order
        ORDER BY c.sort_order
    ")->fetchAll();

    // Find the selected category in this list (unknown slug = all categories).
    foreach ($categories as $category) {
        if ($category['slug'] === $category_slug) {
            $current_category = $category;
        }
    }

    // Build the WHERE part step by step, only with the filters in use.
    // Values are never written in the SQL: they go into $params
    // and are sent separately by the prepared statement.
    $where  = ['p.is_active = 1'];
    $params = [];

    if ($current_category !== null) {
        $where[] = 'p.category_id = :category_id';
        $params['category_id'] = $current_category['id'];
    }

    if ($animal !== '') {
        // A product for "both" also appears in the dog and cat filters.
        $where[] = "(p.animal = :animal OR p.animal = 'both')";
        $params['animal'] = $animal;
    }

    if ($search !== '') {
        // Two different names: with real prepared statements,
        // the same placeholder cannot be used twice.
        $where[] = '(p.name LIKE :search_name OR p.description LIKE :search_description)';
        $params['search_name']        = '%' . $search . '%';
        $params['search_description'] = '%' . $search . '%';
    }

    $sql = "
        SELECT p.*, c.name AS category_name, c.slug AS category_slug
        FROM products p
        JOIN categories c ON c.id = p.category_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY " . $sort_options[$sort]['sql'];

    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    $products = $statement->fetchAll();
}

$filters_used = ($current_category !== null || $animal !== '' || $search !== '');


// ---------- 3. Texts of the page (title, SEO) ----------
if ($current_category !== null) {
    $page_heading     = $current_category['name'];
    $page_intro       = $current_category['description'];
} else {
    $page_heading     = 'La boutique';
    $page_intro       = 'Tous nos accessoires personnalisables pour chiens et chats, fabriqués à la demande et livrés en Belgique.';
}
$page_title       = $page_heading . ' · Poilu & Co';
$page_description = $page_intro;


// ---------- 4. Page HTML ----------
require '../includes/header.php';
?>

<!-- ===================== PAGE TITLE ===================== -->
<section class="page-hero">
    <div class="container">
        <nav class="breadcrumb" aria-label="Fil d'Ariane">
            <a href="../index.php">Accueil</a>
            <span aria-hidden="true">/</span>
            <?php if ($current_category !== null): ?>
                <a href="products.php">Boutique</a>
                <span aria-hidden="true">/</span>
                <span><?= e($current_category['name']) ?></span>
            <?php else: ?>
                <span>Boutique</span>
            <?php endif; ?>
        </nav>
        <h1><?= e($page_heading) ?></h1>
        <p class="page-intro"><?= e($page_intro) ?></p>
    </div>
</section>


<section class="section catalogue">
    <div class="container">

        <?php if ($pdo === null): ?>
            <!-- The database is not installed or MySQL is stopped -->
            <div class="empty-state">
                <h2>La boutique n'est pas encore connectée</h2>
                <p>Démarrez MySQL dans XAMPP/WAMP, puis importez <strong>database/database.sql</strong> dans phpMyAdmin.</p>
            </div>

        <?php else: ?>
        <div class="catalogue-layout">

            <!-- ===================== FILTERS (left column) ===================== -->
            <aside class="filters" data-filters>
                <button type="button" class="filters-toggle button button-outline" data-filters-toggle aria-expanded="false">
                    Filtrer les produits
                </button>

                <!-- One GET form for all the filters -->
                <form method="get" action="products.php" id="filter-form" class="filters-form">

                    <div class="filter-group">
                        <label for="filter-search" class="filter-title">Rechercher</label>
                        <div class="filter-search">
                            <input type="search" id="filter-search" name="search" value="<?= e($search) ?>" placeholder="Prénom, mug, collier…">
                            <button type="submit" aria-label="Rechercher">→</button>
                        </div>
                    </div>

                    <fieldset class="filter-group">
                        <legend class="filter-title">Catégories</legend>
                        <label class="filter-option">
                            <input type="radio" name="category" value="" <?= $current_category === null ? 'checked' : '' ?> data-auto-submit>
                            <span>Tous les produits</span>
                        </label>
                        <?php foreach ($categories as $category): ?>
                            <label class="filter-option">
                                <input type="radio" name="category" value="<?= e($category['slug']) ?>"
                                    <?= ($current_category !== null && $current_category['id'] === $category['id']) ? 'checked' : '' ?> data-auto-submit>
                                <span><?= e($category['name']) ?></span>
                                <small><?= (int) $category['product_count'] ?></small>
                            </label>
                        <?php endforeach; ?>
                    </fieldset>

                    <fieldset class="filter-group">
                        <legend class="filter-title">Pour qui ?</legend>
                        <label class="filter-option">
                            <input type="radio" name="animal" value="" <?= $animal === '' ? 'checked' : '' ?> data-auto-submit>
                            <span>Chiens et chats</span>
                        </label>
                        <?php foreach ($animal_options as $value => $label): ?>
                            <label class="filter-option">
                                <input type="radio" name="animal" value="<?= e($value) ?>" <?= $animal === $value ? 'checked' : '' ?> data-auto-submit>
                                <span><?= e($label) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </fieldset>

                    <!-- Without JavaScript, this button applies the filters -->
                    <button type="submit" class="button filters-submit">Appliquer</button>

                    <?php if ($filters_used): ?>
                        <a href="products.php" class="filters-reset">Effacer les filtres</a>
                    <?php endif; ?>
                </form>
            </aside>


            <!-- ===================== PRODUCTS (right column) ===================== -->
            <div class="catalogue-main">

                <div class="catalogue-toolbar">
                    <p>
                        <strong><?= count($products) ?></strong> produit<?= count($products) > 1 ? 's' : '' ?>
                        <?php if ($search !== ''): ?>
                            pour « <?= e($search) ?> »
                        <?php endif; ?>
                    </p>

                    <!-- form="filter-form": this select belongs to the filter form above -->
                    <label class="sort-select">
                        <span>Trier par</span>
                        <select name="sort" form="filter-form" data-auto-submit>
                            <?php foreach ($sort_options as $value => $option): ?>
                                <option value="<?= e($value) ?>" <?= $sort === $value ? 'selected' : '' ?>><?= e($option['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>

                <?php if (count($products) === 0): ?>
                    <div class="empty-state">
                        <h2>Aucun produit trouvé</h2>
                        <p>Essayez un autre mot ou retirez un filtre.</p>
                        <a href="products.php" class="button button-outline">Voir tous les produits</a>
                    </div>

                <?php else: ?>
                    <div class="product-grid product-grid-catalogue">
                        <?php foreach ($products as $product): ?>
                            <?php $badge = product_badge($product); ?>
                            <article class="product-card reveal">
                                <a href="product.php?id=<?= (int) $product['id'] ?>" class="product-image">
                                    <?php if ($badge !== ''): ?>
                                        <span class="product-badge"><?= e($badge) ?></span>
                                    <?php endif; ?>

                                    <?php if (!empty($product['image']) && file_exists(__DIR__ . '/../assets/images/products/' . $product['image'])): ?>
                                        <img src="../assets/images/products/<?= e($product['image']) ?>" alt="<?= e($product['name']) ?>" loading="lazy">
                                    <?php else: ?>
                                        <!-- No photo yet: show the category icon instead -->
                                        <span class="product-placeholder product-placeholder-<?= e($product['category_slug']) ?>">
                                            <?= category_icon($product['category_slug']) ?>
                                        </span>
                                    <?php endif; ?>
                                </a>

                                <div class="product-info">
                                    <p class="product-category">
                                        <?= e($product['category_name']) ?> · <?= e(animal_label($product['animal'])) ?>
                                    </p>
                                    <h3><a href="product.php?id=<?= (int) $product['id'] ?>"><?= e($product['name']) ?></a></h3>
                                    <p class="product-price">
                                        <?= format_price($product['price']) ?>
                                        <?php if (!empty($product['old_price'])): ?>
                                            <del><?= format_price($product['old_price']) ?></del>
                                        <?php endif; ?>
                                    </p>
                                    <a href="product.php?id=<?= (int) $product['id'] ?>" class="button button-small">Personnaliser</a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            </div>
        </div>
        <?php endif; ?>

    </div>
</section>

<?php require '../includes/footer.php'; ?>