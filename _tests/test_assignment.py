"""
_tests/test_assignment.py — checks the website against the assignment PDF
==========================================================================

NOT part of the zip we hand in (the _tests folder stays out of the submission).

It acts like a visitor: it opens pages, fills in forms and reads the HTML,
exactly like a browser would (with its own cookies = its own session).

Before running:
    1. XAMPP: Apache + MySQL started
    2. database/database.sql imported (re-import it to start from clean data)

Run (in a terminal, from the project folder):
    python _tests/test_assignment.py
    python _tests/test_assignment.py http://localhost/ecommerce       <- if your folder has another name

Result: one line per check
    PASS  the requirement works
    FAIL  the requirement is NOT met (the message says why)
    WARN  works, but the assignment may expect something different
    SKIP  could not be checked (the message says why)
"""

import html
import http.cookiejar
import re
import sys
import time
import urllib.error
import urllib.parse
import urllib.request
from pathlib import Path

BASE_URL     = (sys.argv[1] if len(sys.argv) > 1 else 'http://localhost/poilu-and-co').rstrip('/')
PROJECT_ROOT = Path(__file__).resolve().parent.parent

# Products used by the tests (from database/database.sql)
TAG_ID    = 5    # Médaille ronde en laiton  (personalisation: pet name, colour, size)
COLLAR_ID = 2    # Collier cuir brodé au prénom
TAG_FORM    = {'pet_name': 'Moka', 'color': 'Laiton', 'size': 'M'}
COLLAR_FORM = {'pet_name': 'Moka', 'color': 'Caramel', 'size': 'M'}

results = []


# ============================================================
# Small tools
# ============================================================

class Visitor:
    """A browser with its own cookies (= its own PHP session)."""

    def __init__(self):
        self.cookies = http.cookiejar.CookieJar()
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.cookies))

    def get(self, path):
        with self.opener.open(BASE_URL + '/' + path.lstrip('/'), timeout=15) as response:
            return response.read().decode('utf-8'), response.geturl()

    def post(self, path, fields):
        data = urllib.parse.urlencode(fields).encode()
        with self.opener.open(BASE_URL + '/' + path.lstrip('/'), data=data, timeout=15) as response:
            return response.read().decode('utf-8'), response.geturl()

    def submit(self, path, fields):
        """Opens the page, takes its CSRF token, then sends the form (like a real user)."""
        page, _ = self.get(path)
        fields = dict(fields, csrf_token=csrf_token(page))
        return self.post(path, fields)

    # --- shortcuts for this website ---
    def register(self, username, password='Patte2026', first='Test', last='Robot', email=None):
        return self.submit('pages/register.php', {
            'username': username, 'first_name': first, 'last_name': last,
            'email': email or f'{username}@example.be',
            'password': password, 'password_confirm': password,
            'accept_terms': '1', 'website': '',
        })

    def login(self, identifier, password):
        return self.submit('pages/login.php', {'identifier': identifier, 'password': password})

    def add_to_cart(self, product_id, quantity, form):
        return self.submit(f'pages/product.php?id={product_id}', dict(form, quantity=str(quantity)))

    def cart(self):
        page, _ = self.get('pages/cart.php')
        return page, cart_lines(page)


def csrf_token(page):
    match = re.search(r'name="csrf_token" value="([^"]+)"', page)
    return match.group(1) if match else ''


def text(fragment):
    """HTML -> plain text."""
    return html.unescape(re.sub(r'<[^>]+>', ' ', fragment)).replace('\xa0', ' ')


def price(value):
    """'1 039,80 €' -> 1039.8"""
    match = re.search(r'(\d[\d  ]*,\d{2})', value)
    return float(match.group(1).replace(' ', '').replace(' ', '').replace(',', '.')) if match else None


def cart_lines(page):
    """Reads the lines of cart.php: name, unit price, quantity, line total, image."""
    lines = []
    for block in re.findall(r'<li class="cart-item">(.*?)</li>\s*(?=<li class="cart-item">|</ul>)', page, re.S):
        name  = re.search(r'<h2><a[^>]*>(.*?)</a></h2>', block, re.S)
        unit  = re.search(r'class="cart-item-unit">(.*?)</p>', block, re.S)
        qty   = re.search(r'name="quantity"[^>]*value="(\d+)"', block, re.S)
        total = re.search(r'class="cart-item-total">(.*?)</p>', block, re.S)
        lines.append({
            'name':      text(name.group(1)).strip() if name else None,
            'unit':      price(text(unit.group(1))) if unit else None,
            'quantity':  int(qty.group(1)) if qty else None,
            'total':     price(text(total.group(1))) if total else None,
            'has_image': '<img' in block or 'product-placeholder' in block,
        })
    return lines


