<?php

namespace Akatsuki;

use Akatsuki\Command\AkatsukiCommand;
use Symfony\Component\Console\Application as BaseApplication;

/**
 * Class Application
 *
 * @package Akatsuki
 * @author  Aurimas Niekis <aurimas@niekis.lt>
 */
class Application extends BaseApplication
{
    public function __construct()
    {
        parent::__construct('Akatsuki', '1.0.0');

        $this->add(new AkatsukiCommand());

        $this->setDefaultCommand('akatsuki', true);
    }
}