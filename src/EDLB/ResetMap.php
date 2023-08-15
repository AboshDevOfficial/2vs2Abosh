<?php

namespace EDLB;

use EDLB\GameSender;

Class ResetMap
{
    public function __construct(GameSender $plugin){
        $this->plugin = $plugin;
    }
    
    public function reload($name)
    {
        if ($this->plugin->plugin->getServer()->isLevelLoaded($name))
        {
            $this->plugin->plugin->getServer()->unloadLevel($this->plugin->plugin->getServer()->getLevelByName($name));
        }
        $zip = new \ZipArchive;
        $zip->open($this->plugin->plugin->getDataFolder() . 'arenas/' . $name . '.zip');
        $zip->extractTo($this->plugin->plugin->getServer()->getDataPath() . 'worlds');
        $zip->close();
        unset($zip);
        $this->plugin->plugin->getServer()->loadLevel($name);
        return true;
    }
}
