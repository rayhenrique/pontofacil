<?php

namespace App\Domain\PTRP\Actions;

use App\Domain\Company\Services\SectorScopeService;
use App\Domain\PTRP\Services\MonthlyAttendanceReviewService;
use App\Enums\AttendanceReviewStatus;
use App\Models\ClosedPeriod;
use App\Models\Employee;
use App\Models\MonthlyAttendanceReview;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReviewMonthlyAttendanceAction
{
    public function __construct(
        protected MonthlyAttendanceReviewService $reviewService,
    ) {}

    /**
     * Realiza a conferência individual de frequência de um servidor.
     *
     * @throws \DomainException|\InvalidArgumentException
     */
    public function reviewEmployee(
        Employee $employee,
        int $year,
        int $month,
        User $reviewer,
        ?string $reservationNotes = null,
    ): MonthlyAttendanceReview {
        $this->validatePermissions($employee, $reviewer);

        if (ClosedPeriod::isClosed($year, $month)) {
            throw new \DomainException(sprintf(
                'A competência %02d/%04d já está fechada e congelada. Não é permitido alterar conferências.',
                $month,
                $year
            ));
        }

        // Analisar apuração do colaborador para avaliar ocorrências
        $reviewData = $this->reviewService->resolveEmployeeReviewData($employee, $year, $month);
        $hasOccurrences = $reviewData['has_unresolved_occurrences'] ?? false;

        $trimmedReservation = trim((string) $reservationNotes);

        if ($hasOccurrences && mb_strlen($trimmedReservation) < 5) {
            throw new \InvalidArgumentException(
                'O servidor possui ocorrências pendentes na competência. É obrigatório fornecer uma ressalva/justificativa fundamentada (mínimo de 5 caracteres) para confirmar a frequência.'
            );
        }

        $finalStatus = (! empty($trimmedReservation) || $hasOccurrences)
            ? AttendanceReviewStatus::ReviewedWithReservation
            : AttendanceReviewStatus::Reviewed;

        return DB::transaction(function () use ($employee, $year, $month, $reviewer, $finalStatus, $trimmedReservation) {
            $review = MonthlyAttendanceReview::lockForUpdate()
                ->where('employee_id', $employee->id)
                ->where('year', $year)
                ->where('month', $month)
                ->first();

            if (! $review) {
                $review = new MonthlyAttendanceReview([
                    'employee_id' => $employee->id,
                    'year' => $year,
                    'month' => $month,
                ]);
            }

            $review->reviewed_by = $reviewer->id;
            $review->reviewed_at = now();
            $review->status = $finalStatus;
            $review->reservation_notes = ! empty($trimmedReservation) ? $trimmedReservation : null;
            $review->save();

            Log::info('monthly_attendance.reviewed', [
                'employee_id' => $employee->id,
                'year' => $year,
                'month' => $month,
                'reviewed_by' => $reviewer->id,
                'status' => $finalStatus->value,
                'has_reservation' => ! empty($trimmedReservation),
            ]);

            return $review;
        });
    }

    /**
     * Realiza a conferência em lote apenas de registros elegíveis (sem ocorrências pendentes).
     *
     * @param  array<int>  $employeeIds
     * @return array{reviewed_count: int, skipped_count: int, skipped_ids: array<int>}
     */
    public function reviewBatch(
        array $employeeIds,
        int $year,
        int $month,
        User $reviewer,
    ): array {
        if (ClosedPeriod::isClosed($year, $month)) {
            throw new \DomainException(sprintf(
                'A competência %02d/%04d já está fechada e congelada. Não é permitido realizar conferências.',
                $month,
                $year
            ));
        }

        $employees = Employee::whereIn('id', $employeeIds)->get();
        $reviewedCount = 0;
        $skippedIds = [];

        foreach ($employees as $employee) {
            try {
                $this->validatePermissions($employee, $reviewer);

                $reviewData = $this->reviewService->resolveEmployeeReviewData($employee, $year, $month);
                $hasOccurrences = $reviewData['has_unresolved_occurrences'] ?? false;

                // Em lote, somente registros elegíveis sem ocorrências são confirmados automaticamente
                if ($hasOccurrences) {
                    $skippedIds[] = $employee->id;

                    continue;
                }

                $this->reviewEmployee($employee, $year, $month, $reviewer, null);
                $reviewedCount++;
            } catch (\Exception $e) {
                $skippedIds[] = $employee->id;
            }
        }

        return [
            'reviewed_count' => $reviewedCount,
            'skipped_count' => count($skippedIds),
            'skipped_ids' => $skippedIds,
        ];
    }

    /**
     * Valida se o usuário tem permissão para conferir o servidor.
     */
    protected function validatePermissions(Employee $employee, User $reviewer): void
    {
        app(SectorScopeService::class)->authorizeEmployee($reviewer, $employee);
    }
}
