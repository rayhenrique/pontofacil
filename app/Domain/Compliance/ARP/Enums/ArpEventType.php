<?php

namespace App\Domain\Compliance\ARP\Enums;

/**
 * Tipos de Eventos Fiscais Registrados na ARP (Armazenamento de Registro de Ponto)
 * conforme as diretrizes do REP-P na Portaria 671/2021 MTP.
 */
enum ArpEventType: string
{
    /** Marcação de Ponto (Batida) */
    case Punch = 'punch';

    /** Inclusão / alteração de dados do empregador ou do estabelecimento */
    case EmployerEstablishmentMutation = 'employer_establishment_mutation';

    /** Inclusão, alteração ou inativação de dados do trabalhador */
    case WorkerMutation = 'worker_mutation';

    /** Ajustes relevantes do relógio / sincronização de data e hora */
    case TimeSync = 'time_sync';

    /** Eventos sensíveis de segurança / integridade do REP */
    case RepSensitiveEvent = 'rep_sensitive_event';

    public function label(): string
    {
        return match ($this) {
            self::Punch => 'Marcação de Ponto',
            self::EmployerEstablishmentMutation => 'Mutação de Empregador / Estabelecimento',
            self::WorkerMutation => 'Mutação Cadastral de Trabalhador',
            self::TimeSync => 'Sincronização de Relógio / Tempo',
            self::RepSensitiveEvent => 'Evento Sensível do REP',
        };
    }
}