def summary_value(page, label):
    """Amount next to a label in the cart summary ('Sous-total', 'Total')."""
    match = re.search(r'<dt>\s*' + label + r'\b.*?</dt>\s*<dd>(.*?)</dd>', page, re.S)
    return price(text(match.group(1))) if match else None


def error_messages(page):
    found = re.findall(r'class="(?:field-error|flash flash-error)[^"]*"[^>]*>(.*?)</(?:p|div)>', page, re.S)
    return [text(message).strip() for message in found]


def header_name(page):
    match = re.search(r'class="header-user-name">.*?<strong>(.*?)</strong>', page, re.S)
    return text(match.group(1)).strip() if match else None


def cart_badge(page):
    match = re.search(r'class="cart-badge">(\d+)<', page)
    return int(match.group(1)) if match else None


def check(name, condition, fail_message='', warn=False):
    status = 'PASS' if condition else ('WARN' if warn else 'FAIL')
    results.append((status, name, '' if condition else fail_message))


def skip(name, reason):
    results.append(('SKIP', name, reason))


def database_connection():
    """Optional: pymysql (installed for the Apriori part). Returns None if not available."""
    try:
        import pymysql
    except ImportError:
        return None
    db_php = (PROJECT_ROOT / 'includes' / 'db.php').read_text(encoding='utf-8')
    setting = lambda key: re.search(r'\$db_' + key + r"\s*=\s*'([^']*)'", db_php).group(1)
    try:
        return pymysql.connect(host=setting('host'), user=setting('user'), password=setting('pass'),
                               database=setting('name'), charset='utf8mb4')
    except Exception:
        return None


# ============================================================
# The checks (numbers = requirements of the PDF)
# ============================================================

def test_navigation():
    visitor = Visitor()
    for path in ['index.php', 'pages/products.php', 'pages/cart.php', 'pages/login.php', 'pages/register.php']:
        page, _ = visitor.get(path)
        check(f'Menu present on {path}', 'class="main-nav"' in page and 'products.php' in page,
              'no navigation menu found')

    page, _ = visitor.get('index.php')
    has_about_page = (PROJECT_ROOT / 'pages' / 'about.php').exists()
    check('"About" information page (section 5)', has_about_page,
          'no pages/about.php: "À propos" is only a section of the homepage (index.php#about). '
          'The PDF lists "About" as one of the main sections.', warn=True)


def test_registration():
    unique = 'test_' + str(int(time.time()))
    visitor = Visitor()

    # Mandatory fields
    page, _ = visitor.submit('pages/register.php', {'username': '', 'first_name': '', 'last_name': '',
                                                    'email': '', 'password': '', 'password_confirm': ''})
    errors = ' '.join(error_messages(page))
    check('Register: empty username/password/name are refused (mandatory fields)',
          'utilisateur' in errors and 'prénom' in errors and 'mot de passe' in errors.lower(),
          f'errors shown: {errors[:150]}')

    # New username -> registration confirmed
    page, url = visitor.register(unique, first='Test', last='Robot')
    check('Register: a new username is accepted and confirmed',
          'register.php' not in url and 'Inscription confirmée' in page,
          f'still on {url}; errors: {error_messages(page)}')
    check('Register: the new user sees forename + last name after registering',
          header_name(page) == 'Test Robot', f'header shows: {header_name(page)}')

    # Same username again -> refused
    other = Visitor()
    page, url = other.register(unique, email=f'other_{unique}@example.be')
    check('Register: the SAME username twice is refused with a warning',
          'register.php' in url and 'déjà utilisé' in page,
          f'url {url}, errors {error_messages(page)}')

    # Password stored hashed (needs pymysql)
    connection = database_connection()
    if connection is None:
        skip('Register: password stored encrypted in the database',
             'pip install pymysql to check it (the Apriori part installs it)')
    else:
        with connection.cursor() as cursor:
            cursor.execute('SELECT password_hash FROM users WHERE username = %s', (unique,))
            row = cursor.fetchone()
        connection.close()
        stored = row[0] if row else ''
        check('Register: password stored encrypted in the database (not in clear)',
              stored and stored != 'Patte2026' and 'Patte2026' not in stored and len(stored) >= 60,
              f'stored value: {stored[:20]}...')
    return unique


def test_login(username):
    visitor = Visitor()
    page, url = visitor.login(username, 'WrongPassword1')
    check('Login: wrong password -> error message, not logged in',
          'incorrect' in page and header_name(page) is None, f'errors: {error_messages(page)}')

    page, url = Visitor().login('nobody_' + username, 'Patte2026')
    check('Login: unknown username -> error message, not logged in',
          'incorrect' in page and header_name(page) is None, f'errors: {error_messages(page)}')

    visitor = Visitor()
    page, url = visitor.login(username, 'Patte2026')
    check('Login: correct username + password -> logged in', header_name(page) == 'Test Robot',
          f'header shows: {header_name(page)}')

    pages_ok = []
    for path in ['index.php', 'pages/products.php', f'pages/product.php?id={TAG_ID}', 'pages/cart.php']:
        page, _ = visitor.get(path)
        pages_ok.append(header_name(page) == 'Test Robot')
    check('Login: forename + last name visible on all other pages', all(pages_ok),
          'name missing on at least one page')
    return visitor


