"""
analytics/apriori_analysis.py — Apriori on the Poilu & Co orders
=================================================================

The algorithm itself is the one from the course (lecture 01-ARM,
notebook Apriori_e_commerce.ipynb): the `apyori` library.
(apriori_lecture.py shows the same algorithm written like the lecture pseudo-code.)
This file only contains the NEW functions we need around it:

    1. load_order_items()        read OUR orders (instead of store_data.csv)
    2. orders_to_store_data()    put them in the SAME shape as store_data.csv
                                 (1 row = 1 basket = 1 order), so the course
                                 code works without changes
    3. rules_to_json()           "customers who bought this also bought" for each
                                 product page (lecture Recommender Systems 1.1)
    4. recommend_for_customers() recommendations for each customer: Association Rule
                                 Mining for Collaborative Filtering (lecture
                                 Recommender Systems 1.7.2), shown on pages/account.php
       evaluate_recommendations() precision and recall (lecture Recommender Systems 1.10)
    5. save_json()               write analytics/output/apriori_rules.json,
                                 read by pages/product.php and pages/account.php

Two ways to use it:
    - open analytics/Apriori_e_commerce.ipynb in VS Code (step by step, like the course)
    - or run everything at once in a terminal:   python apriori_analysis.py

Where the orders come from:
    1. the MySQL database of the website (XAMPP must be running), so new
       orders placed on the website are included;
    2. if MySQL is not reachable: the file database/database.sql (demo data).
"""

import json
import re
from datetime import datetime
from pathlib import Path

import numpy as np
import pandas as pd
from apyori import apriori


# ---------- Settings ----------

# Same values as includes/db.php (change BOTH files if you rename the database to groupX)
DB_HOST     = 'localhost'
DB_NAME     = 'group2'
DB_USER     = 'root'
DB_PASSWORD = ''

ANALYTICS_FOLDER = Path(__file__).resolve().parent
SQL_FILE         = ANALYTICS_FOLDER.parent / 'database' / 'database.sql'
OUTPUT_FILE      = ANALYTICS_FOLDER / 'output' / 'apriori_rules.json'

# Apriori thresholds, tuned for our data (about 170 orders, 24 products).
# The course used min_support=0.0045 on 7501 baskets of a supermarket:
# with only ~170 orders, 0.02 means "the products appear together in at least 4 orders".
MIN_SUPPORT    = 0.02
MIN_CONFIDENCE = 0.2
MIN_LIFT       = 1.5

# Thresholds for the recommendations per CUSTOMER (lecture Recommender Systems 1.7.2).
# There, 1 transaction = 1 customer (all the products he/she ever bought), and we only
# have ~50 customers: 0.10 means "bought by at least 5 customers".
# Lower values give thousands of rules (the "combinatorial explosion" of the lecture).
USER_MIN_SUPPORT    = 0.10
USER_MIN_CONFIDENCE = 0.5
USER_MIN_LIFT       = 1.2

# How many products are recommended (product page and account page)
MAX_RECOMMENDATIONS = 4


# ============================================================
# 1. Read the orders
# ============================================================

def load_order_items(source='auto'):
    """
    Returns a DataFrame with one row per product of an order:

        order_id | customer | product_id | product_name
        ---------+----------+------------+-------------------------
            1    |    4     |     5      | Médaille ronde en laiton
            1    |    4     |     2      | Collier cuir brodé au prénom
            2    | guest-2  |    23      | Coffret anniversaire

    customer = the user id (as text), or "guest-<order id>" for an order placed
    without an account (each guest order is counted as a different customer).
    Cancelled orders are left out (the customer did not really buy them).

    source = 'mysql'  -> read the website database (XAMPP must be running)
             'sql'    -> read database/database.sql (demo data only)
             'auto'   -> try MySQL, and use the .sql file if it fails
    """
    if source in ('mysql', 'auto'):
        try:
            return _load_from_mysql()
        except Exception as error:
            if source == 'mysql':
                raise
            print(f'MySQL not reachable ({error.__class__.__name__}): '
                  f'using {SQL_FILE.name} instead (demo data only).')
    return _load_from_sql_file(SQL_FILE)


def _load_from_mysql():
    import pymysql   # imported here: only needed when we use MySQL

    connection = pymysql.connect(host=DB_HOST, user=DB_USER, password=DB_PASSWORD,
                                 database=DB_NAME, charset='utf8mb4')
    try:
        with connection.cursor() as cursor:
            cursor.execute("""
                SELECT oi.order_id,
                       COALESCE(CAST(o.user_id AS CHAR), CONCAT('guest-', o.id)) AS customer,
                       oi.product_id, oi.product_name
                FROM order_items oi
                JOIN orders o ON o.id = oi.order_id
                WHERE o.status != 'cancelled'
                ORDER BY oi.order_id, oi.id
            """)
            rows = cursor.fetchall()
    finally:
        connection.close()

    print(f'Orders read from the MySQL database "{DB_NAME}".')
    return pd.DataFrame(rows, columns=['order_id', 'customer', 'product_id', 'product_name'])


