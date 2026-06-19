<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $title ?? 'Reporte SGA' }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #1a56db; color: #fff; padding: 5px; }
        td { padding: 4px; border-bottom: 1px solid #ddd; }
    </style>
</head>
<body>
    <h1>{{ $title ?? 'Reporte' }}</h1>
    <p>Generado: {{ $generated ?? now() }}</p>
    @if(!empty($date_from) && !empty($date_to))
        <p>Rango de fechas evaluado: {{ $date_from }} a {{ $date_to }}</p>
    @endif
    <table>
        <thead>
            <tr>
                @foreach($headers ?? [] as $header)
                    <th>{{ $header }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($rows ?? [] as $row)
            <tr>
                @foreach($row as $cell)
                    <td>{{ $cell }}</td>
                @endforeach
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
