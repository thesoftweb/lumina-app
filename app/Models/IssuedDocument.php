<?php

namespace App\Models;

use App\Enums\IssuedDocumentStatus;
use App\Enums\IssuedDocumentType;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;
use LogicException;

/**
 * Documento emitido: o conteúdo fica congelado em `data` e protegido por `content_hash`.
 * Depois de emitido só pode ser cancelado; qualquer outra alteração ou exclusão é recusada.
 */
class IssuedDocument extends Model
{
    /** Disco privado onde ficam as cópias da logo usadas nos documentos já emitidos. */
    public const LOGO_DISK = 'local';

    /** Crockford base32: sem I, L, O e U, que se confundem ao digitar. */
    public const CODE_ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    public const CODE_LENGTH = 16;

    private const CANCELLATION_FIELDS = ['canceled_at', 'canceled_by', 'cancel_reason', 'updated_at'];

    protected $guarded = [];

    protected $casts = [
        'type' => IssuedDocumentType::class,
        'data' => 'array',
        'valid_until' => 'date',
        'issued_at' => 'datetime',
        'canceled_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (IssuedDocument $document): void {
            $locked = array_diff(array_keys($document->getDirty()), self::CANCELLATION_FIELDS);

            if ($locked !== []) {
                throw new LogicException('Documento emitido não pode ser alterado: '.implode(', ', $locked).'.');
            }
        });

        static::deleting(function (): void {
            throw new LogicException('Documento emitido não pode ser excluído; cancele-o.');
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function canceler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'canceled_by');
    }

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function status(): IssuedDocumentStatus
    {
        return match (true) {
            $this->canceled_at !== null => IssuedDocumentStatus::Canceled,
            $this->valid_until !== null && $this->valid_until->endOfDay()->isPast() => IssuedDocumentStatus::Expired,
            default => IssuedDocumentStatus::Valid,
        };
    }

    public function scopeWithStatus(Builder $query, IssuedDocumentStatus $status): Builder
    {
        return match ($status) {
            IssuedDocumentStatus::Canceled => $query->whereNotNull('canceled_at'),
            IssuedDocumentStatus::Expired => $query->whereNull('canceled_at')->whereDate('valid_until', '<', today()),
            IssuedDocumentStatus::Valid => $query->whereNull('canceled_at')
                ->where(fn (Builder $query) => $query->whereNull('valid_until')->orWhereDate('valid_until', '>=', today())),
        };
    }

    public static function hashContent(array $attributes): string
    {
        return hash('sha256', json_encode([
            $attributes['type'] instanceof IssuedDocumentType ? $attributes['type']->value : $attributes['type'],
            $attributes['number'],
            $attributes['verification_code'],
            $attributes['subject'],
            $attributes['purpose'] ?? null,
            $attributes['valid_until'] ?? null,
            $attributes['issued_at'],
            self::canonical($attributes['data']),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /** O MySQL reordena as chaves de colunas JSON; o hash usa as chaves em ordem alfabética. */
    private static function canonical(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(self::canonical(...), $value);
    }

    /** Confere se o conteúdo gravado é o mesmo da emissão (detecta alteração direta no banco). */
    public function hasIntactContent(): bool
    {
        return hash_equals($this->content_hash, self::hashContent([
            'type' => $this->type,
            'number' => $this->number,
            'verification_code' => $this->verification_code,
            'subject' => $this->subject,
            'purpose' => $this->purpose,
            'valid_until' => $this->valid_until?->toDateString(),
            'issued_at' => $this->issued_at->toIso8601String(),
            'data' => $this->data,
        ]));
    }

    /** Aceita o código como digitado: minúsculas, sem hífens, O no lugar de 0 e I/L no lugar de 1. */
    public static function normalizeCode(string $code): string
    {
        return strtr(strtoupper(preg_replace('/[^0-9a-zA-Z]/', '', $code)), ['O' => '0', 'I' => '1', 'L' => '1']);
    }

    public static function generateCode(): string
    {
        $code = '';

        for ($i = 0; $i < self::CODE_LENGTH; $i++) {
            $code .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
        }

        return $code;
    }

    public function formattedCode(): string
    {
        return implode('-', str_split($this->verification_code, 4));
    }

    public function verificationUrl(): string
    {
        return route('documents.verify', $this->formattedCode());
    }

    public function qrCodeDataUri(): string
    {
        return (new QRCode(new QROptions([
            'outputType' => QROutputInterface::MARKUP_SVG,
            'outputBase64' => true,
            'addQuietzone' => false,
        ])))->render($this->verificationUrl());
    }

    /** QR code em PNG para os PDFs gerados com dompdf. */
    public function qrCodePngDataUri(): string
    {
        return (new QRCode(new QROptions([
            'outputType' => QROutputInterface::GDIMAGE_PNG,
            'outputBase64' => true,
            'addQuietzone' => false,
            'scale' => 6,
        ])))->render($this->verificationUrl());
    }

    public function logoDataUri(): ?string
    {
        return $this->fileDataUri($this->data['company']['logo'] ?? null);
    }

    /** Arquivo copiado na emissão (logo, foto), embutido na página para não depender de URL pública. */
    public function fileDataUri(?string $path): ?string
    {
        $disk = Storage::disk(self::LOGO_DISK);

        if (! $path || ! $disk->exists($path)) {
            return null;
        }

        return 'data:'.$disk->mimeType($path).';base64,'.base64_encode($disk->get($path));
    }

    /** CPF parcialmente oculto na página pública de verificação: ***.234.214-** */
    public static function maskDocument(?string $document): ?string
    {
        if (blank($document)) {
            return $document;
        }

        $digits = preg_replace('/\D/', '', $document);

        return strlen($digits) === 11
            ? '***.'.substr($digits, 3, 3).'.'.substr($digits, 6, 3).'-**'
            : str_repeat('*', max(strlen($document) - 4, 0)).substr($document, -4);
    }
}
