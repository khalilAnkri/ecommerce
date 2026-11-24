# Change: ask the phone number at registration

| File | Line | Now | Change |
| --- | --- | --- | --- |
| database/database.sql | 48 | `phone VARCHAR(20) NULL,` | nothing: the column already exists |
| pages/register.php | 77 | `$values = ['username' => '', ... 'email' => '', ...]` | add `'phone' => ''` |
| pages/register.php | 122 | the email checks | add the phone check of pages/checkout.php line 227: `preg_match('/^\+?[0-9 .\/]{8,18}$/', ...)` |
| pages/register.php | 286 | `<input type="email" id="email" name="email" ...>` | add an `<input name="phone">` next to it |
| pages/register.php | 172-173 | `INSERT INTO users (username, first_name, last_name, email, password_hash, role)` | add `phone` and `:phone` |
