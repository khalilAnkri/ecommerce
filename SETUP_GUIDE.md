# Setup guide: run Poilu & Co with XAMPP (Windows)

## 1. Start XAMPP
1. Open the **XAMPP Control Panel** (run it as administrator the first time).
2. Click **Start** next to **Apache** and next to **MySQL**. Both should turn green.
   - If Apache won't start, port 80 is usually taken by Skype, IIS or another program. Close that program, or go to *Config → Apache (httpd.conf)*, change `Listen 80` to `Listen 8080`, and then use `http://localhost:8080/...` in every URL below.
   - If MySQL won't start, port 3306 is taken, usually by another MySQL install. Stop that service in Windows *Services*.

## 2. Put the project in htdocs
1. Unzip the project.
2. Copy the folder `poilu-and-co` into `C:\xampp\htdocs\`.
   You should end up with `C:\xampp\htdocs\poilu-and-co\index.php`.
   (If you keep the name `ecommerce` from GitHub, replace `poilu-and-co` with `ecommerce` in the URLs.)

## 3. Create the database
1. Open `http://localhost/phpmyadmin` in your browser.
2. Click the **Import** tab at the top. Do not select a database first.
3. Click *Choose file*, pick `poilu-and-co\database\database.sql`, then click **Import** (or **Go**) at the bottom.
4. On the left you should now see the database `poilu_and_co` with 7 tables.
   Importing the file again resets all the data, which is handy before the presentation.

## 4. Check the connection settings
Open `includes\db.php`. The defaults match XAMPP (`root` user, empty password):
```php
$db_host = 'localhost';
$db_name = 'poilu_and_co';
$db_user = 'root';
$db_pass = '';
```

## 5. Open the website
`http://localhost/poilu-and-co/`

| Test | Username | Password |
|---|---|---|
| Customer | `client` | `Client123!` |
| Admin | `admin` | `Admin123!` |

## 6. Teacher requirement: the database must be called `groupX`
When you know your group number (for example 7):
1. In `database\database.sql`, replace both occurrences of `poilu_and_co` (in `CREATE DATABASE` and in `USE`) with `group7`.
2. In `includes\db.php`, set `$db_name = 'group7';`.
3. Import the SQL file again.

## Troubleshooting
| Problem | Fix |
|---|---|
| "La base de données n'est pas connectée" | MySQL isn't started in XAMPP, or the name in `db.php` doesn't match the database name. |
| Blank page | Look at `C:\xampp\apache\logs\error.log`. |
| "Trop de tentatives" while testing login | Wait 15 minutes, or in phpMyAdmin run `DELETE FROM login_attempts;`. |
| Page without CSS | Check that you opened it through `http://localhost/...`, not by double-clicking the file. |

## Optional: work on the code
- **VS Code** with the *PHP Intelephense* extension.
- **Git**: `git pull` to get your teammates' work, then copy or clone the repo straight into `htdocs` so you don't have to copy files by hand.
