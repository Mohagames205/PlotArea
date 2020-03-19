<?php

/*
  _____   _         _
 |  __ \ | |       | |      /\
 | |__) || |  ___  | |_    /  \    _ __  ___   __ _
 |  ___/ | | / _ \ | __|  / /\ \  | '__|/ _ \ / _` |
 | |     | || (_) || |_  / ____ \ | |  |  __/| (_| |
 |_|     |_| \___/  \__|/_/    \_\|_|   \___| \__,_|
 */

namespace mohagames\PlotArea\listener;

use mohagames\PlotArea\Main;
use mohagames\PlotArea\tasks\blockMovementTask;
use mohagames\PlotArea\utils\PermissionManager;
use mohagames\PlotArea\utils\Plot;
use mohagames\PlotArea\utils\PublicChest;
use pocketmine\block\Chest;
use pocketmine\block\Door;
use pocketmine\block\FenceGate;
use pocketmine\block\Hopper;
use pocketmine\block\ItemFrame;
use pocketmine\block\Trapdoor;
use pocketmine\entity\object\ArmorStand;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEntityEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\Player;

class EventListener implements Listener
{

    private $main;
    public $cancelMovement;
    private $item;

    public function __construct()
    {
        $this->main = Main::getInstance();
        $this->item = $this->main->item;
    }

    public function chestInteraction(PlayerInteractEvent $e)
    {
        $block = $e->getBlock();
        $player = $e->getPlayer();
        if ($block instanceof Chest) {
            $plot = Plot::get($e->getBlock());
            if ($plot !== null) {
                if ($plot->hasPermission($player->getName(), PermissionManager::PLOT_INTERACT_CHESTS) || $player->hasPermission("pa.staff.interactbypass") || PublicChest::getChest($block) !== null) {
                    return;
                }

                $player->sendPopup("§4U kan deze actie niet uitvoeren.");
                $e->setCancelled();

            }
        }
    }

    public function trapdoorInteraction(PlayerInteractEvent $e)
    {
        $block = $e->getBlock();
        if ($block instanceof Trapdoor) {
            $plot = Plot::get($block);
            if ($plot !== null) {
                $player = $e->getPlayer();

                if ($plot->hasPermission($player->getName(), PermissionManager::PLOT_INTERACT_TRAPDOORS) || $player->hasPermission("pa.staff.interactbypass")) {
                    return;
                }
                $player->sendPopup("§4U kan deze actie niet uitvoeren.");
                Main::getInstance()->getScheduler()->scheduleDelayedTask(new blockMovementTask($this, $player), 10);
                $this->cancelMovement[$e->getPlayer()->getName()] = true;
                $e->setCancelled();
            }
        }
    }

    public function doorInteraction(PlayerInteractEvent $e)
    {
        $block = $e->getBlock();
        $player = $e->getPlayer();
        if ($block instanceof Door) {
            $plot = Plot::get($block);
            if ($plot !== null) {
                if ($plot->hasPermission($player->getName(), PermissionManager::PLOT_INTERACT_DOORS) || $player->hasPermission("pa.staff.interactbypass")) {
                    return;
                }
                $player->sendPopup("§4U kan deze actie niet uitvoeren.");
                Main::getInstance()->getScheduler()->scheduleDelayedTask(new blockMovementTask($this, $player), 10);
                $this->cancelMovement[$e->getPlayer()->getName()] = true;
                $e->setCancelled();
            }
        }
    }

    public function gateInteraction(PlayerInteractEvent $e)
    {
        $block = $e->getBlock();
        $player = $e->getPlayer();
        if ($block instanceof FenceGate) {
            $plot = Plot::get($block);
            if ($plot !== null) {
                if ($plot->hasPermission($player->getName(), PermissionManager::PLOT_INTERACT_GATES) || $player->hasPermission("pa.staff.interactbypass")) {
                    return;
                }
                $player->sendPopup("§4U kan deze actie niet uitvoeren.");
                Main::getInstance()->getScheduler()->scheduleDelayedTask(new blockMovementTask($this, $player), 10);
                $this->cancelMovement[$e->getPlayer()->getName()] = true;
                $e->setCancelled();
            }
        }
    }

