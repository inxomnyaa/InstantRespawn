<?php

namespace xenialdan\InstantRespawn;

use CortexPE\Commando\args\BaseArgument;
use CortexPE\Commando\BaseCommand;
use InvalidArgumentException;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use pocketmine\utils\TextFormat;
use xenialdan\customui\elements\Dropdown;
use xenialdan\customui\windows\CustomForm;

class Command extends BaseCommand
{

    protected function prepare(): void
    {
        $this->setPermission("irespawn");
        $this->setAliases(["instantrespawn"]);
    }

    /**
     * @param CommandSender $sender
     * @param string $aliasUsed
     * @param BaseArgument[] $args
     * @throws InvalidArgumentException
     */
    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            $sender->sendMessage(TextFormat::RED . "Run ingame!");
            return;
        }
        $config = Loader::getInstance()->getConfig();
        $ui = new CustomForm("Edit InstantRespawn Settings");
        $ui->addToggle("Force enable in all worlds", $config->get("enable-all", false))
            ->addLabel(TextFormat::ITALIC . TextFormat::DARK_BLUE . TextFormat::BOLD . "Worlds");
        $worlds = Loader::getAllWorlds();
        foreach ($worlds as $worldname) {
            $ui->addLabel($worldname);
            $setting = Loader::getInstance()->getConfig()->getNested("worlds.{$worldname}", [false, $worldname]);
            [$enabled, $target] = [$setting["enabled"] ?? false, $setting["target"] ?? $worldname];
            $dr = new Dropdown('Target world', $worlds);
            $dr->setOptionAsDefault($target ?? $worldname);
            $ui->addToggle('Instant Respawn', $enabled ?? false);
            $ui->addElement($dr);
        }
        $ui->setCallable(static function (Player $player, $data) {
            $enableAll = array_shift($data);
            Loader::getInstance()->getConfig()->set("enable-all", $enableAll);
            array_shift($data);
            while (($worldname = array_shift($data)) !== null) {
                $enabled = array_shift($data);
                $target = array_shift($data);
                Loader::getInstance()->getConfig()->setNested("worlds.{$worldname}", ["enabled" => $enabled, "target" => $target]);
            }
            Loader::getInstance()->getConfig()->save();
            $player->sendMessage(TextFormat::GREEN . "InstantRespawn settings have been updated!");
        });
        $sender->sendForm($ui);
    }
}
