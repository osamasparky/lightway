(function ($) {
    "use strict";

    // Sidebar ↔ filter drawer (below 992px)
    var lastOpener = null;

    function openDrawer($drawer, opener) {
        lastOpener = opener || null;
        $drawer.addClass('is-open');
        $('body').addClass('ms-drawer-open');
        $('.js-lw-drawer-open[aria-controls="' + $drawer.attr('id') + '"]').attr('aria-expanded', 'true');
        setTimeout(function () {
            $drawer.find('.lw-sidebar__head .js-lw-drawer-close').trigger('focus');
        }, 50);
    }

    function closeDrawer($drawer) {
        if (!$drawer.hasClass('is-open')) {
            return;
        }

        $drawer.removeClass('is-open');
        $('body').removeClass('ms-drawer-open');
        $('.js-lw-drawer-open[aria-controls="' + $drawer.attr('id') + '"]').attr('aria-expanded', 'false');

        if (lastOpener) {
            lastOpener.focus();
        }
    }

    $('body').on('click', '.js-lw-drawer-open', function () {
        var $drawer = $('#' + $(this).attr('aria-controls'));

        if ($drawer.length) {
            openDrawer($drawer, this);
        }
    });

    $('body').on('click', '.js-lw-drawer-close', function () {
        closeDrawer($(this).closest('.lw-sidebar'));
    });

    $(document).on('keydown', function (e) {
        if (e.key === 'Escape') {
            $('.lw-sidebar.is-open').each(function () {
                closeDrawer($(this));
            });
        }
    });

    if (window.matchMedia) {
        var desktop = window.matchMedia('(min-width: 992px)');
        var onChange = function (mq) {
            if (mq.matches) {
                $('.lw-sidebar.is-open').each(function () {
                    closeDrawer($(this));
                });
            }
        };

        if (desktop.addEventListener) {
            desktop.addEventListener('change', onChange);
        } else if (desktop.addListener) {
            desktop.addListener(onChange);
        }
    }

    // List / grid switch for result lists (remembered per browser; storage may be unavailable)
    function applyLayout(targetSelector, layout) {
        var $target = $(targetSelector);
        $target.toggleClass('is-grid', layout === 'grid');
        $('.js-lw-layout[data-target="' + targetSelector + '"]').each(function () {
            var active = $(this).data('layout') === layout;
            $(this).toggleClass('is-active', active).attr('aria-pressed', active ? 'true' : 'false');
        });
    }

    $('.js-lw-layout.is-active').each(function () {
        var target = $(this).data('target');
        var saved = null;
        try {
            saved = window.localStorage.getItem('lw-layout:' + target);
        } catch (e) {
        }
        if (saved === 'grid' || saved === 'list') {
            applyLayout(target, saved);
        }
    });

    $('body').on('click', '.js-lw-layout', function () {
        var target = $(this).data('target');
        var layout = $(this).data('layout');
        applyLayout(target, layout);
        try {
            window.localStorage.setItem('lw-layout:' + target, layout);
        } catch (e) {
        }
    });

    // Toolbar controls that submit their form immediately (toggles, sort select)
    $('body').on('change', '.js-lw-autosubmit', function () {
        var form = this.form || $(this).closest('form')[0];

        if (form) {
            form.submit();
        }
    });
})(jQuery);
