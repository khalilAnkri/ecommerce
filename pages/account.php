<?php
// ------------------------------------------------------------
// pages/account.php — "Mon compte"
// Reserved to logged-in users: profile + order history.
// ------------------------------------------------------------

require '../includes/db.php';
require '../includes/functions.php';

require_login();   // visitors are sent to login.php?redirect=account.php

$base_path    = '../';
$current_page = 'account';
$page_css     = 'auth';            // shares assets/css/auth.css with login/register
$page_title   = 'Mon compte · Poilu & Co';

$user   = null;
$orders = [];

if ($pdo !== null) {
    $statement = $pdo->prepare('SELECT username, first_name, last_name, email, phone, address, postal_code, city, role, created_at FROM users WHERE id = :id');
    $statement->execute(['id' => $_SESSION['user_id']]);
    $user = $statement->fetch();

    // The account was deleted while the user was logged in: log out.
    if (!$user) {
        log_user_out();
        header('Location: login.php');
        exit;
    }

    $statement = $pdo->prepare('
        SELECT o.id, o.total, o.status, o.created_at, COALESCE(SUM(oi.quantity), 0) AS item_count
        FROM orders o
        LEFT JOIN order_items oi ON oi.order_id = o.id
        WHERE o.user_id = :id
        GROUP BY o.id
        ORDER BY o.created_at DESC
    ');
    $statement->execute(['id' => $_SESSION['user_id']]);
    $orders = $statement->fetchAll();
}

require '../includes/header.php';
?>

<section class="page-hero">
    <div class="container">
        <nav class="breadcrumb" aria-label="Fil d'Ariane">
            <a href="../index.php">Accueil</a> <span aria-hidden="true">/</span>
            <span>Mon compte</span>
        </nav>
        <h1>Bonjour <em><?= e($_SESSION['user_first_name']) ?></em></h1>
        <p class="page-intro">Retrouvez vos informations et l'historique de vos commandes.</p>
    </div>
</section>

<section class="section account-section">
    <div class="container account-layout">

        <?php if ($user === null): ?>
            <div class="empty-state">
                <h2>Compte indisponible</h2>
                <p>La base de données n'est pas connectée. Démarrez MySQL dans XAMPP.</p>
            </div>
        <?php else: ?>

        <!-- ===================== Profile ===================== -->
        <aside class="account-card">
            <div class="account-avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($user['first_name'], 0, 1))) ?></div>
            <h2><?= e($user['first_name'] . ' ' . $user['last_name']) ?></h2>
            <p class="account-username">@<?= e($user['username']) ?></p>

            <dl class="account-details">
                <div><dt>Email</dt><dd><?= e($user['email']) ?></dd></div>
                <?php if (!empty($user['phone'])): ?>
                    <div><dt>Téléphone</dt><dd><?= e($user['phone']) ?></dd></div>
                <?php endif; ?>
                <?php if (!empty($user['address'])): ?>
                    <div><dt>Adresse</dt><dd><?= e($user['address']) ?><br><?= e($user['postal_code'] . ' ' . $user['city']) ?></dd></div>
                <?php endif; ?>
                <div><dt>Membre depuis</dt><dd><?= e(format_date_fr(new DateTime($user['created_at']))) ?> <?= e(date('Y', strtotime($user['created_at']))) ?></dd></div>
            </dl>

            <?php if ($user['role'] === 'admin'): ?>
                <a href="admin.php" class="button button-small">Tableau de bord admin</a>
            <?php endif; ?>

            <form method="post" action="logout.php">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <button type="submit" class="button button-outline button-small account-logout">Se déconnecter</button>
            </form>
        </aside>

        <!-- ===================== Orders ===================== -->
        <div class="account-orders">
            <h2>Mes commandes</h2>

            <?php if (empty($orders)): ?>
                <div class="empty-state">
                    <h3>Pas encore de commande</h3>
                    <p>Votre première médaille gravée vous attend.</p>
                    <a href="products.php" class="button">Découvrir la boutique <span aria-hidden="true">→</span></a>
                </div>
            <?php else: ?>
                <ul class="order-list">
                    <?php foreach ($orders as $order): ?>
                        <li class="order-row">
                            <div>
                                <strong>Commande n° <?= e(order_number($order['id'])) ?></strong>
                                <span><?= e(date('d/m/Y', strtotime($order['created_at']))) ?> · <?= (int) $order['item_count'] ?> article<?= $order['item_count'] > 1 ? 's' : '' ?></span>
                            </div>
                            <span class="order-status order-status-<?= e($order['status']) ?>"><?= e(order_status_label($order['status'])) ?></span>
                            <strong class="order-total"><?= format_price($order['total']) ?></strong>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <?php endif; ?>
    </div>
</section>

<?php require '../includes/footer.php'; ?>
