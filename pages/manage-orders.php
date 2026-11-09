<?php
// ------------------------------------------------------------
//
//   manage-orders.php                         -> list (25 per page)
//   manage-orders.php?status=shipped          -> only one status
//   manage-orders.php?status=to_process       -> paid + in production
//   manage-orders.php?search=dupont           -> name, email or order number
//   manage-orders.php?id=12                   -> details of order 12
//
// ------------------------------------------------------------

require '../includes/db.php';
require '../includes/functions.php';

require_admin($pdo);

$base_path    = '../';
$current_page = 'admin';
$page_css     = 'admin';
$page_title   = 'Commandes · Admin Poilu & Co';

$statuses = order_statuses();


// ---------- 1. Change the status of an order (POST) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id   = (int) ($_POST['order_id'] ?? 0);
    $new_status = $_POST['status'] ?? '';

    if (!check_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Le formulaire a expiré. Merci de réessayer.');
    } elseif (!array_key_exists($new_status, $statuses)) {
        set_flash('error', 'Statut invalide.');
    } else {
        $statement = $pdo->prepare('UPDATE orders SET status = :status WHERE id = :id');
        $statement->execute(['status' => $new_status, 'id' => $order_id]);
        set_flash('success', 'Commande #' . order_number($order_id) . ' : statut « ' . order_status_label($new_status) . ' ».');
    }

    // Go back to the same page (details or list with its filters).
    // The return address is rebuilt here, never copied from the form.
    if (($_POST['from'] ?? '') === 'details') {
        header('Location: manage-orders.php?id=' . $order_id);
    } else {
        $query = http_build_query([
            'status' => $_POST['filter_status'] ?? '',
            'search' => $_POST['filter_search'] ?? '',
            'page'   => (int) ($_POST['filter_page'] ?? 1),
        ]);
        header('Location: manage-orders.php?' . $query);
    }
    exit;
}
$flash = get_flash();


