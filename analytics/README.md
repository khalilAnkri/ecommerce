# analytics/ — Apriori and the recommender system

| File | Role |
|---|---|
| `Apriori_e_commerce.ipynb` | The course notebook (lecture 01-ARM) without the Colab cells, run on OUR orders, + the lecture examples, the recommender and its evaluation |
| `apriori_analysis.py` | The NEW functions: load our orders, same shape as store_data.csv, recommendations, evaluation, save JSON. Also runnable alone |
| `apriori_lecture.py` | The Apriori algorithm written like the lecture pseudo-code (Figures 4 and 5), to explain it and to check apyori |
| `output/apriori_rules.json` | The result, read by the website (`includes/functions.php`) |

Python libraries: `requirements.txt` at the root of the project (`pip install -r requirements.txt`), plus `pymysql` to read the orders from MySQL.

## What matches which lecture

| Lecture | Where |
|---|---|
| ARM: support, confidence, lift, minsup / minconf | `apyori` (course code), notebook part 1 |
| ARM: Figures 1, 2, 3 and the candidate-gen example | notebook part 2, `_tests/test_apriori.py` |
| ARM: Apriori algorithm (frequent itemsets level by level, join + prune, then rules) | `apriori_lecture.py`, notebook part 3 (same itemsets as apyori) |
| Recommender Systems 1.1: "Customers who bought this also bought" | product page, block **Fréquemment achetés ensemble** (rules on orders: 1 transaction = 1 order) |
| Recommender Systems 1.7.2: ARM for collaborative filtering | account page, block **Recommandé pour vous**: binary user-item matrix (1 transaction = 1 customer), the target customer's transactions are ignored, rule A ⇒ b with A bought → recommend b |
| Recommender Systems 1.5: cold start | customers without orders get a message instead of recommendations |
| Recommender Systems 1.10: precision and recall | notebook part 6 (last order hidden, 4 recommendations) |

## Run it

XAMPP MySQL started (otherwise `database/database.sql` is used), then either open the notebook in VS Code and *Run All*, or:

```bash
cd analytics
```

```bash
python apriori_analysis.py
```

Run it again after new orders were placed on the website: the pages show the new results immediately (PHP just reads the file).

Demo: product page of the leather collar (`pages/product.php?id=2`), and the account of `zoe.renard` / `Client123!` (the demo account `client` has no order: cold start).

## Format of `output/apriori_rules.json`

```json
{
  "generated_at": "2026-11-20 16:00:00",
  "algorithm": "apriori (apyori)",
  "parameters": { "min_support": 0.02, "min_confidence": 0.2, "min_lift": 1.5 },
  "basket_count": 172,
  "rule_count": 16,
  "rules": [
    { "if_ids": [2], "if_names": ["Collier cuir brodé au prénom"],
      "then_ids": [5], "then_names": ["Médaille ronde en laiton"],
      "support": 0.093, "confidence": 0.6667, "lift": 2.2933 }
  ],
  "recommendations": {
    "2": [
      { "product_id": 5, "name": "Médaille ronde en laiton", "confidence": 0.6667, "lift": 2.2933, "support": 0.093 }
    ]
  },
  "user_parameters": { "min_support": 0.1, "min_confidence": 0.5, "min_lift": 1.2 },
  "user_recommendations": {
    "19": [
      { "product_id": 5, "name": "Médaille ronde en laiton",
        "because_ids": [4], "because_names": ["Plaid portrait de votre animal"],
        "support": 0.1346, "confidence": 0.7, "lift": 1.4 }
    ]
  },
  "evaluation": { "customers_evaluated": 19, "recommendations_per_customer": 4,
                  "true_positives": 5, "false_positives": 22, "false_negatives": 18,
                  "precision": 0.1852, "recall": 0.2174 }
}
```

- `rules`: every rule found on the orders, best confidence first.
- `recommendations`: product page. Key = product id **as text**, up to 4 products, from the rules "1 product → 1 product".
- `user_recommendations`: account page. Key = user id **as text**, up to 4 products not bought yet, with the products that triggered the rule (`because_…`).
- A product or user without a key: show nothing.

In PHP (already done in `includes/functions.php`):

```php
apriori_recommendations($pdo, $product_id);              // product page
apriori_user_recommendations($pdo, $_SESSION['user_id']); // account page
```
