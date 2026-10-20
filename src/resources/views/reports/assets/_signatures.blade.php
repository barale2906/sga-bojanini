{{-- Firmas de entrega y recibido. Variables: $signatures (Collection keyBy role), $pendingText --}}
<div class="signatures">
    @foreach(['delivered_by' => 'Entregado por', 'received_by' => 'Recibido por'] as $role => $label)
        @php $sig = $signatures->get($role); @endphp
        <div class="sig-box">
            <div class="sig-label">{{ $label }}</div>
            @if($sig)
                @if($sig->signature_data)
                    <img class="sig-img" src="{{ $sig->signature_data }}" alt="{{ $label }}">
                @endif
                <div class="sig-name">
                    {{ $sig->signer_name }}<br>
                    Doc: {{ $sig->signer_document }}<br>
                    <span style="color:#666;">{{ $sig->signed_at?->format('Y-m-d H:i') }}</span>
                    @if($sig->method !== 'pad')
                        <br><span class="sig-method">{{ $sig->method === 'pin' ? 'Firma registrada · validada con PIN' : 'Firma registrada · sesión autenticada' }}</span>
                    @endif
                </div>
            @else
                <div class="sig-pending">{{ $pendingText ?? 'Sin firma' }}</div>
            @endif
        </div>
    @endforeach
</div>
