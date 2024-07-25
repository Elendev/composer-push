<?php

namespace Clearlyip\ComposerPush;

use Composer\Plugin\Capability\CommandProvider;

class PushCommandProvider implements CommandProvider
{
    /**
     * {@inheritDoc}
     */
    public function getCommands()
    {
        return [new PushCommand()];
    }
}
