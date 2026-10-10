<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Teste de Integridade Documental das Referências Oficiais MTE (31/07/2026).
 *
 * Garante que os arquivos oficiais baixados do gov.br permaneçam estritamente
 * idênticos aos binários originais congelados, sem modificações acidentais,
 * e sem qualquer dependência ou acesso à rede externa durante a suíte de testes.
 */
class MteReferenceDocumentsIntegrityTest extends TestCase
{
    private string $refDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->refDir = dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'ref'.DIRECTORY_SEPARATOR.'mte'.DIRECTORY_SEPARATOR.'2026-07-31';
    }

    public function test_reference_directory_and_manifest_exist(): void
    {
        $this->assertDirectoryExists($this->refDir, 'O diretório versionado ref/mte/2026-07-31 deve existir.');
        $this->assertFileExists($this->refDir.DIRECTORY_SEPARATOR.'README.md', 'O manifesto README.md deve existir.');
        $this->assertFileExists($this->refDir.DIRECTORY_SEPARATOR.'SHA256SUMS.txt', 'O arquivo SHA256SUMS.txt deve existir.');
    }

    public function test_official_pdfs_exist_and_are_valid_binaries(): void
    {
        $afdPath = $this->refDir.DIRECTORY_SEPARATOR.'afd.pdf';
        $aejPath = $this->refDir.DIRECTORY_SEPARATOR.'aej.pdf';

        $this->assertFileExists($afdPath, 'O arquivo afd.pdf deve existir no repositório.');
        $this->assertFileExists($aejPath, 'O arquivo aej.pdf deve existir no repositório.');

        $this->assertGreaterThan(50000, filesize($afdPath), 'O arquivo afd.pdf deve conter tamanho binário consistente.');
        $this->assertGreaterThan(50000, filesize($aejPath), 'O arquivo aej.pdf deve conter tamanho binário consistente.');

        // Verifica magic bytes do formato PDF (%PDF-)
        $afdHandle = fopen($afdPath, 'rb');
        $this->assertIsResource($afdHandle);
        $afdHeader = fread($afdHandle, 5);
        fclose($afdHandle);
        $this->assertSame('%PDF-', $afdHeader, 'afd.pdf deve iniciar com o cabeçalho binário %PDF-.');

        $aejHandle = fopen($aejPath, 'rb');
        $this->assertIsResource($aejHandle);
        $aejHeader = fread($aejHandle, 5);
        fclose($aejHandle);
        $this->assertSame('%PDF-', $aejHeader, 'aej.pdf deve iniciar com o cabeçalho binário %PDF-.');
    }

    public function test_recalculated_sha256_strictly_matches_sha256sums_manifest(): void
    {
        $sumsPath = $this->refDir.DIRECTORY_SEPARATOR.'SHA256SUMS.txt';
        $lines = file($sumsPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        $this->assertIsArray($lines);
        $this->assertNotEmpty($lines, 'SHA256SUMS.txt não deve estar vazio.');

        $expectedHashes = [];
        foreach ($lines as $line) {
            $cleanLine = preg_replace('/^\xEF\xBB\xBF/', '', trim($line));
            $parts = preg_split('/\s+/', $cleanLine, 2);
            if (count($parts) === 2) {
                $expectedHashes[$parts[1]] = strtolower($parts[0]);
            }
        }

        $this->assertArrayHasKey('afd.pdf', $expectedHashes, 'SHA256SUMS.txt deve conter a entrada para afd.pdf.');
        $this->assertArrayHasKey('aej.pdf', $expectedHashes, 'SHA256SUMS.txt deve conter a entrada para aej.pdf.');

        $actualAfdHash = hash_file('sha256', $this->refDir.DIRECTORY_SEPARATOR.'afd.pdf');
        $actualAejHash = hash_file('sha256', $this->refDir.DIRECTORY_SEPARATOR.'aej.pdf');

        $this->assertSame(
            $expectedHashes['afd.pdf'],
            $actualAfdHash,
            'O hash SHA-256 de afd.pdf diverge do manifesto congelado. O binário não pode ser alterado.'
        );

        $this->assertSame(
            $expectedHashes['aej.pdf'],
            $actualAejHash,
            'O hash SHA-256 de aej.pdf diverge do manifesto congelado. O binário não pode ser alterado.'
        );
    }
}
