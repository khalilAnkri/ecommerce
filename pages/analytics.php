<?php
require '../includes/db.php';
require '../includes/functions.php';

require_admin($pdo);   // not logged in as admin -> redirected to login.php

$base_path    = '../';
$current_page = 'admin';
$page_css     = 'admin';      // assets/css/admin.css (shared by the admin pages)
$page_title   = 'Analyse des ventes · Admin Poilu & Co';


// ---------- 1. Read the result file of the Python analysis ----------
$analysis   = apriori_data();                 // [] if the file does not exist
$has_result = !empty($analysis['rules']) || !empty($analysis['recommendations']);

$rules            = $analysis['rules'] ?? [];
$parameters       = $analysis['parameters'] ?? [];
$user_parameters  = $analysis['user_parameters'] ?? [];
$user_suggestions = $analysis['user_recommendations'] ?? [];
$evaluation       = $analysis['evaluation'] ?? null;


// ---------- 2. New orders since the analysis? ----------
// The analysis counts the orders that are not cancelled, like here.
$orders_now   = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status != 'cancelled'")->fetchColumn();
$new_orders   = $orders_now - (int) ($analysis['basket_count'] ?? 0);


// ---------- 3. Names of the customers who get recommendations ----------
// The JSON file only contains user ids: we read the names in the database.
$customer_names = [];
if (count($user_suggestions) > 0) {
    $user_ids     = array_map('intval', array_keys($user_suggestions));
    $placeholders = implode(',', array_fill(0, count($user_ids), '?'));
    $statement    = $pdo->prepare("SELECT id, first_name, last_name, username FROM users WHERE id IN ($placeholders)");
    $statement->execute($user_ids);
    foreach ($statement->fetchAll() as $user) {
        $customer_names[$user['id']] = $user;
    }
}

// Shows 0.6667 as "67 %"
function percent($value)
{
    return round($value * 100) . "\u{00A0}%";
}


// ---------- Page HTML ----------
require '../includes/header.php';
?>

<section class="admin-hero">
    <div class="container">
        <p class="eyebrow">Espace administrateur</p>
        <h1>Analyse des ventes</h1>
        <nav class="admin-nav" aria-label="Menu admin">
            <a href="admin.php">Tableau de bord</a>
            <a href="manage-products.php">Produits</a>
            <a href="manage-orders.php">Commandes</a>
            <a href="analytics.php" class="is-active">Analyse</a>
        </nav>
    </div>
</section>

<section class="admin-section">
    <div class="container">

<?php if (!$has_result): ?>

        <!-- ===================== NO RESULT YET ===================== -->
        <div class="empty-state">
            <h2>Aucune analyse pour le moment</h2>
            <p>Le fichier <code>analytics/output/apriori_rules.json</code> n'existe pas encore.<br>
               Démarrez MySQL dans XAMPP, puis lancez l'analyse Python :</p>
            <p><code>cd analytics</code> puis <code>python apriori_analysis.py</code></p>
            <p>(ou ouvrez <code>Apriori_e_commerce.ipynb</code> dans VS Code et faites <em>Run All</em>), puis rechargez cette page.</p>
        </div>

