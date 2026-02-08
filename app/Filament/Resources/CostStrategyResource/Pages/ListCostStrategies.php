<?php

namespace App\Filament\Resources\CostStrategyResource\Pages;

use App\Filament\Resources\CostStrategyResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCostStrategies extends ListRecords
{
    protected static string $resource = CostStrategyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
