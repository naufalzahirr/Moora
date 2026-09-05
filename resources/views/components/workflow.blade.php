@props(['step', 'run' => null, 'period' => null])
@php
    $period ??= $run?->period;
    $steps = [
        ['label' => 'Data', 'url' => route('datasets.index', $period ? ['period' => $period] : [])],
        ['label' => 'Rekomendasi', 'url' => $run ? route('calculations.results', $run) : null],
        ['label' => 'Persetujuan', 'url' => $run ? route('restock-actions.index', ['run' => $run]) : null],
        ['label' => 'Pesanan & Penerimaan', 'url' => route('purchase-orders.index', $run ? ['run' => $run] : [])],
    ];
@endphp
<nav class="workflow" aria-label="Tahapan restock">
    @foreach($steps as $index => $item)
        @if($item['url'])<a href="{{ $item['url'] }}" @if($step === $index + 1) aria-current="step" @endif><span>{{ $index + 1 }}</span>{{ $item['label'] }}</a>
        @else<span class="workflow-disabled"><span>{{ $index + 1 }}</span>{{ $item['label'] }}</span>@endif
    @endforeach
</nav>
