<?php

declare(strict_types=1);

namespace Tests;

use Framework\Ioc;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use TestFixtures\Defaults\User37;
use TestFixtures\Defaults\User37b;
use TestFixtures\Defaults\User37c;

require_once __DIR__ . '/fixtures/DefaultFixtures.php';

final class DefaultArgTest extends TestCase
{
    public static function strictModes(): array
    {
        return ['non-strict' => [false], 'strict' => [true]];
    }

    #[DataProvider('strictModes')]
    public function testDefaultInitArgWorks(bool $strict): void
    {
        $factory = $this->createFactory($strict, ['dsn' => 'sample']);

        foreach (['user37', 'user37b'] as $beanName) {
            $user = $factory->getBean($beanName);
            $this->assertSame('sample', $user->getDSN());
            $this->assertSame(0, $user->getID());
        }
    }

    #[DataProvider('strictModes')]
    public function testDefaultInitArgThreeArgs(bool $strict): void
    {
        $factory = $this->createFactory($strict, ['dsn' => 'sample']);
        $user = $factory->getBean('user37c');
        $this->assertSame('sample', $user->getDSN());
        $this->assertSame(0, $user->getID());
        $this->assertSame('Bob', $user->getName());

        $factory = $this->createFactory($strict, ['dsn' => 'sample', 'name' => 'John']);
        $user = $factory->getBean('user37c');
        $this->assertSame('sample', $user->getDSN());
        $this->assertSame(0, $user->getID());
        $this->assertSame('John', $user->getName());
    }

    public function testConstructorUsesCaseInsensitiveParentBean(): void
    {
        $factory = $this->createFactory(true, ['dsn' => 'sample']);
        $factory->setParent(new Ioc('', ['constants' => ['name' => 'John']]));

        $this->assertSame('John', $factory->getBean('user37c')->getName());
    }

    public function testExactConstructorBeanNameTakesPrecedence(): void
    {
        $factory = $this->createFactory(true, ['dsn' => 'sample', 'name' => 'John', 'NAME' => 'Jane']);

        $this->assertSame('Jane', $factory->getBean('user37c')->getName());
    }

    public function testLocalCaseInsensitiveBeanTakesPrecedenceOverParent(): void
    {
        $factory = $this->createFactory(true, ['dsn' => 'sample', 'name' => 'John']);
        $factory->setParent(new Ioc('', ['constants' => ['Name' => 'Jane']]));

        $this->assertSame('John', $factory->getBean('user37c')->getName());
    }

    public function testAmbiguousConstructorBeanNameIsReported(): void
    {
        $factory = $this->createFactory(true, ['dsn' => 'sample', 'name' => 'John', 'Name' => 'Jane']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ambiguous bean name: NAME');
        $factory->getBean('user37c');
    }

    private function createFactory(bool $strict, array $constants): Ioc
    {
        $factory = new Ioc('', ['strict' => $strict, 'constants' => $constants]);
        $factory->declareBean('user37', User37::class, false);
        $factory->declareBean('user37b', User37b::class, false);
        $factory->declareBean('user37c', User37c::class, false);

        return $factory;
    }
}
