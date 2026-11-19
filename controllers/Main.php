<?php

declare(strict_types=1);

namespace App\Controllers;

use Framework\Inject;
use Framework\Ioc;
use Framework\One;

final class Main
{
    #[Inject]
    protected Ioc $beanFactory;

    #[Inject]
    protected $formatterService;

    private One $fw;

    public function __construct(One $fw)
    {
        $this->fw = $fw;
    }

    public function default(array &$rc): void
    {
        $instant = $this->beanFactory->getBean('instant');
        $rc['title'] = 'Default View';
        $rc['today'] = $this->formatterService->longdate($instant->created());
    }

    public function error(array &$rc): void
    {
        $rc['title'] = 'Error';
    }
}