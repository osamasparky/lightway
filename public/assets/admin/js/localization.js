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

    // Dashboard: the three main actions follow the chosen target language.
    $('.js-lz-target-lang').on('change', function () {
        var locale = encodeURIComponent(this.value);
        $('.js-lz-target-link').each(function () {
            var href = String($(this).data('href'));
            $(this).attr('href', href.indexOf('__LOCALE__') !== -1 ? href.replace('__LOCALE__', locale) : href + locale);
        });
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

            renderIssues($row, data.issues || []);
            if (!data.pending) {
                $row.find('.js-lz-pending').remove();
            }
            if (data.origin !== 'ai' && data.origin !== 'memory' || data.state === 'reviewed' || data.state === 'missing') {
                $row.find('.js-lz-reject').remove();
            }

            $row.addClass('is-saved');
            setTimeout(function () {
                $row.removeClass('is-saved');
            }, 1400);

            updateStats(data.stats);
        };

        var renderIssues = function ($row, issues) {
            var $list = $row.find('.js-lz-issues');
            if (!issues.length) {
                $list.remove();
                return;
            }
            if (!$list.length) {
                $list = $('<ul class="lz-issues js-lz-issues">').appendTo($row.find('.js-lz-target-cell'));
            }
            $list.empty().append(issues.map(function (issue) {
                return $('<li>').text(issue);
            }));
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
                    $box.find('.lz-suggestion__issues').remove();
                    if (data.issues && data.issues.length) {
                        $('<ul class="lz-issues lz-suggestion__issues">').append(data.issues.map(function (issue) {
                            return $('<li>').text(issue);
                        })).insertAfter($box.find('.lz-suggestion__text'));
                    }
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
                    syncBulk();
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
            var count = $table.find('.js-lz-select:checked').length;
            var $label = $('.js-lz-selected-count');
            $('.js-lz-bulk-review').prop('disabled', !count);
            $label.toggleClass('d-none', !count).text(String($label.data('template') || '').replace('__COUNT__', count));
            $table.find('.lz-row').each(function () {
                $(this).toggleClass('is-selected', $(this).find('.js-lz-select').is(':checked'));
            });
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

        /* Suggestion kept aside for approved / human text: accept or reject */
        $table.on('click', '.js-lz-suggestion-action', function () {
            var $btn = $(this);
            var $row = rowOf(this);
            $btn.closest('.js-lz-pending').find('button').prop('disabled', true);

            $.post(base + '/suggestion', {hash: $row.data('hash'), action: $btn.data('action')})
                .done(function (data) {
                    applyRow($row, data);
                    $row.find('.js-lz-pending').remove();
                    toast(t('saved'));
                })
                .fail(function (xhr) {
                    $btn.closest('.js-lz-pending').find('button').prop('disabled', false);
                    toast(errorMessage(xhr, t('save_failed')), false);
                });
        });

        /* Reject a machine translation (the string becomes missing again) */
        $table.on('click', '.js-lz-reject', function () {
            var $row = rowOf(this);
            if (!window.confirm($(this).data('confirm'))) {
                return;
            }

            $.post(base + '/reject', {hash: $row.data('hash')})
                .done(function (data) {
                    applyRow($row, data);
                    $row.find('.js-lz-reject, .js-lz-review').remove();
                    toast(t('saved'));
                })
                .fail(function (xhr) {
                    toast(errorMessage(xhr, t('save_failed')), false);
                });
        });

        /* Approve all: every string waiting for review that matches the file/search filters, on all pages */
        $('.js-lz-approve-all').on('click', function () {
            var $btn = $(this);
            if (!window.confirm($btn.data('confirm'))) {
                return;
            }
            $btn.prop('disabled', true).addClass('is-loading');

            $.post($btn.data('url'), {group: $btn.data('group') || '', q: $btn.data('q') || ''})
                .done(function (data) {
                    updateStats(data.stats);
                    toast(data.message);
                    setTimeout(function () {
                        window.location.reload();
                    }, 1200);
                })
                .fail(function (xhr) {
                    $btn.prop('disabled', false).removeClass('is-loading');
                    toast(errorMessage(xhr, t('save_failed')), false);
                });
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
                if (ctx.module) {
                    add(t('context_module'), $('<span>').text(ctx.module));
                }
                if (ctx.ui_kind) {
                    add(t('context_kind'), $('<span>').text(ctx.ui_kind));
                }
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

                if (ctx.memory && ctx.memory.length) {
                    var $memory = $('<div>');
                    ctx.memory.forEach(function (match) {
                        var $use = $('<button type="button" class="btn btn-sm btn-outline-primary ml-2">').text(t('use_this'));
                        $use.on('click', function () {
                            $('#lzContextModal').modal('hide');
                            var $editor = openEditor($row);
                            $editor.find('.lz-editor__input').val(match.text).trigger('focus');
                        });
                        $memory.append($('<div class="lz-context-lang d-flex align-items-start justify-content-between">').append(
                            $('<div>').append(
                                $('<div class="lz-text">').attr('dir', $table.data('dir')).text(match.text),
                                $('<div class="small text-muted">').text(match.keys.join(', ') + (match.count > match.keys.length ? ' …' : ''))
                            ),
                            $table.data('can-edit') ? $use : null
                        ));
                    });
                    add(t('memory_matches'), $memory);
                }

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
                return ['target', 'scope', 'groups[]', 'quality_mode', '_token'].indexOf(f.name) !== -1;
            });

            $.post($wizard.data('preview'), $.param(data)).done(function (p) {
                ['total', 'translated', 'missing', 'ai', 'reviewed', 'needs_review', 'outdated', 'invalid', 'selected'].forEach(function (name) {
                    $('.js-lz-preview-' + name).text(fmt(p[name]));
                });
                $('.js-lz-preview-requests').text(fmt(p.estimate.requests));
                $('.js-lz-preview-qa_tokens').text(p.estimate.qa_input_tokens ? '~' + fmt(p.estimate.qa_input_tokens) + ' / ~' + fmt(p.estimate.qa_output_tokens) : '—');
                $('.js-lz-preview-model').text(p.model + (p.qa_model && p.mode !== 'economy' ? ' · QA: ' + p.qa_model : ''));
                $('.js-lz-preview-profile').text(p.profile);
                $('.js-lz-over-cost').toggleClass('d-none', !p.over_cost_cap);
                $('.js-lz-preview-batches').text(fmt(p.estimate.batches));
                $('.js-lz-preview-input_tokens').text('~' + fmt(p.estimate.input_tokens));
                $('.js-lz-preview-output_tokens').text('~' + fmt(p.estimate.output_tokens));
                $('.js-lz-preview-cost').text(p.estimate.cost === null ? t('no_cost') : '~$' + Number(p.estimate.cost).toFixed(2));

                var $confirm = $('.js-lz-confirm-text');
                $confirm.text(String($confirm.data('template')).replace('__COUNT__', fmt(p.selected)));

                $('.js-lz-over-limit').toggleClass('d-none', !p.over_limit).text(p.over_limit ? t('over_limit').replace('__LIMIT__', fmt(p.limit)) : '');
                $('.js-lz-start').prop('disabled', p.selected < 1 || p.over_limit || p.over_cost_cap || $('.js-lz-start').data('locked') === true);
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
                $('.js-lz-job-needs_review').text(Number(p.needs_review || 0).toLocaleString());
                $('.js-lz-job-memory_hits').text(Number(p.memory_hits || 0).toLocaleString());
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
    $('.js-lz-model').on('change', function () {
        var $custom = $(this).data('custom') ? $($(this).data('custom')) : $(this).closest('.form-group').find('.js-lz-model-custom');
        $custom.toggleClass('d-none', this.value !== '__custom');
    });

    // Settings tabs: switched here (not by the theme's tab plugin), one pane at a time,
    // and the open tab is kept in the URL for reloads and after saving.
    $('.js-lz-settings-tab').on('click', function (e) {
        e.preventDefault();
        var tab = $(this).data('tab');

        $('.js-lz-settings-tab').removeClass('active').attr('aria-selected', 'false');
        $(this).addClass('active').attr('aria-selected', 'true');
        $('.lz-settings__content > .tab-pane').removeClass('show active');
        $('#lz-pane-' + tab).addClass('show active');

        if (window.history && window.history.replaceState) {
            var url = new URL(window.location.href);
            url.searchParams.set('tab', tab);
            url.hash = '';
            window.history.replaceState(null, '', url.toString());
        }
    });

    // Glossary: show the fields each term type uses.
    var syncTermType = function () {
        var type = $('.js-lz-term-type').val();
        $('.js-lz-term-translation').toggleClass('d-none', type === 'do_not_translate' || type === 'brand')
            .find('input').prop('required', ['preferred', 'technical', 'context', 'forbidden'].indexOf(type) !== -1);
        $('.js-lz-term-context').toggleClass('d-none', type !== 'context')
            .find('input').prop('required', type === 'context');
    };
    if ($('.js-lz-term-type').length) {
        $('.js-lz-term-type').on('change', syncTermType);
        syncTermType();
    }

    // Enable the test as soon as a key is typed; the typed key is tested without saving it.
    $('#lzApiKey').on('input', function () {
        var $btn = $('.js-lz-test');
        $btn.prop('disabled', !this.value.trim() && Number($btn.data('has-saved')) !== 1);
    });

    $('.js-lz-test').on('click', function () {
        var $btn = $(this).prop('disabled', true);
        var $result = $('.js-lz-test-result').removeClass('is-ok is-error').text(t('loading'));

        $.post($btn.data('url'), {api_key: $('#lzApiKey').val() || ''})
            .done(function (r) {
                $result.addClass('is-ok').text(t('test_ok') + ' ' + (r.models.length ? t('models_found').replace(':count', r.models.length) : ''));
                // Refill the model dropdown with what this key can use, keeping the choice.
                $('.js-lz-model').each(function () {
                    var $select = $(this);
                    var current = $select.val();
                    var $other = $select.find('option[value=__custom]').detach();
                    $select.find('option').not(':first').remove();
                    r.models.forEach(function (m) {
                        $select.append($('<option>').attr('value', m).text(m));
                    });
                    $select.append($other);
                    if (current && $select.find('option').filter(function () { return this.value === current; }).length) {
                        $select.val(current);
                    }
                    $select.trigger('change');
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
