<?php

namespace App\Filament\Resources\CostStrategyResource\Pages;

use App\Filament\Resources\CostStrategyResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCostStrategy extends EditRecord
{
    protected static string $resource = CostStrategyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
