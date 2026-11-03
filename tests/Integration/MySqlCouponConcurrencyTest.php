<?php

declare(strict_types=1);

use Fayne\Promotion\Enums\CouponReservationStatus;
use Fayne\Promotion\Enums\PromotionType;
use Fayne\Promotion\Exceptions\InvalidCouponException;
use Fayne\Promotion\Exceptions\ReservationConflictException;
use Fayne\Promotion\Models\Coupon;
use Fayne\Promotion\Models\CouponReservation;
use Fayne\Promotion\Models\Promotion;
use Fayne\Promotion\Services\CouponService;
use Fayne\Promotion\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(TestCase::class);

beforeEach(function (): void {
    if (! getenv('CMS_TEST_MYSQL_CONFIG')) {
        $this->markTestSkipped('Run this integration test with CMS_TEST_MYSQL_CONFIG against MySQL.');
    }
    DB::setDefaultConnection((string) config('database.default'));
    expect(DB::connection()->getDriverName())->toBe('mysql');
    if (! str_starts_with(DB::connection()->getTablePrefix(), 'cpt_')) {
        throw new RuntimeException('MySQL concurrency tests require a dedicated cpt_ table prefix.');
    }
    Schema::dropIfExists('coupon_reservations');
    Schema::dropIfExists('coupons');
    Schema::dropIfExists('promotions');
});

test('two requests cannot reserve the last coupon usage concurrently', function (): void {
    migrateCouponReservationTables();
    $coupon = createConcurrentTestCoupon('LAST-ONE', 1);

    $results = runConcurrentCouponCalls(function (int $worker): string {
        try {
            app(CouponService::class)->reserve(0, 'CNY', 'LAST-ONE', 'checkout', 'cart-' . $worker, 'key-' . $worker, now()->addMinutes(10));

            return 'reserved';
        } catch (InvalidCouponException) {
            return 'unavailable';
        }
    });

    expect($results)->toContain('reserved', 'unavailable')
        ->and(CouponReservation::query()->where('coupon_id', $coupon->id)->where('status', CouponReservationStatus::RESERVED)->count())->toBe(1);
});

test('commit and release races allow exactly one reservation transition', function (): void {
    migrateCouponReservationTables();
    $coupon = createConcurrentTestCoupon('RACE', 1);
    $reservation = app(CouponService::class)->reserve(0, 'CNY', 'RACE', 'checkout', 'race-cart', 'race-key', now()->addMinutes(10));

    $results = runConcurrentCouponCalls(function (int $worker) use ($reservation): string {
        try {
            if ($worker === 1) {
                app(CouponService::class)->commit(0, $reservation->id);

                return 'committed';
            }

            app(CouponService::class)->release(0, $reservation->id);

            return 'released';
        } catch (ReservationConflictException) {
            return 'conflict';
        }
    });

    expect(count(array_filter($results, fn (string $result): bool => in_array($result, ['committed', 'released'], true))))
        ->toBe(1)
        ->and(count(array_filter($results, fn (string $result): bool => $result === 'conflict')))->toBe(1);

    $reservation->refresh();
    $coupon->refresh();
    expect($reservation->status)->toBeIn([CouponReservationStatus::COMMITTED, CouponReservationStatus::RELEASED])
        ->and($coupon->used_count)->toBe($reservation->status === CouponReservationStatus::COMMITTED ? 1 : 0);
});

function migrateCouponReservationTables(): void
{
    (require dirname(__DIR__, 2) . '/database/migrations/2026_11_02_000001_create_promotions_table.php')->up();
    (require dirname(__DIR__, 2) . '/database/migrations/2026_11_02_000002_create_coupons_table.php')->up();
    (require dirname(__DIR__, 2) . '/database/migrations/2026_11_02_000003_create_coupon_reservations_table.php')->up();
}

function createConcurrentTestCoupon(string $code, int $usageLimit): Coupon
{
    $promotion = Promotion::query()->create([
        'name' => $code,
        'type' => PromotionType::COUPON,
        'is_active' => true,
        'conditions' => [],
        'actions' => [['type' => 'free_shipping', 'config' => []]],
    ]);

    return $promotion->coupons()->create([
        'code' => $code,
        'is_active' => true,
        'usage_limit' => $usageLimit,
    ]);
}

function runConcurrentCouponCalls(Closure $call): array
{
    $directory = sys_get_temp_dir() . '/promotion-concurrency-' . bin2hex(random_bytes(8));
    mkdir($directory, 0700);
    $children = [];

    try {
        foreach ([1, 2] as $worker) {
            $pid = pcntl_fork();
            if ($pid === -1) {
                throw new RuntimeException('Unable to fork a coupon concurrency worker.');
            }
            if ($pid === 0) {
                $connection = (string) config('database.default');
                DB::purge($connection);
                DB::reconnect($connection);
                file_put_contents($directory . '/ready-' . $worker, '1');
                $deadline = microtime(true) + 10;
                while (! file_exists($directory . '/go') && microtime(true) < $deadline) {
                    usleep(1000);
                }
                try {
                    $result = file_exists($directory . '/go') ? $call($worker) : 'barrier-timeout';
                    file_put_contents($directory . '/result-' . $worker, $result);
                    exit($result === 'barrier-timeout' ? 1 : 0);
                } catch (Throwable $exception) {
                    file_put_contents($directory . '/result-' . $worker, 'error:' . $exception::class . ':' . $exception->getMessage());
                    exit(1);
                }
            }
            $children[$worker] = $pid;
        }

        $deadline = microtime(true) + 10;
        while (count(glob($directory . '/ready-*') ?: []) < count($children) && microtime(true) < $deadline) {
            usleep(1000);
        }
        if (count(glob($directory . '/ready-*') ?: []) !== count($children)) {
            throw new RuntimeException('Coupon concurrency workers did not reach the start barrier.');
        }
        touch($directory . '/go');

        foreach ($children as $pid) {
            pcntl_waitpid($pid, $status);
            if (! pcntl_wifexited($status) || pcntl_wexitstatus($status) !== 0) {
                throw new RuntimeException('Coupon concurrency worker failed: ' . (string) file_get_contents($directory . '/result-' . array_search($pid, $children, true)));
            }
        }

        return [
            (string) file_get_contents($directory . '/result-1'),
            (string) file_get_contents($directory . '/result-2'),
        ];
    } finally {
        foreach (glob($directory . '/*') ?: [] as $path) {
            unlink($path);
        }
        rmdir($directory);
    }
}
