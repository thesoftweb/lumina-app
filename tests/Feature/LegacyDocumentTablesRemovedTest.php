<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** O módulo antigo de documentos e o contrato escolar foram removidos. */
class LegacyDocumentTablesRemovedTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): object
    {
        return require database_path('migrations/2026_10_23_000003_drop_legacy_document_tables.php');
    }

    public function test_tables_and_routes_are_gone(): void
    {
        $this->assertFalse(Schema::hasTable('documents'));
        $this->assertFalse(Schema::hasTable('document_templates'));

        foreach (['documents.print', 'documents.pdf', 'enrollments.contract.print', 'enrollments.contract.pdf'] as $route) {
            $this->assertFalse(app('router')->has($route), $route);
        }

        $this->get('/documents/1/print')->assertNotFound();
        $this->get('/enrollments/1/contract/print')->assertNotFound();
    }

    public function test_existing_rows_are_exported_before_dropping(): void
    {
        Storage::fake('local');
        $this->migration()->down();
        $templateId = DB::table('document_templates')->insertGetId(['name' => 'Recibo antigo', 'type' => 'receipt', 'content' => '<p>Recibo</p>']);
        DB::table('documents')->insert(['document_template_id' => $templateId, 'content' => '<p>Recibo de Maria</p>', 'status' => 'generated']);

        $this->migration()->up();

        $this->assertFalse(Schema::hasTable('documents'));
        $files = Storage::disk('local')->files('backups');
        $this->assertCount(1, $files);
        $backup = json_decode(Storage::disk('local')->get($files[0]), true);
        $this->assertSame('Recibo antigo', $backup['document_templates'][0]['name']);
        $this->assertSame('<p>Recibo de Maria</p>', $backup['documents'][0]['content']);
    }

    public function test_empty_tables_are_dropped_without_backup(): void
    {
        Storage::fake('local');
        $this->migration()->down();

        $this->migration()->up();

        $this->assertSame([], Storage::disk('local')->files('backups'));
    }
}
