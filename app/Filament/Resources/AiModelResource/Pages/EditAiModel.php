<?php

namespace App\Filament\Resources\AiModelResource\Pages;

use App\Filament\Resources\AiModelResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAiModel extends EditRecord
{
    protected static string $resource = AiModelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
