<?php


// Start the PHP session once (needed for the cart, login and messages).
// The options make the session cookie harder to steal or abuse:
//   httponly  -> JavaScript cannot read the cookie (limits XSS damage)
//   samesite  -> the cookie is not sent by forms posted from other sites (CSRF)
//   secure    -> cookie only sent over HTTPS (turned on automatically when the site uses HTTPS)
//   use_strict_mode -> PHP refuses session ids it did not create itself (session fixation)
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'lifetime' => 0,                                   // cookie deleted when the browser closes
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

// ---------- Store settings (used by cart and checkout later) ----------
const SHIPPING_COST         = 4.95;  // EUR, delivery anywhere in Belgium
const FREE_SHIPPING_FROM    = 50.00; // EUR, free delivery from this amount
const DELIVERY_WORKING_DAYS = 5;


// ---------- Output safety ----------

// Escape text before printing it in HTML (protects against XSS).
// Usage: echo e($product['name']);
function e($text)
{
    return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
}

// Format a price the Belgian/French way: 34.9 -> "34,90 €"
function format_price($amount)
{
    return number_format((float) $amount, 2, ',', ' ') . ' €';
}


// ---------- Product helpers ----------

// Text shown for the `animal` column of a product.
function animal_label($animal)
{
    $labels = ['dog' => 'Chien', 'cat' => 'Chat', 'both' => 'Chien & chat'];
    return $labels[$animal] ?? '';
}

// Small label on the product photo.
// A product on sale shows its discount ("-15 %"), otherwise its badge
// from the database ("Nouveau", "Best-seller"), otherwise nothing.
function product_badge($product)
{
    if (!empty($product['old_price']) && $product['old_price'] > $product['price']) {
        $discount = round((1 - $product['price'] / $product['old_price']) * 100);
        return '-' . $discount . ' %';
    }
    return $product['badge'] ?? '';
}


// ---------- Dates ----------

// Adds working days (Monday to Friday) to a date.
// Used for the estimated delivery date: add_working_days(date('Y-m-d'), 5)
function add_working_days($start_date, $days)
{
    $date = new DateTime($start_date);
    while ($days > 0) {
        $date->modify('+1 day');
        if ($date->format('N') <= 5) {   // N: 1 = Monday ... 7 = Sunday
            $days--;
        }
    }
    return $date;
}

// Shows a date in French: "lundi 16 novembre"
function format_date_fr($date)
{
    $days   = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];
    $months = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet',
               'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    return $days[$date->format('N') - 1] . ' ' . $date->format('j') . ' ' . $months[$date->format('n') - 1];
}


// ---------- CSRF protection for POST forms ----------

// Returns the CSRF token of this session (creates it the first time).
// Put it in every POST form as a hidden field.
function csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Checks the token sent by a form. Returns true or false.
function check_csrf_token($sent_token)
{
    return isset($_SESSION['csrf_token'])
        && is_string($sent_token)
        && hash_equals($_SESSION['csrf_token'], $sent_token);
}


// ---------- Flash messages (shown once, after a redirect) ----------

// Save a message: set_flash('success', 'Merci !');
function set_flash($type, $message)
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

