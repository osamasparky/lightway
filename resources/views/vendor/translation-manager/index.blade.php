@extends('admin.layouts.app')

@push('styles_top')
    {{-- <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap.min.css"> --}}

    <link href="//cdnjs.cloudflare.com/ajax/libs/x-editable/1.5.0/bootstrap3-editable/css/bootstrap-editable.css"
        rel="stylesheet" />

    <style>
        a.status-1 {
            font-weight: bold;
        }

        .translation-manager-card {
            background: #fff;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, .05);
        }

        .table thead th {
            background: #fff;
        }

        .group-select {
            height: 45px !important;
            padding: 10px 12px !important;
            font-size: 14px !important;
            line-height: 1.5 !important;
        }

        .group-select option {
            padding: 10px;
        }

        .tanslation-import-select {
            height: 45px !important;
            padding: 10px 12px !important;
            font-size: 14px !important;
            line-height: 1.5 !important;
        }

        .tanslation-import-select option {
            padding: 10px;
        }
        .editable {
          display: block;
          min-height: 38px;
          padding: 8px 10px;
          color: #333 !important;
          text-decoration: none !important;
          border-radius: 6px;
        }
 
        .editable:hover {
            background-color: #f5f5f5;
        }

        .editable-empty {
            color: #999 !important;
            font-style: italic;
        }

        .editable-click {
            border-bottom: none !important;
        }

        .table-responsive {
            overflow-x: auto;
        }

        .translation-manager-card {
            overflow: hidden;
        }
        .editableform .form-control {
            height: 36px;
            padding: 6px 12px;
            border-radius: 6px;
        }

        .editable-buttons {
            display: inline-flex !important;
            align-items: center;
            gap: 6px;
            margin-left: 8px;
        }

        .editable-buttons .btn {
            width: 34px;
            height: 34px;
            padding: 0;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .editable-buttons .btn-primary {
            background: #28a745;
            border-color: #28a745;
        }

        .editable-buttons .btn-default {
            background: #dc3545;
            border-color: #dc3545;
            color: #fff;
        }

        .editable-input {
            width: 250px;
        }

        .editable-container.editable-inline {
            display: flex !important;
            align-items: center;
        }
        .editable-buttons button {
            font-size: 0 !important;
            position: relative;
        }

        .editable-buttons .editable-submit::before {
            content: "✓";
            font-size: 16px;
            color: #fff;
        }

        .editable-buttons .editable-cancel::before {
            content: "✕";
            font-size: 16px;
            color: #fff;
        }
    </style>
@endpush

@section('content')
    <div class="container-fluid mt-4">

        <div class="translation-manager-card">

            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>{{ trans('admin/main.translation_manager') }}</h2>
            </div>

            <div class="alert alert-warning">
                {{-- Warning, translations are not visible until they are exported back to the app/lang file,
                using <code>php artisan translation:export</code> command or publish button. --}}
                {{ trans('admin/main.translation_warning') }}
            </div>

            <div class="alert alert-success success-import" style="display:none;">
                {{-- Done importing, processed <strong class="counter">N</strong> items! --}}
                {{ trans('admin/main.done_importing_processed') }}
                 <strong class="counter">N</strong>
                {{ trans('admin/main.items') }}
            </div>

            {{-- <div class="alert alert-success success-find" style="display:none;">
                Done searching for translations, found <strong class="counter">N</strong> items!
            </div> --}}
              <div class="alert alert-success success-find" style="display:none;">
                {{ trans('admin/main.done_searching_found') }}
                <strong class="counter">N</strong>
                {{ trans('admin/main.items') }}
              </div>

            <div class="alert alert-success success-publish" style="display:none;">
                {{ trans('admin/main.done_publishing_translations') }}
            </div>

            <div class="alert alert-success success-publish-all" style="display:none;">
                {{ trans('admin/main.done_publishing_all_translations') }}
            </div>

            @if (!isset($group))
                <form class="form-import mb-3" method="POST" action="{{ url('/admin/translations/import') }}">

                    @csrf

                    <div class="row">
                        <div class="col-md-3">
                            <select name="replace" class="form-control tanslation-import-select">
                                <option value="0">
                                    {{ trans('admin/main.append_new_translations') }}
                                </option>
                                <option value="1">
                                    {{ trans('admin/main.replace_existing_translations') }}
                                </option>
                            </select>
                        </div>

                        <div class="col-md-2">
                             <button type="submit" class="btn btn-success btn-block">
                                {{ trans('admin/main.import_groups') }}
                            </button>
                        </div>
                    </div>
                </form>

                <form class="form-find mb-4" method="POST" action="{{ url('/admin/translations/find') }}">

                    @csrf

                      <button type="submit" class="btn btn-info">
                        {{ trans('admin/main.find_translations_in_files') }}
                    </button>
                </form>
            @endif

            @if (isset($group))
                <form class="form-inline mb-4 form-publish" method="POST"
                    action="{{ url('/admin/translations/publish/' . $group) }}" data-remote="true">

                    @csrf

                    <button type="submit" class="btn btn-primary">
                        {{ trans('admin/main.publish_translations') }}
                      </button>

                      <a href="{{ url('/admin/translations') }}" class="btn btn-secondary ml-2">
                        {{ trans('admin/main.back') }}
                    </a>
                </form>
            @endif

            <form method="POST" action="{{ url('/admin/translations/groups/add') }}">

                @csrf

                <div class="form-group">
                    <label>{{ trans('admin/main.select_group') }}</label>

                    <select name="group" id="group" class="form-control group-select">

                        @foreach ($groups as $key => $value)
                            <option value="{{ $key }}" {{ $key == $group ? 'selected' : '' }}>
                                {{ $value }}
                            </option>
                        @endforeach

                    </select>
                </div>

                <div class="form-group">
                    <label>{{ trans('admin/main.new_group_name') }}</label>

                    <input type="text" class="form-control" name="new-group">
                </div>

                <button type="submit" class="btn btn-dark">
                    {{ trans('admin/main.add_and_edit_keys') }}
                </button>

            </form>

            @if ($group)
                <hr>

                <form method="POST" action="{{ url('/admin/translations/add/' . $group) }}">

                    @csrf

                    <div class="form-group">
                        <label>{{ trans('admin/main.add_new_keys') }}</label>

                        <textarea class="form-control" rows="4" name="keys" placeholder="Add 1 key per line"></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        {{ trans('admin/main.add_keys') }}
                    </button>

                </form>

                <hr>

                <h4>
                    {{ trans('admin/main.total') }}: {{ $numTranslations }},
                    {{ trans('admin/main.changed') }}: {{ $numChanged }}
                </h4>

                @php
                    $path = lang_path();

                    $locales = array_filter($locales, function ($locale) use ($path) {
                        return is_dir($path . '/' . $locale);
                    });

                    $locales = array_values($locales);
                @endphp
                <div class="table-responsive">

                    <table class="table table-bordered">

                        <thead>
                            <tr>

                                <th width="20%">{{ trans('admin/main.key') }}</th>

                                @foreach ($locales as $locale)
                                    <th>{{ $locale }}</th>
                                @endforeach

                                @if ($deleteEnabled)
                                    <th width="60"></th>
                                @endif

                            </tr>
                        </thead>

                        <tbody>

                            @foreach ($translations as $key => $translation)
                                <tr id="{{ $key }}">

                                    <td>
                                        {{ $key }}
                                    </td>

                                    @foreach ($locales as $locale)
                                        @php
                                            $t = $translation[$locale] ?? null;
                                        @endphp

                                        <td>

                                            <a href="#edit"
                                                class="editable status-{{ $t ? (int) $t->status : 0 }} locale-{{ $locale }}"
                                                data-locale="{{ $locale }}" data-name="{{ $locale . '|' . $key }}"
                                                data-type="textarea" data-pk="{{ $t ? (int) $t->id : 0 }}"
                                                data-url="{{ url('/admin/translations/edit/' . $group) }}"
                                                data-title="Enter translation">

                                                {!! $t && !empty($t->value) ? e($t->value, false) : 'Empty' !!}

                                            </a>

                                        </td>
                                    @endforeach

                                    @if ($deleteEnabled)
                                        <td>

                                            <a href="{{ url('/admin/translations/delete/' . $group . '/' . $key) }}"
                                                class="delete-key">

                                                <i class="fa fa-trash"></i>

                                            </a>

                                        </td>
                                    @endif

                                </tr>
                            @endforeach

                        </tbody>

                    </table>

                </div>
            @endif

        </div>

    </div>
@endsection

@push('scripts_bottom')
    {{-- <script src="//code.jquery.com/jquery-1.11.0.min.js"></script> --}}

    {{-- <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/js/bootstrap.min.js"></script> --}}

    <script src="//cdnjs.cloudflare.com/ajax/libs/x-editable/1.5.0/bootstrap3-editable/js/bootstrap-editable.min.js">
    </script>

    <script src="//cdnjs.cloudflare.com/ajax/libs/jquery-cookie/1.4.1/jquery.cookie.js"></script>

    <script>
        $(document).ready(function() {

            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            });
               $(document).on('click', '.editable', function () {
                let el = $(this);

                if (!el.data('editable')) {

                    el.editable({
                        mode: 'inline',
                        success: function(response, newValue) {

                            let currentUrl = window.location.pathname;

                            setTimeout(function () {
                                window.location.href = currentUrl;
                            }, 200);
                        }
                    }).on('hidden', function(e, reason) {

                        let locale = $(this).data('locale');

                        if (reason === 'save') {
                            $(this)
                                .removeClass('status-0')
                                .addClass('status-1');
                        }

                    });
                }

                el.editable('show');
            });
            $('.group-select').on('change', function() {

                let group = $(this).val();

                if (group) {
                    window.location.href =
                        "{{ url('/admin/translations/view') }}/" + group;
                } else {
                    window.location.href =
                        "{{ url('/admin/translations') }}";
                }

            });

            $("a.delete-key").on('click', function(e) {

                e.preventDefault();

                if (confirm('Delete this translation key?')) {

                    let row = $(this).closest('tr');
                    let url = $(this).attr('href');

                    $.post(url, {}, function() {
                        row.remove();
                    });

                }

            });

            $('.form-import').on('submit', function(e) {

                e.preventDefault();

                let form = $(this);

                $.ajax({
                    url: form.attr('action'),
                    type: 'POST',
                    data: form.serialize(),

                    beforeSend: function() {

                        form.find('button')
                            .prop('disabled', true)
                            .text('Importing...');
                    },

                    success: function(data) {

                        $('div.success-import strong.counter')
                            .text(data.counter);

                        $('div.success-import')
                            .slideDown();

                        setTimeout(function() {
                            window.location.reload();
                        }, 1000);
                    },

                    error: function(xhr) {
                        console.log(xhr.responseText);
                        alert('Import failed');
                    },

                    complete: function() {

                        form.find('button')
                            .prop('disabled', false)
                            .text('Import groups');
                    }
                });

            });

            $('.form-find').on('submit', function(e) {

                e.preventDefault();

                let form = $(this);

                $.ajax({
                    url: form.attr('action'),
                    type: 'POST',
                    data: form.serialize(),

                    beforeSend: function() {

                        form.find('button')
                            .prop('disabled', true)
                            .text('Searching...');
                    },

                    success: function(data) {

                        $('div.success-find strong.counter')
                            .text(data.counter);

                        $('div.success-find')
                            .slideDown();

                        setTimeout(function() {
                            window.location.reload();
                        }, 1000);
                    },

                    error: function(xhr) {
                        console.log(xhr.responseText);
                        alert('Find failed');
                    },

                    complete: function() {

                        form.find('button')
                            .prop('disabled', false)
                            .text('Find translations in files');
                    }
                });

            });

            $('.form-publish').on('submit', function(e) {

                e.preventDefault();

                let form = $(this);

                $.ajax({
                    url: form.attr('action'),
                    type: 'POST',
                    data: form.serialize(),

                    success: function() {

                        $('.success-publish').slideDown();

                        setTimeout(function() {
                            window.location.reload();
                        }, 1000);
                    }
                });

            });

        });
    </script>
@endpush
