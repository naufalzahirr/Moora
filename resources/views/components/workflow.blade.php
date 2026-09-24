@props(['step', 'run' => null, 'period' => null])
@php
    $period ??= $run?->period;
    $steps = [
        ['label' => 'Transaksi', 'url' => route('transactions.index')],
        ['label' => 'Hasil MOORA', 'url' => $run ? route('calculations.results', $run) : null],
        ['label' => 'Pembelian', 'url' => $run ? route('purchases.review', ['run' => $run]) : null],
        ['label' => 'Laporan', 'url' => $run ? route('reports.index', ['run' => $run]) : null],
    ];
@endphp
<nav class="workflow" aria-label="Tahapan restock">
    @foreach($steps as $index => $item)
        @if($item['url'])<a href="{{ $item['url'] }}" @if($step === $index + 1) aria-current="step" @endif><span>{{ $index + 1 }}</span>{{ $item['label'] }}</a>
        @else<span class="workflow-disabled"><span>{{ $index + 1 }}</span>{{ $item['label'] }}</span>@endif
    @endforeach
</nav>
