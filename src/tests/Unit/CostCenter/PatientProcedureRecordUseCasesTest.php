<?php

namespace Tests\Unit\CostCenter;

use App\Modules\CostCenter\Application\DTOs\PatientProcedureRecordData;
use App\Modules\CostCenter\Application\UseCases\CreatePatientProcedureRecordUseCase;
use App\Modules\CostCenter\Application\UseCases\GetPatientProcedureHistoryUseCase;
use App\Modules\CostCenter\Application\UseCases\UpdatePatientProcedureRecordUseCase;
use App\Modules\CostCenter\Domain\Entities\MedicalService;
use App\Modules\CostCenter\Domain\Entities\PatientProcedureRecord;
use App\Modules\CostCenter\Domain\Repositories\MedicalServiceRepositoryInterface;
use App\Modules\CostCenter\Domain\Repositories\PatientProcedureRecordRepositoryInterface;
use DateTimeImmutable;
use DomainException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitarios para los use cases de PatientProcedureRecord.
 *
 * Usa mocks de los repositorios para aislar la lógica de negocio.
 */
class PatientProcedureRecordUseCasesTest extends TestCase
{
    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function makeDto(array $overrides = []): PatientProcedureRecordData
    {
        return new PatientProcedureRecordData(
            medicalServiceId:  $overrides['medicalServiceId']  ?? 1,
            patientExternalId: $overrides['patientExternalId'] ?? 'EXT-001',
            patientDocument:   $overrides['patientDocument']   ?? '1020304050',
            patientFirstName:  $overrides['patientFirstName']  ?? 'Ana',
            patientLastName:   $overrides['patientLastName']   ?? 'García',
            quantity:          $overrides['quantity']          ?? 1.0,
            unitPrice:         $overrides['unitPrice']         ?? 80000.0,
            serviceDate:       $overrides['serviceDate']       ?? new DateTimeImmutable('2026-06-01'),
            notes:             $overrides['notes']             ?? null,
            isActive:          $overrides['isActive']          ?? true,
        );
    }

    private function makeSavedRecord(int $id = 1, array $overrides = []): PatientProcedureRecord
    {
        return new PatientProcedureRecord(
            id:               $id,
            medicalServiceId: $overrides['medicalServiceId'] ?? 1,
            patientExternalId: $overrides['patientExternalId'] ?? 'EXT-001',
            patientDocument:  $overrides['patientDocument']  ?? '1020304050',
            patientFirstName: $overrides['patientFirstName'] ?? 'Ana',
            patientLastName:  $overrides['patientLastName']  ?? 'García',
            quantity:         1.0,
            unitPrice:        80000.0,
            total:            80000.0,
            serviceDate:      new DateTimeImmutable('2026-06-01'),
        );
    }

    /** @return MockObject&PatientProcedureRecordRepositoryInterface */
    private function mockRepository(): MockObject
    {
        return $this->createMock(PatientProcedureRecordRepositoryInterface::class);
    }

    /** @return MockObject&MedicalServiceRepositoryInterface */
    private function mockServiceRepository(?MedicalService $procedure = null): MockObject
    {
        $mock = $this->createMock(MedicalServiceRepositoryInterface::class);
        $mock->method('findById')->willReturn($procedure);

        return $mock;
    }

    private function makeProcedure(bool $isProcedure = true): MedicalService
    {
        $mock = $this->createMock(MedicalService::class);
        $mock->method('isProcedure')->willReturn($isProcedure);
        $mock->method('getName')->willReturn('Consulta General');

        return $mock;
    }

    // ─── CreatePatientProcedureRecordUseCase ─────────────────────────────────

    public function test_create_persiste_nombres_del_paciente(): void
    {
        $dto  = $this->makeDto(['patientFirstName' => 'Laura', 'patientLastName' => 'Torres']);
        $repo = $this->mockRepository();
        $repo->expects($this->once())
            ->method('save')
            ->with($this->callback(fn ($r) => $r->getPatientFirstName() === 'Laura'
                && $r->getPatientLastName() === 'Torres'))
            ->willReturn($this->makeSavedRecord());

        $useCase = new CreatePatientProcedureRecordUseCase($repo, $this->mockServiceRepository($this->makeProcedure()));
        $useCase->execute($dto);
    }

    public function test_create_lanza_excepcion_cuando_procedimiento_no_existe(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessageMatches('/no encontrado/');

        $useCase = new CreatePatientProcedureRecordUseCase(
            $this->mockRepository(),
            $this->mockServiceRepository(null),
        );
        $useCase->execute($this->makeDto());
    }

    public function test_create_lanza_excepcion_cuando_es_servicio_no_procedimiento(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessageMatches('/servicio, no un procedimiento/');

        $useCase = new CreatePatientProcedureRecordUseCase(
            $this->mockRepository(),
            $this->mockServiceRepository($this->makeProcedure(false)),
        );
        $useCase->execute($this->makeDto());
    }

    public function test_create_lanza_excepcion_con_cantidad_cero(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessageMatches('/cantidad debe ser mayor a cero/');

        $useCase = new CreatePatientProcedureRecordUseCase(
            $this->mockRepository(),
            $this->mockServiceRepository($this->makeProcedure()),
        );
        $useCase->execute($this->makeDto(['quantity' => 0.0]));
    }

