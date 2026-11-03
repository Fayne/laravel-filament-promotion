<?php

declare(strict_types=1);

namespace Fayne\Promotion\Filament\Resources;

use Fayne\Promotion\Filament\Resources\Coupons\Pages\CreateCoupon;
use Fayne\Promotion\Filament\Resources\Coupons\Pages\EditCoupon;
use Fayne\Promotion\Filament\Resources\Coupons\Pages\ListCoupons;
use Fayne\Promotion\Models\Coupon;
use Fayne\Promotion\Services\TenantContext;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class CouponResource extends Resource
{
    protected static ?string $model = Coupon::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-ticket';

    protected static bool $shouldCheckPolicyExistence = true;

    public static function getNavigationLabel(): string
    {
        return __('promotion::promotion.navigation.coupons');
    }
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('promotion_id')->options(fn () => \Fayne\Promotion\Models\Promotion::query()->where('tenant_id', app(TenantContext::class)->tenantId())->where('type', \Fayne\Promotion\Enums\PromotionType::COUPON)->pluck('name', 'id'))->searchable()->required()->exists('promotions', 'id', modifyRuleUsing: fn ($rule) => $rule->where('tenant_id', app(TenantContext::class)->tenantId())->where('type', \Fayne\Promotion\Enums\PromotionType::COUPON->value)),
            TextInput::make('code')->required()->maxLength(255)->dehydrateStateUsing(fn ($state) => strtoupper(trim($state)))->unique(table: Coupon::class, column: 'code', ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->where('tenant_id', app(TenantContext::class)->tenantId())),
            Toggle::make('is_active')->default(true)->helperText(__('promotion::promotion.coupon_deactivation')),
            DateTimePicker::make('starts_at'), DateTimePicker::make('ends_at')->afterOrEqual('starts_at'),
            TextInput::make('usage_limit')->numeric()->integer()->minValue(1),
            TextInput::make('used_count')->numeric()->integer()->minValue(0)->default(0)->disabled()->dehydrated(false),
        ]);
    }
    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('code')->searchable()->sortable(), TextColumn::make('promotion.name')->label('Promotion')->searchable(),
            IconColumn::make('is_active')->boolean(), TextColumn::make('used_count')->label('Used'), TextColumn::make('usage_limit')->label('Limit'),
            TextColumn::make('starts_at')->dateTime(), TextColumn::make('ends_at')->dateTime(),
        ])->recordActions([\Filament\Actions\EditAction::make(), \Filament\Actions\DeleteAction::make()])->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()->authorizeIndividualRecords('delete')])]);
    }
    public static function getPages(): array
    {
        return ['index' => ListCoupons::route('/'), 'create' => CreateCoupon::route('/create'), 'edit' => EditCoupon::route('/{record}/edit')];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('tenant_id', app(TenantContext::class)->tenantId());
    }

}
