<?php
// ------------------------------------------------------------
// pages/login.php — Log in (customers and admins)
//
// The user types a username (or an email) and a password.
//   - wrong username and/or password -> ONE generic error message
//     (we never say which of the two is wrong: that would tell an
//     attacker which usernames exist)
//   - correct -> session id regenerated, user stored in the session,
//     forename + last name shown in the header on every page
//
// Protection against password guessing: after 5 failed attempts for
// the same username from the same IP in 15 minutes, the login is
// blocked for that username until the 15 minutes are over
// (failures are stored in the `login_attempts` table).
// ------------------------------------------------------------

require '../includes/db.php';
require '../includes/functions.php';

$base_path    = '../';
$current_page = 'login';
$page_css     = 'auth';            // loads assets/css/auth.css
$page_title   = 'Connexion · Poilu & Co';
$page_description = 'Connectez-vous à votre compte Poilu & Co.';

const MAX_FAILED_ATTEMPTS = 5;
const LOCK_MINUTES        = 15;


// ---------- 0. Already logged in? ----------
if (is_logged_in()) {
    header('Location: account.php');
    exit;
}

$identifier  = '';                 // username or email typed by the user
$error       = '';
$redirect_to = safe_redirect_target($_GET['redirect'] ?? $_POST['redirect'] ?? '', '');


// ---------- 1. Form sent (POST) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $identifier = trim((string) ($_POST['identifier'] ?? ''));
    $password   = (string) ($_POST['password'] ?? '');

    if ($pdo === null) {
        $error = 'La base de données n\'est pas connectée. Démarrez MySQL dans XAMPP.';

    } elseif (!check_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Le formulaire a expiré. Merci de réessayer.';

    } elseif ($identifier === '' || $password === '') {
        $error = 'Merci de remplir votre nom d\'utilisateur et votre mot de passe.';

    } else {
        $identifier = mb_substr($identifier, 0, 120);
        $ip         = client_ip();

        // --- 1a. Too many failed attempts recently? ---
        $statement = $pdo->prepare('
            SELECT COUNT(*) FROM login_attempts
            WHERE username = :username AND ip_address = :ip
              AND attempted_at > NOW() - INTERVAL ' . LOCK_MINUTES . ' MINUTE
        ');
        $statement->execute(['username' => $identifier, 'ip' => $ip]);
        $failed_attempts = (int) $statement->fetchColumn();

        if ($failed_attempts >= MAX_FAILED_ATTEMPTS) {
            $error = 'Trop de tentatives. Pour votre sécurité, réessayez dans ' . LOCK_MINUTES . ' minutes.';

        } else {
            // --- 1b. Find the user by username OR email ---
            $statement = $pdo->prepare('
                SELECT id, username, first_name, last_name, password_hash, role
                FROM users
                WHERE username = :username OR email = :email
                LIMIT 1
            ');
            $statement->execute(['username' => $identifier, 'email' => $identifier]);
            $user = $statement->fetch();

            // --- 1c. Check the password ---
            // If the user does not exist we still run password_verify() on a
            // fake hash: the answer takes the same time, so an attacker cannot
            // guess which usernames exist by measuring the response time.
            $fake_hash   = '$2y$10$wgre6hvt3K0Fl8ejUUr8rOCtMebW/w2LXyXPCA.PlMo.uRqgE.dh.';
            $password_ok = password_verify($password, $user ? $user['password_hash'] : $fake_hash);

            if ($user && $password_ok) {
                // Success: forget the failed attempts of this username
                $statement = $pdo->prepare('DELETE FROM login_attempts WHERE username = :username AND ip_address = :ip');
                $statement->execute(['username' => $identifier, 'ip' => $ip]);

                // If PHP now has a stronger default algorithm, update the hash
                if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
                    $statement = $pdo->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
                    $statement->execute(['hash' => password_hash($password, PASSWORD_DEFAULT), 'id' => $user['id']]);
                }

                log_user_in($user);
                set_auth_notice('Bonjour ' . $user['first_name'] . ', vous êtes connecté(e).');

                // Admins go to the dashboard, customers back to where they were
                $default = $user['role'] === 'admin' ? 'admin.php' : '../index.php';
                header('Location: ' . ($redirect_to !== '' ? $redirect_to : $default));
                exit;
            }

            // Failure: remember it, then show ONE generic message
            $statement = $pdo->prepare('INSERT INTO login_attempts (username, ip_address) VALUES (:username, :ip)');
            $statement->execute(['username' => $identifier, 'ip' => $ip]);

            // Small clean-up: old attempts are useless
            $pdo->exec('DELETE FROM login_attempts WHERE attempted_at < NOW() - INTERVAL 1 DAY');

            $remaining = MAX_FAILED_ATTEMPTS - $failed_attempts - 1;
            $error = 'Nom d\'utilisateur ou mot de passe incorrect.';
            if ($remaining > 0 && $remaining <= 2) {
                $error .= ' Encore ' . $remaining . ' essai' . ($remaining > 1 ? 's' : '') . ' avant un blocage de ' . LOCK_MINUTES . ' minutes.';
            } elseif ($remaining <= 0) {
                $error = 'Trop de tentatives. Pour votre sécurité, réessayez dans ' . LOCK_MINUTES . ' minutes.';
            }
        }
    }
}

