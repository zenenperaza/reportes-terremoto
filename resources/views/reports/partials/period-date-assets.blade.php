@once
    @push('styles')
        <link rel="stylesheet" href="{{ asset('assets/libs/flatpickr/flatpickr.min.css') }}">
    @endpush
    @push('scripts')
        <script src="{{ asset('assets/libs/flatpickr/flatpickr.min.js') }}" defer></script>
        <script src="{{ asset('assets/libs/flatpickr/l10n/es.js') }}" defer></script>
        <script src="{{ asset('js/report-period-dates.js') }}?v={{ filemtime(public_path('js/report-period-dates.js')) }}" defer></script>
    @endpush
@endonce
