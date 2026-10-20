{{-- Encabezado. Variables: $title, $subtitle, $barcode (opcional) --}}
<div class="header">
    <div class="left">
        <div class="company">Centro Dermatológico Giovanni Bojanini</div>
        <div class="doc-title">{{ $title }}</div>
        <div class="doc-sub">{{ $subtitle }}</div>
    </div>
    @isset($barcode)
        <div class="right"><img src="{{ $barcode }}" alt="código" style="height:34px;"></div>
    @endisset
</div>
