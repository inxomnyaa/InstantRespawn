<?php

namespace xenialdan\InstantRespawn;

use pocketmine\level\format\io\LevelProvider;
use pocketmine\level\format\io\LevelProviderManager;
use pocketmine\plugin\PluginBase;

class Loader extends PluginBase
{

    /** @var Loader */
    private static $instance;

    /**
     * Returns an instance of the plugin
     * @return Loader
     */
    public static function getInstance()
    {
        return self::$instance;
    }

    public function onLoad()
    {
        $this->saveDefaultConfig();
        self::$instance = $this;
    }

    public function onEnable()
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        $this->getServer()->getPluginManager()->registerEvents(new EventListener(), $this);
        $this->getServer()->getCommandMap()->register("InstantRespawn", new Command("irespawn", "Setup for InstantRespawn"));
    }

    /**
     * Returns all world names (!NOT FOLDER NAMES, level.dat entries) of valid levels in "/worlds"
     * @return string[]
     */
    public static function getAllWorlds(): array
    {
        $worldNames = [];
        $glob = glob(self::getInstance()->getServer()->getDataPath() . "worlds/*", GLOB_ONLYDIR);
        if ($glob === false) return $worldNames;
        foreach ($glob as $path) {
            $path .= DIRECTORY_SEPARATOR;
            if (self::getInstance()->getServer()->isLevelLoaded(basename($path))) {
                $worldNames[] = self::getInstance()->getServer()->getLevelByName(basename($path))->getName();
                continue;
            }
            $provider = LevelProviderManager::getProvider($path);
            if ($provider !== null) {
                /** @var LevelProvider $c */
                $c = (new $provider($path));
                $worldNames[] = $c->getName();
                unset($provider);
            }
        }
        sort($worldNames);
        return $worldNames;
    }
}