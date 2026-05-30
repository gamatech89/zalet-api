<?php

namespace App\Filament\Resources\GiftCategoryResource\Pages;

use App\Filament\Resources\GiftCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGiftCategory extends EditRecord
{
    protected static string $resource = GiftCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}