<?php


namespace Elendev\NexusComposerPush;

use Composer\Plugin\Capability\CommandProvider;

class PushCommandProvider implements CommandProvider
{
    public function getCommands()
    {
        return array(new PushCommand());
    }
}
