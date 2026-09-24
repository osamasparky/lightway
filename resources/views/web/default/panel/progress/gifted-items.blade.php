@extends(getTemplate() . '.panel.layouts.panel_layout')

@section('content')
    <div class="section-title mb-20">
        <h2 class="font-24 font-weight-bold">
            {{ trans('update.gifted_items') }}
        </h2>
    </div>

    <div class="panel-section-card py-20 px-25 mt-20">
      <div class="mb-25">
        <form method="GET" action="">
            <div class="row align-items-end">
                <div class="col-md-4">
                    <label class="font-14 text-dark-blue font-weight-500 mb-5">
                        {{ trans('update.search_by_user_name') }}
                    </label>
                    <input
                        type="text"
                        name="user"
                        class="form-control"
                        value="{{ request('user') }}"
                        placeholder="{{ trans('update.enter_user_name') }}"
                    >
                </div>

                <div class="col-md-2 mt-10 mt-md-0">

                    <button type="submit" class="btn btn-primary btn-block">
                         {{ trans('public.filter') }}
                    </button>

                </div>

                @if(request()->filled('user'))
                    <div class="col-md-2 mt-10 mt-md-0">

                        <a href="{{ url()->current() }}" class="btn btn-outline-secondary btn-block">
                          {{ trans('public.reset') }}
                        </a>

                    </div>
                @endif

            </div>

        </form>

    </div>
        <div class="row">
            <div class="col-12">
                <div class="table-responsive">

                    <table class="table custom-table text-center">

                        <thead>
                            <tr>
                                <th class="text-left text-gray">
                                    {{ trans('update.user_receiver') }}
                                </th>

                                <th class="text-center text-gray">
                                    {{ trans('update.gifted_item') }}
                                </th>

                                <th class="text-center text-gray">
                                    {{ trans('update.date') }}
                                </th>
                            </tr>
                        </thead>

                        <tbody>

                            @forelse($userAccesses as $access)
                                <tr>

                                    {{-- Receiver --}}
                                    <td class="text-left">
                                        <div class="user-inline-avatar d-flex align-items-center">

                                            <div class="avatar bg-gray200">
                                                <img loading="lazy" src="{{ optional($access->receiver)->getAvatar() }}" class="img-cover"
                                                    alt="">
                                            </div>

                                            <div class="ml-5">
                                                <span class="d-block text-dark-blue font-weight-500">
                                                    {{ optional($access->receiver)->full_name }}
                                                </span>

                                                <span class="mt-5 d-block font-12 text-gray">
                                                    {{ optional($access->receiver)->email }}
                                                </span>
                                            </div>

                                        </div>
                                    </td>

                                    {{-- Gifted Item --}}
                                    <td class="align-middle">

                                        <span class="text-dark-blue font-weight-500">
                                            {{ optional($access->accessible)->title ?? '-' }}
                                        </span>

                                        <div class="mt-5">
                                            <span class="font-12 text-gray text-capitalize">

                                                @if ($access->accessible_type == 'bundle')
                                                    ( ({{ trans('update.bundle') }}) )
                                                @elseif($access->accessible_type == 'subscription')
                                                    ({{ trans('update.subscription') }})
                                                @elseif($access->accessible_type == 'webinar')
                                                    ({{ trans('update.webinar') }})
                                                @else
                                                    ({{ $access->accessible_type }})
                                                @endif

                                            </span>
                                        </div>

                                    </td>

                                    {{-- Date --}}
                                    <td class="align-middle">
                                        <span class="text-dark-blue font-weight-500">
                                            {{ dateTimeFormat($access->created_at, 'j M Y | H:i') }}
                                        </span>
                                    </td>

                                </tr>

                            @empty

                                <tr>
                                    <td colspan="3" class="text-center text-gray">
                                        {{ trans('update.no_gifted_users_found') }}
                                    </td>
                                </tr>
                            @endforelse

                        </tbody>

                    </table>

                                </div>
            </div>
        </div>
        {{-- Pagination --}}
        @if($userAccesses->hasPages())
            <div class="d-flex justify-content-center mt-30">
                {{ $userAccesses->links() }}
            </div>
        @endif
    </div>
@endsection
