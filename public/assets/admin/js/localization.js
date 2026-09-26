/*
 * Translation Manager (admin/localization).
 * Workspace inline editing + AI suggestions, AI wizard estimate, job progress, settings test.
 * Uses the admin layout's jQuery (CSRF header is set globally by the layout).
 */
(function ($) {
    "use strict";

    var cfg = window.lzConfig || {text: {}};
    var t = function (key) {
        return (cfg.text && cfg.text[key]) || key;
    };

    function toast(message, success) {
        if ($.toast) {
            $.toast({
                heading: success === false ? '' : '',
                text: message,
                bgColor: success === false ? '#f63c3c' : '#43d477',
                textColor: 'white',
                hideAfter: 3500,
                position: 'bottom-right',
                icon: success === false ? 'error' : 'success'
            });
        }
    }

    function errorMessage(xhr, fallback) {
        return (xhr && xhr.responseJSON && (xhr.responseJSON.message || (xhr.responseJSON.errors && Object.values(xhr.responseJSON.errors)[0]))) || fallback;
    }

    /* ---------- Shared ---------- */
    $(document).on('submit', '.js-lz-confirm', function (e) {
        if (!window.confirm($(this).data('confirm') || t('confirm'))) {
            e.preventDefault();
        }
    });

    $(document).on('change', '.js-lz-autosubmit', function () {
        $(this).closest('form').trigger('submit');
    });

    $(document).on('click', '.js-lz-copy', function () {
        var text = $(this).data('copy');
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(function () {
                toast(t('copied'));
            });
        }
    });

    if ($.fn.select2) {
        $('.js-lz-select2').each(function () {
            $(this).select2({width: '100%', placeholder: $(this).data('placeholder'), allowClear: true});
        });
    }

    /* ---------- Workspace ---------- */
    var $table = $('.lz-workspace');

    if ($table.length) {
        var locale = $table.data('locale');
        var base = cfg.base + '/languages/' + encodeURIComponent(locale);
        var template = document.getElementById('lzEditorTemplate');

        var updateStats = function (stats) {
            if (!stats) {
                return;
            }
            ['total', 'translated', 'missing', 'needs_review', 'reviewed', 'ai'].forEach(function (name) {
                $('.js-lz-stat-' + name).text(Number(stats[name] || 0).toLocaleString());
            });
            $('.js-lz-percent').text(stats.percent + '%');
            $('.lz-summary .lz-progress__bar').css('width', stats.percent + '%');
            $('.lz-summary .lz-progress__value').text(stats.percent + '%');
        };

        var rowOf = function (el) {
            return $(el).closest('.lz-row');
        };

        var openEditor = function ($row) {
            var $cell = $row.find('.js-lz-target-cell');
            if ($cell.find('.lz-editor').length) {
                return $cell.find('.lz-editor');
            }

            var $editor = $(template.content.firstElementChild.cloneNode(true));
            $editor.find('.lz-editor__input').val($cell.find('.js-lz-value').val());
            $cell.find('.js-lz-display').addClass('d-none');
            $cell.append($editor);
            $editor.find('.lz-editor__input').trigger('focus');
            $row.addClass('is-editing');

            return $editor;
        };

        var closeEditor = function ($row) {
            $row.find('.lz-editor').remove();
            $row.find('.js-lz-display').removeClass('d-none');
            $row.removeClass('is-editing');
        };

        var applyRow = function ($row, data) {
            var $display = $row.find('.js-lz-display');
            $row.attr('data-state', data.state);
            $row.find('.js-lz-value').val(data.value || '');

            if (data.state === 'missing') {
                $display.addClass('is-missing').empty().append($('<span class="lz-missing">').text(data.state_label));
            } else {
                $display.removeClass('is-missing').text(data.value);
            }

            $row.find('.js-lz-state').attr('class', 'lz-badge lz-badge--' + data.state + ' js-lz-state').text(data.state_label);

            var $origin = $row.find('.js-lz-origin');
            if (data.origin_label) {
                if (!$origin.length) {
                    $origin = $('<span class="lz-origin js-lz-origin">').insertAfter($row.find('.js-lz-state'));
                }
                $origin.text(data.origin_label);
            } else {
                $origin.remove();
            }

            if (data.unpublished && !$row.find('.js-lz-unpublished').length) {
                $row.find('.lz-col-status').append(' <span class="lz-unpublished js-lz-unpublished"><i class="fas fa-circle"></i></span>');
            }

            $row.addClass('is-saved');
            setTimeout(function () {
                $row.removeClass('is-saved');
            }, 1400);

            updateStats(data.stats);
        };

        var save = function ($row, reviewed, force) {
            var $editor = $row.find('.lz-editor');
            var $warning = $editor.find('.lz-editor__warning');
            var value = $editor.find('.lz-editor__input').val();

            $editor.find('button').prop('disabled', true);
            $warning.addClass('d-none').empty();

            $.post(base + '/save', {hash: $row.data('hash'), value: value, reviewed: reviewed ? 1 : 0, force: force ? 1 : 0})
                .done(function (data) {
                    closeEditor($row);
                    applyRow($row, data);
                    toast(t('saved'));
                })
                .fail(function (xhr) {
                    $editor.find('button').prop('disabled', false);
                    if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.error === 'placeholders') {
                        var $force = $('<button type="button" class="btn btn-sm btn-outline-danger ml-2">').text(t('placeholder_force'));
                        $force.on('click', function () {
                            save($row, reviewed, true);
                        });
                        $warning.removeClass('d-none').text(xhr.responseJSON.message).append($force);
                        return;
                    }
                    toast(errorMessage(xhr, t('save_failed')), false);
                });
        };

        var suggest = function ($row) {
            var $editor = openEditor($row);
            var $box = $editor.find('.lz-suggestion');

            $box.removeClass('d-none is-error').addClass('is-loading');
            $box.find('.lz-suggestion__text').text(t('ai_working'));
            $box.find('.lz-suggestion__actions button').prop('disabled', true);

            $.post(base + '/suggest', {hash: $row.data('hash')})
                .done(function (data) {
                    $box.removeClass('is-loading').data('suggestion', data.suggestion);
                    $box.find('.lz-suggestion__text').text(data.suggestion);
                    $box.find('.lz-suggestion__actions button').prop('disabled', false);
                })
                .fail(function (xhr) {
                    $box.removeClass('is-loading').addClass('is-error');
                    $box.find('.lz-suggestion__text').text(errorMessage(xhr, t('ai_failed')));
                    $box.find('.js-lz-regenerate, .js-lz-reject').prop('disabled', false);
                });
        };

        var review = function (hashes, $rows) {
            $.post(base + '/review', {hashes: hashes})
                .done(function (data) {
                    $rows.each(function () {
                        var $row = $(this);
                        if ($row.attr('data-state') !== 'missing') {
                            $row.attr('data-state', 'reviewed');
                            $row.find('.js-lz-state').attr('class', 'lz-badge lz-badge--reviewed js-lz-state').text(data.state_label);
                            $row.find('.js-lz-review').remove();
                        }
                        $row.find('.js-lz-select').prop('checked', false);
                    });
                    $('.js-lz-select-all').prop('checked', false);
                    $('.js-lz-bulk-review').prop('disabled', true);
                    updateStats(data.stats);
                    toast(t('reviewed') + ' (' + data.reviewed + ')');
                })
                .fail(function (xhr) {
                    toast(errorMessage(xhr, t('save_failed')), false);
                });
        };

        $table.on('click', '.js-lz-edit', function () {
            openEditor(rowOf(this));
        });
        $table.on('click', '.js-lz-cancel', function () {
            closeEditor(rowOf(this));
        });
        $table.on('click', '.js-lz-save', function () {
            save(rowOf(this), false, false);
        });
        $table.on('click', '.js-lz-save-review', function () {
            save(rowOf(this), true, false);
        });
        $table.on('click', '.js-lz-copy-source', function () {
            var $row = rowOf(this);
            $row.find('.lz-editor__input').val($row.find('.js-lz-source').text()).trigger('focus');
        });
        $table.on('click', '.js-lz-ai, .js-lz-ai-inline, .js-lz-regenerate', function () {
            suggest(rowOf(this));
        });
        $table.on('click', '.js-lz-reject', function () {
            rowOf(this).find('.lz-suggestion').addClass('d-none');
        });
        $table.on('click', '.js-lz-accept', function () {
            var $row = rowOf(this);
            var suggestion = $row.find('.lz-suggestion').data('suggestion');
            if (suggestion) {
                $row.find('.lz-editor__input').val(suggestion);
                $row.find('.lz-suggestion').addClass('d-none');
                save($row, false, false);
            }
        });
        $table.on('keydown', '.lz-editor__input', function (e) {
            if (e.key === 'Escape') {
                closeEditor(rowOf(this));
            } else if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
                e.preventDefault();
                save(rowOf(this), false, false);
            }
        });

        $table.on('click', '.js-lz-review', function () {
            var $row = rowOf(this);
            review([$row.data('hash')], $row);
        });

        var syncBulk = function () {
            $('.js-lz-bulk-review').prop('disabled', !$table.find('.js-lz-select:checked').length);
        };
        $table.on('change', '.js-lz-select', syncBulk);
        $table.on('change', '.js-lz-select-all', function () {
            $table.find('.js-lz-select').prop('checked', this.checked);
            syncBulk();
        });
        $('.js-lz-bulk-review').on('click', function () {
            var $checked = $table.find('.js-lz-select:checked');
            review($checked.map(function () {
                return this.value;
            }).get(), $checked.closest('.lz-row'));
        });

        /* Context */
        $table.on('click', '.js-lz-context', function () {
            var $row = rowOf(this);
            var $body = $('.js-lz-context-body').empty().append($('<p class="text-muted">').text(t('loading')));
            $('#lzContextModal').modal('show');

            $.getJSON(base + '/context/' + $row.data('hash')).done(function (ctx) {
                var $dl = $('<dl class="lz-details">');
                var add = function (label, $value) {
                    $dl.append($('<dt>').text(label), $('<dd>').append($value));
                };

                add('Key', $('<code>').text(ctx.key));
                add(t('file'), $('<code>').text(ctx.file));
                add(t('source_text'), $('<div class="lz-text">').text(ctx.source));
                add(t('placeholders'), ctx.placeholders.length
                    ? $('<div class="lz-tokens">').append(ctx.placeholders.map(function (p) {
                        return $('<code class="lz-token">').text(p);
                    }))
                    : $('<span class="text-muted">').text(t('none')));

                add(t('used_in'), ctx.usages.length
                    ? $('<ul class="list-unstyled mb-0">').append(ctx.usages.map(function (u) {
                        return $('<li>').append($('<code>').text(u));
                    }))
                    : $('<span class="text-muted">').text(t('not_used')));

                var $langs = $('<div>');
                ctx.languages.forEach(function (lang) {
                    $langs.append($('<div class="lz-context-lang">').append(
                        $('<span class="badge badge-light">').text(lang.label),
                        $('<div class="lz-text">').attr('dir', lang.dir).text(lang.value)
                    ));
                });
                add(t('other_languages'), $langs);

                $body.empty().append($dl);
            }).fail(function (xhr) {
                $body.empty().append($('<p class="text-danger">').text(errorMessage(xhr, t('save_failed'))));
            });
        });
    }

    /* ---------- AI wizard ---------- */
    var $wizard = $('.js-lz-wizard');

    if ($wizard.length) {
        var timer = null;
        var fmt = function (n) {
            return Number(n || 0).toLocaleString();
        };

        var refresh = function () {
            var scope = $wizard.find('input[name=scope]:checked').val();
            $('.js-lz-overwrite').toggleClass('d-none', scope !== 'retranslate');
            $('#lzConfirmOverwrite').prop('required', scope === 'retranslate');

            var data = $wizard.serializeArray().filter(function (f) {
                return ['target', 'scope', 'groups[]', '_token'].indexOf(f.name) !== -1;
            });

            $.post($wizard.data('preview'), $.param(data)).done(function (p) {
                ['total', 'translated', 'missing', 'ai', 'reviewed', 'selected'].forEach(function (name) {
                    $('.js-lz-preview-' + name).text(fmt(p[name]));
                });
                $('.js-lz-preview-batches').text(fmt(p.estimate.batches));
                $('.js-lz-preview-input_tokens').text('~' + fmt(p.estimate.input_tokens));
                $('.js-lz-preview-output_tokens').text('~' + fmt(p.estimate.output_tokens));
                $('.js-lz-preview-cost').text(p.estimate.cost === null ? t('no_cost') : '~$' + Number(p.estimate.cost).toFixed(2));

                var $confirm = $('.js-lz-confirm-text');
                $confirm.text(String($confirm.data('template')).replace('__COUNT__', fmt(p.selected)));

                $('.js-lz-over-limit').toggleClass('d-none', !p.over_limit).text(p.over_limit ? t('over_limit').replace('__LIMIT__', fmt(p.limit)) : '');
                $('.js-lz-start').prop('disabled', p.selected < 1 || p.over_limit || $('.js-lz-start').data('locked') === true);
            });
        };

        if ($('.js-lz-start').is(':disabled')) {
            $('.js-lz-start').data('locked', true);
        }

        $wizard.on('change', '.js-lz-preview-input', function () {
            clearTimeout(timer);
            timer = setTimeout(refresh, 250);
        });

        refresh();
    }

    /* ---------- Job progress ---------- */
    var $job = $('.js-lz-job');

    if ($job.length && Number($job.data('active')) === 1) {
        var poll = function () {
            $.getJSON($job.data('progress')).done(function (p) {
                $('.js-lz-job-percent').text(p.percent);
                $('.js-lz-job-bar').css('width', p.percent + '%').closest('[role=progressbar]').attr('aria-valuenow', p.percent);
                $('.js-lz-job-done').text(Number(p.completed + p.failed).toLocaleString());
                $('.js-lz-job-completed').text(Number(p.completed).toLocaleString());
                $('.js-lz-job-remaining').text(Number(p.remaining).toLocaleString());
                $('.js-lz-job-failed').text(Number(p.failed).toLocaleString());
                $('.js-lz-job-batches').text(p.batches);
                $('.js-lz-job-tokens').text(Number(p.tokens).toLocaleString());
                $('.js-lz-job-status').text(p.status_label);
                $('.js-lz-job-error').toggleClass('d-none', !p.last_error);
                $('.js-lz-job-error-text').text(p.last_error || '');

                if (p.finished || p.status === 'paused') {
                    window.location.reload();
                    return;
                }
                setTimeout(poll, 3000);
            }).fail(function () {
                setTimeout(poll, 10000);
            });
        };
        setTimeout(poll, 2000);
    }

    /* ---------- Settings: test connection ---------- */
    $('.js-lz-test').on('click', function () {
        var $btn = $(this).prop('disabled', true);
        var $result = $('.js-lz-test-result').removeClass('is-ok is-error').text(t('loading'));

        $.post($btn.data('url'))
            .done(function (r) {
                $result.addClass('is-ok').text(t('test_ok') + ' ' + (r.models.length ? t('models_found').replace(':count', r.models.length) : ''));
                var $list = $('#lzModels').empty();
                r.models.forEach(function (m) {
                    $list.append($('<option>').attr('value', m));
                });
            })
            .fail(function (xhr) {
                $result.addClass('is-error').text(t('test_failed') + ' ' + errorMessage(xhr, ''));
            })
            .always(function () {
                $btn.prop('disabled', false);
            });
    });
})(jQuery);
