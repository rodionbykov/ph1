<?php

declare(strict_types=1);

namespace Tests;

use Framework\Ioc;
use PHPUnit\Framework\TestCase;

final class BeanInfoTest extends TestCase
{
    private Ioc $factory;

    protected function setUp(): void
    {
        $this->factory = new Ioc([], []);
    }

    public function testBeanInfoReturnsTopLevelMetadataArray(): void
    {
        $info = $this->factory->getBeanInfo();

        $this->assertCount(1, $info);
        $this->assertSame(['beanInfo'], array_keys($info));
    }

    public function testBeanFactoryMetadataIncludesValueAndSingletonFlag(): void
    {
        $info = $this->factory->getBeanInfo('beanFactory');

        $this->assertCount(2, $info);
        $this->assertArrayHasKey('value', $info);
        $this->assertTrue($info['isSingleton']);
    }

    public function testBeanInfoNestsParentMetadata(): void
    {
        $this->factory->setParent(new Ioc([], []));

        $info = $this->factory->getBeanInfo();

        $this->assertArrayHasKey('parent', $info);
        $this->assertArrayHasKey('beanInfo', $info['parent']);
    }

    public function testBeanInfoCanFlattenParentMetadata(): void
    {
        $parent = (new Ioc([], []))
            ->declare('father')
            ->asValue('figure')
            ->done();
        $this->factory->setParent($parent);

        $info = $this->factory->getBeanInfo('', true);

        $this->assertArrayNotHasKey('parent', $info);
        $this->assertArrayHasKey('beanInfo', $info);
        $this->assertArrayHasKey('father', $info['beanInfo']);
    }

    public function testRegexFiltersLocalBeanMetadata(): void
    {
        $this->factory
            ->declare('father')
            ->asValue('figure')
            ->done()
            ->addBean('mother', 'figure');

        $matchingBoth = $this->factory->getBeanInfo('', false, 'her$');
        $this->assertCount(2, $matchingBoth['beanInfo']);
        $this->assertArrayHasKey('father', $matchingBoth['beanInfo']);
        $this->assertArrayHasKey('mother', $matchingBoth['beanInfo']);

        $matchingFather = $this->factory->getBeanInfo('', false, '^f');
        $this->assertCount(1, $matchingFather['beanInfo']);
        $this->assertArrayHasKey('father', $matchingFather['beanInfo']);
        $this->assertArrayNotHasKey('mother', $matchingFather['beanInfo']);

        $matchingNone = $this->factory->getBeanInfo('', false, 'child');
        $this->assertCount(0, $matchingNone['beanInfo']);
        $this->assertArrayNotHasKey('father', $matchingNone['beanInfo']);
        $this->assertArrayNotHasKey('mother', $matchingNone['beanInfo']);
    }

    public function testRegexFiltersParentAndLocalBeanMetadata(): void
    {
        $this->factory->addBean('father', 'figure');
        $parent = (new Ioc([], []))->addBean('mother', 'figure');
        $this->factory->setParent($parent);

        $matchingBoth = $this->factory->getBeanInfo('', false, 'her$');
        $this->assertCount(2, $matchingBoth['beanInfo']);
        $this->assertArrayHasKey('father', $matchingBoth['beanInfo']);
        $this->assertArrayHasKey('mother', $matchingBoth['beanInfo']);

        $matchingFather = $this->factory->getBeanInfo('', false, '^f');
        $this->assertCount(1, $matchingFather['beanInfo']);
        $this->assertArrayHasKey('father', $matchingFather['beanInfo']);
        $this->assertArrayNotHasKey('mother', $matchingFather['beanInfo']);

        $matchingNone = $this->factory->getBeanInfo('', false, 'child');
        $this->assertCount(0, $matchingNone['beanInfo']);
        $this->assertArrayNotHasKey('father', $matchingNone['beanInfo']);
        $this->assertArrayNotHasKey('mother', $matchingNone['beanInfo']);
    }
}
