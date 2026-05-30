<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GiftResource\Pages;
use App\Models\Gift;
use App\Models\GiftCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class GiftResource extends Resource
{
    protected static ?string $model = Gift::class;

    protected static ?string $navigationIcon = 'heroicon-o-gift';

    protected static ?string $navigationGroup = 'Economy';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Gift Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(50),
                        Forms\Components\TextInput::make('coin_price')
                            ->label('Price (ZaletCoins)')
                            ->numeric()
                            ->required()
                            ->minValue(1),
                        Forms\Components\Select::make('category_id')
                            ->label('Category')
                            ->options(GiftCategory::pluck('name', 'id'))
                            ->searchable()
                            ->nullable(),
                        Forms\Components\TextInput::make('sort_order')
                            ->label('Sort Order')
                            ->numeric()
                            ->default(0)
                            ->helperText('Lower numbers appear first.'),
                        Forms\Components\Textarea::make('description')
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Inactive gifts will not appear in the catalog.'),
                        Forms\Components\Toggle::make('is_epic')
                            ->label('Epic Badge')
                            ->default(false)
                            ->helperText('Show Epic glowing badge on this gift.'),
                        Forms\Components\Toggle::make('is_rare')
                            ->label('Rare Badge')
                            ->default(false)
                            ->helperText('Show Rare glowing badge on this gift.'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Icons')
                    ->schema([
                        Forms\Components\FileUpload::make('icon_2d')
                            ->label('2D Icon (Catalog)')
                            ->image()
                            ->disk('public')
                            ->directory('gifts/2d')
                            ->imagePreviewHeight('120')
                            ->maxSize(2048)
                            ->helperText('Shown in the gift grid. PNG recommended, max 2MB.'),
                        Forms\Components\FileUpload::make('icon_3d')
                            ->label('3D Icon (Animation)')
                            ->image()
                            ->disk('public')
                            ->directory('gifts/3d')
                            ->imagePreviewHeight('120')
                            ->maxSize(2048)
                            ->helperText('Shown during gift send animation. PNG recommended, max 2MB.'),
                        Forms\Components\TextInput::make('icon_url')
                            ->label('Legacy Icon URL')
                            ->url()
                            ->maxLength(255)
                            ->helperText('Fallback URL if no uploaded icons are set.')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                Tables\Columns\ImageColumn::make('icon_2d')
                    ->label('2D')
                    ->disk('public')
                    ->size(40),
                Tables\Columns\ImageColumn::make('icon_3d')
                    ->label('3D')
                    ->disk('public')
                    ->size(40),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('coin_price')
                    ->label('Price')
                    ->formatStateUsing(fn ($state) => number_format($state) . ' ZLC')
                    ->sortable(),
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Category')
                    ->sortable()
                    ->badge(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_epic')
                    ->label('Epic')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_rare')
                    ->label('Rare')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status'),
                Tables\Filters\SelectFilter::make('category_id')
                    ->label('Category')
                    ->options(GiftCategory::pluck('name', 'id')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort_order');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGifts::route('/'),
            'create' => Pages\CreateGift::route('/create'),
            'edit' => Pages\EditGift::route('/{record}/edit'),
        ];
    }
}