<?php

namespace App\Filament\Resources\GenerationResource\Pages;

use App\Filament\Resources\GenerationResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageGenerations extends ManageRecords
{
    protected static string $resource = GenerationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
