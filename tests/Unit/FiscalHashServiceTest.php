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

    public function test_canonical_punch_fiscal_string_has_exactly_37_characters(): void
    {
        $nsr = 1;
        $occurredAt = Carbon::create(2026, 7, 31, 8, 0, 0, 'America/Maceio');
        $utcOffset = '-03:00';
        $cpf = '123.456.789-01';

        $canonical = $this->fiscalHashService->canonicalPunchFiscalString($nsr, $occurredAt, $utcOffset, $cpf);

        $this->assertSame(37, strlen($canonical));
        $this->assertSame('0000000013310720260800-03012345678901', $canonical);
    }

    public function test_fiscal_hash_calculation_is_deterministic_with_fixed_vector(): void
    {
        $nsr = 1;
        $occurredAt = Carbon::create(2026, 7, 31, 8, 0, 0, 'America/Maceio');
        $utcOffset = '-03:00';
        $cpf = '12345678901';

        $expectedCanonical = '0000000013310720260800-03012345678901';
        $expectedHash = hash('sha256', $expectedCanonical);

        $actualHash = $this->fiscalHashService->calculatePunchFiscalHash($nsr, $occurredAt, $utcOffset, $cpf);

        $this->assertSame(64, strlen($actualHash));
        $this->assertSame($expectedHash, $actualHash);
        $this->assertSame(
            hash('sha256', '0000000013310720260800-03012345678901'),
            $actualHash
        );
    }

    public function test_fiscal_hash_handles_large_nsr_and_unformatted_cpf(): void
    {
        $nsr = 999999999;
        $occurredAt = Carbon::create(2026, 12, 31, 23, 59, 0, 'America/Maceio');
        $utcOffset = '-0300';
        $cpf = '987.654.321-00';

        $canonical = $this->fiscalHashService->canonicalPunchFiscalString($nsr, $occurredAt, $utcOffset, $cpf);

        $this->assertSame(37, strlen($canonical));
        $this->assertSame('9999999993311220262359-03098765432100', $canonical);
        $this->assertSame(hash('sha256', '9999999993311220262359-03098765432100'), $this->fiscalHashService->calculatePunchFiscalHash($nsr, $occurredAt, $utcOffset, $cpf));
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
