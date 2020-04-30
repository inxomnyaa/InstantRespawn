<?php

namespace xenialdan\InstantRespawn;

use BadMethodCallException;
use pocketmine\entity\Effect;
use pocketmine\entity\EffectInstance;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\inventory\InventoryPickupItemEvent;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerBlockPickEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerItemConsumeEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\inventory\PlayerInventory;
use pocketmine\level\LevelException;
use pocketmine\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\scheduler\Task;
use pocketmine\utils\TextFormat;
use ReflectionMethod;

class EventListener implements Listener
{
    /**
     * @var ReflectionMethod
     */
    private $deathReflection;
    /**
     * @var ReflectionMethod
     */
    private $respawnReflection;

    public function __construct()
    {
        $this->deathReflection = new ReflectionMethod(Player::class, 'onDeath');
        $this->deathReflection->setAccessible(true);
        $this->respawnReflection = new ReflectionMethod(Player::class, 'respawn');
        $this->respawnReflection->setAccessible(true);
    }

    /**
     * @priority HIGHEST
     * @param EntityDamageEvent $event
     */
    public function onTheoreticalDeath(EntityDamageEvent $event): void
    {
        if (!($player = $event->getEntity()) instanceof Player) return;
        /** @var Player $player */
        if($player->deadTicks > 0){
            //Stop damaging already dead players
            $event->setCancelled();
            return;
        }
        if ($event->getFinalDamage() < $player->getHealth()) return;
        $worldname = $player->getLevel()->getName();
        $setting = Loader::getInstance()->getConfig()->getNested("worlds.{$worldname}", [false, $worldname]);
        $enabled = $setting["enabled"] ?? false;
        if ($enabled || Loader::getInstance()->getConfig()->get('enable-all', false)) {
            $this->death($player);
            $event->setCancelled();
        }
    }

