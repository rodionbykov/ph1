<?php

declare(strict_types=1);

namespace Tests;

use Framework\Ioc;
use PHPUnit\Framework\TestCase;
use App\Model\Beans\Fava;
use App\Model\Services\Factory;

require_once __DIR__ . '/fixtures/model/services/Factory.php';
require_once __DIR__ . '/fixtures/model/beans/Fava.php';

/**
 * PHPUnit port of cfml_tests/FactoryBeanTest.cfc.
 */
final class FactoryBeanTest extends TestCase
{
    public function testSupportBasicFactoryMethod(): void
    {
        $bf = new Ioc('tests/fixtures/model');
        $bf->declare('a')->fromFactory('factory', 'makeMeAnA');

        $this->assertSame('I am an A', $bf->getBean('a'));
    }

    public function testSupportFactoryFunction(): void
    {
        $bf = new Ioc('');
        $bf->declare('a')->fromFactory(static fn (): string => 'I am an A');

        $this->assertSame('I am an A', $bf->getBean('a'));
    }

    public function testSupportFactoryMethodViaBean(): void
    {
        $bf = new Ioc('');
        $bf->factoryBean('a', new Factory(), 'makeMeAnA');

        $this->assertSame('I am an A', $bf->getBean('a'));
    }

    public function testSupportFactoryMethodWithBeanArg(): void
    {
        $bf = new Ioc('tests/fixtures/model');
        $bf->declare('a')
            ->fromFactory('factory', 'makeAWithFava')
            ->withArguments(['favaBean']);

        $this->assertSame('I am a fava bean', $bf->getBean('a'));
    }

    public function testSupportFactoryMethodWithLocalArg(): void
    {
        $bf = new Ioc('tests/fixtures/model');
        $bf->declare('a')
            ->fromFactory('factory', 'makeAWithFava')
            ->withArguments(['favaBean'])
            ->withOverrides(['favaBean' => ['stamp' => 'different']]);

        $this->assertSame('I am a different bean', $bf->getBean('a'));
    }

    public function testFactoryCanBeDeclaredSingleton(): void
    {
        $bf = new Ioc('');
        $bf->declare('a')
            ->fromFactory(static fn (): object => new FactoryProduct())
            ->asSingleton();

        $this->assertSame($bf->getBean('a'), $bf->getBean('a'));
    }
}

final class FactoryProduct
{
}