// Message left by another page (e.g. "Vous êtes déconnecté(e)", or an admin page)
$flash = get_flash();


// ---------- 2. Page HTML ----------
require '../includes/header.php';
?>

<section class="auth-section">
    <div class="container auth-layout">

        <!-- ===================== LEFT: welcome panel ===================== -->
        <aside class="auth-aside auth-aside-login" aria-hidden="true">
            <div class="auth-aside-paw"><?= category_icon('paw') ?></div>
            <p class="eyebrow">Content de vous revoir</p>
            <h2>Votre compagnon <em>vous attendait</em></h2>
            <ul class="auth-benefits">
                <li><span><?= category_icon('plaids') ?></span> Votre panier est conservé à la connexion</li>
                <li><span><?= category_icon('mugs') ?></span> Coordonnées pré-remplies au moment de commander</li>
                <li><span><?= category_icon('coffrets') ?></span> Toutes vos commandes au même endroit</li>
            </ul>
        </aside>

        <!-- ===================== RIGHT: form ===================== -->
        <div class="auth-card">
            <nav class="breadcrumb" aria-label="Fil d'Ariane">
                <a href="../index.php">Accueil</a> <span aria-hidden="true">/</span>
                <span>Connexion</span>
            </nav>
            <h1>Connexion</h1>
            <p class="auth-intro">Pas encore de compte ? <a href="register.php<?= $redirect_to ? '?redirect=' . e($redirect_to) : '' ?>">Créez-en un en 1 minute</a></p>

            <?php if ($error !== ''): ?>
                <p class="flash flash-error auth-flash" role="alert"><?= e($error) ?></p>
            <?php elseif ($flash): ?>
                <p class="flash flash-<?= e($flash['type']) ?> auth-flash" role="status"><?= e($flash['message']) ?></p>
            <?php endif; ?>

            <form method="post" action="login.php" class="auth-form" novalidate>
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="redirect" value="<?= e($redirect_to) ?>">

                <div class="form-field">
                    <label for="identifier">Nom d'utilisateur ou email</label>
                    <input type="text" id="identifier" name="identifier" maxlength="120" autocomplete="username"
                           autocapitalize="none" spellcheck="false" value="<?= e($identifier) ?>" required autofocus>
                </div>

                <div class="form-field">
                    <label for="password">Mot de passe</label>
                    <div class="password-wrap">
                        <input type="password" id="password" name="password" maxlength="72" autocomplete="current-password" required>
                        <button type="button" class="password-toggle" data-password-toggle aria-label="Afficher le mot de passe">Afficher</button>
                    </div>
                    <p class="field-status" data-caps-warning aria-live="polite"></p>
                </div>

                <button type="submit" class="button auth-submit">Se connecter <span aria-hidden="true">→</span></button>
            </form>

            <div class="auth-demo">
                <strong>Comptes de démonstration</strong>
                <span>Client : <code>client</code> / <code>Client123!</code></span>
                <span>Admin : <code>admin</code> / <code>Admin123!</code></span>
            </div>
        </div>

    </div>
</section>

<?php require '../includes/footer.php'; ?>
