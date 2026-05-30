<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BoardResource\Pages;
use App\Models\Board;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class BoardResource extends Resource
{
    protected static ?string $model = Board::class;

    protected static ?string $navigationIcon = 'heroicon-o-globe-alt';

    protected static ?string $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Communities';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Community Info')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(100),
                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->unique(ignoreRecord: true),
                        Forms\Components\TextInput::make('country_code')
                            ->required()
                            ->length(2)
                            ->label('Country Code'),
                        Forms\Components\TextInput::make('city')
                            ->nullable(),
                        Forms\Components\Textarea::make('description')
                            ->nullable()
                            ->rows(3),
                    ])->columns(2),

                Forms\Components\Section::make('Status')
                    ->schema([
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active (approved)')
                            ->helperText('Inactive communities are pending approval and hidden from public listing.'),
                        Forms\Components\Toggle::make('is_featured')
                            ->label('Featured')
                            ->helperText('Featured communities appear in the highlighted section on the community page.'),
                        Forms\Components\Toggle::make('is_public')
                            ->label('Public'),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('country_code')
                    ->label('Country')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('members_count')
                    ->counts('members')
                    ->label('Members')
                    ->sortable(),
                Tables\Columns\TextColumn::make('posts_count')
                    ->counts('posts')
                    ->label('Posts')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_public')
                    ->label('Public')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_featured')
                    ->label('Featured')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status')
                    ->trueLabel('Approved')
                    ->falseLabel('Pending approval')
                    ->placeholder('All'),
                Tables\Filters\TernaryFilter::make('is_featured')
                    ->label('Featured'),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Board $record) => !$record->is_active)
                    ->requiresConfirmation()
                    ->action(function (Board $record) {
                        $record->update(['is_active' => true]);
                        Notification::make()
                            ->title("Community \"{$record->name}\" approved.")
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('reject')
                    ->label('Reject & Delete')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Board $record) => !$record->is_active)
                    ->requiresConfirmation()
                    ->modalDescription('This will permanently delete the community and its group chat. This cannot be undone.')
                    ->action(function (Board $record) {
                        $name = $record->name;
                        if ($record->conversation_id) {
                            $record->conversation->users()->detach();
                            $record->conversation->delete();
                        }
                        $record->delete();
                        Notification::make()
                            ->title("Community \"{$name}\" rejected and deleted.")
                            ->warning()
                            ->send();
                    }),
                Tables\Actions\Action::make('toggle_featured')
                    ->label(fn (Board $record) => $record->is_featured ? 'Unfeature' : 'Feature')
                    ->icon(fn (Board $record) => $record->is_featured ? 'heroicon-o-star' : 'heroicon-o-star')
                    ->color(fn (Board $record) => $record->is_featured ? 'warning' : 'gray')
                    ->visible(fn (Board $record) => $record->is_active)
                    ->action(function (Board $record) {
                        $record->update(['is_featured' => !$record->is_featured]);
                        Notification::make()
                            ->title($record->is_featured ? "Featured: {$record->name}" : "Unfeatured: {$record->name}")
                            ->success()
                            ->send();
                    }),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('approve_selected')
                        ->label('Approve selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(fn ($records) => $records->each->update(['is_active' => true]))
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBoards::route('/'),
            'edit'  => Pages\EditBoard::route('/{record}/edit'),
        ];
    }
}
