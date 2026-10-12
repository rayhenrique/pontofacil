<?php

return [
    [
        'term' => 'REP-P (Registrador Eletrônico de Ponto por Programa)',
        'acronym' => 'REP-P',
        'definition' => 'Sistema de registro eletrônico de ponto composto por software executado em servidor dedicado, responsável por coletar as batidas de ponto, gerar comprovantes assinados e gravar as marcações no Arquivo Fonte de Dados (AFD).',
    ],
    [
        'term' => 'PTRP (Programa de Tratamento de Registro de Ponto)',
        'acronym' => 'PTRP',
        'definition' => 'Módulo de software responsável por processar e confrontar as marcações com as escalas de trabalho, aplicar tolerâncias legais, registrar justificativas de ausência, apurar o espelho de ponto e emitir o Arquivo Eletrônico de Jornada (AEJ).',
    ],
    [
        'term' => 'ARP (Arquivo de Registro de Ponto)',
        'acronym' => 'ARP',
        'definition' => 'Base soberana de dados onde cada marcação física é gravada de forma inviolável no momento da leitura do QR Code, sem sofrer nenhuma alteração ou sobrescrita posterior.',
    ],
    [
        'term' => 'NSR (Número Sequencial de Registro)',
        'acronym' => 'NSR',
        'definition' => 'Numeração monotônica estritamente crescente e sem repetição gerada para cada marcação de ponto realizada em um estabelecimento, servindo de prova fiscal de ordem cronológica ininterrupta.',
    ],
    [
        'term' => 'Hash SHA-256',
        'acronym' => 'SHA-256',
        'definition' => 'Resumo criptográfico de 64 caracteres hexadecimais gerado matematicamente sobre os dados de cada marcação. Qualquer mínima tentativa de alteração do registro gerará um hash incompatível, denunciando adulteração.',
    ],
    [
        'term' => 'Horas Físicas',
        'acronym' => 'HF',
        'definition' => 'O tempo real cronológico transcorrido entre a entrada e a saída apuradas no relógio de ponto, sem acréscimos artificiais ou conversões de redução ficta.',
    ],
    [
        'term' => 'Equivalência Legal Noturna',
        'acronym' => 'Hora Noturna',
        'definition' => 'Fórmula legal que considera a hora noturna (trabalhada entre 22h e 05h) como tendo 52 minutos e 30 segundos (redução ficta de 1,1428x). No PontoFácil, as horas físicas e a equivalência noturna são sempre apresentadas em colunas distintas.',
    ],
    [
        'term' => 'Carga Prevista da Competência',
        'acronym' => 'Carga Prevista',
        'definition' => 'A soma exata das horas de expediente programadas na escala do colaborador para os dias daquele mês específico, descontados descansos e feriados regulamentares.',
    ],
    [
        'term' => 'Carga Horária Contratual',
        'acronym' => 'Carga Contratual',
        'definition' => 'A carga pactuada no edital de concurso público ou no contrato individual de trabalho (ex: 40h semanais, 30h semanais, 20h semanais).',
    ],
    [
        'term' => 'Snapshot Imutável',
        'acronym' => 'Snapshot',
        'definition' => 'Fotografia digital congelada no banco de dados no instante em que o RH executa o fechamento mensal da competência. Impede que recálculos futuros alterem a apuração de um mês já homologado.',
    ],
    [
        'term' => 'Chave de Idempotência',
        'acronym' => 'Idempotência',
        'definition' => 'Identificador único de tentativa de batida que impede que oscilações de conexão de rede façam o servidor gravar a mesma marcação duas vezes.',
    ],
    [
        'term' => 'Ressalva Fundamentada',
        'acronym' => 'Ressalva',
        'definition' => 'Justificativa textual formal gravada por um gestor ou RH ao conferir a frequência de um colaborador que possui alguma situação atípica conhecida pendente de regularização administrativa.',
    ],
];
