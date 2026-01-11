<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentTemplate;

class DocumentService
{
    /**
     * Gerar um documento a partir de um template com dados
     *
     * @param DocumentTemplate $template
     * @param array $data
     * @return Document
     */
    public static function generateDocument(DocumentTemplate $template, array $data): Document
    {
        $content = $template->content;

        // Substituir merge tags pelos dados
        foreach ($data as $key => $value) {
            // Substituir {{key}} pelo valor
            $content = str_replace("{{" . $key . "}}", $value, $content);
        }

        return Document::create([
            'document_template_id' => $template->id,
            'content' => $content,
            'data' => $data,
            'status' => 'generated',
        ]);
    }

    /**
     * Processar preview de documento com dados
     *
     * @param string $templateContent
     * @param array $data
     * @return string
     */
    public static function previewDocument(string $templateContent, array $data): string
    {
        $content = $templateContent;

        foreach ($data as $key => $value) {
            $content = str_replace("{{" . $key . "}}", $value ?? '', $content);
        }

        return $content;
    }

    /**
     * Limpar merge tags não preenchidos
     *
     * @param string $content
     * @return string
     */
    public static function cleanEmptyTags(string $content): string
    {
        return preg_replace('/\{\{[^}]+\}\}/', '', $content);
    }
}