    public function ItemFrameInteraction(PlayerInteractEvent $e)
    {
        $block = $e->getBlock();
        if ($block instanceof ItemFrame) {
            $plot = Plot::get($block);
            if ($plot !== null) {
                if ($e->getAction() == PlayerInteractEvent::RIGHT_CLICK_BLOCK) {
                    if ($plot->hasPermission($e->getPlayer()->getName(), PermissionManager::PLOT_INTERACT_ITEMFRAMES) || $e->getPlayer()->hasPermission("pa.staff.interactbypass")) {
                        return;
                    }
                    $e->getPlayer()->sendPopup("§4U kan deze actie niet uitvoeren.");
                    $e->setCancelled();
                } elseif ($e->getAction() == PlayerInteractEvent::LEFT_CLICK_BLOCK && !$e->getPlayer()->hasPermission("pa.staff.interactbypass")) {
                    $e->getPlayer()->sendPopup("§4U kan deze actie niet uitvoeren.");
                    $e->setCancelled();
                }
            }
        }
    }

    public function armorStandInteraction(PlayerInteractEntityEvent $e)
    {
        if ($e->getEntity() instanceof ArmorStand) {
            $plot = Plot::get($e->getEntity());
            if ($plot !== null) {
                if ($plot->hasPermission($e->getPlayer()->getName(), PermissionManager::PLOT_INTERACT_ARMORSTANDS) || $e->getPlayer()->hasPermission("pa.staff.interactbypass")) {
                    return;
                }
                $e->getPlayer()->sendPopup("§4U kan deze actie niet uitvoeren.");
                $e->setCancelled();
            }
        }
    }

    public function onArmorStandBreak(EntityDamageByEntityEvent $e)
    {
        $damager = $e->getDamager();
        if ($damager instanceof Player) {
            if ($damager->getGamemode() == Player::CREATIVE) {
                return;
            }
            if ($e->getEntity() instanceof ArmorStand) {
                $e->getPlayer()->sendPopup("§4U kan deze actie niet uitvoeren.");
                $e->setCancelled();
            }
        }

    }

    public function onHopperInteract(PlayerInteractEvent $e)
    {
        $block = $e->getBlock();
        if ($block instanceof Hopper) {
            $plot = Plot::get($block);
            if ($plot !== null) {
                if ($plot->hasPermission($e->getPlayer()->getName(), PermissionManager::PLOT_INTERACT_HOPPER) || $e->getPlayer()->hasPermission("pa.staff.interactbypass")) {
                    return;
                }
                $e->getPlayer()->sendPopup("§4U kan deze actie niet uitvoeren.");
                $e->setCancelled();

            }
        }
    }

    public function plottool(PlayerInteractEvent $event)
    {
        if ($event->getItem()->getId() == $this->item && $event->getItem()->getCustomName() == "Plot wand") {
            $event->setCancelled();
            if ($event->getAction() == PlayerInteractEvent::RIGHT_CLICK_BLOCK) {
                $block = $event->getBlock();
                $x = $event->getBlock()->getX();
                $y = $event->getBlock()->getY();
                $z = $event->getBlock()->getZ();
                if (Plot::get($block) == null) {
                    $this->main->pos_2[$event->getPlayer()->getName()] = array("x" => $x, "y" => $y, "z" => $z);
                    $event->getPlayer()->sendMessage("§aPOS2: §f(§a" . $x . "§f,§a" . $y . "§f,§a" . $z . "§f)");
                } else {
                    $event->getPlayer()->sendMessage("§4Hier staat al een plot");
                }

            }

        }
    }


    public function plotbreker(BlockBreakEvent $event)
    {
        if ($event->getItem()->getId() == $this->item && $event->getItem()->getCustomName() == "Plot wand") {
            $event->setCancelled();
            $block = $event->getBlock();
            $x = $event->getBlock()->getX();
            $y = $event->getBlock()->getY();
            $z = $event->getBlock()->getZ();
            if (Plot::get($block) == null) {
                $this->main->pos_1[$event->getPlayer()->getName()] = array("x" => $x, "y" => $y, "z" => $z);
                $event->getPlayer()->sendMessage("§aPOS1: §f(§a" . $x . "§f,§a" . $y . "§f,§a" . $z . "§f)");
            } else {
                $event->getPlayer()->sendMessage("§4Hier staat al een plot");
            }
        }
    }


    public function blockMovement(PlayerMoveEvent $e)
    {
        if (isset($this->cancelMovement[$e->getPlayer()->getName()])) {
            $e->getPlayer()->teleport($e->getFrom());
        }
    }
}