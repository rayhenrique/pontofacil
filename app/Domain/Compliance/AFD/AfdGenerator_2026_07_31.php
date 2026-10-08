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
     * Versão oficial vigente do leiaute do AFD conforme a Portaria 671/2021 MTE (publicado 31/07/2026).
     */
    public const LAYOUT_VERSION = '004';

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

        $isPendingInpi = ! $company->isRegisteredInpi() || empty($company->inpi_registration_number);
        $isMissingDeveloper = empty(config('compliance.developer.document')) || empty(config('compliance.developer.name'));
        $isPendingCertificate = config('compliance.icp_brasil.status', 'pending_certificate') === 'pending_certificate';

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

        // 1. Cabeçalho (Registro Tipo 1) - exatamente 302 caracteres (com CRC-16 Kermit)
        $headerNsr = '000000000';
        $headerTipo = '1';
        $idType = ($establishment->identifier_type === 'cpf') ? '2' : '1';
        $idNumberRaw = preg_replace('/\D/', '', (string) $establishment->identifier_number);
        $idNumber = str_pad(substr($idNumberRaw, 0, 14), 14, ' ', STR_PAD_RIGHT);
        $caepfCno = str_repeat(' ', 14);
        $legalName = mb_str_pad(mb_substr($this->sanitize($company->legal_name), 0, 150), 150, ' ', STR_PAD_RIGHT);

        // Registro INPI (17 dígitos numéricos). Se pendente, preenche com 17 espaços sem texto fictício nem zeros fictícios.
        $inpiRaw = preg_replace('/\D/', '', (string) ($company->isRegisteredInpi() ? ($company->inpi_registration_number ?? '') : ''));
        $inpiRegistration = ($company->isRegisteredInpi() && $inpiRaw !== '')
            ? str_pad(substr($inpiRaw, 0, 17), 17, ' ', STR_PAD_RIGHT)
            : str_repeat(' ', 17);

        $dtInicio = $startDate->format('Y-m-d');
        $dtFim = $endDate->format('Y-m-d');
        $dtGeracao = $this->fiscalHashService->formatDateTimeIso($genTime);
        $versao = self::LAYOUT_VERSION;

        $devDocRaw = config('compliance.developer.document');
        $devDocClean = ! empty($devDocRaw) ? preg_replace('/\D/', '', (string) $devDocRaw) : '';
        $devIdTypeRaw = config('compliance.developer.document_type');
        $devIdType = $devDocClean !== ''
            ? (in_array((string) $devIdTypeRaw, ['1', '2'], true) ? (string) $devIdTypeRaw : (strlen($devDocClean) === 11 ? '2' : '1'))
            : ' ';
        $devIdNumber = $devDocClean !== ''
            ? str_pad(substr($devDocClean, 0, 14), 14, ' ', STR_PAD_RIGHT)
            : str_repeat(' ', 14);

        // Modelo, no caso de REP-C (para REP-P, preencher com 30 espaços)
        $softwareModel = str_repeat(' ', 30);

        $headerPrefix = $headerNsr.$headerTipo.$idType.$idNumber.$caepfCno.$legalName.$inpiRegistration.$dtInicio.$dtFim.$dtGeracao.$versao.$devIdType.$devIdNumber.$softwareModel;
        $headerCrc = $this->fiscalHashService->calculateCrc16($headerPrefix);
        $lines[] = $headerPrefix.$headerCrc;

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
                        $dtGravacao = $this->fiscalHashService->formatDateTimeIso($event->occurred_at_local, $event->utc_offset);
                        $cpfRespRaw = preg_replace('/\D/', '', (string) ($event->payload['responsible_cpf'] ?? $event->user?->cpf ?? ''));
                        $cpfResp = str_pad(substr($cpfRespRaw, 0, 11), 14, ' ', STR_PAD_RIGHT);
                        $empIdType = ($event->payload['identifier_type'] ?? $establishment->identifier_type) === 'cpf' ? '2' : '1';
                        $empIdNumRaw = preg_replace('/\D/', '', (string) ($event->payload['identifier_number'] ?? $establishment->identifier_number));
                        $empIdNumber = str_pad(substr($empIdNumRaw, 0, 14), 14, ' ', STR_PAD_RIGHT);
                        $cnoCaepfRaw = preg_replace('/\D/', '', (string) ($event->payload['cei_caepf'] ?? $event->payload['cno'] ?? $event->payload['caepf'] ?? ''));
                        $cei = $cnoCaepfRaw !== '' ? str_pad(substr($cnoCaepfRaw, 0, 14), 14, ' ', STR_PAD_RIGHT) : str_repeat(' ', 14);
                        $razao = mb_str_pad(mb_substr($this->sanitize($event->payload['legal_name'] ?? $company->legal_name), 0, 150), 150, ' ', STR_PAD_RIGHT);
                        $local = mb_str_pad(mb_substr($this->sanitize($event->payload['workplace'] ?? $establishment->name ?? 'MATRIZ'), 0, 100), 100, ' ', STR_PAD_RIGHT);

                        $prefix = $nsr.$tipo.$dtGravacao.$cpfResp.$empIdType.$empIdNumber.$cei.$razao.$local;
                        $crc = $this->fiscalHashService->calculateCrc16($prefix);
                        $lines[] = $prefix.$crc;
                        break;

                    case ArpEventType::TimeSync:
                        $countTipo4++;
                        $tipo = '4';
                        $dtAntesVal = isset($event->payload['before_occurred_at'])
                            ? Carbon::parse($event->payload['before_occurred_at'])
                            : $event->occurred_at_local;
                        $dtAntes = $this->fiscalHashService->formatDateTimeIso($dtAntesVal, $event->utc_offset);
                        $dtDepois = $this->fiscalHashService->formatDateTimeIso($event->occurred_at_local, $event->utc_offset);
                        $cpfRespRaw = preg_replace('/\D/', '', (string) ($event->payload['responsible_cpf'] ?? $event->user?->cpf ?? ''));
                        $cpfResp = str_pad(substr($cpfRespRaw, 0, 11), 11, '0', STR_PAD_LEFT);

                        $prefix = $nsr.$tipo.$dtAntes.$dtDepois.$cpfResp;
                        $crc = $this->fiscalHashService->calculateCrc16($prefix);
                        $lines[] = $prefix.$crc;
                        break;

                    case ArpEventType::WorkerMutation:
                        $countTipo5++;
                        $tipo = '5';
                        $dtGravacao = $this->fiscalHashService->formatDateTimeIso($event->occurred_at_local, $event->utc_offset);
                        $operacao = strtoupper(substr((string) ($event->payload['mutation_type'] ?? 'A'), 0, 1));
                        if (! in_array($operacao, ['I', 'A', 'E'], true)) {
                            $operacao = 'A';
                        }
                        $cpfRaw = preg_replace('/\D/', '', (string) ($event->employee?->cpf ?? ($event->payload['cpf'] ?? '')));
                        $cpfEmp = str_pad(substr($cpfRaw, 0, 11), 12, '0', STR_PAD_LEFT);
                        $nomeEmp = mb_str_pad(mb_substr($this->sanitize($event->employee?->user?->name ?? ($event->payload['name'] ?? '')), 0, 52), 52, ' ', STR_PAD_RIGHT);
                        $demaisDados = str_pad(substr((string) ($event->payload['additional_data'] ?? $event->payload['other_data'] ?? ''), 0, 4), 4, ' ', STR_PAD_RIGHT);
                        $cpfRespRaw = preg_replace('/\D/', '', (string) ($event->payload['responsible_cpf'] ?? $event->user?->cpf ?? ''));
                        $cpfResp = str_pad(substr($cpfRespRaw, 0, 11), 11, '0', STR_PAD_LEFT);

                        $prefix = $nsr.$tipo.$dtGravacao.$operacao.$cpfEmp.$nomeEmp.$demaisDados.$cpfResp;
                        $crc = $this->fiscalHashService->calculateCrc16($prefix);
                        $lines[] = $prefix.$crc;
                        break;

                    case ArpEventType::RepSensitiveEvent:
                        $countTipo6++;
                        $tipo = '6';
                        $dtHrGravacao = $this->fiscalHashService->formatDateTimeIso($event->occurred_at_local, $event->utc_offset);
                        $eventCodeRaw = (string) ($event->payload['event_code'] ?? '07');
                        // Códigos oficiais MTE REP-P: '07' Disponibilidade de serviço, '08' Indisponibilidade de serviço, '02' Retorno de energia
                        $codEvento = match ($eventCodeRaw) {
                            '08', 'unavailable', 'indisponibilidade' => '08',
                            '02', 'power_restore', 'retorno_energia' => '02',
                            default => '07',
                        };

                        $lines[] = $nsr.$tipo.$dtHrGravacao.$codEvento;
                        break;

                    case ArpEventType::Punch:
                        $countTipo7++;
                        $tipo = '7';
                        $dtHrMarcacao = $this->fiscalHashService->formatDateTimeIso($event->occurred_at_local, $event->utc_offset);
                        $cpfRaw = preg_replace('/\D/', '', (string) ($event->employee?->cpf ?? ''));
                        $cpfEmp = str_pad(substr($cpfRaw, 0, 11), 12, '0', STR_PAD_LEFT);

                        $recordedLocal = isset($event->payload['recorded_at'])
                            ? Carbon::parse($event->payload['recorded_at'])
                            : $event->occurred_at_local;
                        $dtHrGravacao = $this->fiscalHashService->formatDateTimeIso($recordedLocal, $event->utc_offset);

                        $collector = $this->mapCollectorType($event->payload['collector_type'] ?? $event->payload['source'] ?? 'browser');
                        $tipoMarcacao = ($event->payload['is_offline'] ?? false) ? '1' : '0';

                        // Solução Oficial: O fiscal_hash gravado na ARP no momento da batida é a fonte imutável
                        if (! empty($event->fiscal_hash)) {
                            $hash = $event->fiscal_hash;
                            $previousTipo7Hash = $hash;
                        } else {
                            // Se precisar recalcular, busca o último evento Tipo 7 anterior ao intervalo exportado
                            if ($previousTipo7Hash === null) {
                                $priorPunchEvent = ArpEvent::where('establishment_id', $establishment->id)
                                    ->where('event_type', ArpEventType::Punch)
                                    ->where('nsr', '<', $event->nsr)
                                    ->orderBy('nsr', 'desc')
                                    ->first();
                                $previousTipo7Hash = $priorPunchEvent?->fiscal_hash;
                            }

                            $hash = $this->fiscalHashService->calculateTipo7FiscalHash(
                                nsr: (int) $event->nsr,
                                occurredAtLocal: $event->occurred_at_local,
                                recordedAtLocal: $recordedLocal,
                                cpf: $event->employee?->cpf,
                                collectorType: $collector,
                                punchType: $tipoMarcacao,
                                previousTipo7FiscalHash: $previousTipo7Hash
                            );
                            $previousTipo7Hash = $hash;
                        }

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

                if ($previousHash === null) {
                    $priorPunch = PunchEvent::where('establishment_id', $establishment->id)
                        ->where('nsr', '<', $punch->nsr)
                        ->orderBy('nsr', 'desc')
                        ->first();
                    // Fallback para cadeia legada
                }

                $fiscalHash = $this->fiscalHashService->calculateTipo7FiscalHash(
                    nsr: (int) $punch->nsr,
                    occurredAtLocal: $punch->occurred_at_local,
                    recordedAtLocal: $punch->occurred_at_local,
                    cpf: $punch->employee?->cpf,
                    collectorType: $collector,
                    punchType: $tipoMarcacao,
                    previousTipo7FiscalHash: $previousHash
                );
                $previousHash = $fiscalHash;
                $hash = str_pad(strtolower(substr($fiscalHash, 0, 64)), 64, '0', STR_PAD_RIGHT);

                $lines[] = $nsr.$tipo.$dtHrMarcacao.$cpfEmp.$dtHrGravacao.$collector.$tipoMarcacao.$hash;
            }
        }

        // 3. Trailer (Registro Tipo 9) - exatamente 64 caracteres com '9' no final (posição 64)
        $trailerNsr = '999999999';
        $trailerTipo = '9';
        $qtdTipo2 = str_pad((string) $countTipo2, 9, '0', STR_PAD_LEFT);
        $qtdTipo3 = str_pad((string) $countTipo3, 9, '0', STR_PAD_LEFT);
        $qtdTipo4 = str_pad((string) $countTipo4, 9, '0', STR_PAD_LEFT);
        $qtdTipo5 = str_pad((string) $countTipo5, 9, '0', STR_PAD_LEFT);
        $qtdTipo6 = str_pad((string) $countTipo6, 9, '0', STR_PAD_LEFT);
        $qtdTipo7 = str_pad((string) $countTipo7, 9, '0', STR_PAD_LEFT);

        $lines[] = $trailerNsr.$qtdTipo2.$qtdTipo3.$qtdTipo4.$qtdTipo5.$qtdTipo6.$qtdTipo7.$trailerTipo;

        // 4. Marcador Oficial de Assinatura Externa CAdES (.p7s) - exatamente 100 caracteres
        $lines[] = mb_str_pad('ASSINATURA_DIGITAL_EM_ARQUIVO_P7S', 100, ' ', STR_PAD_RIGHT);

        $finalContent = implode("\r\n", $lines)."\r\n";

        $idClean = preg_replace('/\D/', '', (string) $establishment->identifier_number);
        $inpiClean = preg_replace('/[^A-Za-z0-9]/', '', (string) ($company->isRegisteredInpi() ? ($company->inpi_registration_number ?? '') : ''));

        if ($forcePreview) {
            $filename = sprintf('AFD_PREVIA_%s_%s_%s.txt', $idClean, $startDate->format('Ymd'), $endDate->format('Ymd'));
        } elseif ($isPendingInpi) {
            // Enquanto o registro no INPI estiver pendente, mantém nomenclatura de prévia/desenvolvimento
            $filename = sprintf('AFD_%s_%s_%s.txt', $idClean, $startDate->format('Ymd'), $endDate->format('Ymd'));
        } else {
            // Nomenclatura oficial MTE quando houver registro no INPI (Portaria 671/2021 MTP, item 10.3):
            // Junção da palavra "AFD" com número de registro no INPI, CNPJ/CPF do empregador e "REP_P"
            $filename = sprintf('AFD_%s_%s_REP_P.txt', $inpiClean, $idClean);
        }

        $totalRecords = $countTipo2 + $countTipo3 + $countTipo4 + $countTipo5 + $countTipo6 + $countTipo7;

        // Sem arquivo .p7s real gerado e validado com certificado ICP-Brasil, o arquivo permanece não homologado
        $homologationReason = null;
        if ($isPendingInpi) {
            $homologationReason = 'pending_inpi';
        } elseif ($isMissingDeveloper) {
            $homologationReason = 'missing_developer_data';
        } else {
            $homologationReason = 'pending_certificate';
        }

        return new AfdExportResult(
            content: $finalContent,
            filename: $filename,
            establishment: $establishment,
            startDate: $startDate,
            endDate: $endDate,
            totalRecords: $totalRecords,
            crcChecksum: $headerCrc,
            isHomologated: false,
            signatureStatus: 'pending_certificate',
            homologationReason: $homologationReason,
            structureValid: true,
            signatureValid: false
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