def _load_from_sql_file(path):
    """Reads the INSERT lines of database.sql (used when MySQL is not running)."""
    text = Path(path).read_text(encoding='utf-8')

    # Cancelled orders: "(12, 4, 'Jules', ..., 'card', 'cancelled', '2026-..."
    cancelled = {int(order_id) for order_id in
                 re.findall(r"^\((\d+), .*'cancelled', '\d{4}-", text, flags=re.MULTILINE)}

    # Customer of each order: "(1, 4, 'Jules', ..." or "(2, NULL, 'Thomas', ..." (guest)
    orders_part = text[text.index('INSERT INTO orders'):]
    orders_part = orders_part[:orders_part.index(';\n')]
    customers = {int(order_id): (user_id if user_id != 'NULL' else f'guest-{order_id}')
                 for order_id, user_id in re.findall(r"^\((\d+), (\d+|NULL), '", orders_part, flags=re.MULTILINE)}

    # Order items: "(1, 1, 5, 'Médaille ronde en laiton', 19.90, 1),"
    order_items_part = text[text.index('INSERT INTO order_items'):]
    order_items_part = order_items_part[:order_items_part.index(';\n')]
    rows = re.findall(r"\((\d+), (\d+), (\d+), '((?:[^']|'')*)', [\d.]+, \d+\)", order_items_part)

    data = [(int(order_id), customers[int(order_id)], int(product_id), name.replace("''", "'"))
            for _, order_id, product_id, name in rows
            if int(order_id) not in cancelled]
    return pd.DataFrame(data, columns=['order_id', 'customer', 'product_id', 'product_name'])


# ============================================================
# 2. Same shape as store_data.csv
# ============================================================

def orders_to_store_data(order_items):
    """
    In the course, store_data.csv has 1 row per basket and 1 product per column,
    with NaN in the empty cells:

        0                         1                             2
        Médaille ronde en laiton  Collier cuir brodé au prénom  NaN
        Plaid portrait            Coussin portrait              NaN

    Here: 1 basket = 1 order. We build the same table, so the course code
    (the loop that removes 'nan', then apriori()) works without changes.
    """
    # One name per product id (an old order may contain an older name)
    names = order_items.groupby('product_id')['product_name'].last()

    baskets = []
    for order_id, items in order_items.groupby('order_id'):
        product_names = [names[product_id] for product_id in items['product_id'].unique()]
        baskets.append(product_names)

    longest = max(len(basket) for basket in baskets)
    padded = [basket + [np.nan] * (longest - len(basket)) for basket in baskets]
    return pd.DataFrame(padded)


def product_ids_by_name(order_items):
    """{'Médaille ronde en laiton': 5, ...}: to turn names back into ids for PHP."""
    names = order_items.groupby('product_id')['product_name'].last()
    return {name: int(product_id) for product_id, name in names.items()}


# ============================================================
# 3. Results -> simple data for the website
# ============================================================

def rules_to_json(association_results, ids_by_name, n_baskets):
    """
    Turns the apyori results into a dictionary that PHP can read easily.

    "rules": every rule "if the basket contains A, it often also contains B"
             with its support, confidence and lift (same numbers as in the course).

    "recommendations": for each product id, the products to show on its page,
             best first (highest confidence, then highest lift). Only the simple
             rules "1 product -> 1 product" are used: a product page is about one
             product, and each suggestion keeps the numbers of its own rule.
    """
    rules = []
    for record in association_results:
        for statistic in record.ordered_statistics:
            if len(statistic.items_base) == 0:
                continue   # a rule needs a left side: "{} -> X" is not a rule
            base = sorted(statistic.items_base)
            add  = sorted(statistic.items_add)
            rules.append({
                'if_ids':     [ids_by_name[name] for name in base],
                'if_names':   base,
                'then_ids':   [ids_by_name[name] for name in add],
                'then_names': add,
                'support':    round(record.support, 4),
                'confidence': round(statistic.confidence, 4),
                'lift':       round(statistic.lift, 4),
            })

    # Same sorting as the course: highest confidence first
    rules.sort(key=lambda rule: (rule['confidence'], rule['lift']), reverse=True)

    recommendations = {}
    for rule in rules:
        if len(rule['if_ids']) != 1 or len(rule['then_ids']) != 1:
            continue
        product_id = str(rule['if_ids'][0])          # JSON keys are always text
        recommendations.setdefault(product_id, []).append({
            'product_id': rule['then_ids'][0],
            'name':       rule['then_names'][0],
            'confidence': rule['confidence'],
            'lift':       rule['lift'],
            'support':    rule['support'],
        })

    for product_id in recommendations:
        recommendations[product_id] = recommendations[product_id][:MAX_RECOMMENDATIONS]

    return {
        'generated_at': datetime.now().strftime('%Y-%m-%d %H:%M:%S'),
        'algorithm':    'apriori (apyori)',
        'parameters':   {'min_support': MIN_SUPPORT, 'min_confidence': MIN_CONFIDENCE, 'min_lift': MIN_LIFT},
        'basket_count': n_baskets,
        'rule_count':   len(rules),
        'rules':        rules,
        'recommendations': recommendations,
    }


