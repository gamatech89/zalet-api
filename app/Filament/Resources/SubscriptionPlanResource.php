<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SubscriptionPlanResource\Pages;
use App\Models\SubscriptionPlan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SubscriptionPlanResource extends Resource
{
    protected static ?string $model = SubscriptionPlan::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationGroup = 'Subscriptions';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Plan Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(100),
                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(50)
                            ->regex('/^[a-z0-9-]+$/'),
                        Forms\Components\Textarea::make('description')
                            ->maxLength(500)
                            ->rows(3),
                        Forms\Components\TextInput::make('level')
                            ->numeric()
                            ->required()
                            ->helperText('Higher level = more access. E.g. 1=Premium, 2=VIP')
                            ->minValue(1)
                            ->maxValue(10),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Pricing (RSD)')
                    ->schema([
                        Forms\Components\TextInput::make('price_monthly')
                            ->label('Monthly Price (RSD)')
                            ->numeric()
                            ->required()
                            ->prefix('RSD'),
                        Forms\Components\TextInput::make('price_yearly')
                            ->label('Yearly Price (RSD)')
                            ->numeric()
                            ->nullable()
                            ->prefix('RSD')
                            ->helperText('Leave empty to disable yearly billing'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Features & Display')
                    ->schema([
                        Forms\Components\Repeater::make('features')
                            ->simple(
                                Forms\Components\TextInput::make('feature')
                                    ->required()
                                    ->maxLength(100),
                            )
                            ->addActionLabel('Add Feature')
                            ->defaultItems(0),
                        Forms\Components\TextInput::make('sort_order')
                            ->numeric()
                            ->default(1)
                            ->minValue(1),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ]),

                Forms\Components\Section::make('Plan Limits')
                    ->description('Configure what users on this plan can do. Leave empty for unlimited.')
                    ->schema([
                        Forms\Components\TextInput::make('limits.max_moments')
                            ->label('Max Moments')
                            ->numeric()
                            ->nullable()
                            ->helperText('Total moments allowed. Empty = unlimited.'),
                        Forms\Components\TextInput::make('limits.max_moment_duration_seconds')
                            ->label('Max Moment Duration (seconds)')
                            ->numeric()
                            ->default(30)
                            ->helperText('Maximum video length in seconds.'),
                        Forms\Components\TextInput::make('limits.max_groups')
                            ->label('Max Group Chats')
                            ->numeric()
                            ->nullable()
                            ->helperText('Max group chats a user can join. Empty = unlimited.'),
                        Forms\Components\TextInput::make('limits.max_community_posts_per_month')
                            ->label('Community Posts / Month')
                            ->numeric()
                            ->nullable()
                            ->helperText('Posts per month. 0 = must pay coins. Empty = unlimited.'),
                        Forms\Components\TextInput::make('limits.community_post_coin_cost')
                            ->label('Coin Cost per Post')
                            ->numeric()
                            ->default(0)
                            ->helperText('ZaletCoins charged per community post (for free users).'),
                        Forms\Components\Toggle::make('limits.can_watch_premium')
                            ->label('Can Watch Premium Content')
                            ->default(false),
                        Forms\Components\Toggle::make('limits.can_create_community')
                            ->label('Can Create Community')
                            ->default(false)
                            ->helperText('VIP feature — admin must still approve.'),
                        Forms\Components\TextInput::make('limits.monthly_free_coins')
                            ->label('Monthly Free ZaletCoins')
                            ->numeric()
                            ->default(0)
                            ->helperText('Auto-credited each billing cycle.'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('slug')
                    ->colors([
                        'primary' => 'premium',
                        'warning' => 'vip',
                    ]),
                Tables\Columns\TextColumn::make('level')
                    ->sortable()
                    ->badge(),
                Tables\Columns\TextColumn::make('price_monthly')
                    ->label('Monthly')
                    ->money('rsd')
                    ->sortable(),
                Tables\Columns\TextColumn::make('price_yearly')
                    ->label('Yearly')
                    ->money('rsd')
                    ->sortable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('subscriptions_count')
                    ->label('Active Subs')
                    ->counts([
                        'subscriptions' => fn ($query) => $query->where('status', 'active')->where('ends_at', '>', now()),
                    ]),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),
                Tables\Columns\TextColumn::make('sort_order')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active')
                    ->trueLabel('Active Only')
                    ->falseLabel('Inactive Only'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort_order', 'asc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSubscriptionPlans::route('/'),
            'create' => Pages\CreateSubscriptionPlan::route('/create'),
            'edit' => Pages\EditSubscriptionPlan::route('/{record}/edit'),
        ];
    }
}