// ============================================================
// VIEW 1: DETAILS OF ONE ORDER
// ============================================================
$order = false;
if (isset($_GET['id'])) {
    $statement = $pdo->prepare('SELECT * FROM orders WHERE id = :id');
    $statement->execute(['id' => (int) $_GET['id']]);
    $order = $statement->fetch();

    if ($order) {
        // Items + their personalisation (LEFT JOIN: an item may have none)
        $statement = $pdo->prepare("
            SELECT oi.*, pz.pet_name, pz.phone AS engraved_phone, pz.color, pz.size, pz.message, pz.photo
            FROM order_items oi
            LEFT JOIN personalizations pz ON pz.order_item_id = oi.id
            WHERE oi.order_id = :id
        ");
        $statement->execute(['id' => $order['id']]);
        $order_items = $statement->fetchAll();
        $page_title  = 'Commande #' . order_number($order['id']) . ' · Admin Poilu & Co';
    }
}


// ============================================================
// VIEW 2: LIST WITH FILTERS AND PAGES
// ============================================================
if (!$order) {
    $filter_status = $_GET['status'] ?? '';
    $search        = mb_substr(trim($_GET['search'] ?? ''), 0, 60);
    $page          = max(1, (int) ($_GET['page'] ?? 1));
    $per_page      = 25;

    $where  = [];
    $params = [];

    if ($filter_status === 'to_process') {
        $where[] = "o.status IN ('pending', 'paid', 'in_production')";
    } elseif (array_key_exists($filter_status, $statuses)) {
        $where[] = 'o.status = :status';
        $params['status'] = $filter_status;
    } else {
        $filter_status = '';
    }

    if ($search !== '') {
        $where[] = '(o.id = :search_id OR o.email LIKE :search_email OR CONCAT(o.first_name, \' \', o.last_name) LIKE :search_name)';
        $params['search_id']    = (int) ltrim($search, '#0');    // "#00012" or "12" -> 12
        $params['search_email'] = '%' . $search . '%';
        $params['search_name']  = '%' . $search . '%';
    }

    $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    // How many orders match -> how many pages
    $statement = $pdo->prepare("SELECT COUNT(*) FROM orders o $where_sql");
    $statement->execute($params);
    $order_total = (int) $statement->fetchColumn();
    $page_count  = max(1, (int) ceil($order_total / $per_page));
    $page        = min($page, $page_count);
    $offset      = ($page - 1) * $per_page;

    // LIMIT and OFFSET are integers we calculated ourselves: safe to write in the SQL.
    $statement = $pdo->prepare("
        SELECT o.*, (SELECT SUM(quantity) FROM order_items WHERE order_id = o.id) AS item_count
        FROM orders o
        $where_sql
        ORDER BY o.created_at DESC
        LIMIT $per_page OFFSET $offset
    ");
    $statement->execute($params);
    $orders = $statement->fetchAll();

    // Link to another page while keeping the filters
    function page_link($page_number, $filter_status, $search)
    {
        return 'manage-orders.php?' . http_build_query(['status' => $filter_status, 'search' => $search, 'page' => $page_number]);
    }
}


// ============================================================
// PAGE HTML
// ============================================================
require '../includes/header.php';
?>

<section class="admin-hero">
    <div class="container">
        <p class="eyebrow">Espace administrateur</p>
        <h1><?= $order ? 'Commande #' . order_number($order['id']) : 'Commandes' ?></h1>
        <nav class="admin-nav" aria-label="Menu admin">
            <a href="admin.php">Tableau de bord</a>
            <a href="manage-products.php">Produits</a>
            <a href="manage-orders.php" class="is-active">Commandes</a>
        </nav>
    </div>
</section>

<section class="admin-section">
    <div class="container">

        <?php if ($flash): ?>
            <p class="flash flash-<?= e($flash['type']) ?> admin-flash" role="status"><?= e($flash['message']) ?></p>
        <?php endif; ?>


        <?php if ($order): ?>
            <!-- ===================== ORDER DETAILS ===================== -->
            <a href="manage-orders.php" class="admin-link admin-back">← Toutes les commandes</a>

            <div class="admin-grid">
                <div class="admin-card admin-card-wide">
                    <h2>Articles</h2>
                    <ul class="order-detail-items">
                        <?php foreach ($order_items as $item): ?>
                            <li>
                                <div>
                                    <strong><?= (int) $item['quantity'] ?> × <?= e($item['product_name']) ?></strong>
                                    <div class="order-detail-perso">
                                        <?php if ($item['pet_name']): ?><span>Prénom : <b><?= e($item['pet_name']) ?></b></span><?php endif; ?>
                                        <?php if ($item['engraved_phone']): ?><span>Téléphone : <b><?= e($item['engraved_phone']) ?></b></span><?php endif; ?>
                                        <?php if ($item['color']): ?><span>Couleur : <b><?= e($item['color']) ?></b></span><?php endif; ?>
                                        <?php if ($item['size']): ?><span>Taille : <b><?= e($item['size']) ?></b></span><?php endif; ?>
                                        <?php if ($item['message']): ?><span>Message : <b><?= e($item['message']) ?></b></span><?php endif; ?>
                                        <?php if ($item['photo']): ?>
                                            <a href="../assets/images/uploads/<?= e($item['photo']) ?>" target="_blank" rel="noopener">Voir la photo de l'animal ↗</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <span><?= format_price($item['unit_price'] * $item['quantity']) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <dl class="order-detail-totals">
                        <div><dt>Sous-total</dt><dd><?= format_price($order['subtotal']) ?></dd></div>
                        <div><dt>Livraison</dt><dd><?= $order['shipping_cost'] > 0 ? format_price($order['shipping_cost']) : 'Offerte' ?></dd></div>
                        <div class="order-detail-total"><dt>Total</dt><dd><?= format_price($order['total']) ?></dd></div>
                    </dl>
                </div>

                <div class="admin-card">
                    <h2>Statut</h2>
                    <p><span class="status-badge status-<?= e($order['status']) ?>"><?= e(order_status_label($order['status'])) ?></span></p>
                    <form method="post" action="manage-orders.php" class="status-form">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                        <input type="hidden" name="from" value="details">
                        <label for="status" class="visually-hidden">Nouveau statut</label>
                        <select id="status" name="status">
                            <?php foreach ($statuses as $value => $label): ?>
                                <option value="<?= e($value) ?>" <?= $order['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="button button-small">Mettre à jour</button>
                    </form>
                    <p class="field-help">Production et livraison simulées : le statut est changé à la main.</p>
                </div>

                <div class="admin-card">
                    <h2>Client</h2>
                    <p class="order-detail-customer">
                        <strong><?= e($order['first_name'] . ' ' . $order['last_name']) ?></strong>
                        <span class="admin-tag"><?= $order['user_id'] ? 'Compte client' : 'Invité' ?></span><br>
                        <a href="mailto:<?= e($order['email']) ?>"><?= e($order['email']) ?></a><br>
                        <?= $order['phone'] ? e($order['phone']) . '<br>' : '' ?>
                        <br>
                        <?= e($order['address']) ?><br>
                        <?= e($order['postal_code']) ?> <?= e($order['city']) ?>, Belgique
                    </p>
                    <p class="field-help">
                        Commandée le <?= date('d/m/Y à H:i', strtotime($order['created_at'])) ?><br>
                        Paiement (simulé) : <?= e(payment_method_label($order['payment_method'])) ?>
                    </p>
                </div>
            </div>


        <?php else: ?>
            <!-- ===================== FILTERS ===================== -->
            <form method="get" action="manage-orders.php" class="admin-filters">
                <div class="admin-status-tabs">
                    <a href="manage-orders.php?search=<?= e(urlencode($search)) ?>" class="<?= $filter_status === '' ? 'is-active' : '' ?>">Toutes</a>
                    <a href="manage-orders.php?status=to_process&amp;search=<?= e(urlencode($search)) ?>" class="<?= $filter_status === 'to_process' ? 'is-active' : '' ?>">À traiter</a>
                    <?php foreach ($statuses as $value => $label): ?>
                        <a href="manage-orders.php?status=<?= e($value) ?>&amp;search=<?= e(urlencode($search)) ?>" class="<?= $filter_status === $value ? 'is-active' : '' ?>"><?= e($label) ?></a>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="status" value="<?= e($filter_status) ?>">
                <div class="filter-search admin-search">
                    <label for="order-search" class="visually-hidden">Rechercher une commande</label>
                    <input type="search" id="order-search" name="search" value="<?= e($search) ?>" placeholder="N°, nom ou email">
                    <button type="submit" aria-label="Rechercher">→</button>
                </div>
            </form>

            <!-- ===================== ORDER LIST ===================== -->
            <div class="admin-card">
                <div class="admin-card-head">
                    <h2><?= $order_total ?> commande<?= $order_total > 1 ? 's' : '' ?></h2>
                    <?php if ($page_count > 1): ?><small>Page <?= $page ?> / <?= $page_count ?></small><?php endif; ?>
                </div>

                <?php if (count($orders) === 0): ?>
                    <p class="admin-empty">Aucune commande ne correspond.</p>
                <?php else: ?>
                <div class="table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr><th>N°</th><th>Date</th><th>Client</th><th>Articles</th><th>Total</th><th>Statut</th><th></th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $row): ?>
                                <tr>
                                    <td><a href="manage-orders.php?id=<?= (int) $row['id'] ?>">#<?= order_number($row['id']) ?></a></td>
                                    <td><?= date('d/m/Y', strtotime($row['created_at'])) ?></td>
                                    <td>
                                        <?= e($row['first_name'] . ' ' . $row['last_name']) ?>
                                        <?php if (!$row['user_id']): ?><span class="admin-tag">Invité</span><?php endif; ?>
                                        <br><small><?= e($row['city']) ?></small>
                                    </td>
                                    <td><?= (int) $row['item_count'] ?></td>
                                    <td><?= format_price($row['total']) ?></td>
                                    <td>
                                        <!-- Change the status directly from the list -->
                                        <form method="post" action="manage-orders.php" class="status-form-inline">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="order_id" value="<?= (int) $row['id'] ?>">
                                            <input type="hidden" name="filter_status" value="<?= e($filter_status) ?>">
                                            <input type="hidden" name="filter_search" value="<?= e($search) ?>">
                                            <input type="hidden" name="filter_page" value="<?= (int) $page ?>">
                                            <label for="status-<?= (int) $row['id'] ?>" class="visually-hidden">Statut</label>
                                            <select id="status-<?= (int) $row['id'] ?>" name="status" class="status-select status-<?= e($row['status']) ?>" data-auto-submit>
                                                <?php foreach ($statuses as $value => $label): ?>
                                                    <option value="<?= e($value) ?>" <?= $row['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" class="admin-ok-button">OK</button>
                                        </form>
                                    </td>
                                    <td><a href="manage-orders.php?id=<?= (int) $row['id'] ?>" class="admin-link">Détails</a></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>

                <?php if ($page_count > 1): ?>
                    <nav class="pagination" aria-label="Pages">
                        <?php for ($i = 1; $i <= $page_count; $i++): ?>
                            <a href="<?= e(page_link($i, $filter_status, $search)) ?>" class="<?= $i === $page ? 'is-active' : '' ?>"><?= $i ?></a>
                        <?php endfor; ?>
                    </nav>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </div>
</section>

<?php require '../includes/footer.php'; ?>