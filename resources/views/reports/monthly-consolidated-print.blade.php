<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $reportData['report_type_title'] }} — {{ sprintf('%02d/%04d', $reportData['month'], $reportData['year']) }}</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 10mm 8mm 10mm 8mm;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            font-size: 8pt;
            color: #111827;
            background: #fff;
            margin: 0;
            padding: 0;
        }
        .header {
            border-bottom: 2px solid #1e1b4b;
            padding-bottom: 8px;
            margin-bottom: 10px;
        }
        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        .company-title {
            font-size: 14pt;
            font-weight: bold;
            color: #1e1b4b;
            text-transform: uppercase;
        }
        .doc-title {
            font-size: 11pt;
            font-weight: bold;
            color: #374151;
            margin-top: 2px;
        }
        .badge {
            display: inline-block;
            padding: 4px 8px;
            font-size: 8pt;
            font-weight: bold;
            border-radius: 4px;
            text-transform: uppercase;
        }
        .badge-official {
            background-color: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        .badge-provisional {
            background-color: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        .provisional-alert {
            background-color: #fef2f2;
            border: 1px solid #f87171;
            color: #991b1b;
            padding: 6px 10px;
            font-size: 8pt;
            margin-bottom: 8px;
            border-radius: 4px;
            font-weight: bold;
            text-align: center;
        }
        .meta-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 6px;
            font-size: 7.5pt;
            background-color: #f9fafb;
            padding: 6px 8px;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            margin-bottom: 8px;
        }
        .meta-item strong {
            color: #4b5563;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 7pt;
        }
        th {
            background-color: #f3f4f6;
            color: #374151;
            font-weight: bold;
            text-transform: uppercase;
            padding: 4px 3px;
            border: 1px solid #d1d5db;
            text-align: left;
        }
        td {
            padding: 3.5px 3px;
            border: 1px solid #e5e7eb;
            vertical-align: middle;
        }
        tr:nth-child(even) {
            background-color: #fcfcfd;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; }
        .font-bold { font-weight: bold; }
        .text-positive { color: #059669; }
        .text-negative { color: #dc2626; }
        .tfoot-row td {
            background-color: #f3f4f6;
            font-weight: bold;
            border-top: 2px solid #9ca3af;
        }
        .signatures {
            margin-top: 20px;
            display: flex;
            justify-content: space-between;
            page-break-inside: avoid;
        }
        .sig-box {
            width: 45%;
            text-align: center;
            border-top: 1px solid #374151;
            padding-top: 4px;
            font-size: 8pt;
        }
        .no-print {
            margin-bottom: 12px;
            padding: 8px 12px;
            background: #e0e7ff;
            border: 1px solid #c7d2fe;
            border-radius: 6px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        @media print {
            .no-print { display: none !important; }
        }
        button.btn-print {
            background-color: #4338ca;
            color: white;
            border: none;
            padding: 6px 14px;
            font-weight: bold;
            font-size: 9pt;
            border-radius: 4px;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="no-print">
        <span><strong>Documento pronto para impressão/salvamento em PDF</strong> (Configuração recomendada: Paisagem A4).</span>
        <button class="btn-print" onclick="window.print()">Imprimir / Salvar como PDF</button>
    </div>

    @if ($reportData['is_provisional'])
        <div class="provisional-alert">
            {{ $reportData['provisional_notice'] }}
        </div>
    @endif

    <div class="header">
        <div class="header-top">
            <div>
                <div class="company-title">{{ $reportData['company_name'] }}</div>
                <div class="doc-title">{{ $reportData['report_type_title'] }} — COMPETÊNCIA {{ sprintf('%02d/%04d', $reportData['month'], $reportData['year']) }}</div>
                <div style="font-size: 7.5pt; color: #6b7280; margin-top: 2px;">
                    CNPJ: {{ $reportData['company_cnpj'] ?: 'NÃO INFORMADO' }} • Módulo PTRP Frequência
                </div>
            </div>
            <div style="text-align: right;">
                <span class="badge {{ $reportData['is_provisional'] ? 'badge-provisional' : 'badge-official' }}">
                    {{ $reportData['report_badge_label'] }}
                </span>
                <div style="font-size: 7pt; color: #6b7280; margin-top: 4px;">
                    Emissão: {{ \Carbon\Carbon::parse($reportData['generated_at'])->format('d/m/Y H:i') }}
                </div>
            </div>
        </div>
    </div>

    <div class="meta-grid">
        <div class="meta-item"><strong>Competência:</strong> {{ sprintf('%02d/%04d', $reportData['month'], $reportData['year']) }}</div>
        <div class="meta-item"><strong>Total Servidores:</strong> {{ $reportData['totals']['total_employees'] }}</div>
        <div class="meta-item">
            <strong>Status Mês:</strong> {{ $reportData['is_closed'] ? 'Fechado (Congelado)' : 'Aberto (Em apuração)' }}
        </div>
        <div class="meta-item">
            <strong>Responsável Fechamento:</strong> {{ $reportData['closed_by_name'] ?: 'Pendente' }}
        </div>
        @if ($reportData['is_closed'])
            <div class="meta-item"><strong>Versão Snapshot:</strong> V{{ $reportData['closed_version'] }}</div>
            <div class="meta-item" style="grid-column: span 3; font-family: monospace; font-size: 6.5pt;">
                <strong>Hash Fiscal SHA-256:</strong> {{ $reportData['closed_hash'] }}
            </div>
        @endif
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 2%;">#</th>
                <th style="width: 16%;">Servidor / Matrícula</th>
                <th style="width: 10%;">Cargo / Regime</th>
                <th style="width: 12%;">Lotação / Escala</th>
                <th class="text-right" style="width: 6%;">Previsto</th>
                <th class="text-right" style="width: 6%;">Trabalhado</th>
                <th class="text-right" style="width: 6%;">Saldo</th>
                <th class="text-right" style="width: 5%;">Faltas</th>
                <th class="text-right" style="width: 5%;">Atrasos</th>
                <th class="text-right" style="width: 5%;">Noturno</th>
                <th class="text-center" style="width: 4%;">Plantões</th>
                <th class="text-center" style="width: 8%;">Conferência</th>
                <th style="width: 15%;">Ressalvas da Chefia / RH</th>
            </tr>
        </thead>
        <tbody>
            @php $count = 1; @endphp
            @forelse ($reportData['rows'] as $r)
                <tr>
                    <td class="text-center">{{ $count++ }}</td>
                    <td>
                        <div class="font-bold">{{ $r['name'] }}</div>
                        <div style="font-size: 6.5pt; color: #6b7280;">Matr.: {{ $r['registration_number'] }} • CPF: {{ $r['cpf'] }}</div>
                    </td>
                    <td>
                        <div>{{ $r['job_title'] }}</div>
                        <div style="font-size: 6.5pt; color: #6b7280;">{{ $r['legal_regime'] }}</div>
                    </td>
                    <td>
                        <div>{{ $r['department'] }}</div>
                        <div style="font-size: 6.5pt; color: #6b7280;">{{ $r['schedule_name'] }}</div>
                    </td>
                    <td class="text-right font-mono">{{ sprintf('%02d:%02d', intdiv($r['scheduled_minutes'], 60), $r['scheduled_minutes'] % 60) }}</td>
                    <td class="text-right font-mono font-bold">{{ sprintf('%02d:%02d', intdiv($r['worked_minutes'], 60), $r['worked_minutes'] % 60) }}</td>
                    <td class="text-right font-mono font-bold">
                        @if ($r['balance_minutes'] > 0)
                            <span class="text-positive">+{{ sprintf('%02d:%02d', intdiv($r['balance_minutes'], 60), $r['balance_minutes'] % 60) }}</span>
                        @elseif ($r['balance_minutes'] < 0)
                            <span class="text-negative">-{{ sprintf('%02d:%02d', intdiv(abs($r['balance_minutes']), 60), abs($r['balance_minutes']) % 60) }}</span>
                        @else
                            00:00
                        @endif
                    </td>
                    <td class="text-right font-mono">{{ sprintf('%02d:%02d', intdiv($r['absence_minutes'], 60), $r['absence_minutes'] % 60) }}</td>
                    <td class="text-right font-mono">{{ sprintf('%02d:%02d', intdiv($r['delay_minutes'], 60), $r['delay_minutes'] % 60) }}</td>
                    <td class="text-right font-mono">{{ sprintf('%02d:%02d', intdiv($r['night_minutes'], 60), $r['night_minutes'] % 60) }}</td>
                    <td class="text-center">{{ $r['shifts_count'] ?: '—' }}</td>
                    <td class="text-center">
                        <span style="font-size: 6.5pt; font-weight: bold;">{{ $r['review_status_label'] }}</span>
                    </td>
                    <td style="font-size: 6.5pt; color: #374151;">
                        {{ $r['reservation_notes'] ?: '—' }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="13" class="text-center" style="padding: 15px;">Nenhum registro localizado para os filtros informados.</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            @php $t = $reportData['totals']; @endphp
            <tr class="tfoot-row font-mono">
                <td colspan="4" class="text-right font-bold" style="font-family: inherit;">TOTAIS GERAIS:</td>
                <td class="text-right font-bold">{{ sprintf('%02d:%02d', intdiv($t['scheduled_minutes'], 60), $t['scheduled_minutes'] % 60) }}</td>
                <td class="text-right font-bold">{{ sprintf('%02d:%02d', intdiv($t['worked_minutes'], 60), $t['worked_minutes'] % 60) }}</td>
                <td class="text-right font-bold">
                    @if ($t['balance_minutes'] > 0)
                        <span class="text-positive">+{{ sprintf('%02d:%02d', intdiv($t['balance_minutes'], 60), $t['balance_minutes'] % 60) }}</span>
                    @elseif ($t['balance_minutes'] < 0)
                        <span class="text-negative">-{{ sprintf('%02d:%02d', intdiv(abs($t['balance_minutes']), 60), abs($t['balance_minutes']) % 60) }}</span>
                    @else
                        00:00
                    @endif
                </td>
                <td class="text-right font-bold">{{ sprintf('%02d:%02d', intdiv($t['absence_minutes'], 60), $t['absence_minutes'] % 60) }}</td>
                <td class="text-right font-bold">{{ sprintf('%02d:%02d', intdiv($t['delay_minutes'], 60), $t['delay_minutes'] % 60) }}</td>
                <td class="text-right font-bold">{{ sprintf('%02d:%02d', intdiv($t['night_minutes'], 60), $t['night_minutes'] % 60) }}</td>
                <td class="text-center font-bold">{{ $t['shifts_count'] }}</td>
                <td colspan="2" class="text-center" style="font-family: inherit; font-size: 6.5pt;">
                    {{ $t['reviewed_count'] }} Conferidos • {{ $t['with_reservation_count'] }} com Ressalva
                </td>
            </tr>
        </tfoot>
    </table>

    <div class="signatures">
        <div class="sig-box">
            <strong>Responsável pela Apuração e Conferência de Frequência (RH)</strong><br>
            {{ $reportData['generated_by_name'] }}
        </div>
        <div class="sig-box">
            <strong>Ciência / Recebimento do Setor de Pagamento (Folha Salarial)</strong><br>
            Data: _____/_____/_________
        </div>
    </div>
</body>
</html>
