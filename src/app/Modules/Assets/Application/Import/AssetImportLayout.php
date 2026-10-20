<?php

declare(strict_types=1);

namespace App\Modules\Assets\Application\Import;

/**
 * Formato de la plantilla de importación de activos: única fuente de verdad
 * de columnas, etiquetas y ejemplos, compartida por el generador de la
 * plantilla y el servicio que procesa el archivo.
 *
 * Cada columna: [encabezado técnico, etiqueta en español, ejemplo, obligatorio, descripción].
 */
final class AssetImportLayout
{
    public const ASSET_COLUMNS = [
        ['name', 'Nombre del activo', 'Monitor de signos vitales', 'Sí', 'Nombre descriptivo del activo (máx. 200).'],
        ['category_code', 'Código de categoría', 'BIO', 'Sí', 'Código de una categoría existente o creada en la hoja "Categorías".'],
        ['site_name', 'Sede', 'Clínica', 'Sí', 'Nombre de la sede. Si no existe se crea.'],
        ['place_name', 'Sitio', 'Consultorio 1', 'Sí', 'Nombre del sitio dentro de la sede. Si no existe se crea.'],
        ['responsible_email', 'Correo del responsable', 'enfermeria@clinica.com', 'No', 'Correo de un usuario existente del sistema.'],
        ['brand', 'Marca', 'Mindray', 'No', 'Texto (máx. 100).'],
        ['model', 'Modelo', 'iMEC 10', 'No', 'Texto (máx. 100).'],
        ['serial', 'Serial', 'SN-458722', 'No*', 'Obligatorio si se carga hoja de vida para el activo: es la llave que las relaciona.'],
        ['acquisition_date', 'Fecha de adquisición', '2024-03-15', 'No', 'Fecha AAAA-MM-DD.'],
        ['acquisition_value', 'Valor de adquisición', '12500000', 'No', 'Número sin separadores de miles (informativo).'],
        ['supplier_tax_id', 'NIT del proveedor', '900123456', 'No', 'NIT de un proveedor existente.'],
        ['invoice_number', 'Número de factura', 'FV-1020', 'No', 'Texto (máx. 50).'],
        ['warranty_until', 'Garantía hasta', '2026-03-15', 'No', 'Fecha AAAA-MM-DD.'],
        ['status', 'Estado', 'operational', 'No', 'operational, maintenance, out_of_service o available. Vacío = operational.'],
        ['notes', 'Observaciones', '', 'No', 'Texto libre.'],
        ['comment', 'Comentario inicial', 'Ingresa por inventario inicial', 'No', 'Primer comentario del activo.'],
        ['maintenance_type', 'Tipo de mantenimiento', 'preventive', 'No', 'Para crear un plan: preventive, calibration, metrological_verification o inspection.'],
        ['frequency_value', 'Frecuencia', '6', 'Con plan', 'Entero mayor a cero.'],
        ['frequency_unit', 'Unidad de frecuencia', 'months', 'Con plan', 'days o months.'],
        ['performed_by_type', 'Ejecuta', 'external', 'Con plan', 'internal o external.'],
        ['last_maintenance_date', 'Último mantenimiento', '2026-07-01', 'No', 'Fecha AAAA-MM-DD; la próxima orden se calcula desde aquí. Vacío = desde hoy.'],
    ];

    public const PROFILE_COMMON_COLUMNS = [
        ['serial', 'Serial del activo', 'SN-458722', 'Sí', 'Serial del activo (hoja "Activos" o ya registrado).'],
        ['manufacturer', 'Fabricante', 'Mindray', 'No', 'Texto.'],
        ['country_of_origin', 'País de origen', 'China', 'No', 'Texto.'],
        ['manufacture_year', 'Año de fabricación', '2023', 'No', 'Entero de 4 dígitos.'],
        ['useful_life_years', 'Vida útil (años)', '10', 'No', 'Entero.'],
        ['acquisition_mode', 'Forma de adquisición', 'purchase', 'No', 'purchase, donation, comodato o leasing.'],
        ['accessories', 'Accesorios', 'Brazalete, sensor SpO2', 'No', 'Texto.'],
    ];

    public const PROFILE_BIOMEDICAL_COLUMNS = [
        ['invima_registry', 'Registro INVIMA', '2019DM-0001234', 'No', 'Texto.'],
        ['risk_class', 'Clase de riesgo', 'IIb', 'No', 'I, IIa, IIb o III.'],
        ['biomedical_class', 'Clasificación biomédica', 'diagnosis', 'No', 'diagnosis, treatment, rehabilitation, prevention o laboratory.'],
        ['power_source', 'Fuente de energía', 'electric', 'No', 'electric, battery, pneumatic, mechanical u other.'],
        ['voltage', 'Voltaje', '110 V', 'No', 'Texto.'],
        ['current', 'Corriente', '1.5 A', 'No', 'Texto.'],
        ['power', 'Potencia', '60 W', 'No', 'Texto.'],
        ['frequency', 'Frecuencia eléctrica', '60 Hz', 'No', 'Texto.'],
        ['requires_calibration', 'Requiere calibración', 'TRUE', 'No', 'TRUE/FALSE, 1/0, si/no.'],
    ];

    public const PROFILE_COMPUTING_COLUMNS = [
        ['processor', 'Procesador', 'Intel Core i5-1235U', 'No', 'Texto.'],
        ['ram', 'Memoria RAM', '16 GB', 'No', 'Texto.'],
        ['storage', 'Almacenamiento', 'SSD 512 GB', 'No', 'Texto.'],
        ['operating_system', 'Sistema operativo', 'Windows 11 Pro', 'No', 'Texto.'],
        ['ip_address', 'Dirección IP', '192.168.1.50', 'No', 'IPv4 o IPv6.'],
        ['mac_address', 'Dirección MAC', '00:1A:2B:3C:4D:5E', 'No', 'Formato XX:XX:XX:XX:XX:XX.'],
        ['hostname', 'Nombre del equipo', 'ADM-PC-01', 'No', 'Texto.'],
    ];

    /** @return string[] */
    public static function headers(array $columns): array
    {
        return array_column($columns, 0);
    }

    /** @return string[] */
    public static function labels(array $columns): array
    {
        return array_column($columns, 1);
    }

    /** @return string[] */
    public static function examples(array $columns): array
    {
        return array_column($columns, 2);
    }

    public static function biomedicalColumns(): array
    {
        return [...self::PROFILE_COMMON_COLUMNS, ...self::PROFILE_BIOMEDICAL_COLUMNS];
    }

    public static function computingColumns(): array
    {
        return [...self::PROFILE_COMMON_COLUMNS, ...self::PROFILE_COMPUTING_COLUMNS];
    }

    /**
     * Indica si la fila es la fila de ayuda en español (fila 2 de la plantilla),
     * que se ignora si el usuario no la borró.
     */
    public static function isLabelRow(array $row, array $columns): bool
    {
        $first = $columns[0];

        return ($row[$first[0]] ?? null) === $first[1];
    }
}
