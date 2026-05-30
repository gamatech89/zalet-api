<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransactionResource\Pages;
use App\Models\Transaction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationGroup = 'Economy';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Transaction Details')
                    ->schema([
                        Forms\Components\TextInput::make('id')
                            ->label('Transaction ID')
                            ->disabled(),
                        Forms\Components\TextInput::make('type')
                            ->disabled(),
                        Forms\Components\TextInput::make('status')
                            ->disabled(),
                        Forms\Components\TextInput::make('amount')
                            ->disabled(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Parties')
                    ->schema([
                        Forms\Components\Select::make('from_wallet_id')
                            ->relationship('fromWallet.user', 'email')
                            ->label('From User')
                            ->disabled(),
                        Forms\Components\Select::make('to_wallet_id')
                            ->relationship('toWallet.user', 'email')
                            ->label('To User')
                            ->disabled(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Additional Info')
                    ->schema([
                        Forms\Components\TextInput::make('raiffeisen_order_id')
                            ->label('Raiffeisen Order ID')
                            ->disabled(),
                        Forms\Components\Textarea::make('description')
                            ->disabled(),
                    ]),
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
                    ->limit(8)
                    ->copyable(),
                Tables\Columns\BadgeColumn::make('type')
                    ->colors([
                        'success' => 'deposit',
                        'warning' => 'withdrawal',
                        'primary' => 'tip',
                        'info' => 'subscription',
                        'secondary' => 'ppv',
                    ]),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'completed',
                        'danger' => 'failed',
                    ]),
                Tables\Columns\TextColumn::make('amount')
                    ->formatStateUsing(fn ($state) => number_format($state, 2) . ' ZLC')
                    ->sortable(),
                Tables\Columns\TextColumn::make('fromWallet.user.username')
                    ->label('From')
                    ->default('—')
                    ->searchable(),
                Tables\Columns\TextColumn::make('toWallet.user.username')
                    ->label('To')
                    ->searchable(),
                Tables\Columns\TextColumn::make('gift.name')
                    ->label('Gift')
                    ->default('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('raiffeisen_order_id')
                    ->label('Order ID')
                    ->default('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'deposit' => 'Deposit',
                        'tip' => 'Tip',
                        'subscription' => 'Subscription',
                        'withdrawal' => 'Withdrawal',
                        'ppv' => 'PPV',
                    ]),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'completed' => 'Completed',
                        'failed' => 'Failed',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([])
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
            'index' => Pages\ListTransactions::route('/'),
            'view' => Pages\ViewTransaction::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }
}
