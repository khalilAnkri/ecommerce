"""
analytics/apriori_lecture.py — the Apriori algorithm written like the lecture
==============================================================================

Lecture "Association Rule Mining" (Web and Text Analytics):
    Figure 4  Algorithm Apriori(T)       -> apriori_lecture()
    Figure 5  Function candidate-gen     -> candidate_gen()
    "generate the AR from a frequent itemset X: A => X - A, keep it if conf >= minconf"
                                         -> generate_rules()

The website uses the `apyori` library, like the course notebook. This file is
here to SHOW how the algorithm works, step by step, and to CHECK that apyori
gives the same frequent itemsets (see the notebook and _tests/test_apriori.py).

Itemsets are tuples sorted in lexicographic order, e.g. (1, 3, 5),
as in the lecture ("the order of the items in the itemsets do not change").
"""

from itertools import combinations


def _show(itemsets):
    """Short display for verbose mode: the list if it is short, otherwise how many."""
    itemsets = sorted(itemsets)
    return str(itemsets) if len(itemsets) <= 8 else f'{len(itemsets)} itemsets'


def support(itemset, transactions):
    """sup(X) = #(transactions containing X) / n"""
    itemset = set(itemset)
    return sum(1 for t in transactions if itemset <= set(t)) / len(transactions)


def candidate_gen(F_previous):
    """
    Figure 5. From the frequent (k-1)-itemsets F_previous, build the candidates C_k:
      1. JOIN  f1 and f2 that share their first k-2 items, with last item of f1 < last item of f2
      2. PRUNE c if one of its (k-1)-subsets is not frequent (anti-monotonicity /
               downward closure: every subset of a frequent itemset is frequent)
    """
    F_previous = sorted(F_previous)
    frequent = set(F_previous)
    C = []
    for f1 in F_previous:
        for f2 in F_previous:
            if f1[:-1] == f2[:-1] and f1[-1] < f2[-1]:
                c = f1 + (f2[-1],)                                   # join f1 and f2
                if all(s in frequent for s in combinations(c, len(c) - 1)):
                    C.append(c)                                      # (otherwise: pruned)
    return C


def apriori_lecture(transactions, minsup, verbose=False):
    """
    Figure 4. Returns {itemset: support} for every frequent itemset.

    C1 <- init-pass(T); F1 <- {f in C1 | f.count/n >= minsup}
    for (k = 2; F(k-1) not empty; k++):
        Ck <- candidate-gen(F(k-1))
        for each transaction t, for each candidate c: if c is contained in t then c.count++
        Fk <- {c in Ck | c.count/n >= minsup}
    return F = union of the Fk
    """
    n = len(transactions)
    transactions = [set(t) for t in transactions]

    # Iteration k = 1: count every single item (init-pass)
    counts = {}
    for t in transactions:
        for item in t:
            counts[(item,)] = counts.get((item,), 0) + 1
    F = {c: count / n for c, count in counts.items() if count / n >= minsup}
    all_frequent = dict(F)
    if verbose:
        print(f'k = 1   F1 = {_show(F)}')

    k = 2
    while F:
        C = candidate_gen(F.keys())
        counts = {c: 0 for c in C}
        for t in transactions:
            for c in C:
                if set(c) <= t:
                    counts[c] += 1                                   # c.count++
        F = {c: count / n for c, count in counts.items() if count / n >= minsup}
        if verbose and C:
            print(f'k = {k}   C{k} = {_show(C)}\n        F{k} = {_show(F)}')
        all_frequent.update(F)
        k += 1

    return all_frequent


def generate_rules(frequent, minconf):
    """
    For each frequent itemset X and each proper non-empty subset A of X:
        B = X - A,   A => B is kept if conf(A => B) = sup(X) / sup(A) >= minconf
    Returns a list of dicts with support, confidence and lift
    (lift = sup(X) / (sup(A) * sup(B))).
    """
    rules = []
    for X, sup_X in frequent.items():
        for size in range(1, len(X)):
            for A in combinations(X, size):
                B = tuple(item for item in X if item not in A)
                confidence = sup_X / frequent[A]                     # A is frequent too (downward closure)
                if confidence >= minconf:
                    rules.append({'A': A, 'B': B, 'support': sup_X, 'confidence': confidence,
                                  'lift': sup_X / (frequent[A] * frequent[B])})
    return rules
