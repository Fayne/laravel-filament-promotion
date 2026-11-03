<?php

declare(strict_types=1);

namespace Fayne\Promotion\Filament\Resources\Promotions\Pages;

use Fayne\Promotion\Filament\Resources\PromotionResource;
use Fayne\Promotion\Validation\RuleValidator;
use Filament\Resources\Pages\EditRecord;

final class EditPromotion extends EditRecord
{
    protected static string $resource = PromotionResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        CreatePromotion::normalizePromotionData($data);
        try {
            app(RuleValidator::class)->validate($data['conditions'] ?? [], $data['actions'] ?? []);
        } catch (\InvalidArgumentException $exception) {
            throw \Illuminate\Validation\ValidationException::withMessages(['data.conditions' => $exception->getMessage()]);
        }

        return $data;
    }
}
