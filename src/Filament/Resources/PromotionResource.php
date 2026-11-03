<?php

declare(strict_types=1);

namespace Fayne\Promotion\Filament\Resources;

use Fayne\Promotion\Filament\Resources\Promotions\Pages\CreatePromotion;
use Fayne\Promotion\Filament\Resources\Promotions\Pages\EditPromotion;
use Fayne\Promotion\Filament\Resources\Promotions\Pages\ListPromotions;
use Fayne\Promotion\Models\Promotion;
use Fayne\Promotion\Services\TenantContext;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class PromotionResource extends Resource
{
    protected static ?string $model = Promotion::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static bool $shouldCheckPolicyExistence = true;

    public static function getNavigationLabel(): string
    {
        return __('promotion::promotion.navigation.promotions');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Promotion')->schema([
                TextInput::make('name')->required()->maxLength(255),
                Select::make('type')->options(['auto' => 'Automatic', 'coupon' => 'Coupon'])->required(),
                TextInput::make('priority')->numeric()->integer()->minValue(0)->default(0)->required(),
            ])->columns(2),
            Section::make('Status and timing')->schema([
                \Filament\Forms\Components\Toggle::make('is_active')->default(true)->helperText(__('promotion::promotion.promotion_deactivation')),
                \Filament\Forms\Components\Toggle::make('stop_processing')->default(false),
                DateTimePicker::make('starts_at'), DateTimePicker::make('ends_at')->afterOrEqual('starts_at'),
            ])->columns(2),
            Section::make('Conditions')->schema([
                Repeater::make('conditions')->schema(self::conditionFields(0))->defaultItems(0)->maxItems(50)->columnSpanFull(),
            ]),
            Section::make('Actions')->schema([
                Repeater::make('actions')->schema([
                    Select::make('type')->options(['fixed_discount' => 'Fixed discount', 'percentage_discount' => 'Percentage discount', 'free_shipping' => 'Free shipping', 'quantity_discount' => 'Quantity discount'])->required()->live(),
                    TextInput::make('config.amount')->label('Amount (minor units)')->numeric()->integer()->minValue(1)->visible(fn ($get) => $get('type') === 'fixed_discount')->required(fn ($get) => $get('type') === 'fixed_discount'),
                    TextInput::make('config.rate')->label('Rate (basis points)')->numeric()->integer()->minValue(1)->maxValue(10000)->visible(fn ($get) => in_array($get('type'), ['percentage_discount', 'quantity_discount']))->required(fn ($get) => in_array($get('type'), ['percentage_discount', 'quantity_discount'])),
                    TextInput::make('config.max_discount')->label('Maximum discount (minor units)')->numeric()->integer()->minValue(1)->visible(fn ($get) => in_array($get('type'), ['percentage_discount', 'quantity_discount'])),
                    TextInput::make('config.skus')->label('SKUs (comma separated)')->visible(fn ($get) => $get('type') === 'quantity_discount')->required(fn ($get) => $get('type') === 'quantity_discount'),
                    TextInput::make('config.buy_quantity')->numeric()->integer()->minValue(1)->label('Buy quantity')->visible(fn ($get) => $get('type') === 'quantity_discount')->required(fn ($get) => $get('type') === 'quantity_discount'),
                ])->defaultItems(1)->minItems(1)->maxItems(20)->columnSpanFull(),
            ]),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable()->sortable(),
            TextColumn::make('type')->badge(),
            TextColumn::make('priority')->sortable(),
            IconColumn::make('is_active')->boolean(),
            TextColumn::make('starts_at')->dateTime()->sortable(),
            TextColumn::make('ends_at')->dateTime()->sortable(),
        ])->filters([SelectFilter::make('type')->options(['auto' => 'Automatic', 'coupon' => 'Coupon']), SelectFilter::make('is_active')->options([1 => 'Active', 0 => 'Inactive'])])->defaultSort('priority', 'desc')->recordActions([
            \Filament\Actions\EditAction::make(), \Filament\Actions\DeleteAction::make(),
        ])->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()->authorizeIndividualRecords('delete')])]);
    }

    public static function getPages(): array
    {
        return ['index' => ListPromotions::route('/'), 'create' => CreatePromotion::route('/create'), 'edit' => EditPromotion::route('/{record}/edit')];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('tenant_id', app(TenantContext::class)->tenantId());
    }

    private static function conditionFields(int $depth): array
    {
        $types = ['cart_total' => 'Cart total', 'user_group' => 'User group', 'product_sku' => 'Product SKU', 'category' => 'Category', 'datetime' => 'Date/time'];
        if ($depth < 2) {
            $types['composite'] = 'Condition group';
        }

        $fields = [
            Select::make('type')->options($types)->required()->live(),
            Select::make('config.operator')->options(fn ($get) => match ($get('type')) {
                'cart_total' => ['eq' => 'Equal', 'ne' => 'Not equal', 'gt' => 'Greater than', 'gte' => 'At least', 'lt' => 'Less than', 'lte' => 'At most'],
                'datetime' => ['before' => 'Before', 'after' => 'After', 'between' => 'Between'],
                'composite' => [],
                default => ['in' => 'Includes any', 'not_in' => 'Includes none'],
            })->visible(fn ($get) => $get('type') !== 'composite')->required(fn ($get) => $get('type') !== 'composite'),
            TextInput::make('config.value')->label('Value (minor units)')->numeric()->integer()->minValue(0)->visible(fn ($get) => $get('type') === 'cart_total')->required(fn ($get) => $get('type') === 'cart_total'),
            TextInput::make('config.value')->label('ISO 8601 date/time')->visible(fn ($get) => $get('type') === 'datetime')->required(fn ($get) => $get('type') === 'datetime'),
            TextInput::make('config.end')->label('End date/time')->visible(fn ($get) => $get('type') === 'datetime' && $get('config.operator') === 'between')->required(fn ($get) => $get('type') === 'datetime' && $get('config.operator') === 'between'),
            TextInput::make('config.values')->label('Values (comma separated)')->visible(fn ($get) => in_array($get('type'), ['user_group', 'product_sku', 'category']))->required(fn ($get) => in_array($get('type'), ['user_group', 'product_sku', 'category'])),
        ];

        if ($depth < 2) {
            $fields[] = Select::make('config.logic')->options(['and' => 'AND', 'or' => 'OR'])->visible(fn ($get) => $get('type') === 'composite')->required(fn ($get) => $get('type') === 'composite');
            $fields[] = Repeater::make('config.conditions')->label('Group conditions')->schema(self::conditionFields($depth + 1))->visible(fn ($get) => $get('type') === 'composite')->minItems(1)->maxItems(50);
        }

        return $fields;
    }

}
