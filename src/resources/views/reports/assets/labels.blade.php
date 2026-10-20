<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Etiquetas de activos</title>
    <style>
        @page { margin: 8mm; }
        body { font-family: Arial, sans-serif; margin: 0; }
        table { width: 100%; border-collapse: separate; border-spacing: 4mm; }
        td { width: 33%; border: 1px dashed #bbb; padding: 4px; text-align: center; vertical-align: middle; height: 30mm; }
        .company { font-size: 7px; color: #1a56db; font-weight: bold; }
        .code { font-size: 12px; font-weight: bold; letter-spacing: 2px; margin-top: 2px; }
        .name { font-size: 8px; margin-top: 2px; }
        .place { font-size: 7px; color: #666; }
        img { height: 32px; max-width: 95%; }
    </style>
</head>
<body>
<table>
    @foreach($labels->chunk(3) as $row)
        <tr>
            @foreach($row as $label)
                <td>
                    <div class="company">Activo — Centro Dermatológico Giovanni Bojanini</div>
                    <img src="{{ $label['barcode'] }}" alt="{{ $label['asset']->code }}">
                    <div class="code">{{ $label['asset']->code }}</div>
                    <div class="name">{{ \Illuminate\Support\Str::limit($label['asset']->name, 40) }}</div>
                    <div class="place">{{ $label['asset']->currentPlace?->fullName() }}</div>
                </td>
            @endforeach
            @for($i = $row->count(); $i < 3; $i++)
                <td style="border:none;"></td>
            @endfor
        </tr>
    @endforeach
</table>
</body>
</html>
