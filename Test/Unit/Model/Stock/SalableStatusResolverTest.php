<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Test\Unit\Model\Stock;

use Angeo\LlmsTxt\Model\Stock\SalableStatusResolver;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Api\Data\StoreInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \Angeo\LlmsTxt\Model\Stock\SalableStatusResolver
 */
class SalableStatusResolverTest extends TestCase
{
    private ObjectManagerInterface $objectManager;
    private ModuleManager $moduleManager;

    protected function setUp(): void
    {
        $this->objectManager = $this->createStub(ObjectManagerInterface::class);
        $this->moduleManager = $this->createStub(ModuleManager::class);
    }

    public function testLegacyIsNeverOverriddenByAutoDetection(): void
    {
        // Explicit "legacy" must win even on a store where MSI is installed —
        // it is the merchant's escape hatch.
        $this->moduleManager = $this->createMock(ModuleManager::class);
        $this->moduleManager->expects(self::never())->method('isEnabled');

        self::assertFalse($this->createSubject()->usesMsi(SalableStatusResolver::SOURCE_LEGACY));
    }

    public function testAutoFallsBackToLegacyWhenMsiIsAbsent(): void
    {
        $this->moduleManager->method('isEnabled')->willReturn(false);

        self::assertFalse($this->createSubject()->usesMsi(SalableStatusResolver::SOURCE_AUTO));
    }

    public function testEmptySkuListNeverTouchesTheObjectManager(): void
    {
        $this->objectManager = $this->createMock(ObjectManagerInterface::class);
        $this->objectManager->expects(self::never())->method('get');

        self::assertSame([], $this->createSubject()->forSkus([], $this->createStub(StoreInterface::class)));
    }

    public function testLookupFailureReturnsEmptyMapSoCallerCanFallBack(): void
    {
        // The contract the provider relies on: an empty map means "could not
        // determine", never "everything is out of stock".
        $this->objectManager->method('get')->willThrowException(new \RuntimeException('MSI exploded'));

        $store = $this->createStub(StoreInterface::class);
        $store->method('getCode')->willReturn('default');

        self::assertSame([], $this->createSubject()->forSkus(['SKU-1'], $store));
    }

    private function createSubject(): SalableStatusResolver
    {
        return new SalableStatusResolver(
            $this->objectManager,
            $this->moduleManager,
            $this->createStub(LoggerInterface::class)
        );
    }
}