# ============================================================
# 4. Recommendations per customer (lecture Recommender Systems)
# ============================================================
#
# 1.7.2 "Generating Recommendations from Association Rules":
#   1. ARM needs binary values -> user-item matrix with 1 = bought, 0 = not bought.
#      (The lecture converts RATINGS to 1/0. We have no ratings: a purchase is
#      already an implicit 1, see 1.5 "implicit ratings".)
#   2. Apply Apriori on this matrix (1 transaction = 1 customer).
#      "The transactions of the target user are ignored during this step."
#   3. Use the rules: the customer bought the left side of a rule
#      -> recommend the right side (if not bought yet).
#      Lecture example: Alice bought Item1 and {Item1} => {Item5} -> recommend Item5.

def user_item_matrix(order_items):
    """
    The table of the lecture (1.7.2): 1 row per customer, 1 column per product,
    1 = the customer bought the product at least once, 0 = never.
    """
    names = order_items.groupby('product_id')['product_name'].last()
    matrix = pd.crosstab(order_items['customer'], order_items['product_id']).clip(upper=1)
    matrix.columns = [names[product_id] for product_id in matrix.columns]
    return matrix


def matrix_to_transactions(matrix):
    """Each row of the 0/1 matrix -> the list of the products with a 1 (like `records` in the course)."""
    return [list(matrix.columns[matrix.loc[customer] == 1]) for customer in matrix.index]


def _rules_with_one_product_on_the_right(transactions, min_support, min_confidence, min_lift):
    """apyori rules A => {b} (like the lecture example {Item1} => {Item5})."""
    rules = []
    for record in apriori(transactions, min_support=min_support,
                          min_confidence=min_confidence, min_lift=min_lift):
        for statistic in record.ordered_statistics:
            if len(statistic.items_base) > 0 and len(statistic.items_add) == 1:
                rules.append({'because': set(statistic.items_base),
                              'product': next(iter(statistic.items_add)),
                              'support': record.support,
                              'confidence': statistic.confidence,
                              'lift': statistic.lift})
    return rules


def _recommend(rules, bought, max_items):
    """Rules whose left side was bought -> right side not bought yet. Best rule per product."""
    best = {}
    for rule in rules:
        if rule['because'] <= bought and rule['product'] not in bought:
            current = best.get(rule['product'])
            if current is None or (rule['confidence'], rule['lift']) > (current['confidence'], current['lift']):
                best[rule['product']] = rule
    ranked = sorted(best.values(), key=lambda rule: (rule['confidence'], rule['lift']), reverse=True)
    return ranked[:max_items]


def recommend_for_customers(order_items, min_support=USER_MIN_SUPPORT, min_confidence=USER_MIN_CONFIDENCE,
                            min_lift=USER_MIN_LIFT, max_items=MAX_RECOMMENDATIONS):
    """
    Returns {user id (text): [recommended products, best first]} for every customer
    with an account who gets at least one recommendation.
    Customers without orders get nothing: that is the cold-start problem of the lecture.
    """
    matrix = user_item_matrix(order_items)
    ids_by_name = product_ids_by_name(order_items)
    recommendations = {}

    for customer in matrix.index:
        if customer.startswith('guest-'):
            continue                                   # no account: nobody to show it to

        # The transactions of the target customer are ignored (lecture 1.7.2)
        transactions = matrix_to_transactions(matrix.drop(index=customer))
        rules = _rules_with_one_product_on_the_right(transactions, min_support, min_confidence, min_lift)

        bought = set(matrix.columns[matrix.loc[customer] == 1])
        suggestions = _recommend(rules, bought, max_items)
        if suggestions:
            recommendations[customer] = [{
                'product_id':    ids_by_name[rule['product']],
                'name':          rule['product'],
                'because_ids':   sorted(ids_by_name[name] for name in rule['because']),
                'because_names': sorted(rule['because']),
                'support':       round(rule['support'], 4),
                'confidence':    round(rule['confidence'], 4),
                'lift':          round(rule['lift'], 4),
            } for rule in suggestions]
    return recommendations


