<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Services;

use App\Modules\Catalog\Domain\Entities\GenericProduct;
use App\Modules\Catalog\Domain\Repositories\GenericProductRepositoryInterface;
use App\Modules\Catalog\Domain\Services\BarcodeValueGenerator;
use Illuminate\Support\Facades\DB;
use Picqer\Barcode\BarcodeGeneratorSVG;

class BarcodeService
{
    public function __construct(
        private readonly GenericProductRepositoryInterface $repository,
        private readonly BarcodeValueGenerator $generator,
    ) {}

    public function generateValue(): string
    {
        return DB::transaction(function () {
            $max = $this->repository->getMaxBarcode();
            return $this->generator->generate($max);
        });
    }

    public function renderSvg(string $value): string
    {
        $gen = new BarcodeGeneratorSVG();

        return $gen->getBarcode($value, $gen::TYPE_CODE_128, 2, 60);
    }

    public function renderPrintableHtml(GenericProduct $product): string
    {
        $svg  = $this->renderSvg((string) $product->getBarcode());
        $name = htmlspecialchars($product->getName(), ENT_QUOTES, 'UTF-8');
        $code = htmlspecialchars((string) $product->getBarcode(), ENT_QUOTES, 'UTF-8');

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Etiqueta — {$name}</title>
<style>
  @page { margin: 5mm; }
  body { font-family: Arial, sans-serif; margin: 0; padding: 8px; display: inline-block; }
  .label { border: 1px solid #ccc; padding: 6px 10px; text-align: center; width: 220px; }
  .label svg { width: 200px; height: 60px; }
  .label .code { font-size: 12px; letter-spacing: 2px; margin: 2px 0; }
  .label .name { font-size: 11px; margin: 2px 0; word-break: break-word; }
  @media print { body { padding: 0; } }
</style>
</head>
<body>
<div class="label">
  {$svg}
  <p class="code">{$code}</p>
  <p class="name">{$name}</p>
</div>
</body>
</html>
HTML;
    }

    /** @param GenericProduct[] $generics */
    public function renderListHtml(array $generics): string
    {
        $rows = '';
        foreach ($generics as $product) {
            $name = htmlspecialchars($product->getName(), ENT_QUOTES, 'UTF-8');
            $code = htmlspecialchars((string) $product->getBarcode(), ENT_QUOTES, 'UTF-8');
            $rows .= "<tr><td>{$name}</td><td style=\"font-family:monospace\">{$code}</td></tr>\n";
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Listado de códigos de barras</title>
<style>
  body { font-family: Arial, sans-serif; font-size: 12px; margin: 20px; }
  h2 { margin-bottom: 10px; }
  table { border-collapse: collapse; width: 100%; }
  th, td { border: 1px solid #ccc; padding: 5px 8px; text-align: left; }
  th { background: #f0f0f0; }
  @media print { body { margin: 5mm; } }
</style>
</head>
<body>
<h2>Catálogo — Productos Genéricos y Códigos de Barras</h2>
<table>
  <thead><tr><th>Nombre Genérico</th><th>Código de Barras</th></tr></thead>
  <tbody>
{$rows}  </tbody>
</table>
</body>
</html>
HTML;
    }
}
