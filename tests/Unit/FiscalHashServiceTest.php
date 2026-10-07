<?php

namespace Tests\Unit;

use App\Domain\Compliance\Fiscal\Services\AuditChainHashService;
use App\Domain\Compliance\Fiscal\Services\FiscalHashService;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class FiscalHashServiceTest extends TestCase
{
    private FiscalHashService $fiscalHashService;

    private AuditChainHashService $auditChainHashService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fiscalHashService = new FiscalHashService;
        $this->auditChainHashService = new AuditChainHashService;
    }

    public function test_crc16_kermit_matches_official_validation_vector(): void
    {
        // Vetor oficial da Portaria 671 MTE: "123456789" resulta em 0x2189
        $input = '123456789';
        $crc = $this->fiscalHashService->calculateCrc16($input);

        $this->assertSame('2189', $crc);
        $this->assertSame(4, strlen($crc));
    }

    public function test_format_datetime_iso_complies_with_mte_24_character_format(): void
    {
        $dt = Carbon::create(2026, 10, 1, 8, 30, 0, 'America/Maceio');
        $formatted = $this->fiscalHashService->formatDateTimeIso($dt);

        $this->assertSame(24, strlen($formatted));
        $this->assertSame('2026-10-01T08:30:00-0300', $formatted);
    }

    public function test_tipo7_canonical_prefix_has_exactly_73_characters(): void
    {
        $nsr = 1;
        $occurredAt = Carbon::create(2026, 10, 1, 8, 0, 0, 'America/Maceio');
        $recordedAt = Carbon::create(2026, 10, 1, 8, 0, 0, 'America/Maceio');
        $cpf = '111.222.333-44';

        $prefix = $this->fiscalHashService->canonicalTipo7Prefix(
            nsr: $nsr,
            occurredAtLocal: $occurredAt,
            recordedAtLocal: $recordedAt,
            cpf: $cpf,
            collectorType: '02',
            punchType: '0'
        );

        $this->assertSame(73, strlen($prefix));
        $this->assertSame('00000000172026-10-01T08:00:00-03000111222333442026-10-01T08:00:00-0300020', $prefix);
    }

    public function test_tipo7_fiscal_hash_genesis_is_deterministic_with_fixed_vector(): void
    {
        $nsr = 1;
        $occurredAt = Carbon::create(2026, 10, 1, 8, 0, 0, 'America/Maceio');
        $recordedAt = Carbon::create(2026, 10, 1, 8, 0, 0, 'America/Maceio');
        $cpf = '11122233344';

        $expectedCanonical = '00000000172026-10-01T08:00:00-03000111222333442026-10-01T08:00:00-0300020'.str_repeat('0', 64);
        $expectedHash = hash('sha256', $expectedCanonical);

        $actualHash = $this->fiscalHashService->calculateTipo7FiscalHash(
            nsr: $nsr,
            occurredAtLocal: $occurredAt,
            recordedAtLocal: $recordedAt,
            cpf: $cpf,
            collectorType: '02',
            punchType: '0',
            previousTipo7FiscalHash: null
        );

        $this->assertSame(64, strlen($actualHash));
        $this->assertSame($expectedHash, $actualHash);
        $this->assertSame('bf311110d69665aaa1d1621899803bab4ccbf3fb844e22ba4e96cf5f024cd291', $actualHash);
    }

    public function test_tipo7_fiscal_hash_chains_previous_tipo7_hash_deterministically(): void
    {
        $hash1 = 'bf311110d69665aaa1d1621899803bab4ccbf3fb844e22ba4e96cf5f024cd291';

        $nsr2 = 2;
        $occurredAt2 = Carbon::create(2026, 10, 1, 12, 0, 0, 'America/Maceio');
        $recordedAt2 = Carbon::create(2026, 10, 1, 12, 0, 0, 'America/Maceio');
        $cpf2 = '11122233344';

        $expectedCanonical2 = '00000000272026-10-01T12:00:00-03000111222333442026-10-01T12:00:00-0300020'.$hash1;
        $expectedHash2 = hash('sha256', $expectedCanonical2);

        $actualHash2 = $this->fiscalHashService->calculateTipo7FiscalHash(
            nsr: $nsr2,
            occurredAtLocal: $occurredAt2,
            recordedAtLocal: $recordedAt2,
            cpf: $cpf2,
            collectorType: '02',
            punchType: '0',
            previousTipo7FiscalHash: $hash1
        );

        $this->assertSame(64, strlen($actualHash2));
        $this->assertSame($expectedHash2, $actualHash2);
        $this->assertSame('1eb031810299dfcbc4cfe0af517530a3897ed76b7a71109c447d503e2fc24b8c', $actualHash2);
        $this->assertNotSame($hash1, $actualHash2);
    }

    public function test_audit_chain_hash_uses_genesis_for_first_record(): void
    {
        $time = Carbon::create(2026, 7, 31, 11, 0, 0, 'UTC');
        $hash = $this->auditChainHashService->calculateAuditHash(
            establishmentId: 10,
            userId: 5,
            nsr: 1,
            occurredAtUtc: $time,
            eventTypeOrDirection: 'in',
            previousAuditHash: null
        );

        $expectedRaw = sprintf('10|5|1|%s|in|GENESIS_PONTOFACIL_2.0', $time->toIso8601String());
        $this->assertSame(hash('sha256', $expectedRaw), $hash);
    }

    public function test_audit_chain_hash_chains_previous_hash_deterministically(): void
    {
        $time1 = Carbon::create(2026, 7, 31, 11, 0, 0, 'UTC');
        $hash1 = $this->auditChainHashService->calculateAuditHash(
            establishmentId: 10,
            userId: 5,
            nsr: 1,
            occurredAtUtc: $time1,
            eventTypeOrDirection: 'in',
            previousAuditHash: null
        );

        $time2 = Carbon::create(2026, 7, 31, 15, 0, 0, 'UTC');
        $hash2 = $this->auditChainHashService->calculateAuditHash(
            establishmentId: 10,
            userId: 5,
            nsr: 2,
            occurredAtUtc: $time2,
            eventTypeOrDirection: 'out',
            previousAuditHash: $hash1
        );

        $expectedRaw2 = sprintf('10|5|2|%s|out|%s', $time2->toIso8601String(), $hash1);
        $this->assertSame(hash('sha256', $expectedRaw2), $hash2);
        $this->assertNotSame($hash1, $hash2);
    }
}
