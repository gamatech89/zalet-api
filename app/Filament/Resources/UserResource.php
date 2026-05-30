<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use App\Services\FounderVerificationService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'User Management';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('User Information')
                    ->schema([
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Forms\Components\TextInput::make('username')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(30)
                            ->regex('/^[a-zA-Z0-9_]+$/'),
                        Forms\Components\Select::make('role')
                            ->options([
                                'user' => 'User',
                                'creator' => 'Creator',
                                'admin' => 'Admin',
                            ])
                            ->required()
                            ->default('user'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Founder Status')
                    ->schema([
                        Forms\Components\Toggle::make('is_legacy_founder')
                            ->label('Legacy Founder')
                            ->helperText('Legacy founders receive a one-time 100 ZaletCoin bonus. Note: Toggling this ON for an existing user does NOT automatically credit the bonus. Use the table action instead.')
                            ->disabled(),
                    ]),

                Forms\Components\Section::make('Storage')
                    ->schema([
                        Forms\Components\TextInput::make('storage_limit_mb')
                            ->label('Storage Limit (MB)')
                            ->numeric()
                            ->default(512),
                        Forms\Components\TextInput::make('storage_used_bytes')
                            ->label('Storage Used (bytes)')
                            ->numeric()
                            ->disabled(),
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
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->copyable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('username')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('role')
                    ->colors([
                        'gray' => 'user',
                        'success' => 'creator',
                        'danger' => 'admin',
                    ]),
                Tables\Columns\IconColumn::make('is_legacy_founder')
                    ->label('Founder')
                    ->boolean()
                    ->trueIcon('heroicon-o-star')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('warning')
                    ->falseColor('gray'),
                Tables\Columns\TextColumn::make('storage_used_bytes')
                    ->label('Storage Used')
                    ->formatStateUsing(fn ($state) => number_format($state / 1024 / 1024, 2) . ' MB')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->options([
                        'user' => 'User',
                        'creator' => 'Creator',
                        'admin' => 'Admin',
                    ]),
                Tables\Filters\TernaryFilter::make('is_legacy_founder')
                    ->label('Legacy Founder')
                    ->trueLabel('Founders Only')
                    ->falseLabel('Non-Founders Only'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('markAsFounder')
                    ->label('Mark as Founder')
                    ->icon('heroicon-o-star')
                    ->color('warning')
                    ->visible(fn (User $record) => !$record->is_legacy_founder)
                    ->requiresConfirmation()
                    ->modalHeading('Mark User as Legacy Founder')
                    ->modalDescription('This will mark the user as a legacy founder and credit them with a 100 ZaletCoin bonus. This action cannot be undone.')
                    ->action(function (User $record) {
                        $founderService = app(FounderVerificationService::class);
                        $result = $founderService->manuallyMarkAsFounder($record);
                        
                        if ($result) {
                            Notification::make()
                                ->success()
                                ->title('User marked as Legacy Founder')
                                ->body('The user has been marked as a founder and credited with 100 ZaletCoins.')
                                ->send();
                        } else {
                            Notification::make()
                                ->warning()
                                ->title('User is already a founder')
                                ->send();
                        }
                    }),
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
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