    public function test_create_lanza_excepcion_con_precio_negativo(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessageMatches('/precio unitario no puede ser negativo/');

        $useCase = new CreatePatientProcedureRecordUseCase(
            $this->mockRepository(),
            $this->mockServiceRepository($this->makeProcedure()),
        );
        $useCase->execute($this->makeDto(['unitPrice' => -1.0]));
    }

    // ─── UpdatePatientProcedureRecordUseCase ─────────────────────────────────

    public function test_update_persiste_nuevos_nombres_del_paciente(): void
    {
        $dto  = $this->makeDto(['patientFirstName' => 'Sofía', 'patientLastName' => 'Martínez']);
        $repo = $this->mockRepository();
        $repo->method('findById')->willReturn($this->makeSavedRecord(5));
        $repo->expects($this->once())
            ->method('save')
            ->with($this->callback(fn ($r) => $r->getPatientFirstName() === 'Sofía'
                && $r->getPatientLastName() === 'Martínez'))
            ->willReturn($this->makeSavedRecord(5));

        (new UpdatePatientProcedureRecordUseCase($repo))->execute(5, $dto);
    }

    public function test_update_lanza_excepcion_cuando_registro_no_existe(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessageMatches('/no encontrado/');

        $repo = $this->mockRepository();
        $repo->method('findById')->willReturn(null);

        (new UpdatePatientProcedureRecordUseCase($repo))->execute(99, $this->makeDto());
    }

    public function test_update_lanza_excepcion_con_cantidad_cero(): void
    {
        $this->expectException(DomainException::class);

        $repo = $this->mockRepository();
        $repo->method('findById')->willReturn($this->makeSavedRecord(1));

        (new UpdatePatientProcedureRecordUseCase($repo))->execute(1, $this->makeDto(['quantity' => 0.0]));
    }

    // ─── GetPatientProcedureHistoryUseCase ────────────────────────────────────

    public function test_history_incluye_nombres_del_paciente_en_resultado(): void
    {
        $records = [
            [
                'id'                  => 1,
                'medical_service_id'  => 1,
                'procedure_code'      => 'CONS-GEN',
                'procedure_name'      => 'Consulta General',
                'service_id'          => null,
                'service_name'        => null,
                'patient_external_id' => 'EXT-001',
                'patient_document'    => '1020304050',
                'patient_first_name'  => 'Ana',
                'patient_last_name'   => 'García',
                'quantity'            => 1.0,
                'unit_price'          => 80000.0,
                'total'               => 80000.0,
                'service_date'        => '2026-06-01',
                'notes'               => null,
                'is_active'           => true,
            ],
        ];

        $repo = $this->mockRepository();
        $repo->method('findByPatientWithService')->willReturn($records);

        $result = (new GetPatientProcedureHistoryUseCase($repo))->execute('EXT-001');

        $this->assertSame('Ana', $result['patient_first_name']);
        $this->assertSame('García', $result['patient_last_name']);
        $this->assertSame('1020304050', $result['patient_document']);
    }

    public function test_history_calcula_summary_correctamente(): void
    {
        $records = [
            ['patient_document' => 'DOC', 'patient_first_name' => 'X', 'patient_last_name' => 'Y',
                'total' => 100000.0, 'service_date' => '2026-05-01', 'patient_external_id' => 'E',
                'id' => 1, 'medical_service_id' => 1, 'procedure_code' => 'C', 'procedure_name' => 'C',
                'service_id' => null, 'service_name' => null, 'quantity' => 1.0, 'unit_price' => 100000.0,
                'notes' => null, 'is_active' => true],
            ['patient_document' => 'DOC', 'patient_first_name' => 'X', 'patient_last_name' => 'Y',
                'total' => 200000.0, 'service_date' => '2026-06-01', 'patient_external_id' => 'E',
                'id' => 2, 'medical_service_id' => 1, 'procedure_code' => 'C', 'procedure_name' => 'C',
                'service_id' => null, 'service_name' => null, 'quantity' => 2.0, 'unit_price' => 100000.0,
                'notes' => null, 'is_active' => true],
        ];

        $repo = $this->mockRepository();
        $repo->method('findByPatientWithService')->willReturn($records);

        $result = (new GetPatientProcedureHistoryUseCase($repo))->execute('E');

        $this->assertSame(2, $result['summary']['total_records']);
        $this->assertSame(300000.0, $result['summary']['total_amount']);
        $this->assertSame('2026-05-01', $result['summary']['first_service_date']);
        $this->assertSame('2026-06-01', $result['summary']['last_service_date']);
    }

    public function test_history_paciente_sin_registros_retorna_nulls(): void
    {
        $repo = $this->mockRepository();
        $repo->method('findByPatientWithService')->willReturn([]);

        $result = (new GetPatientProcedureHistoryUseCase($repo))->execute('NO-EXISTE');

        $this->assertSame(0, $result['summary']['total_records']);
        $this->assertNull($result['patient_first_name']);
        $this->assertNull($result['patient_last_name']);
        $this->assertNull($result['summary']['first_service_date']);
    }
}
