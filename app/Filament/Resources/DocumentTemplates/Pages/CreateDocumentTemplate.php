<?php

namespace App\Filament\Resources\DocumentTemplates\Pages;

use App\Filament\Resources\DocumentTemplates\DocumentTemplateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDocumentTemplate extends CreateRecord
{
    protected static string $resource = DocumentTemplateResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
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
