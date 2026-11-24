# Change: the recommendations (Apriori)

| File | Line | Now | Change |
| --- | --- | --- | --- |
| analytics/apriori_analysis.py | 71 | `MAX_RECOMMENDATIONS = 4` | products saved per product / per customer |
| analytics/apriori_analysis.py | 58-60 | `MIN_SUPPORT = 0.02`, `MIN_CONFIDENCE = 0.2`, `MIN_LIFT = 1.5` | thresholds, product page (1 transaction = 1 order) |
| analytics/apriori_analysis.py | 66-68 | `USER_MIN_SUPPORT = 0.10`, `USER_MIN_CONFIDENCE = 0.5`, `USER_MIN_LIFT = 1.2` | thresholds, account page (1 transaction = 1 customer) |
| includes/functions.php | 628, 638 | `$limit = 4` in `apriori_recommendations()` / `apriori_user_recommendations()` | products shown on the site |

Then re-run `python analytics/apriori_analysis.py` (or Run All in analytics/Apriori_e_commerce.ipynb).
