# Change: allow the same username twice

| File | Line | Now | Change |
| --- | --- | --- | --- |
| database/database.sql | 42 | `username VARCHAR(30) NOT NULL UNIQUE,` | remove `UNIQUE` (existing database: `ALTER TABLE users DROP INDEX username;` in phpMyAdmin) |
| pages/register.php | 149-156 | `// --- Is the username already used? ...` | delete this block |
| pages/register.php | 44 | `if (isset($_GET['check_username']))` (live check) | delete this block |
| assets/js/script.js | 260-300 | `fetch('register.php?check_username=' ...)` | delete the live check |
| pages/login.php | 78 | `WHERE username = :username OR email = :email` | login by username becomes ambiguous: log in by email only (`WHERE email = :email`) |

## Notes

For changes if needed ... 

## Unique Username 

Go to register page: delete the check in 2. Form sent POST 

```
        // --- Is the username already used? (only if it is valid) ---
        if (!isset($errors['username'])) {
            $statement = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = :username');
            $statement->execute(['username' => $values['username']]);
            if ($statement->fetchColumn() > 0) {
                $errors['username'] = 'Ce nom d\'utilisateur est déjà utilisé. Merci d\'en choisir un autre.';
            }
        }

```

Also go to [database](../database/database.sql) to change UNIQUE attribute
and the [live check](../assets/js/script.js) in JavaScript.
