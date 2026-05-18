<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Inventario</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 10px; color: #333; margin: 15px; }
        .header { text-align: center; border-bottom: 2px solid #1a56db; padding-bottom: 10px; margin-bottom: 15px; }
        .header h1 { font-size: 16px; margin: 0; color: #1a56db; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th { background: #1a56db; color: #fff; padding: 5px; text-align: left; }
        table.data td { padding: 4px; border-bottom: 1px solid #e5e7eb; }
        .stock-critical { color: #dc2626; font-weight: bold; }
        .stock-low { color: #d97706; font-weight: bold; }
        .stock-ok { color: #059669; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Centro Dermatológico Giovanni Bojanini</h1>
        <h2>Reporte de Inventario General</h2>
        <p>Generado: {{ $generated }} | Almacén: {{ $warehouseName ?? 'Todos' }}</p>
    </div>
    <table class="data">
        <thead>
            <tr>
                <th>Código</th>
                <th>Producto</th>
                <th>Categoría</th>
                <th>Almacén</th>
                <th>Stock</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            @foreach($products as $product)
            <tr>
                <td>{{ $product['code'] }}</td>
                <td>{{ $product['name'] }}</td>
                <td>{{ $product['category'] }}</td>
                <td>{{ $product['location'] }}</td>
                <td class="{{ $product['stock_class'] }}">{{ $product['current_stock'] }}</td>
                <td class="{{ $product['stock_class'] }}">{{ $product['stock_status'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
