<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Forms\Form;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;

class ProductImages extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected static ?string $title = 'Images';

    public static ?string $navigationIcon = 'heroicon-c-photo';

    public function form(Form $form): Form
    {
        return $form
            ->schema(components: [
                SpatieMediaLibraryFileUpload::make('images')
                    ->label(false)
                    ->image()
                    ->multiple()
                    ->openable()
                    ->panelLayout('grid')
                    ->collection('images')
                    ->reorderable()
                    ->appendFiles()
                    ->preserveFilenames()
                    ->columnSpan(2)
            ]);
    }

    protected function mutateFormDataBeforeSave(array $data): array
{
    $this->record->save();

    if (isset($data['images'])) {
        $this->record->syncMediaFromRequest(['images']);
    }

    return $data;
}

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