<?php else: ?>

        <!-- ===================== KEY FIGURES ===================== -->
        <div class="stat-grid">
            <div class="stat-card">
                <span>Commandes analysées</span>
                <strong><?= (int) $analysis['basket_count'] ?></strong>
                <small>1 panier = 1 commande</small>
            </div>
            <div class="stat-card">
                <span>Règles trouvées</span>
                <strong><?= (int) $analysis['rule_count'] ?></strong>
                <small><?= count($analysis['recommendations'] ?? []) ?> produits avec suggestions</small>
            </div>
            <div class="stat-card">
                <span>Clients avec suggestions</span>
                <strong><?= count($user_suggestions) ?></strong>
                <small>bloc « Recommandé pour vous »</small>
            </div>
            <div class="stat-card">
                <span>Dernière analyse</span>
                <strong><?= e(date('d/m/Y', strtotime($analysis['generated_at']))) ?></strong>
                <small>à <?= e(date('H:i', strtotime($analysis['generated_at']))) ?> · <?= e($analysis['algorithm'] ?? 'apriori') ?></small>
            </div>
        </div>

        <?php if ($new_orders > 0): ?>
            <p class="flash analytics-warning">
                <?= $new_orders ?> nouvelle<?= $new_orders > 1 ? 's' : '' ?> commande<?= $new_orders > 1 ? 's' : '' ?> depuis la dernière analyse.
                Relancez <code>python apriori_analysis.py</code> pour mettre les suggestions à jour.
            </p>
        <?php endif; ?>

        <div class="admin-grid">

            <!-- ===================== ASSOCIATION RULES ===================== -->
            <div class="admin-card admin-card-wide">
                <div class="admin-card-head">
                    <h2>Règles d'association</h2>
                    <small class="analytics-params">
                        support ≥ <?= percent($parameters['min_support'] ?? 0) ?> ·
                        confiance ≥ <?= percent($parameters['min_confidence'] ?? 0) ?> ·
                        lift ≥ <?= e($parameters['min_lift'] ?? 0) ?>
                    </small>
                </div>
                <p class="analytics-help">« Si un panier contient A, il contient souvent aussi B. » Les règles avec un seul produit de chaque côté alimentent le bloc <strong>Fréquemment achetés ensemble</strong> de la fiche produit.</p>

                <div class="table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Si le panier contient…</th>
                                <th>… il contient aussi</th>
                                <th>Support</th>
                                <th>Confiance</th>
                                <th>Lift</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rules as $rule): ?>
                                <tr>
                                    <td><?= e(implode(' + ', $rule['if_names'])) ?></td>
                                    <td><strong><?= e(implode(' + ', $rule['then_names'])) ?></strong></td>
                                    <td><?= percent($rule['support']) ?></td>
                                    <td>
                                        <!-- small bar: the longer, the more reliable the rule -->
                                        <span class="confidence-bar"><span style="width: <?= round($rule['confidence'] * 100) ?>%"></span></span>
                                        <?= percent($rule['confidence']) ?>
                                    </td>
                                    <td><?= number_format($rule['lift'], 2, ',', ' ') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ===================== HOW TO READ ===================== -->
            <div class="admin-card admin-card-note">
                <h2>Comment lire ces chiffres</h2>
                <p><strong>Support</strong> : part des commandes qui contiennent tous les produits de la règle.</p>
                <p><strong>Confiance</strong> : parmi les commandes avec A, part qui contient aussi B.</p>
                <p><strong>Lift</strong> : au-dessus de 1, A et B sont achetés ensemble plus souvent que par hasard.</p>
                <small>Calculé en Python (Apriori, bibliothèque apyori) sur les commandes non annulées.</small>
            </div>

            <!-- ===================== EVALUATION ===================== -->
            <?php if ($evaluation): ?>
            <div class="admin-card">
                <h2>Qualité des recommandations</h2>
                <div class="evaluation-grid">
                    <div>
                        <strong><?= percent($evaluation['precision']) ?></strong>
                        <span>Précision</span>
                    </div>
                    <div>
                        <strong><?= percent($evaluation['recall']) ?></strong>
                        <span>Rappel</span>
                    </div>
                </div>
                <p class="analytics-help">
                    Test sur <?= (int) $evaluation['customers_evaluated'] ?> clients : on cache leur dernière commande,
                    on recommande <?= (int) $evaluation['recommendations_per_customer'] ?> produits à partir des précédentes,
                    puis on compare avec ce qu'ils ont vraiment acheté.
                </p>
                <ul class="evaluation-list">
                    <li><span>Bonnes recommandations</span> <strong><?= (int) $evaluation['true_positives'] ?></strong></li>
                    <li><span>Recommandés mais pas achetés</span> <strong><?= (int) $evaluation['false_positives'] ?></strong></li>
                    <li><span>Achetés mais pas recommandés</span> <strong><?= (int) $evaluation['false_negatives'] ?></strong></li>
                </ul>
            </div>
            <?php endif; ?>

            <!-- ===================== RECOMMENDATIONS PER CUSTOMER ===================== -->
            <?php if (count($user_suggestions) > 0): ?>
            <div class="admin-card admin-card-wide">
                <div class="admin-card-head">
                    <h2>Recommandé pour vous, par client</h2>
                    <small class="analytics-params">
                        1 transaction = 1 client ·
                        support ≥ <?= percent($user_parameters['min_support'] ?? 0) ?> ·
                        confiance ≥ <?= percent($user_parameters['min_confidence'] ?? 0) ?>
                    </small>
                </div>
                <p class="analytics-help">Ce que chaque client voit sur sa page « Mon compte ». Les clients sans commande n'ont pas de suggestion (démarrage à froid).</p>

                <div class="table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Client</th>
                                <th>Produits recommandés</th>
                                <th>Parce qu'il a acheté</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($user_suggestions as $user_id => $suggestions): ?>
                                <?php $customer = $customer_names[$user_id] ?? null; ?>
                                <tr>
                                    <td>
                                        <?php if ($customer): ?>
                                            <?= e($customer['first_name'] . ' ' . $customer['last_name']) ?><br>
                                            <small>@<?= e($customer['username']) ?></small>
                                        <?php else: ?>
                                            <small>Client n° <?= (int) $user_id ?> (supprimé)</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php foreach ($suggestions as $suggestion): ?>
                                            <?= e($suggestion['name']) ?> <small>(<?= percent($suggestion['confidence']) ?>)</small><br>
                                        <?php endforeach; ?>
                                    </td>
                                    <td>
                                        <?php foreach ($suggestions as $suggestion): ?>
                                            <small><?= e(implode(' + ', $suggestion['because_names'] ?? [])) ?></small><br>
                                        <?php endforeach; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

        </div>

<?php endif; ?>

    </div>
</section>

<?php require '../includes/footer.php'; ?>