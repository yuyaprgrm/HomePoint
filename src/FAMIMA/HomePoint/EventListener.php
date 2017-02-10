<?php

namespace FAMIMA\HomePoint;

# Base #
use pocketmine\event;
use pocketmine\level\Position;
use pocketmine as pmmp;

class EventListener implements event\Listener {

    public function __construct(HomePoint $main) {
        $this->main = $main;
    }

    public function onMove(event\player\PlayerMoveEvent $ev) {
        if($this->main->getProperty("enable-visible-distance"))
            $this->main->updateText($ev->getPlayer());

    }

    public function onJoin(event\player\PlayerJoinEvent $ev) {
        
        $player = $ev->getPlayer();
        $this->main->loadUserHome($player->getName(), $player->level->getFolderName());
        $this->main->showText($player);
    }
    
    public function onChangeWorld(event\entity\EntityLevelChangeEvent $ev) {
        $entity = $ev->getEntity();
        
        if($entity instanceof pmmp\Player) {
            $this->main->removeText($entity, $ev->getOrigin()->getFolderName());
            $this->main->loadUserHome($entity->getName(), $ev->getTarget()->getFolderName());
            $this->main->showText($entity, $ev->getTarget()->getFolderName());
        }
    }
}