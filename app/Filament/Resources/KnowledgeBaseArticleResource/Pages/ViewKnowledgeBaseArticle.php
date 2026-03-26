<?php

namespace App\Filament\Resources\KnowledgeBaseArticleResource\Pages;

use App\Filament\Resources\KnowledgeBaseArticleResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewKnowledgeBaseArticle extends ViewRecord
{
    protected static string $resource = KnowledgeBaseArticleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
