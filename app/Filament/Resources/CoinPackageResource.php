<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CoinPackageResource\Pages;
use App\Models\CoinPackage;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CoinPackageResource extends Resource
{
    protected static ?string $model = CoinPackage::class;

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?string $navigationGroup = 'Economy';

    protected static ?string $navigationLabel = 'Coin Packages';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Package Details')
                    ->schema([
                        Forms\Components\TextInput::make('coins')
                            ->label('Base Coins')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->helperText('Number of ZaletCoins the user receives.'),
                        Forms\Components\TextInput::make('bonus')
                            ->label('Bonus Coins')
                            ->numeric()
                            ->nullable()
                            ->minValue(0)
                            ->helperText('Extra coins on top of base (leave blank for none).'),
                        Forms\Components\TextInput::make('price_rsd')
                            ->label('Price (RSD)')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->helperText('Amount charged in Serbian dinars via Raiffeisen.'),
                        Forms\Components\TextInput::make('label')
                            ->label('Promo Label')
                            ->nullable()
                            ->maxLength(50)
                            ->helperText('Optional badge text, e.g. "Najpopularnije".'),
                        Forms\Components\TextInput::make('sort_order')
                            ->label('Sort Order')
                            ->numeric()
                            ->default(0)
                            ->helperText('Lower numbers appear first.'),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Inactive packages are hidden from users.'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('#')
                    ->sortable(),
                Tables\Columns\TextColumn::make('coins')
                    ->label('Coins')
                    ->formatStateUsing(fn ($state) => number_format($state) . ' ZC')
                    ->sortable(),
                Tables\Columns\TextColumn::make('bonus')
                    ->label('Bonus')
                    ->formatStateUsing(fn ($state) => $state ? '+' . number_format($state) . ' ZC' : '—')
                    ->sortable(),
                Tables\Columns\TextColumn::make('price_rsd')
                    ->label('Price')
                    ->formatStateUsing(fn ($state) => number_format($state) . ' RSD')
                    ->sortable(),
                Tables\Columns\TextColumn::make('label')
                    ->label('Label')
                    ->placeholder('—'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status'),
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
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListCoinPackages::route('/'),
            'create' => Pages\CreateCoinPackage::route('/create'),
            'edit'   => Pages\EditCoinPackage::route('/{record}/edit'),
        ];
    }
}
