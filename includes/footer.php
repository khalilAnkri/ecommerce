<?php

?>
</main>

<footer class="site-footer" id="contact">
    <div class="container footer-grid">

        <div class="footer-brand">
            <a href="<?= $base_path ?>index.php" class="logo logo-light">
                <span class="logo-icon"><?= category_icon('paw') ?></span>
                <span class="logo-text">
                    <strong>Poilu &amp; Co</strong>
                    <small>Accessoires personnalisés</small>
                </span>
            </a>
            <p>Des accessoires uniques pour chiens et chats, personnalisés avec amour et livrés partout en Belgique.</p>
        </div>

        <div>
            <h3>Boutique</h3>
            <ul>
                <li><a href="<?= $base_path ?>pages/products.php?category=medailles">Médailles gravées</a></li>
                <li><a href="<?= $base_path ?>pages/products.php?category=colliers">Colliers</a></li>
                <li><a href="<?= $base_path ?>pages/products.php?category=plaids">Plaids &amp; coussins</a></li>
                <li><a href="<?= $base_path ?>pages/products.php?category=coffrets">Coffrets cadeaux</a></li>
            </ul>
        </div>

        <div>
            <h3>Aide</h3>
            <ul>
                <li><a href="<?= $base_path ?>index.php#how-it-works">Comment ça marche</a></li>
                <li><a href="<?= $base_path ?>index.php#faq">Questions fréquentes</a></li>
                <li><a href="<?= $base_path ?>pages/account.php">Suivre ma commande</a></li>
                <li><a href="<?= $base_path ?>index.php#about">À propos</a></li>
            </ul>
        </div>

        <div>
            <h3>Contact</h3>
            <ul>
                <li><a href="mailto:bonjour@poilu-and-co.be">bonjour@poilu-and-co.be</a></li>
                <li>Lundi – vendredi, 9 h – 17 h</li>
                <li>Bruxelles, Belgique</li>
            </ul>
        </div>

    </div>

    <div class="container footer-bottom">
        <p>© <?= date('Y') ?> Poilu &amp; Co · Prix en euros, TVA comprise</p>
        <p>Projet universitaire : le paiement, la production et la livraison sont simulés.</p>
    </div>
</footer>

<script src="<?= $base_path ?>assets/js/script.js"></script>
</body>
</html>