def test_basket(visitor):
    # Quantity 0 and negative
    for quantity in (0, -3):
        page, _ = visitor.add_to_cart(TAG_ID, quantity, TAG_FORM)
        _, lines = visitor.cart()
        check(f'Basket: quantity {quantity} -> error message, product NOT added',
              len(lines) == 0 and any('Quantité' in message for message in error_messages(page)),
              f'cart lines: {len(lines)}, errors: {error_messages(page)}')

    # Same product twice -> aggregated quantity
    visitor.add_to_cart(TAG_ID, 2, TAG_FORM)
    visitor.add_to_cart(TAG_ID, 3, TAG_FORM)
    page, lines = visitor.cart()
    check('Basket: same product added twice (2 then 3) -> ONE line with quantity 5',
          len(lines) == 1 and lines[0]['quantity'] == 5, f'cart lines: {lines}')

    # The teacher's exact example: 10, then 3 more -> 13
    _clear_cart(visitor)
    visitor.add_to_cart(TAG_ID, 10, TAG_FORM)
    page, _ = visitor.add_to_cart(TAG_ID, 3, TAG_FORM)
    _, lines = visitor.cart()
    quantity = lines[0]['quantity'] if lines else None
    check('Basket: teacher example "quantity 10, then 3 more" -> 13 (or a clear error)',
          quantity == 13 or (quantity == 10 and error_messages(page)),
          f'quantity in the cart: {quantity}, no error shown. add_to_cart() in functions.php '
          f'caps the quantity at 10 silently (min(..., 10)).')

    # Quantity > stock
    _clear_cart(visitor)
    test_stock(visitor)

    # Totals: per line and overall (requirements 5 and 6)
    _clear_cart(visitor)
    visitor.add_to_cart(TAG_ID, 2, TAG_FORM)
    visitor.add_to_cart(COLLAR_ID, 1, COLLAR_FORM)
    page, lines = visitor.cart()
    lines_ok = all(line['unit'] and abs(line['unit'] * line['quantity'] - line['total']) < 0.001 for line in lines)
    check('Basket: price per line = quantity x unit price', len(lines) == 2 and lines_ok, f'lines: {lines}')
    expected = round(sum(line['total'] for line in lines), 2) if lines else None
    check('Basket: sub-total = sum of the lines', summary_value(page, 'Sous-total') == expected,
          f'sub-total shown {summary_value(page, "Sous-total")}, expected {expected}')
    shipping = 0 if expected and expected >= 50 else 4.95
    check('Basket: TOTAL shown (sub-total + shipping)',
          summary_value(page, 'Total') is not None and abs(summary_value(page, 'Total') - (expected + shipping)) < 0.001,
          f'total shown {summary_value(page, "Total")}, expected {expected} + {shipping}')

    # Basket validation page: image, name, unit price, quantity, sub-total, total, button
    has_all = all(line['has_image'] and line['name'] and line['unit'] and line['quantity'] for line in lines)
    check('Basket page: image, name, unit price, quantity for each product', has_all, f'lines: {lines}')
    check('Basket page: validation button to the payment page', 'href="checkout.php"' in page,
          'no link/button to checkout.php')

    # Basket visible on the other pages
    count = sum(line['quantity'] for line in lines)
    badges = [cart_badge(visitor.get(path)[0]) for path in ['index.php', 'pages/products.php', 'pages/login.php']]
    check('Basket visible on every page (badge with the number of products)', badges == [count] * 3,
          f'badges {badges}, expected {count}')
    _clear_cart(visitor)


def test_stock(visitor):
    sql = (PROJECT_ROOT / 'database' / 'database.sql').read_text(encoding='utf-8')
    products_table = sql[sql.index('CREATE TABLE products'):]
    products_table = products_table[:products_table.index(') ENGINE')]
    stock_column = re.search(r'^\s*(\w*(?:stock|quantity)\w*)\s', products_table, re.M | re.I)

    if not stock_column:
        check('Basket: quantity bigger than the stock -> error, product NOT added', False,
              'the products table has no stock column (the PDF asks for "quantity in stock")')
        return

    connection = database_connection()
    if connection is None:
        skip('Basket: quantity bigger than the stock', 'pip install pymysql to read the stock')
        return
    with connection.cursor() as cursor:
        cursor.execute(f'SELECT {stock_column.group(1)} FROM products WHERE id = %s', (TAG_ID,))
        stock = int(cursor.fetchone()[0])
    connection.close()
    page, _ = visitor.add_to_cart(TAG_ID, stock + 1, TAG_FORM)
    _, lines = visitor.cart()
    check(f'Basket: quantity bigger than the stock ({stock + 1} > {stock}) -> error, NOT added',
          len(lines) == 0 and error_messages(page), f'cart lines {lines}, errors {error_messages(page)}')


