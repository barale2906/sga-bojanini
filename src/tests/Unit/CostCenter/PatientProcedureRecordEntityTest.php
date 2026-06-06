<?php

namespace Tests\Unit\CostCenter;

use App\Modules\CostCenter\Domain\Entities\PatientProcedureRecord;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitarios para la entidad PatientProcedureRecord.
 *
 * Verifica las reglas de negocio puras sin dependencias de infraestructura.
 */
class PatientProcedureRecordEntityTest extends TestCase
{
    private function makeRecord(array $overrides = []): PatientProcedureRecord
    {
        return PatientProcedureRecord::create(
            medicalServiceId:  $overrides['medicalServiceId']  ?? 1,
            patientExternalId: $overrides['patientExternalId'] ?? 'EXT-001',
            patientDocument:   $overrides['patientDocument']   ?? '1020304050',
            patientFirstName:  $overrides['patientFirstName']  ?? 'María',
            patientLastName:   $overrides['patientLastName']   ?? 'López',
            quantity:          $overrides['quantity']          ?? 1.0,
            unitPrice:         $overrides['unitPrice']         ?? 100000.0,
            serviceDate:       $overrides['serviceDate']       ?? new DateTimeImmutable('2026-06-01'),
            notes:             $overrides['notes']             ?? null,
        );
    }

    // ─── create() factory ────────────────────────────────────────────────────

    public function test_create_calcula_total_como_quantity_por_unit_price(): void
    {
        $record = $this->makeRecord(['quantity' => 3.0, 'unitPrice' => 50000.0]);

        $this->assertSame(150000.0, $record->getTotal());
    }

    public function test_create_redondea_total_a_dos_decimales(): void
    {
        $record = $this->makeRecord(['quantity' => 1.0, 'unitPrice' => 10.005]);

        $this->assertSame(10.01, $record->getTotal());
    }

    public function test_create_sin_id_retorna_null(): void
    {
        $record = $this->makeRecord();

        $this->assertNull($record->getId());
    }

    public function test_create_persiste_nombres_del_paciente(): void
    {
        $record = $this->makeRecord([
            'patientFirstName' => 'Carlos Andrés',
            'patientLastName'  => 'Pérez Gómez',
        ]);

        $this->assertSame('Carlos Andrés', $record->getPatientFirstName());
        $this->assertSame('Pérez Gómez', $record->getPatientLastName());
    }

    public function test_create_is_active_true_por_defecto(): void
    {
        $this->assertTrue($this->makeRecord()->isActive());
    }

    public function test_create_notes_nullable(): void
    {
        $this->assertNull($this->makeRecord(['notes' => null])->getNotes());
        $this->assertSame('Nota', $this->makeRecord(['notes' => 'Nota'])->getNotes());
    }

    // ─── calculateTotal() ────────────────────────────────────────────────────

    public function test_calculate_total_con_valores_enteros(): void
    {
        $this->assertSame(200000.0, PatientProcedureRecord::calculateTotal(2.0, 100000.0));
    }

    public function test_calculate_total_con_cantidad_fraccionaria(): void
    {
        $this->assertSame(150.0, PatientProcedureRecord::calculateTotal(0.5, 300.0));
    }

    public function test_calculate_total_precio_cero(): void
    {
        $this->assertSame(0.0, PatientProcedureRecord::calculateTotal(5.0, 0.0));
    }

    // ─── activate / deactivate ───────────────────────────────────────────────

    public function test_activate_y_deactivate_cambian_estado(): void
    {
        $record = $this->makeRecord();

        $record->deactivate();
        $this->assertFalse($record->isActive());

        $record->activate();
        $this->assertTrue($record->isActive());
    }

    // ─── medical_service_name (campo enriquecido) ─────────────────────────────

    public function test_create_medical_service_name_es_null_por_defecto(): void
    {
        $this->assertNull($this->makeRecord()->getMedicalServiceName());
    }
}
