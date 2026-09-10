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

    // ─── calculateDiscountAmount() ───────────────────────────────────────────

    public function test_descuento_fijo_retorna_valor_redondeado(): void
    {
        $this->assertSame(5000.0, PatientProcedureRecord::calculateDiscountAmount(50000.0, 'fixed', 5000.0));
    }

    public function test_descuento_porcentaje_calcula_correctamente(): void
    {
        $this->assertSame(5000.0, PatientProcedureRecord::calculateDiscountAmount(50000.0, 'percentage', 10.0));
    }

    public function test_descuento_porcentaje_con_decimales(): void
    {
        $this->assertSame(10500.0, PatientProcedureRecord::calculateDiscountAmount(100000.0, 'percentage', 10.5));
    }

    public function test_descuento_tipo_null_retorna_cero(): void
    {
        $this->assertSame(0.0, PatientProcedureRecord::calculateDiscountAmount(50000.0, null, 1000.0));
    }

    public function test_descuento_valor_null_retorna_cero(): void
    {
        $this->assertSame(0.0, PatientProcedureRecord::calculateDiscountAmount(50000.0, 'fixed', null));
    }

    public function test_descuento_tipo_desconocido_retorna_cero(): void
    {
        $this->assertSame(0.0, PatientProcedureRecord::calculateDiscountAmount(50000.0, 'unknown', 1000.0));
    }

    // ─── Nuevos campos de contacto ───────────────────────────────────────────

    public function test_campos_contacto_son_null_por_defecto(): void
    {
        $record = $this->makeRecord();

        $this->assertNull($record->getPatientEmail());
        $this->assertNull($record->getPatientAddress());
        $this->assertNull($record->getPatientPhone());
    }

    public function test_create_persiste_campos_de_descuento(): void
    {
        $record = PatientProcedureRecord::create(
            medicalServiceId:  1,
            patientExternalId: 'EXT-001',
            patientDocument:   '123',
            patientFirstName:  'Ana',
            patientLastName:   'Pérez',
            quantity:          1.0,
            unitPrice:         100000.0,
            serviceDate:       new DateTimeImmutable('2026-09-09'),
            orderNumber:       'OS-20260909-000001',
            discountType:      'percentage',
            discountValue:     10.0,
            discountAmount:    10000.0,
            netTotal:          90000.0,
            discountStatus:    'pending',
        );

        $this->assertSame('OS-20260909-000001', $record->getOrderNumber());
        $this->assertSame('percentage', $record->getDiscountType());
        $this->assertSame(10.0, $record->getDiscountValue());
        $this->assertSame(10000.0, $record->getDiscountAmount());
        $this->assertSame(90000.0, $record->getNetTotal());
        $this->assertSame('pending', $record->getDiscountStatus());
    }
}
