(function ($) {
    "use strict";

    var $body = $('body');
    var $drawer = $('#msDrawer');
    var lastFocused = null;

    function closeDropdowns(except) {
        $('.ms-cats.is-open').not(except).removeClass('is-open')
            .find('.js-ms-dropdown-toggle').attr('aria-expanded', 'false');
    }

    // Categories dropdown (click / keyboard; hover-free so it also works on touch laptops)
    $body.on('click', '.js-ms-dropdown-toggle', function (e) {
        e.stopPropagation();
        var $item = $(this).closest('.ms-cats');
        var open = !$item.hasClass('is-open');

        closeDropdowns($item);
        $item.toggleClass('is-open', open);
        $(this).attr('aria-expanded', open ? 'true' : 'false');
    });

    $body.on('click', function (e) {
        if (!$(e.target).closest('.ms-cats').length) {
            closeDropdowns();
        }
    });

    // Mobile drawer
    function openDrawer() {
        lastFocused = document.activeElement;
        $drawer.addClass('is-open').attr('aria-hidden', 'false');
        $body.addClass('ms-drawer-open');
        $('.js-ms-drawer-open').attr('aria-expanded', 'true');
        setTimeout(function () {
            $drawer.find('.ms-drawer__head .js-ms-drawer-close').trigger('focus');
        }, 50);
    }

    function closeDrawer() {
        $drawer.removeClass('is-open').attr('aria-hidden', 'true');
        $body.removeClass('ms-drawer-open');
        $('.js-ms-drawer-open').attr('aria-expanded', 'false');
        if (lastFocused) {
            lastFocused.focus();
        }
    }

    $body.on('click', '.js-ms-drawer-open', openDrawer);
    $body.on('click', '.js-ms-drawer-close', closeDrawer);

    $(document).on('keydown', function (e) {
        if (e.key !== 'Escape') {
            return;
        }

        if ($drawer.hasClass('is-open')) {
            closeDrawer();
        } else {
            closeDropdowns();
        }
    });

    // Close the drawer if the viewport grows past the mobile breakpoint
    if (window.matchMedia) {
        var desktop = window.matchMedia('(min-width: 992px)');
        var onChange = function (mq) {
            if (mq.matches && $drawer.hasClass('is-open')) {
                closeDrawer();
            }
        };

        if (desktop.addEventListener) {
            desktop.addEventListener('change', onChange);
        } else if (desktop.addListener) {
            desktop.addListener(onChange);
        }
    }

    // The hero fills whatever is left of the first screen under the top bar + header
    // (and a top floating bar, if the admin enabled one).
    var hero = document.querySelector('.ms-hero');

    function setHeroOffset() {
        if (!hero) {
            return;
        }

        var offset = Math.max(0, Math.round(hero.getBoundingClientRect().top + window.pageYOffset));
        document.documentElement.style.setProperty('--ms-hero-offset', offset + 'px');
    }

    setHeroOffset();
    window.addEventListener('resize', setHeroOffset);
    window.addEventListener('load', setHeroOffset);

    // Shadow on the sticky header once the page scrolls
    var header = document.getElementById('msHeader');

    if (header) {
        var ticking = false;

        window.addEventListener('scroll', function () {
            if (ticking) {
                return;
            }

            ticking = true;
            window.requestAnimationFrame(function () {
                header.classList.toggle('is-stuck', header.getBoundingClientRect().top <= 0 && window.pageYOffset > 0);
                ticking = false;
            });
        }, {passive: true});
    }
})(jQuery);
