

// Tell the CSS that JavaScript works 
document.documentElement.classList.add('js');


// ---------- 1. Mobile menu (burger button) ----------
const menu_button = document.querySelector('[data-menu-toggle]');
const main_nav = document.querySelector('[data-main-nav]');

if (menu_button && main_nav) {
    menu_button.addEventListener('click', function () {
        const is_open = main_nav.classList.toggle('is-open');
        menu_button.classList.toggle('is-open', is_open);
        menu_button.setAttribute('aria-expanded', is_open);
    });
}


// ---------- 2. "Nos catégories" drop-down ----------
const category_button = document.querySelector('[data-category-toggle]');

if (category_button) {
    const category_menu = category_button.parentElement;

    category_button.addEventListener('click', function (event) {
        event.stopPropagation(); // do not trigger the "click outside" below
        const is_open = category_menu.classList.toggle('is-open');
        category_button.setAttribute('aria-expanded', is_open);
    });

    // Close the list when the user clicks anywhere else.
    document.addEventListener('click', function () {
        category_menu.classList.remove('is-open');
        category_button.setAttribute('aria-expanded', 'false');
    });
}


// ---------- 3. Hero slider (homepage) ----------
const slider = document.querySelector('[data-slider]');

if (slider) {
    const slides = slider.querySelectorAll('[data-slide]');
    const dots = slider.querySelectorAll('[data-dot]');
    let current_slide = 0;
    let timer = null;

    // Show the slide number "index" and hide the others.
    function show_slide(index) {
        slides[current_slide].classList.remove('is-active');
        dots[current_slide].classList.remove('is-active');
        current_slide = index;
        slides[current_slide].classList.add('is-active');
        dots[current_slide].classList.add('is-active');
    }

    // Go to the next slide every 6 seconds.
    function start_auto_play() {
        timer = setInterval(function () {
            show_slide((current_slide + 1) % slides.length);
        }, 6000);
    }

    dots.forEach(function (dot) {
        dot.addEventListener('click', function () {
            clearInterval(timer);            
            show_slide(Number(dot.dataset.dot));
            start_auto_play();
        });
    });

    start_auto_play();
}


// ---------- 4. Fade-in of elements while scrolling ----------
const reveal_items = document.querySelectorAll('.reveal');

if ('IntersectionObserver' in window) {
    const observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target); // animate only once
            }
        });
    }, { threshold: 0.15 });

    reveal_items.forEach(function (item) { observer.observe(item); });
} else {
    // Very old browser: just show everything.
    reveal_items.forEach(function (item) { item.classList.add('is-visible'); });
}


// ---------- 5. Catalogue filters (pages/products.php) ----------

// On phones, the "Filtrer les produits" button shows/hides the filters.
const filters_button = document.querySelector('[data-filters-toggle]');

if (filters_button) {
    const filters_box = document.querySelector('[data-filters]');

    filters_button.addEventListener('click', function () {
        const is_open = filters_box.classList.toggle('is-open');
        filters_button.setAttribute('aria-expanded', is_open);
    });
}

// Choosing a category, an animal or a sort order reloads the page
// right away, so the user does not need to click "Appliquer".
document.querySelectorAll('[data-auto-submit]').forEach(function (field) {
    field.addEventListener('change', function () {
        field.form.submit();   // field.form also works for <select form="filter-form">
    });
});


// ---------- 6. Live preview of the personalisation (pages/product.php) ----------
const preview = document.querySelector('[data-preview]');

