<?php

namespace App\Domain\Calendar\Services;

use App\Enums\CalendarEventScope;
use App\Enums\CalendarEventType;
use App\Enums\WorkBehavior;
use App\Models\CalendarEvent;
use Carbon\Carbon;

/**
 * 20.18.15 — Seeder de feriados nacionais brasileiros.
 *
 * Importa somente FERIADOS LEGAIS (Lei 662/1949, Lei 6.802/1980, Lei 10.607/2002, Lei 14.759/2023)
 * e a Sexta-feira Santa (tradição legal consolidada).
 *
 * NÃO importa pontos facultativos automaticamente — estes devem ser cadastrados
 * manualmente pelo Admin/RH, pois dependem do tipo de instituição (Portaria MGI,
 * Decreto municipal, decisão empresarial etc.).
 */
class BrazilianHolidaysService
{
    /**
     * Retorna a lista de feriados nacionais legais para o ano especificado.
     *
     * @return array<int, array{name: string, date: string, type: CalendarEventType, scope: CalendarEventScope, work_behavior: WorkBehavior, legal_reference: string}>
     */
    public function getNationalHolidaysForYear(int $year): array
    {
        $holidays = [];

        // 1. Feriados Nacionais Fixos
        $fixed = [
            ['01-01', 'Confraternização Universal', 'Lei Federal nº 662/1949'],
            ['04-21', 'Tiradentes', 'Lei Federal nº 662/1949'],
            ['05-01', 'Dia Mundial do Trabalho', 'Lei Federal nº 662/1949'],
            ['09-07', 'Independência do Brasil', 'Lei Federal nº 662/1949'],
            ['10-12', 'Nossa Senhora Aparecida', 'Lei Federal nº 6.802/1980'],
            ['11-02', 'Finados', 'Lei Federal nº 662/1949'],
            ['11-15', 'Proclamação da República', 'Lei Federal nº 662/1949'],
            ['11-20', 'Dia da Consciência Negra', 'Lei Federal nº 14.759/2023'],
            ['12-25', 'Natal', 'Lei Federal nº 662/1949'],
        ];

        foreach ($fixed as [$monthDay, $name, $legalRef]) {
            $holidays[] = [
                'name' => $name,
                'date' => sprintf('%04d-%s', $year, $monthDay),
                'type' => CalendarEventType::Holiday,
                'scope' => CalendarEventScope::National,
                'work_behavior' => WorkBehavior::NoWorkExpected,
                'legal_reference' => $legalRef,
            ];
        }

        // 2. Feriado Nacional Móvel — Sexta-feira Santa (Paixão de Cristo)
        $easterTimestamp = easter_date($year);
        $easter = Carbon::createFromTimestamp($easterTimestamp, 'America/Sao_Paulo');
        $goodFriday = (clone $easter)->subDays(2);

        $holidays[] = [
            'name' => 'Sexta-feira Santa (Paixão de Cristo)',
            'date' => $goodFriday->format('Y-m-d'),
            'type' => CalendarEventType::Holiday,
            'scope' => CalendarEventScope::National,
            'work_behavior' => WorkBehavior::NoWorkExpected,
            'legal_reference' => 'Lei Federal nº 662/1949 c/c tradição legal consolidada',
        ];

        // Ordenar cronologicamente
        usort($holidays, fn ($a, $b) => strcmp($a['date'], $b['date']));

        return $holidays;
    }

    /**
     * Retorna datas sugeridas de pontos facultativos tradicionais federais para referência do Admin.
     *
     * ⚠ Estes NÃO devem ser importados automaticamente como eventos ativos.
     * O Admin/RH deve revisar e decidir o work_behavior individualmente.
     *
     * @return array<int, array{name: string, date: string, note: string}>
     */
    public function getSuggestedOptionalDaysForYear(int $year): array
    {
        $easterTimestamp = easter_date($year);
        $easter = Carbon::createFromTimestamp($easterTimestamp, 'America/Sao_Paulo');

        return [
            [
                'name' => 'Carnaval (Segunda-feira)',
                'date' => (clone $easter)->subDays(48)->format('Y-m-d'),
                'note' => 'Ponto facultativo federal tradicional. Verificar Portaria MGI vigente.',
            ],
            [
                'name' => 'Carnaval (Terça-feira)',
                'date' => (clone $easter)->subDays(47)->format('Y-m-d'),
                'note' => 'Ponto facultativo federal tradicional. Verificar Portaria MGI vigente.',
            ],
            [
                'name' => 'Quarta-feira de Cinzas',
                'date' => (clone $easter)->subDays(46)->format('Y-m-d'),
                'note' => 'Ponto facultativo parcial (até 14h na APF). Verificar Portaria MGI vigente.',
            ],
            [
                'name' => 'Corpus Christi',
                'date' => (clone $easter)->addDays(60)->format('Y-m-d'),
                'note' => 'Ponto facultativo federal. Feriado municipal em diversas capitais.',
            ],
            [
                'name' => 'Dia do Servidor Público',
                'date' => sprintf('%04d-10-28', $year),
                'note' => 'Art. 236 da Lei nº 8.112/1990 — apenas servidores federais.',
            ],
            [
                'name' => 'Véspera de Natal',
                'date' => sprintf('%04d-12-24', $year),
                'note' => 'Ponto facultativo parcial (a partir de 14h na APF).',
            ],
            [
                'name' => 'Véspera de Ano Novo',
                'date' => sprintf('%04d-12-31', $year),
                'note' => 'Ponto facultativo parcial (a partir de 14h na APF).',
            ],
        ];
    }

    /**
     * Importa SOMENTE os feriados nacionais legais para a base de dados.
     *
     * @return int Quantidade de registros criados ou atualizados
     */
    public function importNationalHolidays(int $year, ?int $userId = null): int
    {
        $items = $this->getNationalHolidaysForYear($year);
        $count = 0;

        foreach ($items as $item) {
            CalendarEvent::updateOrCreate(
                [
                    'event_date' => $item['date'],
                    'name' => $item['name'],
                    'scope' => $item['scope'],
                    'type' => $item['type'],
                ],
                [
                    'work_behavior' => $item['work_behavior'],
                    'legal_reference' => $item['legal_reference'],
                    'all_day' => true,
                    'active' => true,
                    'created_by' => $userId,
                ]
            );
            $count++;
        }

        return $count;
    }
}
