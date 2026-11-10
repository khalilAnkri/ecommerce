<?php
// ------------------------------------------------------------
// pages/register.php — Create a customer account
//
// Mandatory fields: username, forename, last name, email, password.
// Steps when the form is sent (POST):
//   1. check the CSRF token and the hidden "honeypot" field (anti-bot)
//   2. validate every field on the SERVER (never trust the browser)
//   3. check that the username (and the email) are not already used
//        -> used: error message, the user must choose another one
//        -> free: the account is created, registration confirmed
//   4. the password is stored HASHED with password_hash() (bcrypt),
//      never in clear text
//   5. the new customer is logged in and sent to the homepage
//
// Extra: register.php?check_username=xxx returns JSON
// {"available": true/false}. script.js calls it while the user types,
// to show "username available" before the form is even sent.
// ------------------------------------------------------------

require '../includes/db.php';
require '../includes/functions.php';

$base_path    = '../';
$current_page = 'register';
$page_css     = 'auth';            // loads assets/css/auth.css
$page_title   = 'Créer un compte · Poilu & Co';
$page_description = 'Créez votre compte Poilu & Co pour commander plus vite et suivre vos commandes.';

// Rules shared by the live check and the form
const USERNAME_PATTERN = '/^[a-zA-Z0-9._-]{3,30}$/';   // 3 to 30 letters, digits, . _ -
const NAME_PATTERN     = "/^[\p{L} '\-]{2,50}$/u";      // letters (with accents), spaces, - and '
const PASSWORD_MIN     = 8;


// ---------- 0. Already logged in? Nothing to do here ----------
if (is_logged_in() && !isset($_GET['check_username'])) {
    header('Location: account.php');
    exit;
}


// ---------- 1. Live username check (AJAX, used by script.js) ----------
if (isset($_GET['check_username'])) {
    header('Content-Type: application/json; charset=utf-8');
    $username = trim((string) $_GET['check_username']);

    if ($pdo === null || !preg_match(USERNAME_PATTERN, $username)) {
        echo json_encode(['available' => false, 'valid' => false]);
        exit;
    }

    $statement = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = :username');
    $statement->execute(['username' => $username]);
    echo json_encode(['available' => $statement->fetchColumn() == 0, 'valid' => true]);
    exit;
}


// Prints the error message of one field (if there is one)
function field_error($errors, $field)
{
    if (isset($errors[$field])) {
        echo '<p class="field-error" id="' . e($field) . '-error">' . e($errors[$field]) . '</p>';
    }
}

// Adds aria-invalid / aria-describedby to an input that has an error
function field_aria($errors, $field)
{
    if (isset($errors[$field])) {
        echo ' aria-invalid="true" aria-describedby="' . e($field) . '-error"';
    }
}


$values = ['username' => '', 'first_name' => '', 'last_name' => '', 'email' => '', 'accept_terms' => false];
$errors = [];
$email_taken = false;   // true -> we show a "Se connecter" link under the email


