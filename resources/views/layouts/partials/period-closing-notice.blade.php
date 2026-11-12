@php($periodClosingNotice = session()->pull(\App\Support\PeriodClosingNotice::SESSION_KEY) ? \App\Support\PeriodClosingNotice::current() : null)
@if($periodClosingNotice)
    <section id="period-closing-notice" class="period-closing-notice" role="alert" aria-labelledby="period-closing-title">
        <div class="period-closing-heading"><i class="ri-calendar-event-line" aria-hidden="true"></i><h2 id="period-closing-title">Aviso de cierre de período</h2></div>
        <div id="period-closing-description">
            <p>Se aproxima el cierre del período <strong>{{ $periodClosingNotice['label'] }}</strong>. El mes finaliza el <strong>{{ $periodClosingNotice['last_day'] }}</strong>.</p>
            <p><strong>Una vez cerrado, usted no podrá agregar, editar ni eliminar registros o beneficiarios de ese período, ya que los datos serán enviados al donante.</strong></p>
            <p>Revise y complete la información pendiente antes del cierre.</p>
        </div>
        <div class="period-closing-actions"><button type="button" class="button button-primary" data-period-closing-dismiss hidden>Entendido</button></div>
    </section>
    @push('scripts')<script src="{{ $versionedAsset('js/period-closing-notice.js') }}" defer></script>@endpush
@endif
