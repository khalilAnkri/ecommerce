"""
_tests/test_apriori.py — checks the Python part against the two lectures
=========================================================================

Does NOT need XAMPP: it uses the lecture examples and database/database.sql.

    Lecture "Association Rule Mining":  Figures 1, 2, 3, the candidate-gen example,
                                         rule generation, apyori == pseudo-code
    Lecture "Recommender Systems":      1.7.2 (Alice -> Item5, target user ignored),
                                         1.10 (precision and recall)

Run (from the project folder):
    python -m unittest _tests/test_apriori.py -v
"""

import json
import sys
import tempfile
import unittest
from pathlib import Path

import pandas as pd
from apyori import apriori

PROJECT_ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(PROJECT_ROOT / 'analytics'))

import apriori_analysis as aa                                      # noqa: E402
from apriori_lecture import apriori_lecture, candidate_gen, generate_rules, support   # noqa: E402


def course_records(store_data):
    """The exact loop of the course notebook."""
    records = []
    for i in range(len(store_data)):
        records.append([str(item) for item in store_data.values[i] if str(item) != 'nan'])
    return records


def items_frame(baskets_by_customer):
    """{'Alice': ['Item1'], ...} -> the DataFrame shape returned by load_order_items()."""
    rows, order_id = [], 0
    for customer, items in baskets_by_customer.items():
        order_id += 1
        for item in items:
            rows.append((order_id, customer, int(item[-1]), item))
    return pd.DataFrame(rows, columns=['order_id', 'customer', 'product_id', 'product_name'])


# ============================================================
# Lecture "Association Rule Mining"
# ============================================================

class LectureAssociationRuleMining(unittest.TestCase):

    FIGURE_1 = [['milk', 'bread'], ['bread', 'butter'], ['beer'], ['milk', 'bread', 'butter'], ['bread', 'butter']]
    FIGURE_2 = [['Beef', 'Chicken', 'Milk'], ['Beef', 'Cheese'], ['Cheese', 'Boots'], ['Beef', 'Chicken', 'Cheese'],
                ['Beef', 'Chicken', 'Clothes', 'Cheese', 'Milk'], ['Chicken', 'Clothes', 'Milk'], ['Chicken', 'Milk', 'Clothes']]
    FIGURE_3 = [[1, 3, 4], [2, 3, 5], [1, 2, 3, 5], [2, 5]]

    def test_figure_1_support_and_confidence_with_apyori(self):
        """sup({milk, bread}) = 0.4 and conf({milk, bread} => {butter}) = 0.2 / 0.4 = 0.5"""
        results = list(apriori(self.FIGURE_1, min_support=0.2, min_confidence=0.0, min_lift=0.0))
        by_items = {frozenset(r.items): r for r in results}
        self.assertAlmostEqual(by_items[frozenset({'milk', 'bread'})].support, 0.4)
        rule = next(s for s in by_items[frozenset({'milk', 'bread', 'butter'})].ordered_statistics
                    if s.items_base == {'milk', 'bread'})
        self.assertAlmostEqual(rule.confidence, 0.5)

    def test_figure_1_with_the_lecture_formulas(self):
        self.assertAlmostEqual(support(['milk', 'bread'], self.FIGURE_1), 0.4)
        self.assertAlmostEqual(support(['milk', 'bread', 'butter'], self.FIGURE_1) / support(['milk', 'bread'], self.FIGURE_1), 0.5)

    def test_figure_2_frequent_itemset_and_rule(self):
        """{chicken, clothes, milk}: support 3/7 = 0.43; {clothes, chicken} => {milk}: support 0.43, confidence 1"""
        frequent = apriori_lecture(self.FIGURE_2, minsup=0.3)
        self.assertAlmostEqual(frequent[('Chicken', 'Clothes', 'Milk')], 3 / 7)
        rule = next(r for r in generate_rules(frequent, minconf=0.8) if r['A'] == ('Chicken', 'Clothes'))
        self.assertEqual(rule['B'], ('Milk',))
        self.assertAlmostEqual(rule['support'], 3 / 7)
        self.assertAlmostEqual(rule['confidence'], 1.0)

    def test_figure_3_iterations(self):
        """minsup 0.5: F1 = {1},{2},{3},{5}; F2 = {1,3},{2,3},{2,5},{3,5}; C3 = F3 = {2,3,5}"""
        frequent = apriori_lecture(self.FIGURE_3, minsup=0.5)
        by_size = lambda k: sorted(itemset for itemset in frequent if len(itemset) == k)
        self.assertEqual(by_size(1), [(1,), (2,), (3,), (5,)])
        self.assertEqual(by_size(2), [(1, 3), (2, 3), (2, 5), (3, 5)])
        self.assertEqual(candidate_gen(by_size(2)), [(2, 3, 5)])
        self.assertEqual(by_size(3), [(2, 3, 5)])

    def test_candidate_gen_join_and_prune(self):
        """Join gives {1,2,3,4} and {1,3,4,5}; {1,3,4,5} is pruned ({1,4,5} not frequent)."""
        self.assertEqual(candidate_gen([(1, 2, 3), (1, 2, 4), (1, 3, 4), (1, 3, 5), (2, 3, 4)]), [(1, 2, 3, 4)])

    def test_rule_generation_from_a_frequent_itemset(self):
        """X = {2,3,4}: rules A => X - A for the proper non-empty subsets A, kept if conf >= minconf."""
        frequent = {(2,): 0.75, (3,): 0.75, (4,): 0.5, (2, 3): 0.5, (2, 4): 0.5, (3, 4): 0.5, (2, 3, 4): 0.5}
        rules = {(r['A'], r['B']) for r in generate_rules(frequent, minconf=0.0) if len(r['A'] + r['B']) == 3}
        for rule in [((2, 3), (4,)), ((2, 4), (3,)), ((3, 4), (2,)), ((2,), (3, 4)), ((3,), (2, 4))]:
            self.assertIn(rule, rules)                     # the 5 rules listed in the lecture
        kept = generate_rules(frequent, minconf=0.8)
        self.assertTrue(all(r['confidence'] >= 0.8 for r in kept))

    def test_lift_formula(self):
        """lift = sup(X u Y) / (sup(X) sup(Y))"""
        frequent = apriori_lecture(self.FIGURE_1, minsup=0.2)
        rule = next(r for r in generate_rules(frequent, minconf=0.0) if r['A'] == ('bread',) and r['B'] == ('butter',))
        self.assertAlmostEqual(rule['lift'], 0.6 / (0.8 * 0.6))


