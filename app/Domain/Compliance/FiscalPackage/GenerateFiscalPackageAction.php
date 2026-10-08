<?php

namespace App\Domain\Compliance\FiscalPackage;

use App\Domain\Compliance\AEJ\AejGenerator_2026_07_31;
use App\Domain\Compliance\AFD\AfdGenerator_2026_07_31;
use App\Models\ClosedPeriod;
use App\Models\Establishment;
use App\Models\TimeBankTransaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use ZipArchive;

class GenerateFiscalPackageAction
{
    public function execute(
        Establishment $establishment,
        int $year,
        int $month,
        ?User $requestedBy = null,
    ): array {
        $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endDate = Carbon::createFromDate($year, $month, 1)->endOfMonth();
        $cnpjClean = preg_replace('/\D/', '', $establishment->identifier_number);
        $company = $establishment->company;

        $closedPeriod = ClosedPeriod::findForPeriod($year, $month);
        $isClosed = $closedPeriod && $closedPeriod->status === 'closed';

        // 1. Gerar AFD (REP-P)
        $afdGenerator = app(AfdGenerator_2026_07_31::class);
        $afdResult = $afdGenerator->generate($establishment, $startDate, $endDate);

        // 2. Gerar AEJ (PTRP)
        $aejGenerator = app(AejGenerator_2026_07_31::class);
        $aejResult = $aejGenerator->generate(
            establishment: $establishment,
            year: $year,
            month: $month,
            forcePreview: ! $isClosed,
        );

        // 3. Gerar Resumo do Espelho de Ponto da Competência
        $timesheetSummary = $this->buildTimesheetSummary($closedPeriod, $establishment, $year, $month);

        // 4. Gerar Extrato de Movimentação do Banco de Horas
        $timeBankExtract = $this->buildTimeBankExtract($closedPeriod, $startDate, $endDate);

        // 5. Gerar LEIA-ME de Fiscalização
        $readmeContent = $this->buildReadme(
            company: $company,
            establishment: $establishment,
            year: $year,
            month: $month,
            isClosed: $isClosed,
            closedPeriod: $closedPeriod,
            aejResult: $aejResult,
            afdResult: $afdResult
        );

        // 6. Montar Manifesto de Hashes (SHA-256)
        $filesToZip = [
            $afdResult->filename => $afdResult->content,
            $aejResult->filename => $aejResult->content,
            sprintf('ESPELHO_PONTO_RESUMO_%s_%04d%02d.txt', $cnpjClean, $year, $month) => $timesheetSummary,
            sprintf('EXTRATO_BANCO_HORAS_%s_%04d%02d.txt', $cnpjClean, $year, $month) => $timeBankExtract,
            'LEIA-ME_FISCALIZACAO.txt' => $readmeContent,
        ];

        $manifestLines = [];
        $manifestLines[] = '================================================================================';
        $manifestLines[] = 'MANIFESTO DE INTEGRIDADE FORENSE - PACOTE FISCAL MTE (PORTARIA 671/2021)';
        $manifestLines[] = sprintf('EMPREGADOR: %s (CNPJ: %s)', $company->legal_name, $establishment->identifier_number);
        $manifestLines[] = sprintf('COMPETÊNCIA: %02d/%04d', $month, $year);
        $manifestLines[] = sprintf('DATA DE GERAÇÃO: %s', now()->toIso8601String());
        if ($closedPeriod) {
            $manifestLines[] = sprintf('SNAPSHOT HASH DA COMPETÊNCIA: %s (Versão: %d)', $closedPeriod->snapshot_hash ?? 'N/A', $closedPeriod->snapshot_version);
        }
        $manifestLines[] = '================================================================================';
        $manifestLines[] = '';
        $manifestLines[] = sprintf('%-64s  %s', 'HASH SHA-256', 'ARQUIVO');
        $manifestLines[] = str_repeat('-', 95);

        foreach ($filesToZip as $fname => $content) {
            $hash = hash('sha256', $content);
            $manifestLines[] = sprintf('%-64s  %s', $hash, $fname);
        }

        $filesToZip['HASHES_SHA256.txt'] = implode("\r\n", $manifestLines)."\r\n";

        // 7. Empacotar em ZIP
        $zipFilename = sprintf('PACOTE_FISCAL_MTE_%s_%04d%02d.zip', $cnpjClean, $year, $month);
        $zipTempPath = tempnam(sys_get_temp_dir(), 'pf_fiscal_').'.zip';

        $zip = new ZipArchive;
        if ($zip->open($zipTempPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Não foi possível inicializar o arquivo compactado ZIP.');
        }

        foreach ($filesToZip as $zipName => $zipContent) {
            $zip->addFromString($zipName, $zipContent);
        }

        $zip->close();

        Log::info('fiscal_package.generated', [
            'establishment_id' => $establishment->id,
            'year' => $year,
            'month' => $month,
            'is_closed' => $isClosed,
            'requested_by' => $requestedBy?->id,
            'zip_filename' => $zipFilename,
        ]);

        return [
            'zip_path' => $zipTempPath,
            'filename' => $zipFilename,
            'is_closed' => $isClosed,
            'period_hash' => $closedPeriod?->snapshot_hash,
            'total_files' => count($filesToZip),
        ];
    }

    protected function buildTimesheetSummary(?ClosedPeriod $period, Establishment $establishment, int $year, int $month): string
    {
        $lines = [];
        $lines[] = '================================================================================';
        $lines[] = 'RELATÓRIO CONSOLIDADO DO ESPELHO DE PONTO DA COMPETÊNCIA (PTRP)';
        $lines[] = sprintf('ESTABELECIMENTO: %s (%s)', $establishment->name, $establishment->identifier_number);
        $lines[] = sprintf('COMPETÊNCIA: %02d/%04d', $month, $year);
        $lines[] = '================================================================================';
        $lines[] = '';

        if (! $period || $period->status !== 'closed') {
            $lines[] = 'STATUS: PRÉVIA (COMPETÊNCIA NÃO FECHADA)';
            $lines[] = 'Os dados consolidados oficiais serão gerados após o fechamento formal da competência.';

            return implode("\r\n", $lines)."\r\n";
        }

        $snapshots = $period->currentSnapshots()->get();
        foreach ($snapshots as $snap) {
            $emp = $snap->employee_snapshot;
            $tb = $snap->time_bank_snapshot;
            $lines[] = sprintf('TRABALHADOR: %s | CPF: %s | MATRÍCULA: %s | CARGO: %s', $emp['name'] ?? '', $emp['cpf'] ?? '', $emp['registration_number'] ?? '', $emp['job_title'] ?? '');
            $lines[] = sprintf('  HASH SNAPSHOT: %s', $snap->snapshot_hash);
            $lines[] = sprintf('  BANCO DE HORAS: Saldo Ant.: %d min | Saldo Fechamento: %d min | Zeramento: %d min | Saldo Final: %d min',
                $tb['balance_before'] ?? 0,
                $tb['balance_at_closing'] ?? 0,
                $tb['reset_applied'] ?? 0,
                $tb['final_balance'] ?? 0
            );
            $lines[] = str_repeat('-', 80);
        }

        return implode("\r\n", $lines)."\r\n";
    }

    protected function buildTimeBankExtract(?ClosedPeriod $period, Carbon $startDate, Carbon $endDate): string
    {
        $lines = [];
        $lines[] = '================================================================================';
        $lines[] = 'EXTRATO DE MOVIMENTAÇÃO DE BANCO DE HORAS DA COMPETÊNCIA (LEDGER IMUTÁVEL)';
        $lines[] = sprintf('PERÍODO: %s A %s', $startDate->format('d/m/Y'), $endDate->format('d/m/Y'));
        $lines[] = '================================================================================';
        $lines[] = '';

        if ($period && $period->status === 'closed') {
            $snapshots = $period->currentSnapshots()->get();
            $hasAny = false;
            foreach ($snapshots as $snap) {
                $empName = $snap->employee_snapshot['name'] ?? 'N/A';
                $txs = $snap->time_bank_snapshot['transactions'] ?? [];
                foreach ($txs as $tx) {
                    $hasAny = true;
                    $lines[] = sprintf(
                        '[%s] TX #%s | EMP: %s | TIPO: %-22s | MIN: %+5d | DESC: %s',
                        Carbon::parse($tx['reference_date'])->format('d/m/Y'),
                        substr((string) ($tx['id'] ?? 'N/A'), -8),
                        $empName,
                        $tx['type'] ?? 'N/A',
                        $tx['minutes'] ?? 0,
                        'Registro imutável em snapshot de fechamento'
                    );
                }
            }
            if (! $hasAny) {
                $lines[] = 'Nenhuma movimentação de banco de horas registrada no período.';
            }

            return implode("\r\n", $lines)."\r\n";
        }

        $transactions = TimeBankTransaction::with('account.employee.user')
            ->whereBetween('reference_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->orderBy('reference_date', 'asc')
            ->get();

        if ($transactions->isEmpty()) {
            $lines[] = 'Nenhuma movimentação de banco de horas registrada no período.';
        } else {
            foreach ($transactions as $tx) {
                $lines[] = sprintf(
                    '[%s] TX #%s | EMP: %s | TIPO: %-22s | MIN: %+5d | DESC: %s',
                    $tx->reference_date?->format('d/m/Y'),
                    substr($tx->id, -8),
                    $tx->account?->employee?->user?->name ?? 'N/A',
                    $tx->type->value,
                    $tx->minutes,
                    $tx->description ?? ''
                );
            }
        }

        return implode("\r\n", $lines)."\r\n";
    }

    protected function buildReadme($company, $establishment, int $year, int $month, bool $isClosed, ?ClosedPeriod $closedPeriod, $aejResult, $afdResult): string
    {
        $statusStr = $isClosed ? 'COMPETÊNCIA FECHADA E CONGELADA' : 'PRÉVIA - COMPETÊNCIA NÃO FECHADA';
        $hashStr = $closedPeriod?->snapshot_hash ?? 'N/A (Competência Aberta)';

        return <<<TXT
================================================================================
PACOTE DE FISCALIZAÇÃO TRABALHISTA - MINISTÉRIO DO TRABALHO E EMPREGO (MTE)
PontoFácil 2.0 (REP-P e PTRP) - Conforme Portaria 671/2021 MTP
================================================================================

1. IDENTIFICAÇÃO DO EMPREGADOR:
   - Razão Social: {$company->legal_name}
   - Nome Fantasia: {$company->trade_name}
   - CNPJ do Estabelecimento: {$establishment->identifier_number}
   - Estabelecimento: {$establishment->name}
   - Fuso Horário Legal: {$establishment->timezone}

2. DADOS DA COMPETÊNCIA FISCAL:
   - Período de Apuração: 01/{$month}/{$year} a {$aejResult->endDate->format('d/m/Y')}
   - Status da Competência: {$statusStr}
   - Versão do Snapshot Congelado: {$closedPeriod?->snapshot_version}
   - Hash Forense SHA-256 da Competência: {$hashStr}
   - Data de Geração: {$aejResult->endDate->now()->format('d/m/Y H:i:s')}

3. IDENTIFICAÇÃO DO SOFTWARE:
   - Nome do Software REP-P: {$company->rep_p_software_name}
   - Versão: {$company->rep_p_software_version}
   - Tipo de Sistema: REP-P (Registrador Eletrônico de Ponto via Programa) e PTRP (Programa de Tratamento de Registro de Ponto)
   - Leiaute Fiscal: Portaria 671/2021 MTP (Leiaute MTE vigente a partir de 31/07/2026)

4. CONTEÚDO DESTE PACOTE:
   a) {$afdResult->filename}:
      Arquivo Fonte de Dados (AFD) gerado exclusivamente pelo REP-P a partir do ledger
      imutável de marcações (punch_events). Contém marcações brutas com NSR sequencial,
      CPF do trabalhador, payload criptográfico SHA-256 encadeado e trailer com CRC-32.

   b) {$aejResult->filename}:
      Arquivo Eletrônico de Jornada (AEJ) gerado exclusivamente pelo PTRP a partir
      do congelamento analítico da competência. Contém cadastro de empregados, horários/escalas,
      marcações efetivas com cruzamento de NSR do REP, apuração mensal de jornada e extrato do banco de horas.

   c) ESPELHO_PONTO_RESUMO:
      Demonstrativo sintético dos espelhos de ponto dos colaboradores e seus respectivos hashes individuais.

   d) EXTRATO_BANCO_HORAS:
      Extrato das movimentações analíticas contábeis de horas extras, compensações e fechamento do período.

   e) HASHES_SHA256.txt:
      Manifesto com o resumo criptográfico SHA-256 de cada arquivo contido neste pacote, permitindo
      a conferência imediata de não adulteração.

5. NOTA REGULATÓRIA SOBRE ASSINATURA DIGITAL:
   Enquanto pendente a instalação de certificado digital ICP-Brasil e homologação definitiva
   do registro de software no INPI, este pacote e seus arquivos fiscais são fornecidos
   em MODO DE DESENVOLVIMENTO / NÃO ASSINADOS DIGITALMENTE, mantendo contudo 100% de conformidade
   estrutural, posicional, regras de validação e cálculo de checksum CRC-32 exigidos pelo MTE.
================================================================================
TXT;
    }
}
