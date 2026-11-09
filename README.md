# Poilu & Co

A Belgian online store for **personalised dog and cat accessories**: engraved ID tags, collars, bowls, portrait blankets, cushions, mugs and adoption gift sets.

This is a **university e-commerce project**. It is a learning project: payment, production (print-on-demand / engraving) and delivery are **simulated**. No real external service is connected.

---

## 1. Project goals

- Target customers: urban 25–40-year-olds who consider their pets family members.
- Customers can personalise a product (pet name, phone number, colour, size, photo, message) and see a **live preview** before ordering.
- Orders are delivered in Belgium within **5 working days** (promise shown on the site, simulated in the project).
- Currency: **EUR**. Prices between about €19 and €59, VAT included.
- Language: **French** first. All texts are organised so that **Dutch** can be added later.

---

## 2. Technologies

| Layer | Technology |
|---|---|
| Back end | Native procedural PHP (no framework) |
| Database | MySQL with PDO (prepared statements) |
| Front end | HTML5, CSS3, vanilla JavaScript |
| Local server | XAMPP or WAMP |
| Data analysis | Python 3 (pandas, scikit-learn, mlxtend) for Apriori and clustering, run separately from the website |

**Not used on purpose:** Laravel, Symfony, React, Vue, Angular, Node.js, Bootstrap, Tailwind, MVC, repositories, service layers.

---

## 3. Project structure

```
poilu-and-co/
├── index.php                  Homepage (only page in the root)
├── pages/
│   ├── products.php           Catalogue: search, filters, sorting
│   ├── product.php            Product details + personalisation + live preview
│   ├── cart.php               Cart (PHP session)
│   ├── checkout.php           Checkout + simulated payment + confirmation
│   ├── login.php              Customer / admin login (username or email)
│   ├── register.php           Registration (+ live username check)
│   ├── logout.php             Logout (POST + CSRF only)
│   ├── account.php            Profile and order history
│   ├── admin.php              Admin dashboard and basic statistics
│   ├── manage-products.php    Admin: products and categories
│   └── manage-orders.php      Admin: orders
├── includes/
│   ├── db.php                 PDO connection
│   ├── header.php             Shared header and navigation
│   ├── footer.php             Shared footer
│   └── functions.php          Shared helper functions
├── assets/
│   ├── css/style.css          The one main stylesheet
│   ├── js/script.js           The one main JavaScript file
│   └── images/
│       ├── products/
│       ├── banners/
│       └── logo/
├── database/
│   └── database.sql           Schema + demo data
├── analytics/                 Python data analysis (not part of the website)
│   ├── requirements.txt       Python dependencies
│   ├── apriori_analysis.py    Market-basket analysis (Apriori)
│   ├── clustering.py          Customer segmentation (K-Means)
│   └── output/                Result files (JSON/CSV) read by PHP
└── README.md
```

Rules for the structure:

- Only `index.php` is a website page in the root. Every other page lives in `pages/`.
- All CSS, JS, images, shared PHP files and SQL files stay in their own folders.
- `analytics/` is the only extra folder, and it holds Python files only. No PHP page is placed in it.
- Do not create other extra folders without agreement from the team.

---

## 4. How the files work together

- Every page starts with `require_once` of `includes/db.php` and `includes/functions.php`, then includes `includes/header.php`, and ends with `includes/footer.php`.
- `includes/db.php` creates one PDO connection in the variable `$pdo`.
- `includes/functions.php` holds the shared helpers (escaping output, CSRF tokens, login checks, price formatting, cart helpers, upload validation).
- `header.php` links `assets/css/style.css` and `footer.php` loads `assets/js/script.js`.
- Pages in `pages/` reach shared files with `../includes/...` and assets with `../assets/...`. `index.php` uses `includes/...` and `assets/...`.

---

## 5. Database (7 tables)

| Table | Purpose |
|---|---|
| `users` | Customers and admins (unique username, role column, hashed password) |
| `login_attempts` | Failed logins, used to block password guessing (5 tries / 15 min) |
| `categories` | Product categories |
| `products` | Catalogue items (price, stock, image, category) |
| `orders` | One row per order (customer, address, shipping, total, status) |
| `order_items` | Products inside an order (quantity, unit price) |
| `personalizations` | Personalisation details linked to an order item |

Main relationships:

