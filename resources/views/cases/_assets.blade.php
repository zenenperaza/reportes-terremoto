@push('styles')
    <link rel="stylesheet" href="{{ asset('css/case-management.css') }}?v={{ filemtime(public_path('css/case-management.css')) }}">
@endpush
@push('scripts')
    <script src="{{ asset('js/case-management.js') }}?v={{ filemtime(public_path('js/case-management.js')) }}" defer></script>
@endpush
