@php($class = ['pending' => 'secondary', 'running' => 'primary', 'paused' => 'warning', 'cancelled' => 'light', 'completed' => 'success', 'failed' => 'danger'][$status] ?? 'secondary')
<span class="badge badge-{{ $class }} js-lz-job-status">{{ trans('localization.job_status_' . $status) }}</span>
