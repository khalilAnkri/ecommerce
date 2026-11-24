# Change: allow buying a product that is out of stock

| File | Line | Now | Change |
| --- | --- | --- | --- |
| includes/functions.php | 357-365 | in `check_quantity()`: `if ($stock <= 0)` ("rupture de stock") and `if ($already + $quantity > $stock)` ("Stock insuffisant") | delete these two tests |
| pages/product.php | 461-468 | `<?php if ($stock > 0 && ...` / `disabled>Rupture de stock` | show the normal button even when the stock is 0 |
| pages/checkout.php | 162-171 | `// Stock check: ...` / `if ($wanted > (int) $row['stock'])` | delete this check |
| pages/checkout.php | 315-316 | `UPDATE products SET stock = stock - :quantity WHERE id = :id AND stock >= :quantity_check` | remove `AND stock >= :quantity_check` (the stock can go below 0: pre-order) |
