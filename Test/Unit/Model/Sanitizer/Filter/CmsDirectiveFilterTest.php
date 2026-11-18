<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Test\Unit\Model\Sanitizer\Filter;

use Angeo\LlmsTxt\Api\OutputContextInterface;
use Angeo\LlmsTxt\Model\Config;
use Angeo\LlmsTxt\Model\Sanitizer\Filter\CmsDirectiveFilter;
use Magento\Cms\Model\Template\FilterProvider;
use Magento\Store\Api\Data\StoreInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \Angeo\LlmsTxt\Model\Sanitizer\Filter\CmsDirectiveFilter
 */
class CmsDirectiveFilterTest extends TestCase
{
    private FilterProvider $filterProvider;
    private Config $config;
    private LoggerInterface $logger;
    private OutputContextInterface $context;
    private StoreInterface $store;
    private CmsDirectiveFilter $filter;

    protected function setUp(): void
    {
        $this->filterProvider = $this->createMock(FilterProvider::class);
        $this->config         = $this->createStub(Config::class);
        $this->logger         = $this->createStub(LoggerInterface::class);
        $this->context        = $this->createStub(OutputContextInterface::class);
        $this->store          = $this->createStub(StoreInterface::class);

        $this->store->method('getId')->willReturn(1);
        $this->store->method('getCode')->willReturn('default');
        $this->context->method('getStore')->willReturn($this->store);

        $this->filter = new CmsDirectiveFilter(
            $this->filterProvider,
            $this->config,
            $this->logger
        );
    }

    public function testContentWithoutDirectivesPassesThroughUntouched(): void
    {
        $this->filterProvider->expects(self::never())->method('getPageFilter');
        self::assertSame(
            'plain content',
            $this->filter->filter('plain content', $this->context)
        );
    }

    /**
     * SECURITY (3.1.0): product content must NOT be passed through the template
     * filter unless the dedicated flag is enabled — directives are stripped
     * instead of resolved (template-directive-injection defense).
     */
    public function testProductDirectivesAreStrippedNotResolvedByDefault(): void
    {
        $this->config->method('shouldResolveDirectives')->willReturn(true);
        $this->config->method('shouldResolveProductDirectives')->willReturn(false);
        $this->context = $this->createMock(OutputContextInterface::class);
        $this->context->method('getStore')->willReturn($this->store);
        $this->context->expects(self::once())->method('getShared')
            ->with(OutputContextInterface::SHARED_ENTITY_TYPE)
            ->willReturn('product');

        // The dangerous path: template filter must never be invoked.
        $this->filterProvider->expects(self::never())->method('getPageFilter');

        $out = $this->filter->filter(
            'Great product {{block id="secret-internal-block"}} indeed',
            $this->context
        );

        self::assertStringNotContainsString('{{', $out);
        self::assertStringNotContainsString('secret-internal-block', $out);
        self::assertStringContainsString('Great product', $out);
        self::assertStringContainsString('indeed', $out);
    }

    public function testDirectivesStrippedWhenResolutionDisabledGlobally(): void
    {
        $this->config->method('shouldResolveDirectives')->willReturn(false);
        $this->context->method('getShared')->willReturn('cms-page');

        $this->filterProvider->expects(self::never())->method('getPageFilter');

        $out = $this->filter->filter('Text {{var store.name}} tail', $this->context);
        self::assertSame('Text  tail', $out);
    }
}