def evaluate_recommendations(order_items, max_items=MAX_RECOMMENDATIONS, min_support=USER_MIN_SUPPORT,
                             min_confidence=USER_MIN_CONFIDENCE, min_lift=USER_MIN_LIFT):
    """
    Lecture 1.10: precision and recall.
    For each customer with at least 2 orders, we HIDE the last order, recommend
    products from the earlier orders only, and check the hidden order:
        relevant items = products of the last order that were not bought before
        precision = tp / (tp + fp) = good recommendations / all recommendations
        recall    = tp / (tp + fn) = good recommendations / all relevant items
    """
    names = order_items.groupby('product_id')['product_name'].last()
    matrix = user_item_matrix(order_items)
    tp = fp = fn = 0
    evaluated = 0

    for customer, orders in order_items.groupby('customer'):
        if customer.startswith('guest-') or orders['order_id'].nunique() < 2:
            continue
        last_order = orders['order_id'].max()            # order ids follow the dates
        known    = {names[p] for p in orders.loc[orders['order_id'] != last_order, 'product_id']}
        relevant = {names[p] for p in orders.loc[orders['order_id'] == last_order, 'product_id']} - known
        if not relevant:
            continue

        transactions = matrix_to_transactions(matrix.drop(index=customer))   # target ignored
        rules = _rules_with_one_product_on_the_right(transactions, min_support, min_confidence, min_lift)
        recommended = {rule['product'] for rule in _recommend(rules, known, max_items)}

        tp += len(recommended & relevant)      # recommended and actually bought
        fp += len(recommended - relevant)      # recommended but not bought
        fn += len(relevant - recommended)      # bought but not recommended
        evaluated += 1

    return {
        'customers_evaluated': evaluated,
        'recommendations_per_customer': max_items,
        'true_positives': tp, 'false_positives': fp, 'false_negatives': fn,
        'precision': round(tp / (tp + fp), 4) if tp + fp else 0.0,
        'recall':    round(tp / (tp + fn), 4) if tp + fn else 0.0,
    }


# ============================================================
# 5. Save for the website
# ============================================================

def save_json(data, path=OUTPUT_FILE):
    """Writes the results to analytics/output/apriori_rules.json (folder created if needed)."""
    path = Path(path)
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(data, ensure_ascii=False, indent=2), encoding='utf-8')
    shown = path.relative_to(ANALYTICS_FOLDER.parent) if path.is_relative_to(ANALYTICS_FOLDER.parent) else path
    users = f', {len(data["user_recommendations"])} customers' if 'user_recommendations' in data else ''
    print(f'Saved {data["rule_count"]} rules ({len(data["recommendations"])} products{users}) in {shown}')


# ============================================================
# Run everything:  python apriori_analysis.py
# ============================================================

def main():
    order_items = load_order_items()
    store_data  = orders_to_store_data(order_items)

    # ----- Course code (Apriori_e_commerce.ipynb) -----
    records = []
    for i in range(len(store_data)):
        records.append([str(item) for item in store_data.values[i] if str(item) != 'nan'])

    association_rules = apriori(records, min_support=MIN_SUPPORT, min_confidence=MIN_CONFIDENCE,
                                min_lift=MIN_LIFT, min_length=2)
    association_results = list(association_rules)
    # ----- end of course code -----

    print(f'{len(records)} baskets (orders), {len(association_results)} itemsets found.')
    data = rules_to_json(association_results, product_ids_by_name(order_items), len(records))

    for rule in data['rules'][:10]:
        print(f"  {' + '.join(rule['if_names'])} -> {' + '.join(rule['then_names'])}"
              f"   (support {rule['support']:.3f}, confidence {rule['confidence']:.2f}, lift {rule['lift']:.2f})")

    # Recommender system: ARM for collaborative filtering (1 transaction = 1 customer)
    data['user_parameters'] = {'min_support': USER_MIN_SUPPORT, 'min_confidence': USER_MIN_CONFIDENCE,
                               'min_lift': USER_MIN_LIFT}
    data['user_recommendations'] = recommend_for_customers(order_items)
    data['evaluation'] = evaluate_recommendations(order_items)
    evaluation = data['evaluation']
    print(f"Recommendations for {len(data['user_recommendations'])} customers. "
          f"Evaluation on {evaluation['customers_evaluated']} customers: "
          f"precision {evaluation['precision']:.2f}, recall {evaluation['recall']:.2f}")

    save_json(data)


if __name__ == '__main__':
    main()
