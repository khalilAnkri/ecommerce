# _tests — checks against the assignment

This folder is **not part of the submission zip** (see "Making the zip" at the end).

| File | What it checks | Needs XAMPP? |
|---|---|---|
| `test_assignment.py` | The website, like a visitor: register, login, basket, totals, search, Apriori blocks (product + account page) | Yes (Apache + MySQL) |
| `test_apriori.py` | The Python part against the lectures: ARM Figures 1–3, candidate-gen, rule generation, apyori = lecture pseudo-code; Recommender Systems 1.7.2 (Alice → Item5) and 1.10 (precision/recall) | No |

## Run the tests

From the project folder (`C:\xampp\htdocs\poilu-and-co`), in a terminal:

```bash
pip install -r requirements.txt
```

```bash
python -m unittest _tests/test_apriori.py -v
```

Re-import `database/database.sql` in phpMyAdmin first (clean data), then:

```bash
python _tests/test_assignment.py
```

If your folder in htdocs has another name: `python _tests/test_assignment.py http://localhost/ecommerce`

Each line says **PASS**, **FAIL** (requirement not met, with the reason), **WARN** (works, but the teacher may expect something else) or **SKIP**.
The test creates a new user `test_<number>` each time, so you can run it again and again.

## Manual checklist (things a script cannot judge)

Assignment requirement → what to do → what you should see.

| # | Requirement | Do | Expected |
|---|---|---|---|
| 1 | Registration, mandatory fields | Register with an empty username / password / name | Red message under each empty field |
| 2 | Username already used | Register again with the username `client` | "Ce nom d'utilisateur est déjà utilisé" and you stay on the form |
| 3 | Registration confirmed | Register with a new username | Green "Inscription confirmée", your name at the top right |
| 4 | Wrong login | Log in with `client` / `wrong` | "Nom d'utilisateur ou mot de passe incorrect", not logged in |
| 5 | Login OK | Log in with `client` / `Client123!` | "Camille Dupont" at the top right **on every page** |
| 6 | Password encrypted | phpMyAdmin → `users` → your row | `password_hash` starts with `$2y$`, not your password |
| 7 | Quantity 0 or negative | On a product page, type 0 (then -3), click "Ajouter au panier" | Error message, cart badge unchanged |
| 8 | Quantity > stock | Type more than the stock | **Not possible yet: no stock column (FAIL)** |
| 9 | Same product twice | Add the same tag (same name/colour/size) with 2, then 3 | One line with quantity 5 in the cart |
| 10 | Basket on every page | Add something, browse other pages | The badge on the bag icon shows the number of products |
| 11 | Basket page | Open the cart | Image, name, unit price, quantity, line price, sub-total, total, "Passer la commande" button |
| 12 | Totals | 2 × 19,90 € + 1 × 34,90 € | Lines 39,80 € and 34,90 €, sub-total 74,70 €, delivery free (≥ 50 €), total 74,70 € |
| 13 | Search | Search "collier", then "zzzz" | Collars listed; then "no product" message |
| 14 | Menu | Click through all menu links | Every page has the menu, no broken link |
| 15 | Apriori (product page) | Open the "Collier cuir brodé au prénom" page | Block "Fréquemment achetés ensemble" with "Médaille ronde en laiton" (67 %) |
| 15b | Recommender (account page) | Log in as `zoe.renard` / `Client123!`, open "Mon compte" | "Recommandé pour vous": Médaille ronde (70 %), Mug « Papa de… » (60 %), Médaille cœur (54 %), each with "Vous avez acheté …" |
| 15c | Cold start | Log in as `client` / `Client123!`, open "Mon compte" | Message "Vos recommandations … après votre première commande" |
| 16 | Payment | "Passer la commande" → fill in → confirm | Confirmation page, order visible in "Mon compte" |
| 17 | Phone | Open the site in a narrow window (or on your phone) | Burger menu, forms usable |

## Making the zip (without `_tests/`)

Use the team's script at the root of the project (it zips only the website folders, so `_tests/`, `notebooks/` and `.git` are left out):

```bash
py make_zip.py
```

It creates `group2.zip` and adds `business_model.pdf` if it is in the project folder.
