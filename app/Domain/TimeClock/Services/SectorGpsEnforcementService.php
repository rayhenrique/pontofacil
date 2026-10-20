<?php

namespace App\Domain\TimeClock\Services;

use App\Domain\TimeClock\Exceptions\GpsEnforcementException;
use App\Models\Employee;
use App\Models\Sector;
use App\Models\User;

class SectorGpsEnforcementService
{
    /**
     * Precisão máxima aceitável em metros declarada pela Geolocation API (WGS84).
     * Sinais com margem de incerteza superior a 150m representam triangulações
     * imprecisas de torre/IP celular e são rejeitados para evitar falsos perímetros.
     */
    public const MAX_ALLOWED_ACCURACY_METERS = 150.0;

    /**
     * Raio médio volumétrico da Terra em metros (IUGG / WGS84).
     */
    public const EARTH_RADIUS_METERS = 6371000.0;

    /**
     * Valida os requisitos geográficos obrigatórios quando a trava de GPS do setor está ativada.
     * Retorna array com telemetria validada caso a trava esteja ativa e aprovada,
     * ou null caso a trava esteja desativada (GPS opcional).
     *
     * @param  User  $user  Colaborador autenticado
     * @param  float|null  $latitude  Latitude enviada pelo dispositivo
     * @param  float|null  $longitude  Longitude enviada pelo dispositivo
     * @param  float|null  $accuracy  Precisão em metros declarada pelo dispositivo
     * @return array{enforced: bool, valid: bool, distance: float, allowed_radius: float, sector_id: int, sector_name: string}|null
     *
     * @throws GpsEnforcementException
     */
    public function validatePunch(
        User $user,
        ?float $latitude,
        ?float $longitude,
        ?float $accuracy
    ): ?array {
        $employee = Employee::where('user_id', $user->id)->first();
        if (! $employee || ! $employee->sector_id) {
            return null;
        }

        /** @var Sector|null $sector */
        $sector = Sector::find($employee->sector_id);
        if (! $sector || ! $sector->gps_enforcement_enabled) {
            return null;
        }

        // Caso E: Configuração inconsistente do setor
        if (
            $sector->latitude === null ||
            $sector->longitude === null ||
            $sector->allowed_radius_meters === null ||
            ! is_numeric($sector->latitude) ||
            ! is_numeric($sector->longitude) ||
            ! is_numeric($sector->allowed_radius_meters) ||
            is_nan((float) $sector->latitude) ||
            is_nan((float) $sector->longitude) ||
            is_infinite((float) $sector->latitude) ||
            is_infinite((float) $sector->longitude) ||
            (float) $sector->latitude < -90.0 ||
            (float) $sector->latitude > 90.0 ||
            (float) $sector->longitude < -180.0 ||
            (float) $sector->longitude > 180.0 ||
            (int) $sector->allowed_radius_meters <= 0
        ) {
            throw new GpsEnforcementException(
                'Não foi possível validar a área autorizada. Entre em contato com o RH.',
                'inconsistent_sector_config'
            );
        }

        // Caso C: GPS desativado / sem permissão / coordenadas nulas ou inválidas
        if ($latitude === null || $longitude === null) {
            throw new GpsEnforcementException(
                'Este setor exige localização. Ative a permissão de GPS para tentar novamente.',
                'missing_coordinates'
            );
        }

        if (
            ! is_numeric($latitude) ||
            ! is_numeric($longitude) ||
            is_nan($latitude) ||
            is_nan($longitude) ||
            is_infinite($latitude) ||
            is_infinite($longitude) ||
            $latitude < -90.0 ||
            $latitude > 90.0 ||
            $longitude < -180.0 ||
            $longitude > 180.0
        ) {
            throw new GpsEnforcementException(
                'Este setor exige localização. Ative a permissão de GPS para tentar novamente.',
                'invalid_coordinates'
            );
        }

        // Caso D: Precisão insuficiente
        if (
            $accuracy === null ||
            ! is_numeric($accuracy) ||
            is_nan($accuracy) ||
            is_infinite($accuracy) ||
            $accuracy <= 0.0 ||
            $accuracy > self::MAX_ALLOWED_ACCURACY_METERS
        ) {
            throw new GpsEnforcementException(
                'Não foi possível confirmar sua localização com precisão suficiente. Tente novamente em um local com melhor sinal.',
                'insufficient_accuracy'
            );
        }

        // Cálculo consistente de distância ortodrômica via Haversine
        $distance = $this->calculateHaversineDistance(
            (float) $latitude,
            (float) $longitude,
            (float) $sector->latitude,
            (float) $sector->longitude
        );

        $allowedRadius = (float) $sector->allowed_radius_meters;

        // Caso B: Fora do raio permitido
        // Margem de tolerância infinitesimal (1e-6) estritamente para prevenir imprecisões de ponto flutuante
        // exatamente na fronteira física (ex: distance == allowedRadius).
        if ($distance > ($allowedRadius + 0.000001)) {
            throw new GpsEnforcementException(
                'Registro não autorizado: você está fora da área permitida para seu setor.',
                'outside_allowed_radius'
            );
        }

        // Caso A: Dentro do raio
        return [
            'enforced' => true,
            'valid' => true,
            'distance' => round($distance, 2),
            'allowed_radius' => $allowedRadius,
            'sector_id' => $sector->id,
            'sector_name' => $sector->name,
        ];
    }

    /**
     * Calcula a distância geodésica em metros entre dois pontos geográficos pela fórmula de Haversine.
     */
    public function calculateHaversineDistance(
        float $lat1,
        float $lon1,
        float $lat2,
        float $lon2
    ): float {
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return self::EARTH_RADIUS_METERS * $c;
    }
}