def test_guest_basket():
    guest = Visitor()
    guest.add_to_cart(TAG_ID, 1, TAG_FORM)
    _, lines = guest.cart()
    check('Basket: only a LOGGED user can add products', len(lines) == 0,
          'a visitor who is not logged in can fill the basket (the PDF says "A logged user can add '
          'products"). Guest checkout may be a deliberate choice: be ready to explain it.', warn=True)


def test_search():
    visitor = Visitor()
    page, _ = visitor.get('pages/products.php?search=' + urllib.parse.quote('médaille'))
    names = [text(name).strip() for name in re.findall(r'<article class="product-card.*?<h3><a[^>]*>(.*?)</a>', page, re.S)]

    # products.php searches the name AND the description: read both from database.sql
    sql = (PROJECT_ROOT / 'database' / 'database.sql').read_text(encoding='utf-8')
    catalogue = {name.replace("''", "'"): description.replace("''", "'") for name, description in
                 re.findall(r"^\(\d+,\s*\d+,\s*'((?:[^']|'')*)',\s*'((?:[^']|'')*)',\s*[\d.]+,", sql, re.M)}
    matching = {name for name, description in catalogue.items() if 'médaille' in (name + ' ' + description).lower()}
    check('Search "médaille" -> list of the matching products (name or description)',
          len(names) > 0 and set(names) == matching,
          f'shown: {sorted(names)}\n         expected: {sorted(matching)}')

    page, _ = visitor.get('pages/products.php?search=zzzxqw')
    names = re.findall(r'<article class="product-card', page)
    check('Search with an unknown word -> no product, message shown', len(names) == 0 and 'empty-state' in page,
          f'{len(names)} products shown')


def test_apriori():
    rules_file = PROJECT_ROOT / 'analytics' / 'output' / 'apriori_rules.json'
    check('Apriori: analytics/output/apriori_rules.json exists', rules_file.exists(),
          'run:  python analytics/apriori_analysis.py')
    page, _ = Visitor().get(f'pages/product.php?id={COLLAR_ID}')
    check('Apriori: "Fréquemment achetés ensemble" shown on a product page', 'achetés ensemble' in page,
          'block not found on product.php?id=2')

    # Recommender system (lecture 1.7.2): recommendations for a customer on the account page
    visitor = Visitor()
    visitor.login('zoe.renard', 'Client123!')
    page, _ = visitor.get('pages/account.php')
    suggestions = re.findall(r'class="together-score">', page)
    check('Recommender: "Recommandé pour vous" on the account of a customer with orders (zoe.renard)',
          'Recommandé' in page and len(suggestions) > 0 and 'Vous avez acheté' in page,
          'no recommendation on account.php (run analytics/apriori_analysis.py, re-import database.sql)')

    visitor = Visitor()
    visitor.login('client', 'Client123!')
    page, _ = visitor.get('pages/account.php')
    check('Recommender: new customer without orders -> message instead of recommendations (cold start)',
          'après votre première commande' in page and 'class="together-score"' not in page,
          'expected the cold-start message on the account of "client"')


def _clear_cart(visitor):
    page, _ = visitor.get('pages/cart.php')
    for key in dict.fromkeys(re.findall(r'name="item_key" value="([^"]+)"', page)):
        visitor.post('pages/cart.php', {'csrf_token': csrf_token(page), 'action': 'remove', 'item_key': key})


# ============================================================

def main():
    print(f'Testing {BASE_URL}\n')
    try:
        Visitor().get('index.php')
    except (urllib.error.URLError, OSError) as error:
        print(f'Cannot open {BASE_URL}/index.php ({error}).\nIs Apache started in XAMPP? Is the folder name right?')
        sys.exit(2)

    test_navigation()
    username = test_registration()
    visitor = test_login(username)
    test_basket(visitor)
    test_guest_basket()
    test_search()
    test_apriori()

    width = max(len(name) for _, name, _ in results)
    for status, name, message in results:
        print(f'{status}  {name.ljust(width)}' + (f'\n      -> {message}' if message else ''))

    counts = {status: sum(1 for result in results if result[0] == status) for status in ('PASS', 'FAIL', 'WARN', 'SKIP')}
    print(f"\n{counts['PASS']} passed, {counts['FAIL']} failed, {counts['WARN']} warnings, {counts['SKIP']} skipped")
    sys.exit(1 if counts['FAIL'] else 0)


if __name__ == '__main__':
    main()
