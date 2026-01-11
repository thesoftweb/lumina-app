<?php

namespace App\Filament\Resources\DocumentTemplates\Pages;

use App\Filament\Resources\DocumentTemplates\DocumentTemplateResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditDocumentTemplate extends EditRecord
{
    protected static string $resource = DocumentTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Extrair merge_tags do conteúdo
        $data['merge_tags'] = $this->extractMergeTags($data['content'] ?? '');
        return $data;
    }

    private function extractMergeTags(string $content): array
    {
        $tags = [];
        // Procurar por padrões {{tag_name}} no conteúdo
        if (preg_match_all('/\{\{(\w+)\}\}/', $content, $matches)) {
            foreach ($matches[1] as $tag) {
                $tags[$tag] = $tag;
            }
        }
        return $tags;
    }
}