if (preview) {

    // a) Texts: each input with data-preview-input="pet_name" updates
    //    the SVG text with data-preview-text="pet_name".
    document.querySelectorAll('[data-preview-input]').forEach(function (input) {
        const field_name = input.dataset.previewInput;
        const targets = preview.querySelectorAll('[data-preview-text="' + field_name + '"]');

        function update_text() {
            targets.forEach(function (text_element) {
                // Empty field: show the example text (data-default)
                text_element.textContent = input.value.trim() || text_element.dataset.default;
                fit_text(text_element);
            });
        }

        input.addEventListener('input', update_text);
        update_text(); // also on page load (the form may already contain values)
    });

    // Makes a long name narrower so it stays on the product.
    function fit_text(text_element) {
        const max_width = Number(text_element.dataset.maxWidth);
        text_element.removeAttribute('textLength');
        if (max_width && text_element.getComputedTextLength() > max_width) {
            text_element.setAttribute('textLength', max_width);
            text_element.setAttribute('lengthAdjust', 'spacingAndGlyphs');
        }
    }

    // b) Colour: changes the CSS variable --preview-color used by the SVG.
    const color_name_label = document.querySelector('[data-color-name]');
    document.querySelectorAll('input[name="color"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            preview.style.setProperty('--preview-color', radio.dataset.color);
            if (color_name_label) {
                color_name_label.textContent = radio.value;
            }
        });
    });

    // c) Size: product.css scales the drawing with [data-size="S"], [data-size="L"].
    document.querySelectorAll('[data-size-input]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            preview.dataset.size = radio.value;
        });
    });

    // d) Photo: shows the chosen picture in the preview, before sending.
    //    FileReader reads the file inside the browser (nothing is uploaded yet).
    const photo_input = document.querySelector('[data-preview-photo-input]');
    const photo_image = preview.querySelector('[data-preview-photo]');
    const photo_placeholder = preview.querySelector('[data-photo-placeholder]');

    if (photo_input && photo_image) {
        photo_input.addEventListener('change', function () {
            const file = photo_input.files[0];

            if (!file || !file.type.startsWith('image/')) {
                photo_image.setAttribute('href', '');
                photo_placeholder.style.display = '';
                return;
            }

            const reader = new FileReader();
            reader.onload = function () {
                photo_image.setAttribute('href', reader.result);
                photo_placeholder.style.display = 'none';
            };
            reader.readAsDataURL(file);
        });
    }

    // Fonts load a bit later: fit the texts again once they are ready.
    if (document.fonts) {
        document.fonts.ready.then(function () {
            preview.querySelectorAll('[data-preview-text]').forEach(fit_text);
        });
    }
}


// ---------- 7. "Are you sure?" before a delete (admin pages) ----------
// A form with data-confirm="Question ?" is only sent if the user clicks OK.
document.querySelectorAll('form[data-confirm]').forEach(function (form) {
    form.addEventListener('submit', function (event) {
        if (!confirm(form.dataset.confirm)) {
            event.preventDefault();
        }
    });
});


// ---------- 8. Newsletter: quick check before sending ----------
// This only helps the user. The real check is done in PHP (index.php).
const newsletter_form = document.querySelector('[data-newsletter-form]');

if (newsletter_form) {
    const error_box = document.querySelector('[data-newsletter-error]');

    newsletter_form.addEventListener('submit', function (event) {
        const email = newsletter_form.newsletter_email.value.trim();
        const email_pattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

        if (!email_pattern.test(email)) {
            event.preventDefault(); // stop the form
            error_box.textContent = 'Merci d\'entrer une adresse email valide.';
            error_box.hidden = false;
        }
    });
}

// ---------- 9. Login / register forms (pages/login.php, pages/register.php) ----------
// All of this only HELPS the user. The real checks are done in PHP.

// a) "Afficher / Masquer" button next to each password field
document.querySelectorAll('[data-password-toggle]').forEach(function (button) {
    const input = button.parentElement.querySelector('input');

    button.addEventListener('click', function () {
        const is_hidden = input.type === 'password';
        input.type = is_hidden ? 'text' : 'password';
        button.textContent = is_hidden ? 'Masquer' : 'Afficher';
        button.setAttribute('aria-label', is_hidden ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
    });
});

// b) Small helper: writes a message under a field (ok / bad / wait style)
function show_field_status(element, text, type) {
    element.textContent = text;
    element.classList.remove('is-ok', 'is-bad', 'is-wait');
    if (type) {
        element.classList.add('is-' + type);
    }
}

// c) Live check: is the username still available?
//    Asks register.php?check_username=... 400 ms after the user stops typing.
const username_input = document.querySelector('[data-username-check]');

if (username_input) {
    const username_status = document.querySelector('[data-username-status]');
    const username_pattern = /^[a-zA-Z0-9._-]{3,30}$/;
    let username_timer = null;

    username_input.addEventListener('input', function () {
        clearTimeout(username_timer);
        const username = username_input.value.trim();

        if (username === '') {
            show_field_status(username_status, '', null);
            return;
        }
        if (!username_pattern.test(username)) {
            show_field_status(username_status, '3 à 30 caractères : lettres, chiffres, point, tiret ou underscore.', 'bad');
            return;
        }

        show_field_status(username_status, 'Vérification…', 'wait');
        username_timer = setTimeout(function () {
            fetch('register.php?check_username=' + encodeURIComponent(username))
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (username_input.value.trim() !== username) {
                        return; // the user typed something else in the meantime
                    }
                    if (data.available) {
                        show_field_status(username_status, '✓ Ce nom d\'utilisateur est disponible', 'ok');
                    } else {
                        show_field_status(username_status, '✗ Ce nom d\'utilisateur est déjà utilisé', 'bad');
                    }
                })
                .catch(function () {
                    show_field_status(username_status, '', null); // no network: PHP will check anyway
                });
        }, 400);
    });
}

