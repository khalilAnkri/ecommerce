<?php


// Start the PHP session once (needed for the cart, login and messages).
if (session_status() === PHP_SESSION_NONE) {
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


// ---------- Login helpers (completed in step 6) ----------

function is_logged_in()
{
    return isset($_SESSION['user_id']);
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
