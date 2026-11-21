<?php

declare(strict_types=1);

namespace Tests;

use Framework\One;
use Framework\SubsystemNotSpecifiedException;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/bootstrap.php';

/**
 * Small helper exposing the protected configuration/request state that the
 * CFML test manipulates through `variables.fw.__config()` and `request`.
 */
final class TestableOne extends One
{
    public function setFrameworkValue(string $key, mixed $value): void
    {
        $this->framework[$key] = $value;
    }

    public function setRequestSubsystem(?string $subsystem): void
    {
        if ($subsystem === null) {
            unset($this->requestState['subsystem']);
            return;
        }

        $this->requestState['subsystem'] = $subsystem;
    }
}

/**
 * PHPUnit port of cfml_tests/coreFunctions.cfc (getDefaultSubsystem spec).
 */
final class CoreFunctionsTest extends TestCase
{
    private TestableOne $fw;

    protected function setUp(): void
    {
        $this->fw = new TestableOne();
        $this->fw->setRequestSubsystem(null);
        $this->fw->setFrameworkValue('usingSubsystems', false);
        $this->fw->setFrameworkValue('subsystemDelimiter', '@');
        $this->fw->setFrameworkValue('defaultSubsystem', '');
        $this->fw->setFrameworkValue('defaultSection', 'section');
        $this->fw->setFrameworkValue('defaultItem', 'item');
    }

    public function testShouldReturnEmptyStringWithoutSubsystems(): void
    {
        $this->assertSame('', $this->fw->getDefaultSubsystem());
    }

    public function testShouldReturnRequestSubsystemIfPresent(): void
    {
        $this->fw->setFrameworkValue('usingSubsystems', true);
        $this->fw->setRequestSubsystem('requested');

        $this->assertSame('requested', $this->fw->getDefaultSubsystem());
    }

    public function testShouldReturnDefaultSubsystemIfRequestNotPresent(): void
    {
        $this->fw->setFrameworkValue('usingSubsystems', true);
        $this->fw->setFrameworkValue('defaultSubsystem', 'subsystem');

        $this->assertSame('subsystem', $this->fw->getDefaultSubsystem());
    }

    public function testShouldThrowAnExceptionIfNoDefault(): void
    {
        $this->fw->setFrameworkValue('usingSubsystems', true);

        $this->expectException(SubsystemNotSpecifiedException::class);

        $this->fw->getDefaultSubsystem();
    }
}
