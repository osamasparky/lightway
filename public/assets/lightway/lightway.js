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

    // Toolbar controls that submit their form immediately (toggles, sort select)
    $('body').on('change', '.js-lw-autosubmit', function () {
        var form = this.form || $(this).closest('form')[0];

        if (form) {
            form.submit();
        }
    });
})(jQuery);