// ---------- 2. Form sent (POST) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    foreach (['username', 'first_name', 'last_name', 'email'] as $field) {
        $values[$field] = trim((string) ($_POST[$field] ?? ''));
    }
    $password         = (string) ($_POST['password'] ?? '');
    $password_confirm = (string) ($_POST['password_confirm'] ?? '');
    $values['accept_terms'] = isset($_POST['accept_terms']);

    if ($pdo === null) {
        $errors['form'] = 'La base de données n\'est pas connectée. Démarrez MySQL dans XAMPP.';

    } elseif (!check_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors['form'] = 'Le formulaire a expiré. Merci de réessayer.';

    } elseif (($_POST['website'] ?? '') !== '') {
        // Honeypot: this field is hidden for humans. Only bots fill it in.
        $errors['form'] = 'Inscription refusée.';

    } else {
        // --- Server-side validation: every field is MANDATORY ---
        if ($values['username'] === '') {
            $errors['username'] = 'Choisissez un nom d\'utilisateur.';
        } elseif (!preg_match(USERNAME_PATTERN, $values['username'])) {
            $errors['username'] = 'De 3 à 30 caractères : lettres, chiffres, point, tiret ou underscore.';
        }

        if ($values['first_name'] === '') {
            $errors['first_name'] = 'Indiquez votre prénom.';
        } elseif (!preg_match(NAME_PATTERN, $values['first_name'])) {
            $errors['first_name'] = 'Votre prénom contient des caractères non autorisés.';
        }

        if ($values['last_name'] === '') {
            $errors['last_name'] = 'Indiquez votre nom.';
        } elseif (!preg_match(NAME_PATTERN, $values['last_name'])) {
            $errors['last_name'] = 'Votre nom contient des caractères non autorisés.';
        }

        if ($values['email'] === '') {
            $errors['email'] = 'Indiquez votre adresse email.';
        } elseif (!filter_var($values['email'], FILTER_VALIDATE_EMAIL) || strlen($values['email']) > 120) {
            $errors['email'] = 'Cette adresse email n\'est pas valide.';
        }

        if ($password === '') {
            $errors['password'] = 'Choisissez un mot de passe.';
        } elseif (strlen($password) < PASSWORD_MIN) {
            $errors['password'] = 'Au moins ' . PASSWORD_MIN . ' caractères.';
        } elseif (strlen($password) > 72) {
            // bcrypt only uses the first 72 bytes: refuse longer passwords
            $errors['password'] = '72 caractères maximum.';
        } elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
            $errors['password'] = 'Utilisez au moins une lettre et un chiffre.';
        } elseif (strcasecmp($password, $values['username']) === 0) {
            $errors['password'] = 'Le mot de passe ne peut pas être votre nom d\'utilisateur.';
        }

        if ($password_confirm !== $password) {
            $errors['password_confirm'] = 'Les deux mots de passe ne sont pas identiques.';
        }

        if (!$values['accept_terms']) {
            $errors['accept_terms'] = 'Merci d\'accepter les conditions pour créer votre compte.';
        }

        // --- Is the username already used? (only if it is valid) ---
        if (!isset($errors['username'])) {
            $statement = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = :username');
            $statement->execute(['username' => $values['username']]);
            if ($statement->fetchColumn() > 0) {
                $errors['username'] = 'Ce nom d\'utilisateur est déjà utilisé. Merci d\'en choisir un autre.';
            }
        }

        // --- Is the email already used? ---
        if (!isset($errors['email'])) {
            $statement = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = :email');
            $statement->execute(['email' => $values['email']]);
            if ($statement->fetchColumn() > 0) {
                $errors['email'] = 'Un compte existe déjà avec cette adresse.';
                $email_taken     = true;
            }
        }

        // --- Everything is fine: create the account ---
        if (empty($errors)) {
            try {
                $statement = $pdo->prepare('
                    INSERT INTO users (username, first_name, last_name, email, password_hash, role)
                    VALUES (:username, :first_name, :last_name, :email, :password_hash, \'customer\')
                ');
                $statement->execute([
                    'username'      => $values['username'],
                    'first_name'    => $values['first_name'],
                    'last_name'     => $values['last_name'],
                    'email'         => $values['email'],
                    // password_hash() adds a random salt and uses bcrypt (slow on purpose):
                    // the clear password is never stored anywhere.
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                ]);

                log_user_in([
                    'id'         => $pdo->lastInsertId(),
                    'username'   => $values['username'],
                    'first_name' => $values['first_name'],
                    'last_name'  => $values['last_name'],
                    'role'       => 'customer',
                ]);

                set_auth_notice('Inscription confirmée ! Bienvenue chez Poilu & Co, ' . $values['first_name'] . '.');
                $redirect = safe_redirect_target($_POST['redirect'] ?? '', '../index.php');
                header('Location: ' . $redirect);
                exit;

            } catch (PDOException $error) {
                // 23000 = UNIQUE constraint: someone took the same username or
                // email a split second before us (two forms sent at once).
                if ($error->getCode() === '23000') {
                    $errors['username'] = 'Ce nom d\'utilisateur ou cet email vient d\'être utilisé. Merci d\'en choisir un autre.';
                } else {
                    error_log('Registration failed: ' . $error->getMessage());
                    $errors['form'] = 'Une erreur est survenue. Merci de réessayer plus tard.';
                }
            }
        }
    }
}

$redirect_to = safe_redirect_target($_GET['redirect'] ?? $_POST['redirect'] ?? '', '');


// ---------- 3. Page HTML ----------
require '../includes/header.php';
?>