# ============================================================
# Our orders (product page)
# ============================================================

class OurOrders(unittest.TestCase):

    @classmethod
    def setUpClass(cls):
        cls.order_items = aa.load_order_items(source='sql')
        cls.store_data = aa.orders_to_store_data(cls.order_items)
        cls.records = course_records(cls.store_data)

    def test_orders_are_read(self):
        self.assertGreater(len(self.order_items), 100)
        self.assertEqual(list(self.order_items.columns), ['order_id', 'customer', 'product_id', 'product_name'])
        self.assertTrue(any(c.startswith('guest-') for c in self.order_items['customer']))

    def test_one_basket_per_order_and_cancelled_orders_left_out(self):
        sql = aa.SQL_FILE.read_text(encoding='utf-8')
        cancelled = sql.count("'cancelled', '20")
        self.assertGreater(cancelled, 0)
        all_orders = sql[sql.index('INSERT INTO orders'):].split(';')[0].count("\n(")
        self.assertEqual(len(self.records), all_orders - cancelled)

    def test_same_product_twice_in_an_order_counts_once(self):
        items = items_frame({'A': ['Item1', 'Item2']})
        items = pd.concat([items, items.iloc[[0]]])
        self.assertEqual(course_records(aa.orders_to_store_data(items)), [['Item1', 'Item2']])

    def test_apyori_and_lecture_pseudo_code_find_the_same_frequent_itemsets(self):
        lecture = {frozenset(itemset): sup for itemset, sup in apriori_lecture(self.records, aa.MIN_SUPPORT).items()}
        library = {frozenset(r.items): r.support
                   for r in apriori(self.records, min_support=aa.MIN_SUPPORT, min_confidence=0.0, min_lift=0.0)}
        self.assertEqual(set(lecture), set(library))
        for itemset in lecture:
            self.assertAlmostEqual(lecture[itemset], library[itemset])

    def test_known_pattern_is_found(self):
        """database.sql was built with 'plaid portrait + coussin portrait' bought together."""
        results = list(apriori(self.records, min_support=aa.MIN_SUPPORT, min_confidence=aa.MIN_CONFIDENCE,
                               min_lift=aa.MIN_LIFT, min_length=2))
        data = aa.rules_to_json(results, aa.product_ids_by_name(self.order_items), len(self.records))
        pairs = {(r['if_names'][0], r['then_names'][0]) for r in data['rules']
                 if len(r['if_names']) == 1 and len(r['then_names']) == 1}
        self.assertIn(('Plaid portrait de votre animal', 'Coussin portrait'), pairs)
        for product_id, suggestions in data['recommendations'].items():
            self.assertLessEqual(len(suggestions), aa.MAX_RECOMMENDATIONS)
            self.assertNotIn(int(product_id), [s['product_id'] for s in suggestions])   # never itself


# ============================================================
# Lecture "Recommender Systems" (account page)
# ============================================================