- a category has many products
- a user has many orders
- an order has many order items
- a product appears in many order items
- an order item has one personalisation (when the product is personalised)

The cart is **not** stored in the database. It lives in `$_SESSION['cart']` until checkout.

The full schema and demo data are in `database/database.sql`.

### 5.1 Data analysis with Python (Apriori and clustering)

Two analyses use the order data created by the website:

| Analysis | Input | Result | Use on the website |
|---|---|---|---|
| **Apriori** (association rules) | `orders` + `order_items`: one basket = the products of one order | Rules such as "ID tag → collar" with support, confidence and lift | "Frequently bought together" block on `product.php`, bundle ideas for gift sets |
| **Clustering** (K-Means) | `users` + `orders`: recency, frequency, monetary value, average basket | Customer segments (for example occasional gift buyers, loyal owners) | Segment statistics on the admin dashboard |

How data flows:

1. The Python scripts read the MySQL data (or a CSV export of it).
2. They write result files (JSON or CSV) into `analytics/output/`.
3. PHP only **reads** these files to display the results. **PHP never runs Python.**

This keeps the website simple and safe, and the website works even if the analysis has not been run.

The demo data in `database/database.sql` must contain enough realistic orders (about 150–200 orders and 30–50 customers, with deliberate purchase patterns), otherwise the analyses give empty or meaningless results.

---

## 6. Installation (local)

1. Install **XAMPP** or **WAMP** and start **Apache** and **MySQL**.
2. Copy the `poilu-and-co` folder into:
   - XAMPP: `C:\xampp\htdocs\`
   - WAMP: `C:\wamp64\www\`
3. Open **phpMyAdmin** (`http://localhost/phpmyadmin`).
4. Import `database/database.sql` (it creates the database and tables).
5. Check the database settings in `includes/db.php` (host, database name, user, password). The XAMPP default is user `root` with an empty password.
6. Open `http://localhost/poilu-and-co/` in your browser.

Demo accounts (log in with the username **or** the email):

| Role | Username | Email | Password |
|---|---|---|---|
| Admin | `admin` | admin@poilu-and-co.be | `Admin123!` |
| Customer | `client` | client@poilu-and-co.be | `Client123!` |

The 40 generated customers use the first part of their email as username (e.g. `margaux.lambert`) and the password `Client123!`.

### Security of the accounts

- Passwords are stored with `password_hash()` (bcrypt, random salt, slow on purpose) and checked with `password_verify()`. The clear password is never stored.
- Every form has a CSRF token; the session cookie is `HttpOnly` + `SameSite=Lax`; the session id is regenerated at login (session fixation).
- All SQL queries use PDO prepared statements (SQL injection); all output goes through `e()` (XSS).
- Login shows one generic error ("username or password incorrect") and is blocked for 15 minutes after 5 failed attempts.
- `login.php?redirect=` only accepts a page of `pages/` (no open redirect). Logout only works with a POST form.

### Python analysis (optional, separate from the website)

1. Install Python 3.10 or newer.
2. In a terminal, go to the `analytics/` folder and install the dependencies: `pip install -r requirements.txt`
3. Make sure the database from the previous steps is imported and MySQL is running.
4. Run `python apriori_analysis.py`, then `python clustering.py`.
5. The results appear in `analytics/output/`.

---

## 7. Features

1. **Homepage:** hero, introduction, featured products, categories, how it works, testimonials, newsletter, footer.
2. **Catalogue:** product cards, categories, search, filtering, sorting, product details.
3. **Personalisation:** pet name, phone number, colour, size, photo upload, message, live JavaScript preview (ID tags, collars, bowls, blankets, mugs).
4. **Cart:** add, update quantity, remove, show personalisation details, totals, kept in the PHP session.
5. **Checkout:** customer information, Belgian address, postal code, city, shipping costs, order summary, confirmation. **Payment is simulated.**
6. **Accounts:** registration, login, logout, hashed passwords, profile, order history.
7. **Admin:** login, dashboard, product and category management, order management, basic sales statistics.
8. **Extra pages:** About, Contact, FAQ. Responsive design, SEO basics, form validation.
9. **Data analysis (Python):** Apriori association rules for "frequently bought together" suggestions and K-Means customer segmentation, with results displayed in the website (product page and admin dashboard).

---


## 8. Credits

Poilu & Co is a student project. Product photos and texts are placeholders for educational use.