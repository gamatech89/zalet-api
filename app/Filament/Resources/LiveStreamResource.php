<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LiveStreamResource\Pages;
use App\Models\LiveStream;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class LiveStreamResource extends Resource
{
    protected static ?string $model = LiveStream::class;

    protected static ?string $navigationIcon = 'heroicon-o-video-camera';

    protected static ?string $navigationLabel = 'Live Streams';

    protected static ?string $navigationGroup = 'Content';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Stream Details')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->maxLength(100)
                            ->required(),
                        Forms\Components\TextInput::make('stream_key')
                            ->disabled()
                            ->label('Stream Key'),
                        Forms\Components\Select::make('user_id')
                            ->relationship('user', 'username')
                            ->label('Streamer')
                            ->disabled(),
                        Forms\Components\Toggle::make('is_live')
                            ->label('Currently Live')
                            ->disabled(),
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
                    ->label('Streamer')
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_live')
                    ->label('Live')
                    ->boolean()
                    ->trueIcon('heroicon-o-signal')
                    ->falseIcon('heroicon-o-signal-slash')
                    ->trueColor('success')
                    ->falseColor('gray'),
                Tables\Columns\TextColumn::make('sessions_count')
                    ->label('Sessions')
                    ->counts('sessions'),
                Tables\Columns\TextColumn::make('currentSession.total_coins_collected')
                    ->label('Session Coins')
                    ->money(null, 0)
                    ->prefix('🪙 ')
                    ->default('-'),
                Tables\Columns\TextColumn::make('currentSession.peak_viewers')
                    ->label('Peak Viewers')
                    ->default('-'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_live')
                    ->label('Live Status'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('end_stream')
                    ->label('End Stream')
                    ->icon('heroicon-o-stop')
                    ->color('danger')
                    ->visible(fn (LiveStream $record): bool => $record->is_live)
                    ->requiresConfirmation()
                    ->action(fn (LiveStream $record) => $record->endStream()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLiveStreams::route('/'),
            'view' => Pages\ViewLiveStream::route('/{record}'),
            'edit' => Pages\EditLiveStream::route('/{record}/edit'),
        ];
    }
}
