<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CreatorRequestResource\Pages;
use App\Models\CreatorRequest;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CreatorRequestResource extends Resource
{
    protected static ?string $model = CreatorRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-plus';

    protected static ?string $navigationGroup = 'User Management';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Creator Requests';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Request Details')
                    ->schema([
                        Forms\Components\TextInput::make('user.username')
                            ->label('Username')
                            ->disabled(),
                        Forms\Components\TextInput::make('user.email')
                            ->label('Email')
                            ->disabled(),
                        Forms\Components\Textarea::make('message')
                            ->disabled()
                            ->rows(3),
                        Forms\Components\TextInput::make('portfolio_url')
                            ->label('Portfolio URL')
                            ->disabled(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Admin Response')
                    ->schema([
                        Forms\Components\Textarea::make('admin_notes')
                            ->label('Notes')
                            ->rows(3),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.username')
                    ->label('User')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('message')
                    ->limit(50)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('portfolio_url')
                    ->label('Portfolio')
                    ->limit(30)
                    ->url(fn ($record) => $record->portfolio_url, shouldOpenInNewTab: true)
                    ->toggleable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'approved',
                        'danger' => 'rejected',
                    ]),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Submitted')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('reviewer.username')
                    ->label('Reviewed By')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ])
                    ->default('pending'),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (CreatorRequest $record) => $record->isPending())
                    ->requiresConfirmation()
                    ->modalHeading('Approve Creator Request')
                    ->modalDescription(fn (CreatorRequest $record) => 
                        "This will promote {$record->user->username} to creator role."
                    )
                    ->form([
                        Forms\Components\Textarea::make('admin_notes')
                            ->label('Notes (optional)')
                            ->rows(2),
                    ])
                    ->action(function (CreatorRequest $record, array $data) {
                        $record->approve(auth()->user(), $data['admin_notes'] ?? null);

                        Notification::make()
                            ->success()
                            ->title('Request Approved')
                            ->body("User {$record->user->username} has been promoted to creator.")
                            ->send();
                    }),

                Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (CreatorRequest $record) => $record->isPending())
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('admin_notes')
                            ->label('Reason for rejection')
                            ->required()
                            ->rows(2),
                    ])
                    ->action(function (CreatorRequest $record, array $data) {
                        $record->reject(auth()->user(), $data['admin_notes']);

                        Notification::make()
                            ->warning()
                            ->title('Request Rejected')
                            ->body("Creator request from {$record->user->username} has been rejected.")
                            ->send();
                    }),

                Tables\Actions\ViewAction::make(),
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
            'index' => Pages\ListCreatorRequests::route('/'),
            'view' => Pages\ViewCreatorRequest::route('/{record}'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', 'pending')->count() ?: null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'warning';
    }
}
