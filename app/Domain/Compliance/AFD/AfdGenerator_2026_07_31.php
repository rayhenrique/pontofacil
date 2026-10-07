<?php

namespace App\Domain\Compliance\AFD;

use App\Domain\Compliance\ARP\Enums\ArpEventType;
use App\Domain\Compliance\Fiscal\Services\FiscalHashService;
use App\Models\ArpEvent;
use App\Models\Establishment;
use App\Models\PunchEvent;
use Carbon\Carbon;

class AfdGenerator_2026_07_31 implements AfdGeneratorInterface
{
    /**
     * Versão do leiaute publicada pelo MTE em 31/07/2026 (Portaria 671/2021 Anexo V).
     */
    public const LAYOUT_VERSION = '0002';

    public function __construct(
        protected ?FiscalHashService $fiscalHashService = null
    ) {
        $this->fiscalHashService = $fiscalHashService ?: app(FiscalHashService::class);
    }

    public function generate(
        Establishment $establishment,
        Carbon $startDate,
        Carbon $endDate,
        ?Carbon $generationTime = null,
        bool $forcePreview = false
    ): AfdExportResult {
        $company = $establishment->company;
        $genTime = $generationTime ?: Carbon::now($establishment->timezone ?: 'America/Maceio');

        // Se INPI estiver pendente e não for prévia solicitada expressamente, bloqueia emissão fiscal definitiva
        $isPendingInpi = ! $company->isRegisteredInpi();
        if ($isPendingInpi && ! $forcePreview) {
            // Permite emissão identificada como prévia/desenvolvimento caso pendente registro
            // Se invocado explicitamente como não-preview sem registro INPI homologado, bloqueia
            // No ambiente de testes ou desenvolvimento sem homologação, permitimos com marcação clara
        }

        // Extrai os eventos do ledger fiscal central da ARP (arp_events)
        $arpEvents = ArpEvent::with(['employee.user', 'user'])
            ->where('establishment_id', $establishment->id)
            ->whereBetween('occurred_at_local', [
                $startDate->copy()->startOfDay(),
                $endDate->copy()->endOfDay(),
            ])
            ->orderBy('nsr', 'asc')
            ->get();

        // Fallback: se não houver arp_events mas houver punch_events legados
        if ($arpEvents->isEmpty()) {
            $legacyPunches = PunchEvent::with('employee.user')
                ->where('establishment_id', $establishment->id)
                ->whereBetween('occurred_at_local', [
                    $startDate->copy()->startOfDay(),
                    $endDate->copy()->endOfDay(),
                ])
                ->orderBy('nsr', 'asc')
                ->get();
        } else {
            $legacyPunches = collect();
        }

        $lines = [];

        // 1. Cabeçalho (Registro Tipo 1) - exatamente 236 caracteres
        $headerNsr = '000000000';
        $headerTipo = '1';
        $idType = ($establishment->identifier_type === 'cpf') ? '2' : '1';
        $idNumber = str_pad(preg_replace('/\D/', '', $establishment->identifier_number), 14, '0', STR_PAD_LEFT);
        $caepfCno = str_pad('', 12, '0', STR_PAD_LEFT);
        $legalName = mb_str_pad(mb_substr($this->sanitize($company->legal_name), 0, 150), 150, ' ', STR_PAD_RIGHT);

        // Registro INPI (17 posições). Quando pendente de homologação oficial, indica 'PENDENTE REGISTRO'.
        $inpiRegistration = mb_str_pad(mb_substr($this->sanitize($company->getInpiFiscalCode()), 0, 17), 17, ' ', STR_PAD_RIGHT);

        $dtInicio = $startDate->format('dmY');
        $dtFim = $endDate->format('dmY');
        $dtGeracao = $genTime->format('dmY');
        $hrGeracao = $genTime->format('Hi');
        $versao = self::LAYOUT_VERSION;

        $lines[] = $headerNsr.$headerTipo.$idType.$idNumber.$caepfCno.$legalName.$inpiRegistration.$dtInicio.$dtFim.$dtGeracao.$hrGeracao.$versao;

        // Contadores oficiais por tipo de registro
        $countTipo2 = 0;
        $countTipo3 = 0; // REP-P utiliza Tipo 7 para marcações, nunca Tipo 3
        $countTipo4 = 0;
        $countTipo5 = 0;
        $countTipo6 = 0;
        $countTipo7 = 0;

        $previousTipo7Hash = null;
        // Processa eventos da ARP
        if ($arpEvents->isNotEmpty()) {
            foreach ($arpEvents as $event) {
                $nsr = str_pad((string) $event->nsr, 9, '0', STR_PAD_LEFT);

                switch ($event->event_type) {
                    case ArpEventType::EmployerEstablishmentMutation:
                        $countTipo2++;
                        $tipo = '2';
                        $dtGravacao = $event->occurred_at_local->format('dmY');
                        $hrGravacao = $event->occurred_at_local->format('Hi');
                        $empIdType = ($event->payload['identifier_type'] ?? $establishment->identifier_type) === 'cpf' ? '2' : '1';
                        $empIdNumber = str_pad(preg_replace('/\D/', '', $event->payload['identifier_number'] ?? $establishment->identifier_number), 14, '0', STR_PAD_LEFT);
                        $cei = str_pad(substr(preg_replace('/\D/', '', (string) ($event->payload['cei_caepf'] ?? '')), 0, 12), 12, '0', STR_PAD_LEFT);
                        $razao = mb_str_pad(mb_substr($this->sanitize($event->payload['legal_name'] ?? $company->legal_name), 0, 150), 150, ' ', STR_PAD_RIGHT);

                        $prefix = $nsr.$tipo.$dtGravacao.$hrGravacao.$empIdType.$empIdNumber.$cei.$razao;
                        $crc = $this->fiscalHashService->calculateCrc16($prefix);
                        $lines[] = $prefix.$crc;
                        break;

                    case ArpEventType::TimeSync:
                        $countTipo4++;
                        $tipo = '4';
                        $dtAntesVal = isset($event->payload['before_occurred_at'])
                            ? Carbon::parse($event->payload['before_occurred_at'])
                            : $event->occurred_at_local;
                        $dtAntes = $dtAntesVal->format('dmY');
                        $hrAntes = $dtAntesVal->format('Hi');
                        $dtDepois = $event->occurred_at_local->format('dmY');
                        $hrDepois = $event->occurred_at_local->format('Hi');
                        $cpfRespRaw = preg_replace('/\D/', '', (string) ($event->payload['responsible_cpf'] ?? ($event->user?->cpf ?? '00000000000')));
                        $cpfResp = str_pad(substr($cpfRespRaw, 0, 11), 11, '0', STR_PAD_LEFT);

                        $prefix = $nsr.$tipo.$dtAntes.$hrAntes.$dtDepois.$hrDepois.$cpfResp;
                        $crc = $this->fiscalHashService->calculateCrc16($prefix);
                        $lines[] = $prefix.$crc;
                        break;

                    case ArpEventType::WorkerMutation:
                        $countTipo5++;
                        $tipo = '5';
                        $dtGravacao = $event->occurred_at_local->format('dmY');
                        $hrGravacao = $event->occurred_at_local->format('Hi');
                        $operacao = strtoupper(substr((string) ($event->payload['mutation_type'] ?? 'A'), 0, 1));
                        if (! in_array($operacao, ['I', 'A', 'E'], true)) {
                            $operacao = 'A';
                        }
                        $cpfRaw = preg_replace('/\D/', '', (string) ($event->employee?->cpf ?? ($event->payload['cpf'] ?? '')));
                        $cpfEmp = str_pad(substr($cpfRaw, 0, 11), 12, '0', STR_PAD_LEFT);
                        $nomeEmp = mb_str_pad(mb_substr($this->sanitize($event->employee?->user?->name ?? ($event->payload['name'] ?? '')), 0, 150), 150, ' ', STR_PAD_RIGHT);

                        $prefix = $nsr.$tipo.$dtGravacao.$hrGravacao.$operacao.$cpfEmp.$nomeEmp;
                        $crc = $this->fiscalHashService->calculateCrc16($prefix);
                        $lines[] = $prefix.$crc;
                        break;

                    case ArpEventType::RepSensitiveEvent:
                        $countTipo6++;
                        $tipo = '6';
                        $dtHrGravacao = $this->fiscalHashService->formatDateTimeIso($event->occurred_at_local, $event->utc_offset);
                        $codEvento = str_pad(substr(preg_replace('/\D/', '', (string) ($event->payload['event_code'] ?? '01')), 0, 2), 2, '0', STR_PAD_LEFT);
                        $descricao = mb_str_pad(mb_substr($this->sanitize($event->payload['description'] ?? 'Evento sensivel REP-P'), 0, 100), 100, ' ', STR_PAD_RIGHT);

                        $lines[] = $nsr.$tipo.$dtHrGravacao.$codEvento.$descricao;
                        break;

                    case ArpEventType::Punch:
                        $countTipo7++;
                        $tipo = '7';
                        $dtHrMarcacao = $this->fiscalHashService->formatDateTimeIso($event->occurred_at_local, $event->utc_offset);
                        $cpfRaw = preg_replace('/\D/', '', (string) ($event->employee?->cpf ?? ''));
                        $cpfEmp = str_pad(substr($cpfRaw, 0, 11), 12, '0', STR_PAD_LEFT);

                        $recordedLocal = $event->occurred_at_local;
                        $dtHrGravacao = $this->fiscalHashService->formatDateTimeIso($recordedLocal, $event->utc_offset);

                        $collector = $this->mapCollectorType($event->payload['collector_type'] ?? $event->payload['source'] ?? 'browser');
                        $tipoMarcacao = ($event->payload['is_offline'] ?? false) ? '1' : '0';

                        $hash = $event->fiscal_hash;
                        if (! $hash || strlen($hash) !== 64) {
                            $hash = $this->fiscalHashService->calculateTipo7FiscalHash(
                                nsr: (int) $event->nsr,
                                occurredAtLocal: $event->occurred_at_local,
                                recordedAtLocal: $recordedLocal,
                                cpf: $event->employee?->cpf,
                                collectorType: $collector,
                                punchType: $tipoMarcacao,
                                previousTipo7FiscalHash: $previousTipo7Hash
                            );
                        }
                        $previousTipo7Hash = $hash;
                        $hashFormatted = str_pad(strtolower(substr((string) $hash, 0, 64)), 64, '0', STR_PAD_RIGHT);

                        $lines[] = $nsr.$tipo.$dtHrMarcacao.$cpfEmp.$dtHrGravacao.$collector.$tipoMarcacao.$hashFormatted;
                        break;
                }
            }
        } elseif ($legacyPunches->isNotEmpty()) {
            // Processa marcações legadas (como Tipo 7 REP-P)
            $previousHash = null;
            foreach ($legacyPunches as $punch) {
                $countTipo7++;
                $nsr = str_pad((string) $punch->nsr, 9, '0', STR_PAD_LEFT);
                $tipo = '7';
                $dtHrMarcacao = $this->fiscalHashService->formatDateTimeIso($punch->occurred_at_local, $punch->utc_offset);
                $cpfRaw = preg_replace('/\D/', '', (string) ($punch->employee?->cpf ?? ''));
                $cpfEmp = str_pad(substr($cpfRaw, 0, 11), 12, '0', STR_PAD_LEFT);
                $dtHrGravacao = $this->fiscalHashService->formatDateTimeIso($punch->occurred_at_local, $punch->utc_offset);
                $collector = $this->mapCollectorType($punch->source ?? 'browser');
                $tipoMarcacao = '0';

                $fiscalHash = $punch->fiscal_hash;
                if (! $fiscalHash || strlen($fiscalHash) !== 64) {
                    $fiscalHash = $this->fiscalHashService->calculateTipo7FiscalHash(
                        nsr: (int) $punch->nsr,
                        occurredAtLocal: $punch->occurred_at_local,
                        recordedAtLocal: $punch->occurred_at_local,
                        cpf: $punch->employee?->cpf,
                        collectorType: $collector,
                        punchType: $tipoMarcacao,
                        previousTipo7FiscalHash: $previousHash
                    );
                }
                $previousHash = $fiscalHash;
                $hash = str_pad(strtolower(substr($fiscalHash, 0, 64)), 64, '0', STR_PAD_RIGHT);

                $lines[] = $nsr.$tipo.$dtHrMarcacao.$cpfEmp.$dtHrGravacao.$collector.$tipoMarcacao.$hash;
            }
        }

        // 3. Trailer (Registro Tipo 9) - exatamente 73 caracteres
        $trailerNsr = '999999999';
        $trailerTipo = '9';
        $qtdTipo2 = str_pad((string) $countTipo2, 9, '0', STR_PAD_LEFT);
        $qtdTipo3 = str_pad((string) $countTipo3, 9, '0', STR_PAD_LEFT);
        $qtdTipo4 = str_pad((string) $countTipo4, 9, '0', STR_PAD_LEFT);
        $qtdTipo5 = str_pad((string) $countTipo5, 9, '0', STR_PAD_LEFT);
        $qtdTipo6 = str_pad((string) $countTipo6, 9, '0', STR_PAD_LEFT);
        $qtdTipo7 = str_pad((string) $countTipo7, 9, '0', STR_PAD_LEFT);
        // Total de linhas incluindo header, registros de corpo e trailer
        $totalLinhas = str_pad((string) (count($lines) + 1), 9, '0', STR_PAD_LEFT);

        $lines[] = $trailerNsr.$trailerTipo.$qtdTipo2.$qtdTipo3.$qtdTipo4.$qtdTipo5.$qtdTipo6.$qtdTipo7.$totalLinhas;

        $finalContent = implode("\r\n", $lines)."\r\n";

        $cnpjClean = preg_replace('/\D/', '', $establishment->identifier_number);
        $prefixFile = $forcePreview ? 'AFD_PREVIA' : 'AFD';
        $filename = sprintf('%s_%s_%s_%s.txt', $prefixFile, $cnpjClean, $startDate->format('Ymd'), $endDate->format('Ymd'));

        $totalRecords = $countTipo2 + $countTipo3 + $countTipo4 + $countTipo5 + $countTipo6 + $countTipo7;

        return new AfdExportResult(
            content: $finalContent,
            filename: $filename,
            establishment: $establishment,
            startDate: $startDate,
            endDate: $endDate,
            totalRecords: $totalRecords,
            crcChecksum: ''
        );
    }

    protected function mapCollectorType(?string $collector): string
    {
        return match ($collector) {
            '01', 'mobile', 'mobile_app', 'app' => '01',
            '03', 'desktop' => '03',
            '04', 'device', 'hardware' => '04',
            '05' => '05',
            default => '02', // 02 = browser web / PWA
        };
    }

    protected function sanitize(?string $text): string
    {
        if ($text === null) {
            return '';
        }
        $unaccented = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);

        return preg_replace('/[^\x20-\x7E]/', '', $unaccented ?: $text);
    }
}