// Read the message and delete it, so it appears only once.
function get_flash()
{
    if (empty($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}


// ---------- Login / logout notice ----------
// A small message shown by header.php at the top of the NEXT page
// ("Bonjour Camille, vous êtes connectée", "Vous êtes déconnecté(e)").
// Separate from set_flash(), because the homepage shows its flash
// messages inside the newsletter block.
function set_auth_notice($message)
{
    $_SESSION['auth_notice'] = $message;
}

function get_auth_notice()
{
    $notice = $_SESSION['auth_notice'] ?? null;
    unset($_SESSION['auth_notice']);
    return $notice;
}


// ---------- Login helpers ----------
// After a successful login (login.php / register.php), the session contains:
//   $_SESSION['user_id']          id of the row in `users`
//   $_SESSION['user_username']    username
//   $_SESSION['user_first_name']  forename  } shown in the header
//   $_SESSION['user_last_name']   last name } on every page
//   $_SESSION['user_role']        'customer' or 'admin' (display only:
//                                 admin pages re-check it in the database)

function is_logged_in()
{
    return isset($_SESSION['user_id']);
}

// "Camille Dupont" for the header, or '' when nobody is logged in.
function current_user_name()
{
    if (!is_logged_in()) {
        return '';
    }
    return trim(($_SESSION['user_first_name'] ?? '') . ' ' . ($_SESSION['user_last_name'] ?? ''));
}

// Logs a user in. $user is a row of the `users` table.
function log_user_in($user)
{
    // New session id at login: an id stolen or planted BEFORE login
    // becomes useless (protection against session fixation).
    session_regenerate_id(true);

    $_SESSION['user_id']         = (int) $user['id'];
    $_SESSION['user_username']   = $user['username'];
    $_SESSION['user_first_name'] = $user['first_name'];
    $_SESSION['user_last_name']  = $user['last_name'];
    $_SESSION['user_role']       = $user['role'];

    // New CSRF token too, so a token seen before login cannot be reused.
    unset($_SESSION['csrf_token']);
}

// Logs the user out but KEEPS the cart, so the basket is not lost.
function log_user_out()
{
    $cart = $_SESSION['cart'] ?? [];

    $_SESSION = [];
    session_regenerate_id(true);

    if (!empty($cart)) {
        $_SESSION['cart'] = $cart;
    }
}

// Put at the top of a page reserved to logged-in customers:
// require_login();   -> visitors are sent to login.php, then come back here.
function require_login()
{
    if (!is_logged_in()) {
        set_flash('error', 'Connectez-vous pour accéder à cette page.');
        $page = basename($_SERVER['SCRIPT_NAME']);
        header('Location: login.php?redirect=' . urlencode($page));
        exit;
    }
}

// Where to go after login. Only a page of the pages/ folder is accepted
// ("checkout.php", "account.php"...): a link like
// login.php?redirect=https://evil.example cannot send people to another site.
function safe_redirect_target($target, $default)
{
    if (is_string($target) && preg_match('/^[a-z0-9-]+\.php$/', $target)
        && $target !== 'login.php' && $target !== 'register.php'
        && file_exists(__DIR__ . '/../pages/' . $target)) {
        return $target;
    }
    return $default;
}

// IP address of the visitor (used to limit login attempts).
function client_ip()
{
    return substr($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', 0, 45);
}

// Put at the top of every admin page: require_admin($pdo);
// The role is read from the DATABASE on each page, so an account that
// loses its admin role is blocked immediately.
// Only needs $_SESSION['user_id'] (set by login.php).
function require_admin($pdo)
{
    $is_admin = false;

    if ($pdo !== null && is_logged_in()) {
        $statement = $pdo->prepare('SELECT role FROM users WHERE id = :id');
        $statement->execute(['id' => $_SESSION['user_id']]);
        $is_admin = ($statement->fetchColumn() === 'admin');
    }

    if (!$is_admin) {
        set_flash('error', 'Cette page est réservée aux administrateurs. Connectez-vous avec un compte admin.');
        header('Location: login.php');
        exit;
    }
}


// ---------- Orders ----------

// All the statuses of an order, in their normal order, with French labels.
// (Production and delivery are simulated.)
function order_statuses()
{
    return [
        'pending'       => 'En attente',
        'paid'          => 'Payée',
        'in_production' => 'En production',
        'shipped'       => 'Expédiée',
        'delivered'     => 'Livrée',
        'cancelled'     => 'Annulée',
    ];
}

function order_status_label($status)
{
    return order_statuses()[$status] ?? $status;
}

function payment_method_label($method)
{
    $labels = ['bancontact' => 'Bancontact', 'card' => 'Carte de crédit', 'paypal' => 'PayPal'];
    return $labels[$method] ?? $method;
}

// Order number shown to people: 12 -> "00012"
function order_number($order_id)
{
    return str_pad($order_id, 5, '0', STR_PAD_LEFT);
}


// ---------- Cart helpers (completed in step 5) ----------

// Total number of items in the session cart (for the badge in the header).
function cart_count()
{
    $count = 0;
    if (!empty($_SESSION['cart'])) {
        foreach ($_SESSION['cart'] as $item) {
            $count += (int) $item['quantity'];
        }
    }
    return $count;
}

// Total price of the cart, without shipping.
function cart_subtotal()
{
    $subtotal = 0;
    if (!empty($_SESSION['cart'])) {
        foreach ($_SESSION['cart'] as $item) {
            $subtotal += $item['price'] * $item['quantity'];
        }
    }
    return $subtotal;
}

// Shipping cost for a given subtotal: free from FREE_SHIPPING_FROM,
// nothing to pay for an empty cart. Used by cart.php and checkout.php.
function calculate_shipping($subtotal)
{
    if ($subtotal <= 0 || $subtotal >= FREE_SHIPPING_FROM) {
        return 0;
    }
    return SHIPPING_COST;
}

// Adds a product to the session cart.
// $personalization is an array like ['pet_name' => 'Moka', 'color' => 'Laiton', ...]
//
// Structure of one cart item (agreed for cart.php and checkout.php):
// $_SESSION['cart'][$item_key] = [
//     'product_id'      => 3,
//     'name'            => 'Gamelle céramique prénom',
//     'price'           => 29.90,          // read from the database, never from the form
//     'category_slug'   => 'gamelles',
//     'image'           => 'gamelle-ceramique.jpg',
//     'quantity'        => 1,
//     'personalization' => [ 'pet_name' => 'Moka', 'color' => 'Sauge', ... ],
// ]
function add_to_cart($product, $quantity, $personalization)
{
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    // Same product + same personalisation = same line: we only add the quantity.
    $item_key = md5($product['id'] . json_encode($personalization));

    if (isset($_SESSION['cart'][$item_key])) {
        $new_quantity = $_SESSION['cart'][$item_key]['quantity'] + $quantity;
        $_SESSION['cart'][$item_key]['quantity'] = min($new_quantity, 10);
    } else {
        $_SESSION['cart'][$item_key] = [
            'product_id'      => (int) $product['id'],
            'name'            => $product['name'],
            'price'           => (float) $product['price'],
            'category_slug'   => $product['category_slug'],
            'image'           => $product['image'],
            'quantity'        => $quantity,
            'personalization' => $personalization,
        ];
    }
}


// ---------- Personalisation ----------

// What the customer can personalise, for each personalization_type
// of the products table. Used by product.php (form + preview)
// and later by cart.php / checkout.php to show the choices.
function personalization_options($type)
{
    $options = [
        'tag' => [
            'fields' => ['pet_name', 'phone', 'color', 'size'],
            'colors' => ['Laiton' => '#D9AE6C', 'Argent' => '#C9CDD2', 'Or rose' => '#E5B5A1'],
            'sizes'  => ['S' => 'S · 25 mm', 'M' => 'M · 32 mm'],
        ],
        'collar' => [
            'fields' => ['pet_name', 'color', 'size'],
            'colors' => ['Caramel' => '#B9814F', 'Rose poudré' => '#E3AFA3', 'Sauge' => '#A3B894', 'Bleu ciel' => '#9FBAD0', 'Crème' => '#E9DCC6'],
            'sizes'  => ['S' => 'S · 25–35 cm', 'M' => 'M · 35–45 cm', 'L' => 'L · 45–55 cm'],
        ],
        'bowl' => [
            'fields' => ['pet_name', 'color', 'size'],
            'colors' => ['Crème' => '#F1E7D6', 'Sauge' => '#C9D6BF', 'Rose' => '#F0CEC3', 'Bleu' => '#CBDBE7', 'Terracotta' => '#D99B7B'],
            'sizes'  => ['S' => 'S · 0,4 L', 'M' => 'M · 0,9 L'],
        ],
        'blanket' => [
            'fields' => ['pet_name', 'photo', 'color'],
            'colors' => ['Crème' => '#EFE5D6', 'Gris perle' => '#D8D5D1', 'Rose' => '#EFCFC6', 'Sauge' => '#CBD7C2'],
            'sizes'  => [],
        ],
        'mug' => [
            'fields' => ['pet_name', 'message', 'photo'],
            'colors' => [],
            'sizes'  => [],
        ],
        'gift' => [
            'fields' => ['pet_name', 'message'],
            'colors' => [],
            'sizes'  => [],
        ],
    ];

    return $options[$type] ?? $options['gift'];
}

// French label of each personalisation field (cart and orders reuse it).
function personalization_label($field)
{
    $labels = [
        'pet_name' => 'Prénom',
        'phone'    => 'Téléphone',
        'color'    => 'Couleur',
        'size'     => 'Taille',
        'message'  => 'Message',
        'photo'    => 'Photo',
    ];
    return $labels[$field] ?? $field;
}


// ---------- Image upload ----------

// Checks an uploaded image and saves it with a random name.
// $file is one entry of $_FILES, e.g. $_FILES['photo'].
// Returns ['file_name' => 'a3f9...c2.jpg', 'error' => null]
//      or ['file_name' => null, 'error' => 'message for the user']
// When no file was sent, both are null.
function save_uploaded_image($file, $upload_folder)
{
    $max_size      = 5 * 1024 * 1024;                     // 5 MB
    $allowed_types = [                                    // real MIME type => extension
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['file_name' => null, 'error' => null];
    }
    if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE || $file['size'] > $max_size) {
        return ['file_name' => null, 'error' => 'La photo est trop lourde (5 Mo maximum).'];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['file_name' => null, 'error' => 'Le téléchargement de la photo a échoué. Réessayez.'];
    }

    // 1. The extension of the original name must be an image extension.
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
        return ['file_name' => null, 'error' => 'Format accepté : JPG, PNG ou WEBP.'];
    }

    // 2. The REAL content of the file must be an image
    //    (a renamed .php file would fail here).
    $finfo     = new finfo(FILEINFO_MIME_TYPE);
    $real_type = $finfo->file($file['tmp_name']);
    if (!isset($allowed_types[$real_type]) || getimagesize($file['tmp_name']) === false) {
        return ['file_name' => null, 'error' => 'Ce fichier n\'est pas une image valide.'];
    }

    // 3. Random name: impossible to guess, and the original name is never used.
    $file_name = bin2hex(random_bytes(16)) . '.' . $allowed_types[$real_type];

    if (!move_uploaded_file($file['tmp_name'], $upload_folder . $file_name)) {
        return ['file_name' => null, 'error' => 'Impossible d\'enregistrer la photo.'];
    }

    return ['file_name' => $file_name, 'error' => null];
}


// ---------- Icons ----------

// Returns a simple line icon (inline SVG) for a category.
// Used on the homepage and as a placeholder when a product has no photo yet.
function category_icon($slug)
{
    $icons = [
        'medailles' => '<circle cx="24" cy="28" r="13"/><circle cx="24" cy="10" r="3.5"/><path d="M24 13.5V15"/><path d="M18 26h12M20 31h8"/>',
        'colliers'  => '<ellipse cx="24" cy="20" rx="17" ry="8"/><path d="M7 20v4c0 4.4 7.6 8 17 8s17-3.6 17-8v-4"/><rect x="19" y="26" width="10" height="7" rx="1.5"/><circle cx="24" cy="40" r="4"/><path d="M24 33v3"/>',
        'gamelles'  => '<path d="M5 24h38l-4.5 12.5A4 4 0 0 1 34.7 39H13.3a4 4 0 0 1-3.8-2.5z"/><path d="M14 24c0-5.5 4.5-9 10-9s10 3.5 10 9"/><path d="M20 31h8"/>',
        'plaids'    => '<rect x="7" y="9" width="34" height="30" rx="5"/><circle cx="20" cy="20" r="2.5"/><circle cx="28" cy="20" r="2.5"/><circle cx="17" cy="26" r="2"/><circle cx="31" cy="26" r="2"/><path d="M20 31c1.3-2.6 6.7-2.6 8 0"/>',
        'mugs'      => '<path d="M9 15h23v17a6 6 0 0 1-6 6H15a6 6 0 0 1-6-6z"/><path d="M32 19h3a5 5 0 0 1 0 10h-3"/><path d="M16 6c-1.5 2 1.5 3 0 5M23 6c-1.5 2 1.5 3 0 5"/>',
        'coffrets'  => '<rect x="8" y="20" width="32" height="20" rx="2"/><rect x="6" y="14" width="36" height="7" rx="2"/><path d="M24 14v26"/><path d="M24 14c-3-6-10-7-10-2.5S20 14 24 14c4 0 10-.5 10-4.5S27 8 24 14"/>',
        'paw'       => '<ellipse cx="24" cy="31" rx="9" ry="7.5"/><ellipse cx="13" cy="20" rx="3.6" ry="4.6"/><ellipse cx="20" cy="12.5" rx="3.6" ry="4.8"/><ellipse cx="28" cy="12.5" rx="3.6" ry="4.8"/><ellipse cx="35" cy="20" rx="3.6" ry="4.6"/>',
    ];

    $paths = $icons[$slug] ?? $icons['paw'];

    return '<svg class="icon" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2" '
         . 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths . '</svg>';
}