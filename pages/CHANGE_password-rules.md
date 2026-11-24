# Change: the password rules

Today: 8 to 72 characters, at least one letter and one digit, not the same as the username.

| File | Line | Now | Change |
| --- | --- | --- | --- |
| pages/register.php | 33 | `const PASSWORD_MIN = 8;` | minimum length |
| pages/register.php | 128-139 | the checks (empty, `< PASSWORD_MIN`, `> 72`, letter + digit, = username) | add or remove a rule, e.g. a capital letter: `!preg_match('/[A-Z]/', $password)` |
| pages/register.php | 297 | `minlength="<?= PASSWORD_MIN ?>"` | follows the constant |
| assets/js/script.js | 302-330 | password strength meter | visual only: adapt the score if the rules change |