// d) Password strength meter (register page)
const password_input = document.querySelector('[data-password-strength]');

if (password_input) {
    const meter = document.querySelector('[data-strength-meter]');
    const meter_bar = meter.querySelector('.strength-bar');
    const meter_label = document.querySelector('[data-strength-label]');
    const labels = ['', 'Faible', 'Moyen', 'Bon', 'Excellent'];

    password_input.addEventListener('input', function () {
        const password = password_input.value;
        meter.hidden = password === '';

        // 1 point per rule respected, from 0 to 4
        let score = 0;
        if (password.length >= 8) score++;
        if (/[a-z]/.test(password) && /[A-Z]/.test(password)) score++;
        if (/[0-9]/.test(password)) score++;
        if (/[^A-Za-z0-9]/.test(password) || password.length >= 14) score++;
        if (password.length < 8) score = Math.min(score, 1);
        score = Math.max(score, 1);

        meter_bar.className = 'strength-bar strength-' + score;
        meter_label.textContent = labels[score];
    });
}

// e) "The two passwords match" message (register page)
const confirm_input = document.querySelector('[data-password-confirm]');

if (confirm_input && password_input) {
    const confirm_status = document.querySelector('[data-confirm-status]');

    function check_confirmation() {
        if (confirm_input.value === '') {
            show_field_status(confirm_status, '', null);
        } else if (confirm_input.value === password_input.value) {
            show_field_status(confirm_status, '✓ Les mots de passe correspondent', 'ok');
        } else {
            show_field_status(confirm_status, '✗ Les mots de passe sont différents', 'bad');
        }
    }

    confirm_input.addEventListener('input', check_confirmation);
    password_input.addEventListener('input', check_confirmation);
}

// f) Warning when Caps Lock is on (login page)
const caps_warning = document.querySelector('[data-caps-warning]');

if (caps_warning) {
    const login_password = caps_warning.parentElement.querySelector('input[type="password"], input[name="password"]');

    login_password.addEventListener('keyup', function (event) {
        const caps_on = event.getModifierState && event.getModifierState('CapsLock');
        show_field_status(caps_warning, caps_on ? '⇪ Attention : la touche Majuscule est activée' : '', caps_on ? 'bad' : null);
    });
}

// g) Avoid double submissions: disable the button once the form is sent
document.querySelectorAll('.auth-form').forEach(function (form) {
    form.addEventListener('submit', function () {
        const button = form.querySelector('.auth-submit');
        // small delay so the browser still sends the button value
        setTimeout(function () { button.disabled = true; }, 0);
    });
});
// Coming back with the browser's "Back" button: enable the buttons again
window.addEventListener('pageshow', function () {
    document.querySelectorAll('.auth-submit').forEach(function (button) { button.disabled = false; });
});


// ---------- 10. Message after login / logout (closes by itself) ----------
const auth_notice = document.querySelector('[data-auth-notice]');

if (auth_notice) {
    function hide_notice() {
        auth_notice.classList.add('is-hiding');
        setTimeout(function () { auth_notice.remove(); }, 300);
    }
    auth_notice.querySelector('[data-auth-notice-close]').addEventListener('click', hide_notice);
    setTimeout(hide_notice, 6000);
}
