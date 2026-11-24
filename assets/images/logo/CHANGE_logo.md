# Change: the logo

| File | Line | Now | Change |
| --- | --- | --- | --- |
| includes/header.php | 62 | `<img src="<?= $base_path ?>assets/images/logo/poilu.png" ...>` | change `poilu.png` (e.g. `poilu_and_co_Branding.png`, already in assets/images/logo/) |
| includes/footer.php | 10-15 | paw icon + text "Poilu & Co" (no image) | change the icon or the text |

## Notes

To change logo: go to `includes/header.php` (line 62), in 

```
<header class="site-header">

<!-- Row 1: logo, search, contact -->
<div class="header-top container">
```

and change path of the img src, e.g. `poilu.png` → `poilu_and_co_Branding.png` (the new logo of this folder)

```
<img src="<?= $base_path ?>assets/images/logo/poilu.png" alt="Poilu & Co" style="height: 50px; width: auto;">
```
