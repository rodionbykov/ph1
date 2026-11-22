<?php

declare(strict_types=1);

namespace Tests;

use Framework\Ioc;
use Framework\One;
use PHPUnit\Framework\TestCase;
use stdClass;

final class LayoutTestOne extends One
{
    public function responseBody(): string
    {
        return $this->response->getBody();
    }
}

final class DisableLayoutTest extends TestCase
{
    private LayoutTestOne $fw;
    private array $get;
    private array $post;
    private array $server;
    private mixed $previousFramework;
    private bool $hadFramework;

    protected function setUp(): void
    {
        $this->get = $_GET;
        $this->post = $_POST;
        $this->server = $_SERVER;
        $this->hadFramework = array_key_exists('_fw1', $GLOBALS);
        $this->previousFramework = $GLOBALS['_fw1'] ?? null;
        $_GET = [];
        $_POST = [];
        unset($_SERVER['PATH_INFO'], $GLOBALS['_fw1']);

        $this->fw = new LayoutTestOne([
            'diEngine' => 'none',
            'viewsFolder' => 'tests/fixtures/layout/views',
            'layoutsFolder' => 'tests/fixtures/layout/layouts',
        ]);
        $this->fw->setBeanFactory((new Ioc(''))->addBean('mainController', new stdClass()));
    }

    protected function tearDown(): void
    {
        $_GET = $this->get;
        $_POST = $this->post;
        $_SERVER = $this->server;
        if ($this->hadFramework) {
            $GLOBALS['_fw1'] = $this->previousFramework;
        } else {
            unset($GLOBALS['_fw1']);
        }
    }

    public function testEnabledLayout(): void
    {
        $this->assertSame('[layout]VIEW[/layout]', $this->renderRequest());
    }

    public function testExplicitlyEnabledLayout(): void
    {
        $this->fw->enableLayout();

        $this->assertSame('[layout]VIEW[/layout]', $this->renderRequest());
    }

    public function testDisabledLayout(): void
    {
        $this->fw->disableLayout();

        $this->assertSame('VIEW', $this->renderRequest());
    }

    public function testReEnabledLayout(): void
    {
        $this->fw->disableLayout();
        $this->fw->enableLayout();

        $this->assertSame('[layout]VIEW[/layout]', $this->renderRequest());
    }

    public function testLayoutStateResetsBetweenRequests(): void
    {
        $this->fw->onRequestStart('');
        $this->fw->disableLayout();
        $this->fw->onRequest('');
        $this->assertSame('VIEW', trim($this->fw->responseBody()));
        $this->fw->onRequestEnd();

        $this->assertSame('[layout]VIEW[/layout]', $this->renderRequest());
    }

    public function testDisabledLayoutSurvivesApplicationInitialization(): void
    {
        $this->fw->disableLayout();
        $this->fw->onApplicationStart();

        $this->assertSame('VIEW', $this->renderRequest());
    }

    public function testLayoutCanBeReEnabledDuringARequest(): void
    {
        $this->fw->onRequestStart('');
        $this->fw->disableLayout();
        $this->fw->enableLayout();
        $this->fw->onRequest('');

        $this->assertSame('[layout]VIEW[/layout]', trim($this->fw->responseBody()));
    }

    public function testPreRequestLayoutChoiceIsConsumedByHandleCurrentRequest(): void
    {
        $this->fw->disableLayout();
        $this->assertSame('VIEW', trim($this->fw->handleCurrentRequest()->getBody()));
        $this->assertSame('[layout]VIEW[/layout]', trim($this->fw->handleCurrentRequest()->getBody()));

        $this->fw->disableLayout();
        $this->assertSame('VIEW', trim($this->fw->handleCurrentRequest()->getBody()));
    }

    private function renderRequest(): string
    {
        $this->fw->onRequestStart('');
        $this->fw->onRequest('');

        return trim($this->fw->responseBody());
    }
}