class LectureRecommenderSystems(unittest.TestCase):

    # 1.7.2: the binary user-item matrix of the lecture (Item5 of Alice is unknown)
    LECTURE = {'Alice': ['Item1'],
               'User1': ['Item1', 'Item3', 'Item5'],
               'User2': ['Item1', 'Item3', 'Item5'],
               'User3': ['Item4', 'Item5'],
               'User4': ['Item2', 'Item3']}

    def test_binary_user_item_matrix(self):
        matrix = aa.user_item_matrix(items_frame(self.LECTURE))
        self.assertEqual(matrix.loc['User1'].tolist(), [1, 0, 1, 0, 1])
        self.assertEqual(matrix.loc['Alice'].tolist(), [1, 0, 0, 0, 0])

    def test_alice_gets_item5_from_rule_item1_implies_item5(self):
        """Rule {Item1} => {Item5}, support 0.5, confidence 1 (Alice's row ignored) -> recommend Item5."""
        recommendations = aa.recommend_for_customers(items_frame(self.LECTURE), min_support=0.5,
                                                     min_confidence=0.5, min_lift=1.0)
        item5 = next(r for r in recommendations['Alice'] if r['name'] == 'Item5')
        self.assertEqual(item5['because_names'], ['Item1'])
        self.assertAlmostEqual(item5['support'], 0.5)
        self.assertAlmostEqual(item5['confidence'], 1.0)

    def test_the_target_user_is_ignored_when_mining(self):
        """With Alice counted, Item1 would be in 3 of 5 transactions and the confidence would drop to 2/3."""
        recommendations = aa.recommend_for_customers(items_frame(self.LECTURE), min_support=0.4,
                                                     min_confidence=0.5, min_lift=1.0)
        item5 = next(r for r in recommendations['Alice'] if r['name'] == 'Item5')
        self.assertAlmostEqual(item5['confidence'], 1.0)    # 2/2, not 2/3

    def test_never_recommends_what_was_already_bought(self):
        recommendations = aa.recommend_for_customers(items_frame(self.LECTURE), min_support=0.25,
                                                     min_confidence=0.1, min_lift=0.0)
        for customer, suggestions in recommendations.items():
            for suggestion in suggestions:
                self.assertNotIn(suggestion['name'], self.LECTURE[customer])

    def test_guests_get_no_recommendations_and_cold_start(self):
        order_items = aa.load_order_items(source='sql')
        recommendations = aa.recommend_for_customers(order_items)
        self.assertFalse(any(customer.startswith('guest-') for customer in recommendations))
        self.assertNotIn('2', recommendations)       # demo account "client": no order yet -> cold start
        self.assertIn('19', recommendations)         # zoe.renard (used in the demo)

    def test_precision_and_recall(self):
        """1.10: precision = tp / (tp + fp), recall = tp / (tp + fn), on a hand-made example."""
        rows = [
            # customer A: first order Item1, last order Item5 (hidden) -> rule Item1 => Item5 -> 1 good recommendation
            (1, 'A', 1, 'Item1'), (2, 'A', 5, 'Item5'),
            (3, 'B', 1, 'Item1'), (3, 'B', 5, 'Item5'),
            (4, 'C', 1, 'Item1'), (4, 'C', 5, 'Item5'),
            (5, 'D', 2, 'Item2'),
        ]
        order_items = pd.DataFrame(rows, columns=['order_id', 'customer', 'product_id', 'product_name'])
        result = aa.evaluate_recommendations(order_items, max_items=4, min_support=0.3, min_confidence=0.5, min_lift=1.0)
        self.assertEqual(result['customers_evaluated'], 1)
        self.assertEqual((result['true_positives'], result['false_positives'], result['false_negatives']), (1, 0, 0))
        self.assertEqual((result['precision'], result['recall']), (1.0, 1.0))

    def test_evaluation_on_our_orders_is_between_0_and_1(self):
        result = aa.evaluate_recommendations(aa.load_order_items(source='sql'))
        self.assertGreater(result['customers_evaluated'], 0)
        self.assertTrue(0 <= result['precision'] <= 1 and 0 <= result['recall'] <= 1)


class SavedFile(unittest.TestCase):

    def test_json_file_is_written_and_readable(self):
        data = {'rule_count': 0, 'recommendations': {}, 'rules': [], 'user_recommendations': {}}
        with tempfile.TemporaryDirectory() as folder:
            path = Path(folder) / 'sub' / 'rules.json'
            aa.save_json(data, path)
            self.assertEqual(json.loads(path.read_text(encoding='utf-8')), data)

    def test_website_file_contains_both_parts(self):
        data = json.loads(aa.OUTPUT_FILE.read_text(encoding='utf-8'))
        for key in ('rules', 'recommendations', 'user_recommendations', 'evaluation'):
            self.assertIn(key, data)


if __name__ == '__main__':
    unittest.main(verbosity=2)
