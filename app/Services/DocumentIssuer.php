<?php

namespace App\Services;

use App\Enums\IssuedDocumentType;
use App\Models\Company;
use App\Models\IssuedDocument;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Emite e cancela documentos. Na emissão, os dados da escola (inclusive uma cópia da logo) e do
 * documento são congelados, o número sai da sequência da série no ano e o código de verificação
 * é sorteado.
 */
class DocumentIssuer
{
    public const MISSING_SIGNER_MESSAGE = 'Antes de emitir documentos, informe quem assina e o cargo em Configurações › Empresa › Documentos.';

    /**
     * @param  array<string, mixed>  $content  dados do documento; use apenas textos, números inteiros e booleanos
     */
    public function issue(
        IssuedDocumentType $type,
        Model $documentable,
        string $subject,
        array $content,
        User $issuer,
        ?string $purpose = null,
        ?CarbonInterface $validUntil = null,
    ): IssuedDocument {
        $company = Company::current($issuer)?->loadMissing('city.state');

        if (! $company?->document_signer_name || ! $company->document_signer_role) {
            throw ValidationException::withMessages(['company' => self::MISSING_SIGNER_MESSAGE]);
        }

        $issuedAt = now()->startOfSecond();
        $data = [
            'company' => $this->companySnapshot($company),
            'signer' => ['name' => $company->document_signer_name, 'role' => $company->document_signer_role],
            'content' => $content,
        ];

        return DB::transaction(function () use ($type, $documentable, $subject, $data, $issuer, $purpose, $validUntil, $issuedAt, $company): IssuedDocument {
            $sequence = $this->nextSequence($type->series(), $issuedAt->year);

            $attributes = [
                'type' => $type,
                'number' => sprintf('%s-%d-%06d', $type->series(), $issuedAt->year, $sequence),
                'verification_code' => $this->uniqueCode(),
                'subject' => $subject,
                'purpose' => filled($purpose) ? trim($purpose) : null,
                'valid_until' => $validUntil?->toDateString(),
                'issued_at' => $issuedAt->toIso8601String(),
                'data' => $data,
            ];

            return IssuedDocument::create([
                ...$attributes,
                'company_id' => $company->getKey(),
                'series' => $type->series(),
                'year' => $issuedAt->year,
                'sequence' => $sequence,
                'documentable_type' => $documentable->getMorphClass(),
                'documentable_id' => $documentable->getKey(),
                'issued_at' => $issuedAt,
                'issued_by' => $issuer->getKey(),
                'content_hash' => IssuedDocument::hashContent($attributes),
            ]);
        });
    }

    /** Sem usuário, o cancelamento é automático (ex.: recibo de pagamento estornado). */
    public function cancel(IssuedDocument $document, ?User $user, string $reason): IssuedDocument
    {
        if ($document->canceled_at) {
            throw ValidationException::withMessages(['reason' => 'Este documento já foi cancelado.']);
        }

        $document->update([
            'canceled_at' => now(),
            'canceled_by' => $user?->getKey(),
            'cancel_reason' => trim($reason),
        ]);

        return $document;
    }

    /** Próximo número da série no ano, com a linha da sequência travada até o fim da transação. */
    private function nextSequence(string $series, int $year): int
    {
        DB::table('document_sequences')->insertOrIgnore([
            'series' => $series, 'year' => $year, 'last_number' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $current = DB::table('document_sequences')
            ->where(['series' => $series, 'year' => $year])
            ->lockForUpdate()
            ->value('last_number');

        DB::table('document_sequences')
            ->where(['series' => $series, 'year' => $year])
            ->update(['last_number' => $current + 1, 'updated_at' => now()]);

        return $current + 1;
    }

    private function uniqueCode(): string
    {
        do {
            $code = IssuedDocument::generateCode();
        } while (IssuedDocument::where('verification_code', $code)->exists());

        return $code;
    }

    private function companySnapshot(Company $company): array
    {
        return [
            'name' => $company->name,
            'tax_id' => $company->tax_id,
            'inep_code' => $company->inep_code,
            'email' => $company->email,
            'phone' => $company->phone,
            'website' => $company->website,
            'address' => $company->full_address,
            'city' => $company->city_and_state,
            'logo' => $this->snapshotFile(Company::LOGO_DISK, $company->logo_path, 'logos'),
        ];
    }

    /**
     * Copia um arquivo (logo da escola, foto do aluno) para o disco privado dos documentos, com o nome pelo
     * conteúdo: trocar o arquivo original não muda os documentos já emitidos, e cópias iguais são guardadas uma vez só.
     */
    public function snapshotFile(string $disk, ?string $path, string $directory): ?string
    {
        $source = Storage::disk($disk);

        if (! $path || ! $source->exists($path)) {
            return null;
        }

        $contents = $source->get($path);
        $path = "issued-documents/{$directory}/".hash('sha256', $contents).'.'.pathinfo($path, PATHINFO_EXTENSION);
        $target = Storage::disk(IssuedDocument::LOGO_DISK);

        if (! $target->exists($path)) {
            $target->put($path, $contents);
        }

        return $path;
    }
}
