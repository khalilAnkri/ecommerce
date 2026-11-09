

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


// ---------- 6. Newsletter: quick check before sending ----------
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
