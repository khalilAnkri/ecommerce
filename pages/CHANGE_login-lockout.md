# Change: the login lockout

Today: 5 failed attempts (same username + IP) block the login for 15 minutes.

| File | Line | Now | Change |
| --- | --- | --- | --- |
| pages/login.php | 27 | `const MAX_FAILED_ATTEMPTS = 5;` | number of attempts |
| pages/login.php | 28 | `const LOCK_MINUTES = 15;` | minutes blocked |
