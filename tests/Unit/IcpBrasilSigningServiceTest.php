<?php

namespace Tests\Unit;

use App\Domain\Compliance\Signing\IcpBrasilSigningService;
use App\Domain\Compliance\Signing\SigningServiceInterface;
use PHPUnit\Framework\TestCase;

class IcpBrasilSigningServiceTest extends TestCase
{
    public function test_it_implements_signing_service_interface(): void
    {
        $service = new IcpBrasilSigningService(null, null);
        $this->assertInstanceOf(SigningServiceInterface::class, $service);
    }

    public function test_it_reports_unavailable_and_pending_when_no_certificate_configured(): void
    {
        $service = new IcpBrasilSigningService(null, null);

        $this->assertFalse($service->isAvailable());
        $this->assertSame('pending_certificate', $service->status());
    }

    public function test_it_reports_unavailable_when_certificate_path_does_not_exist(): void
    {
        $service = new IcpBrasilSigningService('/tmp/non_existent_certificate.pfx', 'password');

        $this->assertFalse($service->isAvailable());
        $this->assertSame('pending_certificate', $service->status());
    }

    public function test_it_does_not_fake_pdf_signature_when_unconfigured(): void
    {
        $service = new IcpBrasilSigningService(null, null);
        $samplePdf = '%PDF-1.4 sample content';

        // When unconfigured, it must not alter or fake signatures
        $result = $service->signPdf($samplePdf);
        $this->assertSame($samplePdf, $result);
    }

    public function test_it_does_not_fake_detached_signature_when_unconfigured(): void
    {
        $service = new IcpBrasilSigningService(null, null);
        $content = '00000000112345678901';

        $signature = $service->signDetached($content);
        $this->assertSame('', $signature);
    }
}