<section class="auth-section">
    <div class="container auth-layout">

        <!-- ===================== LEFT: welcome panel ===================== -->
        <aside class="auth-aside" aria-hidden="true">
            <div class="auth-aside-paw"><?= category_icon('paw') ?></div>
            <p class="eyebrow">Rejoignez la meute</p>
            <h2>Un compte pour <em>votre compagnon</em></h2>
            <ul class="auth-benefits">
                <li><span><?= category_icon('medailles') ?></span> Commandez plus vite, vos coordonnées sont pré-remplies</li>
                <li><span><?= category_icon('coffrets') ?></span> Retrouvez l'historique de vos commandes</li>
                <li><span><?= category_icon('colliers') ?></span> Suivez la fabrication de vos accessoires</li>
            </ul>
        </aside>

        <!-- ===================== RIGHT: form ===================== -->
        <div class="auth-card">
            <nav class="breadcrumb" aria-label="Fil d'Ariane">
                <a href="../index.php">Accueil</a> <span aria-hidden="true">/</span>
                <span>Créer un compte</span>
            </nav>
            <h1>Créer un compte</h1>
            <p class="auth-intro">Déjà inscrit ? <a href="login.php<?= $redirect_to ? '?redirect=' . e($redirect_to) : '' ?>">Connectez-vous</a></p>

            <?php if (isset($errors['form'])): ?>
                <p class="flash flash-error auth-flash" role="alert"><?= e($errors['form']) ?></p>
            <?php elseif (!empty($errors)): ?>
                <p class="flash flash-error auth-flash" role="alert">Merci de corriger les champs en rouge.</p>
            <?php endif; ?>

            <form method="post" action="register.php" class="auth-form" data-register-form novalidate>
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="redirect" value="<?= e($redirect_to) ?>">

                <!-- Honeypot: invisible for humans, bots fill it in -->
                <div class="auth-honeypot" aria-hidden="true">
                    <label for="website">Ne pas remplir</label>
                    <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                </div>

                <div class="form-field">
                    <label for="username">Nom d'utilisateur *</label>
                    <input type="text" id="username" name="username" maxlength="30" autocomplete="username"
                           autocapitalize="none" spellcheck="false" pattern="[a-zA-Z0-9._\-]{3,30}"
                           value="<?= e($values['username']) ?>" required data-username-check
                           <?php field_aria($errors, 'username'); ?>>
                    <p class="field-status" data-username-status aria-live="polite"></p>
                    <?php field_error($errors, 'username'); ?>
                </div>

                <div class="form-row">
                    <div class="form-field">
                        <label for="first-name">Prénom *</label>
                        <input type="text" id="first-name" name="first_name" maxlength="50" autocomplete="given-name"
                               value="<?= e($values['first_name']) ?>" required <?php field_aria($errors, 'first_name'); ?>>
                        <?php field_error($errors, 'first_name'); ?>
                    </div>
                    <div class="form-field">
                        <label for="last-name">Nom *</label>
                        <input type="text" id="last-name" name="last_name" maxlength="50" autocomplete="family-name"
                               value="<?= e($values['last_name']) ?>" required <?php field_aria($errors, 'last_name'); ?>>
                        <?php field_error($errors, 'last_name'); ?>
                    </div>
                </div>

                <div class="form-field">
                    <label for="email">Email *</label>
                    <input type="email" id="email" name="email" maxlength="120" autocomplete="email"
                           value="<?= e($values['email']) ?>" required <?php field_aria($errors, 'email'); ?>>
                    <?php field_error($errors, 'email'); ?>
                    <?php if ($email_taken): ?>
                        <p class="field-help"><a href="login.php">Se connecter avec ce compte →</a></p>
                    <?php endif; ?>
                </div>

                <div class="form-field">
                    <label for="password">Mot de passe *</label>
                    <div class="password-wrap">
                        <input type="password" id="password" name="password" minlength="<?= PASSWORD_MIN ?>" maxlength="72"
                               autocomplete="new-password" required data-password-strength
                               <?php field_aria($errors, 'password'); ?>>
                        <button type="button" class="password-toggle" data-password-toggle aria-label="Afficher le mot de passe">Afficher</button>
                    </div>
                    <div class="strength-meter" data-strength-meter hidden>
                        <span class="strength-bar"><span data-strength-bar></span></span>
                        <span class="strength-label" data-strength-label></span>
                    </div>
                    <p class="field-help">Au moins <?= PASSWORD_MIN ?> caractères, avec une lettre et un chiffre.</p>
                    <?php field_error($errors, 'password'); ?>
                </div>

                <div class="form-field">
                    <label for="password-confirm">Confirmer le mot de passe *</label>
                    <div class="password-wrap">
                        <input type="password" id="password-confirm" name="password_confirm" maxlength="72"
                               autocomplete="new-password" required data-password-confirm
                               <?php field_aria($errors, 'password_confirm'); ?>>
                        <button type="button" class="password-toggle" data-password-toggle aria-label="Afficher le mot de passe">Afficher</button>
                    </div>
                    <p class="field-status" data-confirm-status aria-live="polite"></p>
                    <?php field_error($errors, 'password_confirm'); ?>
                </div>

                <label class="auth-check">
                    <input type="checkbox" name="accept_terms" value="1" <?= $values['accept_terms'] ? 'checked' : '' ?> required>
                    <span>J'accepte que mes données soient utilisées pour gérer mon compte et mes commandes (projet universitaire, aucune donnée n'est partagée).</span>
                </label>
                <?php field_error($errors, 'accept_terms'); ?>

                <button type="submit" class="button auth-submit">Créer mon compte <span aria-hidden="true">→</span></button>
                <p class="auth-required">* champs obligatoires</p>
            </form>
        </div>

    </div>
</section>

<?php require '../includes/footer.php'; ?>
