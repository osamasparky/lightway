{{-- Everything the original Translation Manager could do, kept here. --}}
<div class="card" id="lzTools">
    <div class="card-header">
        <h4>{{ trans('localization.tools_title') }}</h4>
        <div class="card-header-action">
            <a href="{{ url('/admin/translations') }}" class="small">{{ trans('localization.classic_editor') }} <i class="fas fa-external-link-alt"></i></a>
        </div>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-12 col-lg-4 lz-tool">
                <h6>{{ trans('localization.tool_sync') }}</h6>
                <p class="small text-muted">{{ trans('localization.tool_sync_hint') }}</p>
                <div class="d-flex flex-wrap lz-gap">
                    <button type="button" class="btn btn-sm btn-outline-primary" data-toggle="modal" data-target="#lzImportModal">{{ trans('localization.import') }}</button>
                    <form action="{{ getAdminPanelUrl('/localization/tools/find') }}" method="post">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-primary" title="{{ trans('localization.find_hint') }}">{{ trans('localization.find_in_code') }}</button>
                    </form>
                    <form action="{{ getAdminPanelUrl('/localization/tools/scan-usage') }}" method="post">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-primary" title="{{ trans('localization.scan_usage_hint') }}">{{ trans('localization.scan_usage') }}</button>
                    </form>
                </div>

                <h6 class="mt-4">{{ trans('localization.tool_publish') }}</h6>
                <form action="{{ getAdminPanelUrl('/localization/tools/publish') }}" method="post" class="d-flex lz-gap">
                    @csrf
                    <label class="sr-only" for="lzPublishGroup">{{ trans('localization.group') }}</label>
                    <select id="lzPublishGroup" name="group" class="form-control form-control-sm">
                        <option value="*">{{ trans('localization.all_groups') }}</option>
                        @foreach($groups as $group)
                            <option value="{{ $group }}">{{ $group }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn btn-sm btn-primary text-nowrap">{{ trans('localization.publish') }}</button>
                </form>
                <p class="small text-muted mt-1 mb-0">{{ trans('localization.publish_hint') }}</p>
            </div>

            <div class="col-12 col-lg-4 lz-tool">
                <h6>{{ trans('localization.tool_keys') }}</h6>
                <form action="{{ getAdminPanelUrl('/localization/tools/keys') }}" method="post">
                    @csrf
                    <div class="form-group mb-2">
                        <label for="lzKeysGroup" class="small">{{ trans('localization.group_new_or_existing') }}</label>
                        <input type="text" id="lzKeysGroup" name="group" class="form-control form-control-sm" list="lzGroupList" required pattern="[A-Za-z0-9_\-\/]+">
                        <datalist id="lzGroupList">
                            @foreach($groups as $group)
                                <option value="{{ $group }}">
                            @endforeach
                        </datalist>
                    </div>
                    <div class="form-group mb-2">
                        <label for="lzKeysList" class="small">{{ trans('localization.keys_one_per_line') }}</label>
                        <textarea id="lzKeysList" name="keys" rows="3" class="form-control form-control-sm" placeholder="welcome_title = Welcome back&#10;cta_button" required></textarea>
                    </div>
                    <button type="submit" class="btn btn-sm btn-primary">{{ trans('localization.add_keys') }}</button>
                </form>

                @can('admin_translation_manager_delete')
                    <h6 class="mt-4">{{ trans('localization.tool_delete_key') }}</h6>
                    <form action="{{ getAdminPanelUrl('/localization/tools/keys/delete') }}" method="post" class="js-lz-confirm" data-confirm="{{ trans('localization.delete_key_confirm') }}">
                        @csrf
                        <div class="d-flex lz-gap">
                            <label class="sr-only" for="lzDeleteGroup">{{ trans('localization.group') }}</label>
                            <select id="lzDeleteGroup" name="group" class="form-control form-control-sm" required>
                                @foreach($groups as $group)
                                    <option value="{{ $group }}">{{ $group }}</option>
                                @endforeach
                            </select>
                            <label class="sr-only" for="lzDeleteKey">{{ trans('localization.key') }}</label>
                            <input type="text" id="lzDeleteKey" name="key" class="form-control form-control-sm" placeholder="{{ trans('localization.key') }}" required>
                            <button type="submit" class="btn btn-sm btn-outline-danger">{{ trans('localization.delete') }}</button>
                        </div>
                    </form>
                @endcan
            </div>

            <div class="col-12 col-lg-4 lz-tool">
                <h6>{{ trans('localization.tool_locales') }}</h6>
                <p class="small text-muted">{{ trans('localization.tool_locales_hint') }}</p>
                <ul class="list-unstyled lz-locales">
                    @foreach($fileLocales as $fileLocale)
                        <li>
                            <code>{{ $fileLocale }}</code>
                            @if(isset($languages[$fileLocale]))
                                <span class="badge badge-success">{{ trans('localization.enabled_on_site') }}</span>
                            @else
                                <span class="badge badge-light">{{ trans('localization.not_on_site') }}</span>
                                @can('admin_translation_manager_delete')
                                    <form action="{{ getAdminPanelUrl('/localization/tools/locales/remove') }}" method="post" class="d-inline js-lz-confirm" data-confirm="{{ trans('localization.remove_locale_confirm', ['locale' => $fileLocale]) }}">
                                        @csrf
                                        <input type="hidden" name="locale" value="{{ $fileLocale }}">
                                        <button type="submit" class="btn btn-link btn-sm text-danger p-0 ml-1">{{ trans('localization.remove') }}</button>
                                    </form>
                                @endcan
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
</div>