    private function death(Player $player): void
    {
        $this->deathReflection->invoke($player);
        $delay = Loader::getInstance()->getConfig()->get('respawn-after-seconds', 0);
        if ($delay <= 0) {
            $this->respawn($player);
        } else {
            $player->setImmobile(true);
            $player->setInvisible(Loader::getInstance()->getConfig()->get('hide-player', true));
            if (Loader::getInstance()->getConfig()->get('blind-respawn', true)) {
                $player->addEffect(new EffectInstance(Effect::getEffect(Effect::BLINDNESS), $delay * 20 + 20 * 5, 255, false, false));//Effect is longer so it does not flicker
            }
            Loader::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(
                function (int $currentTick) use ($player): void {
                    if ($player->isOnline() && $player->isValid()) $this->respawn($player);
                }
            ), $delay * 20);
            if (Loader::getInstance()->getConfig()->get('show-countdown', true)) {
                Loader::getInstance()->getScheduler()->scheduleRepeatingTask(new class($player, Loader::getInstance()->getServer()->getTick() + $delay * 20) extends Task {
                    /** @var Player */
                    private $player;
                    /** @var int */
                    private $end;

                    public function __construct(Player $player, int $end)
                    {
                        $this->player = $player;
                        $this->end = $end;
                    }

                    public function onRun(int $currentTick): void
                    {
                        if ($currentTick >= $this->end || $this->player === null || !$this->player->isOnline()) {
                            if ($this->player !== null && $this->player->isOnline()) {
                                #$this->player->removeTitles();
                                #$this->player->deadTicks = 0;
                            }
                            $this->getHandler()->cancel();
                            return;
                        }
                        $this->player->deadTicks++;
                        if (($this->end - $currentTick) % 20 === 19) {
                            $this->player->addTitle(TextFormat::RED . TextFormat::BOLD . "Respawning in", TextFormat::GOLD . TextFormat::BOLD . ceil(($this->end - $currentTick) / 20), 0, 20, 0);
                        }
                    }
                }, 1);
            }
        }
    }

    private function respawn(Player $player): void
    {
        $this->respawnReflection->invoke($player);
        $player->setInvisible(false);
        $player->setImmobile(false);
        $player->extinguish();//Due to a PMMP bug respawning with no delay whilst standing in a fire damage source still might cause you to catch on fire before being teleported away.
    }

    /**
     * @priority MONITOR
     * @param PlayerRespawnEvent $event
     * @throws LevelException
     */
    public function onRespawn(PlayerRespawnEvent $event): void
    {
        $player = $event->getPlayer();
        $worldname = $player->getLevel()->getName();
        $setting = Loader::getInstance()->getConfig()->getNested("worlds.{$worldname}", [false, $worldname]);
        [$enabled, $target] = [$setting["enabled"] ?? false, $setting["target"] ?? $worldname];
        if ($enabled || Loader::getInstance()->getConfig()->get('enable-all', false)) {
            if ($event->getRespawnPosition()->getLevel()->getName() !== $target) {
                Loader::getInstance()->getServer()->loadLevel($target);
                $event->setRespawnPosition(Loader::getInstance()->getServer()->getLevelByName($target)->getSafeSpawn());
            }
        }
    }

    /**
     * This is a shitty hack because PMMP allows picking up items whilst being dead.. 🤦🏻‍
     * @priority MONITOR
     * @param InventoryPickupItemEvent $event
     */
    public function hackyItemPickupFix(InventoryPickupItemEvent $event): void
    {
        /** @var PlayerInventory $inventory */
        $inventory = $event->getInventory();
        $player = $inventory->getHolder();
        if ($player->deadTicks > 0) $event->setCancelled();
    }

    /**
     * This is a shitty hack because PMMP allows dropping items whilst being dead.. 🤦🏻‍
     * @priority MONITOR
     * @param PlayerDropItemEvent $event
     */
    public function hackyItemDropFix(PlayerDropItemEvent $event): void
    {
        if ($event->getPlayer()->deadTicks > 0) $event->setCancelled();
    }

    /**
     * This is a shitty hack because PMMP allows consuming items whilst being dead.. 🤦🏻‍
     * @priority MONITOR
     * @param PlayerItemConsumeEvent $event
     */
    public function hackyItemConsumeFix(PlayerItemConsumeEvent $event): void
    {
        if ($event->getPlayer()->deadTicks > 0) $event->setCancelled();
    }

    /**
     * This is a shitty hack because PMMP allows moving items whilst being dead.. 🤦🏻‍
     * Should handle crafting, breaking and placing blocks
     * @priority MONITOR
     * @param InventoryTransactionEvent $event
     * @throws BadMethodCallException
     */
    public function hackyItemTransactionFix(InventoryTransactionEvent $event): void
    {
        if ($event->getTransaction()->getSource()->deadTicks > 0) $event->setCancelled();
    }

    /**
     * This is a shitty hack because PMMP allows picking blocks whilst being dead.. 🤦🏻‍
     * @priority MONITOR
     * @param PlayerBlockPickEvent $event
     */
    public function hackyBlockPickFix(PlayerBlockPickEvent $event): void
    {
        if ($event->getPlayer()->deadTicks > 0) $event->setCancelled();
    }

    /**
     * This is a shitty hack because PMMP allows damaging entities whilst being dead.. 🤦🏻‍
     * @priority MONITOR
     * @param EntityDamageByEntityEvent $event
     */
    public function hackyEntityDamageFix(EntityDamageByEntityEvent $event): void
    {
        $entity = $event->getDamager();
        if ($entity instanceof Player && $entity->deadTicks > 0) $event->setCancelled();
    }

    /**
     * This is a shitty hack because PMMP allows walking whilst being dead.. 🤦🏻‍
     * @priority MONITOR
     * @param PlayerMoveEvent $event
     */
    public function hackyPlayerMoveFix(PlayerMoveEvent $event): void
    {
        if ($event->getPlayer()->deadTicks > 0 && !$event->getFrom()->asPosition()->equals($event->getTo()->asPosition())) $event->setCancelled();
    }

    /*
     * This is a shitty hack because PMMP allows crafting items whilst being dead.. 🤦🏻‍
     * @priority MONITOR
     * @param EntityDamageByEntityEvent $event
     * /
    public function hackyEntityDamageFix(BlockPlaceEvent $event): void
    {
        $entity = $event->getDamager();
        if ($entity instanceof Player && $entity->deadTicks > 0) $event->setCancelled();
    }*/
}