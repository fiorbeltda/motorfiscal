<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\TransitionCatalog;
use App\Modules\Fiscal\Core\Shared\LocalDate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Split lifecycle regression; synthetic selector tests, not deployment authentication. */
final class TransitionDeploymentLifecycle114Test extends TestCase
{
    /** @return array<string, array{0: array<string, mixed>, 1: bool}> */
    public static function lifecycleCases(): array
    {
        return [
            'active' => [[], true],
            'not yet effective' => [['valid_from' => '2026-10-21'], false],
            'expired' => [['valid_to' => '2026-10-19'], false],
            'valid through inclusive' => [['valid_to' => '2026-10-20'], true],
            'unpublished' => [['published_at' => '2026-10-20T11:20:00.000001+00:00'], false],
            'inactive' => [['status' => 'INACTIVE'], false],
            'missing source hash' => [['source_hash' => null], false],
            'revoked before knowledge' => [['revoked_at' => '2026-10-20T11:19:59+00:00'], false],
            'revoked at knowledge' => [['revoked_at' => '2026-10-20T11:20:00+00:00'], false],
            'superseded before knowledge' => [['superseded_at' => '2026-10-20T11:19:59+00:00'], false],
            'superseded at knowledge' => [['superseded_at' => '2026-10-20T11:20:00+00:00'], false],
            'revoked after knowledge' => [['revoked_at' => '2026-10-20T11:20:00.000001+00:00'], true],
            'superseded after knowledge' => [['superseded_at' => '2026-10-20T11:20:00.000001+00:00'], true],
            'same instant civil offset' => [['revoked_at' => '2026-10-20T08:20:00-03:00'], false],
        ];
    }

    /** @param array<string, mixed> $overrides */
    #[DataProvider('lifecycleCases')]
    public function test_lifecycle_eligibility(array $overrides, bool $expected): void
    {
        $catalog = new TransitionCatalog(['split_deployments' => [array_replace(self::deployment(), $overrides)]]);
        self::assertSame($expected, $catalog->splitOperational(LocalDate::fromString('2026-10-20'),
            new DateTimeImmutable('2026-10-20T11:20:00+00:00')));
    }

    public function test_an_unavailable_row_does_not_hide_an_independent_active_row(): void
    {
        $catalog = new TransitionCatalog(['split_deployments' => [
            array_replace(self::deployment(), ['revoked_at' => '2026-10-20T11:19:59+00:00']),
            self::deployment(),
        ]]);
        self::assertTrue($catalog->splitOperational(LocalDate::fromString('2026-10-20'),
            new DateTimeImmutable('2026-10-20T11:20:00+00:00')));
    }

    public function test_empty_deployment_catalog_does_not_activate_split(): void
    {
        self::assertFalse((new TransitionCatalog(['split_deployments' => []]))->splitOperational(
            LocalDate::fromString('2026-10-20'), new DateTimeImmutable('2026-10-20T11:20:00+00:00')));
    }

    /** @return array<string, mixed> */
    private static function deployment(): array
    {
        return ['status' => 'ACTIVE', 'valid_from' => '2026-01-01', 'valid_to' => '2026-12-31',
            'published_at' => '2026-01-01T00:00:00+00:00', 'source_hash' => str_repeat('a', 64)];
    }
}
