<?php


require '../includes/db.php';
require '../includes/functions.php';

require_admin($pdo);   // not logged in as admin -> redirected to login.php

$base_path    = '../';
$current_page = 'admin';
$page_css     = 'admin';      // assets/css/admin.css (shared by the 3 admin pages)
$page_title   = 'Tableau de bord · Admin Poilu & Co';


// ---------- 1. Key figures ----------
$totals = $pdo->query("
    SELECT COUNT(*) AS order_count,
           COALESCE(SUM(total), 0) AS revenue,
           COALESCE(AVG(total), 0) AS average_basket
    FROM orders
    WHERE status != 'cancelled'
")->fetch();

$revenue_30_days = $pdo->query("
    SELECT COALESCE(SUM(total), 0) FROM orders
    WHERE status != 'cancelled' AND created_at >= NOW() - INTERVAL 30 DAY
")->fetchColumn();

$customer_count = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'customer'")->fetchColumn();

// Orders the team still has to handle (simulated production)
$to_process = $pdo->query("SELECT COUNT(*) FROM orders WHERE status IN ('pending', 'paid', 'in_production')")->fetchColumn();


// ---------- 2. Revenue of the last 12 months ----------
$rows = $pdo->query("
    SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, SUM(total) AS revenue, COUNT(*) AS order_count
    FROM orders
    WHERE status != 'cancelled' AND created_at >= DATE_FORMAT(NOW() - INTERVAL 11 MONTH, '%Y-%m-01')
    GROUP BY month
    ORDER BY month
")->fetchAll();

// Make sure all 12 months exist, even a month without orders (= 0).
$months_fr = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
$monthly   = [];
for ($i = 11; $i >= 0; $i--) {
    $date = new DateTime("first day of -$i month");
    $monthly[$date->format('Y-m')] = [
        'label'       => $months_fr[$date->format('n') - 1],
        'revenue'     => 0,
        'order_count' => 0,
    ];
}
foreach ($rows as $row) {
    if (isset($monthly[$row['month']])) {
        $monthly[$row['month']]['revenue']     = (float) $row['revenue'];
        $monthly[$row['month']]['order_count'] = (int) $row['order_count'];
    }
}
$max_revenue = max(array_column($monthly, 'revenue')) ?: 1;   // avoid dividing by 0


// ---------- 3. Orders per status ----------
$status_counts = $pdo->query("SELECT status, COUNT(*) AS n FROM orders GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);


// ---------- 4. Best-selling products and categories ----------
$top_products = $pdo->query("
    SELECT oi.product_id, oi.product_name, SUM(oi.quantity) AS quantity, SUM(oi.quantity * oi.unit_price) AS revenue
    FROM order_items oi
    JOIN orders o ON o.id = oi.order_id
    WHERE o.status != 'cancelled'
    GROUP BY oi.product_id, oi.product_name
    ORDER BY quantity DESC
    LIMIT 5
")->fetchAll();

$category_sales = $pdo->query("
    SELECT c.name, SUM(oi.quantity * oi.unit_price) AS revenue
    FROM order_items oi
    JOIN orders o     ON o.id = oi.order_id
    JOIN products p   ON p.id = oi.product_id
    JOIN categories c ON c.id = p.category_id
    WHERE o.status != 'cancelled'
    GROUP BY c.id, c.name
    ORDER BY revenue DESC
")->fetchAll();
$max_category = $category_sales ? max(array_column($category_sales, 'revenue')) : 1;


// ---------- 5. Latest orders ----------
$latest_orders = $pdo->query("SELECT * FROM orders ORDER BY created_at DESC LIMIT 6")->fetchAll();


// ---------- Page HTML ----------
require '../includes/header.php';
?>

<section class="admin-hero">
    <div class="container">
        <p class="eyebrow">Espace administrateur</p>
        <h1>Tableau de bord</h1>
        <nav class="admin-nav" aria-label="Menu admin">
            <a href="admin.php" class="is-active">Tableau de bord</a>
            <a href="manage-products.php">Produits</a>
            <a href="manage-orders.php">Commandes</a>
        </nav>
    </div>
</section>

<section class="admin-section">
    <div class="container">

        <!-- ===================== KEY FIGURES ===================== -->
        <div class="stat-grid">
            <div class="stat-card">
                <span>Chiffre d'affaires</span>
                <strong><?= format_price($totals['revenue']) ?></strong>
                <small><?= format_price($revenue_30_days) ?> ces 30 derniers jours</small>
            </div>
            <div class="stat-card">
                <span>Commandes</span>
                <strong><?= (int) $totals['order_count'] ?></strong>
                <small>hors commandes annulées</small>
            </div>
            <div class="stat-card">
                <span>Panier moyen</span>
                <strong><?= format_price($totals['average_basket']) ?></strong>
                <small>TVA et livraison comprises</small>
            </div>
            <div class="stat-card">
                <span>Clients inscrits</span>
                <strong><?= (int) $customer_count ?></strong>
                <small><a href="manage-orders.php?status=to_process"><?= (int) $to_process ?> commande<?= $to_process > 1 ? 's' : '' ?> à traiter →</a></small>
            </div>
        </div>

        <div class="admin-grid">

            <!-- ===================== REVENUE PER MONTH ===================== -->
            <div class="admin-card admin-card-wide">
                <h2>Chiffre d'affaires par mois</h2>
                <!-- Simple bar chart in HTML/CSS: each bar height = revenue / best month -->
                <div class="bar-chart">
                    <?php foreach ($monthly as $month): ?>
                        <div class="bar" title="<?= e($month['label']) ?> : <?= format_price($month['revenue']) ?> (<?= $month['order_count'] ?> commandes)">
                            <span class="bar-value"><?= $month['revenue'] > 0 ? round($month['revenue']) . ' €' : '' ?></span>
                            <span class="bar-fill" style="height: <?= round($month['revenue'] / $max_revenue * 100) ?>%"></span>
                            <span class="bar-label"><?= e($month['label']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- ===================== ORDERS PER STATUS ===================== -->
            <div class="admin-card">
                <h2>Commandes par statut</h2>
                <ul class="status-list">
                    <?php foreach (order_statuses() as $status => $label): ?>
                        <li>
                            <a href="manage-orders.php?status=<?= e($status) ?>">
                                <span class="status-badge status-<?= e($status) ?>"><?= e($label) ?></span>
                                <strong><?= (int) ($status_counts[$status] ?? 0) ?></strong>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <!-- ===================== TOP PRODUCTS ===================== -->
            <div class="admin-card">
                <h2>Meilleures ventes</h2>
                <ol class="top-list">
                    <?php foreach ($top_products as $product): ?>
                        <li>
                            <span><?= e($product['product_name']) ?></span>
                            <small><?= (int) $product['quantity'] ?> vendus · <?= format_price($product['revenue']) ?></small>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </div>

            <!-- ===================== SALES PER CATEGORY ===================== -->
            <div class="admin-card">
                <h2>Ventes par catégorie</h2>
                <ul class="category-bars">
                    <?php foreach ($category_sales as $category): ?>
                        <li>
                            <div><span><?= e($category['name']) ?></span><strong><?= format_price($category['revenue']) ?></strong></div>
                            <span class="category-bar"><span style="width: <?= round($category['revenue'] / $max_category * 100) ?>%"></span></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <!-- ===================== CUSTOMER SEGMENTS (Python, step 11-12) ===================== -->
            <div class="admin-card admin-card-note">
                <h2>Segments clients</h2>
                <p>Les groupes de clients (K-Means) et les produits « fréquemment achetés ensemble » (Apriori) s'afficheront ici après l'analyse Python.</p>
                <small>Données prêtes : <?= (int) $customer_count ?> clients et <?= (int) $totals['order_count'] ?> commandes.</small>
            </div>

            <!-- ===================== LATEST ORDERS ===================== -->
            <div class="admin-card admin-card-wide">
                <div class="admin-card-head">
                    <h2>Dernières commandes</h2>
                    <a href="manage-orders.php">Toutes les commandes →</a>
                </div>
                <div class="table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr><th>N°</th><th>Date</th><th>Client</th><th>Total</th><th>Statut</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($latest_orders as $order): ?>
                                <tr>
                                    <td><a href="manage-orders.php?id=<?= (int) $order['id'] ?>">#<?= order_number($order['id']) ?></a></td>
                                    <td><?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></td>
                                    <td><?= e($order['first_name'] . ' ' . $order['last_name']) ?></td>
                                    <td><?= format_price($order['total']) ?></td>
                                    <td><span class="status-badge status-<?= e($order['status']) ?>"><?= e(order_status_label($order['status'])) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</section>

<?php require '../includes/footer.php'; ?>