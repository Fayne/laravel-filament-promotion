<?php

declare(strict_types=1);

namespace Fayne\Promotion\Filament\Resources\Promotions\Pages;

use Fayne\Promotion\Filament\Resources\PromotionResource;
use Fayne\Promotion\Validation\RuleValidator;
use Filament\Resources\Pages\CreateRecord;

final class CreatePromotion extends CreateRecord
{
    protected static string $resource = PromotionResource::class;
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        self::normalizePromotionData($data);
        try {
            app(RuleValidator::class)->validate($data['conditions'], $data['actions']);
        } catch (\InvalidArgumentException $exception) {
            throw \Illuminate\Validation\ValidationException::withMessages(['data.conditions' => $exception->getMessage()]);
        }

        return $data;
    }
    public static function normalizePromotionData(array &$data): void
    {
        $normalize = function (array &$nodes) use (&$normalize): void {
            foreach ($nodes as &$node) {
                $type = $node['type'] ?? '';
                $config = is_array($node['config'] ?? null) ? $node['config'] : [];
                if ($type === 'composite') {
                    if (isset($config['conditions']) && is_array($config['conditions'])) {
                        $normalize($config['conditions']);
                    }
                    $allowed = ['logic', 'conditions'];
                } elseif (in_array($type, ['user_group', 'product_sku', 'category'], true)) {
                    if (is_string($config['values'] ?? null)) {
                        $config['values'] = self::parseList($config['values']);
                    }
                    $allowed = ['operator', 'values'];
                } elseif ($type === 'cart_total') {
                    $config['value'] = self::normalizeInteger($config['value'] ?? null);
                    $allowed = ['operator', 'value'];
                } elseif ($type === 'datetime') {
                    $allowed = ($config['operator'] ?? null) === 'between' ? ['operator', 'value', 'end'] : ['operator', 'value'];
                } else {
                    $allowed = array_keys($config);
                }
                $node['config'] = array_intersect_key($config, array_flip($allowed));
            }
            $nodes = array_values($nodes);
        };
        $data['conditions'] ??= [];
        $data['actions'] ??= [];
        $normalize($data['conditions']);
        foreach ($data['actions'] as &$action) {
            $type = $action['type'] ?? '';
            $config = is_array($action['config'] ?? null) ? $action['config'] : [];
            if ($type === 'fixed_discount') {
                $allowed = ['amount'];
            } elseif ($type === 'percentage_discount') {
                $allowed = ['rate', 'max_discount'];
            } elseif ($type === 'quantity_discount') {
                if (is_string($config['skus'] ?? null)) {
                    $config['skus'] = self::parseList($config['skus']);
                }
                $allowed = ['skus', 'buy_quantity', 'rate', 'max_discount'];
            } else {
                $allowed = [];
            }
            foreach (['amount', 'rate', 'max_discount', 'buy_quantity'] as $key) {
                if (array_key_exists($key, $config)) {
                    $config[$key] = self::normalizeInteger($config[$key]);
                }
            }
            $action['config'] = array_intersect_key($config, array_flip($allowed));
        }
        $data['actions'] = array_values($data['actions']);
    }

    private static function parseList(string $value): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $value)), fn (string $item): bool => $item !== ''));
    }

    private static function normalizeInteger(mixed $value): mixed
    {
        return is_string($value) && preg_match('/^-?\d+$/D', $value) ? (int) $value : $value;
    }
}
