<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MediaResource\Pages;
use App\Models\Media;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MediaResource extends Resource
{
    protected static ?string $model = Media::class;

    protected static ?string $navigationIcon = 'heroicon-o-film';

    protected static ?string $navigationLabel = 'Media';

    protected static ?string $navigationGroup = 'Content';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Media Details')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->maxLength(255),
                        Forms\Components\Textarea::make('description')
                            ->rows(3),
                        Forms\Components\Select::make('type')
                            ->options([
                                'moment' => 'Moment',
                                'long_form' => 'Long Form',
                                'embed' => 'Embed',
                            ])
                            ->disabled(),
                        Forms\Components\Select::make('provider')
                            ->options([
                                'native' => 'Native',
                                'youtube' => 'YouTube',
                                'vimeo' => 'Vimeo',
                                'dailymotion' => 'Dailymotion',
                            ])
                            ->disabled(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Monetization')
                    ->schema([
                        Forms\Components\Toggle::make('is_ppv')
                            ->label('Pay-Per-View'),
                        Forms\Components\TextInput::make('price_coins')
                            ->numeric()
                            ->prefix('🪙'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->limit(30),
                Tables\Columns\TextColumn::make('user.username')
                    ->label('Creator')
                    ->searchable(),
                Tables\Columns\BadgeColumn::make('type')
                    ->colors([
                        'primary' => 'moment',
                        'success' => 'long_form',
                        'warning' => 'embed',
                    ]),
                Tables\Columns\BadgeColumn::make('provider')
                    ->colors([
                        'gray' => 'native',
                        'danger' => 'youtube',
                        'info' => 'vimeo',
                        'warning' => 'dailymotion',
                    ]),
                Tables\Columns\IconColumn::make('is_ppv')
                    ->label('PPV')
                    ->boolean(),
                Tables\Columns\TextColumn::make('price_coins')
                    ->label('Price')
                    ->money(null, 0)
                    ->prefix('🪙 '),
                Tables\Columns\TextColumn::make('size_bytes')
                    ->label('Size')
                    ->formatStateUsing(fn ($state) => $state ? number_format($state / 1024 / 1024, 2) . ' MB' : '-'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'moment' => 'Moment',
                        'long_form' => 'Long Form',
                        'embed' => 'Embed',
                    ]),
                Tables\Filters\SelectFilter::make('provider')
                    ->options([
                        'native' => 'Native',
                        'youtube' => 'YouTube',
                        'vimeo' => 'Vimeo',
                        'dailymotion' => 'Dailymotion',
                    ]),
                Tables\Filters\TernaryFilter::make('is_ppv')
                    ->label('PPV Content'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMedia::route('/'),
            'view' => Pages\ViewMedia::route('/{record}'),
            'edit' => Pages\EditMedia::route('/{record}/edit'),
        ];
    }
}
