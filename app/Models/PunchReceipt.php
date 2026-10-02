<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PunchReceipt extends Model
{
    protected $fillable = [
        'punch_event_id',
        'verification_code',
        'receipt_hash',
        'signature_status',
        'signed_at',
        'signature_metadata',
    ];

    protected $casts = [
        'signed_at' => 'datetime',
        'signature_metadata' => 'array',
    ];

    public function punchEvent(): BelongsTo
    {
        return $this->belongsTo(PunchEvent::class, 'punch_event_id', 'id');
    }

    /**
     * Retorna a URL de verificação pública do comprovante.
     */
    public function verificationUrl(): string
    {
        return url('/verificar-comprovante?codigo='.$this->verification_code);
    }
}
