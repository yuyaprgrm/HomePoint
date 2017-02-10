<?php

namespace FAMIMA\HomePoint;

# Base # 
use pocketmine\plugin\PluginBase;

# Command #
use pocketmine\command as cmd;

# Packet
use pocketmine\network\protocol;

# Other #
use pocketmine\utils;
use pocketmine as pmmp;
use pocketmine\entity\Entity;
use pocketmine\entity\Item as ItemEntity;
use pocketmine\level\Position;

class HomePoint extends PluginBase {
    
    private static $instance;

    private $db;
    private $properties;
    private $message;

    public function onLoad(){
        self::$instance = $this;
    }

    public function onEnable() {
        $df = $this->getDataFolder();
        $this->saveDefaultConfig();
		$this->reloadConfig();
        $this->saveResource("message.yml", false);
        $config = new utils\Config($df."config.yml");
        $mes = new utils\Config($df."message.yml");
        $this->properties = $config->getAll();
        $this->message = $mes->getAll();
        
        if($this->properties["cversion"] !== "1.00") {
            $this->getLogger()->info(utils\TextFormat::RED."Configのバージョンが現在のものではありません. config.ymlを削除してください(".$df."config.yml)");
            $this->getServer()->getPluginManager()->disablePlugin($this);
            return;
        }

        $this->db = new DataBaseManager($df);
        $this->getLogger()->info(utils\TextFormat::GREEN."HomePoint Enabled!");
        $this->getServer()->getPluginManager()->registerEvents(new EventListener($this), $this);
        // $this->getLogger()->info(utils\TextFormat::AQUA."HomePoint is OpenSource");
    }


    public function onDisable() {

    }


    public function onCommand(cmd\CommandSender $sender, cmd\Command $cmd, $label, array $args) {
        
        if(!$sender instanceof pmmp\Player) {
            $sender->sendMessage("コンソールからコマンドを実行することはできません");
            return true;
        }

        $params = count($args);
        
        if($params < 2) {
            $sender->sendMessage($this->getMessage("home.help"));
            return true;
        }

        $user = $sender->getName();

        switch($args[0]){
            case "add":
                $id = $this->addUserHome($user, $args[1], $sender);

                if($id === -1) {
                    $sender->sendMessage($this->getMessage("error2"));
                    return true;
                }else if($id === -2) {
                    $sender->sendMessage($this->getMessage("error1"));
                    return true;
                }

                $this->SpawnFloatingText($id, $args[1], $sender);
                $this->loadUserHome($user, $sender->level->getFolderName());

                $sender->sendMessage($this->getMessage("home.add"));
                return true;

            break;

            case "del":
                if(($id = $this->deleteUserHome($user, $sender->level->getFolderName(), $args[1])) !== false) {
                    // var_dump($id);
                    $this->RemoveFloatingText($id, $sender);
                    $sender->sendMessage($this->getMessage("home.del"));
                }else {
                    $sender->sendMessage($this->getMessage("error3"));
                }
                
                return true;
            break;

            default:
                $sender->sendMessage($this->getMessage("home.help"));
            break;

        }
    }

    public function getUserHome(string $user, $world) {
        return $this->user[$user];
    }

    public function loadUserHome(string $user, $world) {
        $this->user[$user] = $this->db->getUserHome($user, $world);
    }

    public function addUserHome(string $user, string $title, $pos) {
        $max = $this->getProperty("max-register-count");
        $id = -1;
        if($max === -1 || count($this->db->getUserHome($user, $pos->getLevel()->getFolderName())) < $max) {
            $bool = $this->db->addUserHome($user, $title, floor($pos->x), floor($pos->y), floor($pos->z), $pos->getLevel()->getFolderName());
        
            
            $id = -2;
            // var_dump($bool);
            if($bool) {
                $this->users[$user] = $data = $this->db->getUserHome($user, $pos->getLevel()->getFolderName());
                $id = $data[count($data)-1]["id"];
                // var_dump($data);
            }
        }
        

        return $id;
    }

    public function deleteUserHome(string $user, string $world, string $title) {
        if($this->db->isExists(addslashes($user), addslashes($world), addslashes($title))) {
            $iddata = $this->db->deleteUserHome($user, $world, $title);
            $this->users[$user] = $data = $this->db->getUserHome($user, $world);
            // var_dump($iddata);
            return $iddata["id"];
        }else {
            return false;
        }
    }

    public function SpawnFloatingText($id, $title, $player, $pos = null) {

        if($pos === null)
            $pos = $player;
        $pk = new protocol\AddEntityPacket();
        $pk->eid = 1000000 + $id;
        $pk->type = ItemEntity::NETWORK_ID;
        $pk->x = floor($pos->x);
        $pk->y = floor($pos->y)+1.5;
        $pk->z = floor($pos->z);
        $sdis = pow($pk->x - $player->x, 2) + pow($pk->y - $player->y, 2) + pow($pk->z - $player->z, 2);
        $distance = round(sqrt($sdis), 2);
		$pk->speedX = 0;
		$pk->speedY = 0;
		$pk->speedZ = 0;
		$pk->yaw = 0;
		$pk->pitch = 0;
		$pk->item = 0;
		$pk->meta = 0;
		$flags = 0;
        @$flags |= 1 << Entity::DATA_FLAG_INVISIBLE;
		@$flags |= 1 << Entity::DATA_FLAG_CAN_SHOW_NAMETAG;
		@$flags |= 1 << Entity::DATA_FLAG_ALWAYS_SHOW_NAMETAG;
		@$flags |= 1 << Entity::DATA_FLAG_IMMOBILE;

        
        $pk->metadata = [
			Entity::DATA_FLAGS => [Entity::DATA_TYPE_LONG, $flags],
			Entity::DATA_NAMETAG => [Entity::DATA_TYPE_STRING, utils\TextFormat::GOLD."◆".utils\TextFormat::WHITE.$title."\n".($this->getProperty("enable-visible-distance") ? utils\TextFormat::GREEN.$distance."m": "")],
			Entity::DATA_LEAD_HOLDER_EID => [Entity::DATA_TYPE_LONG,-1]
		];

        $player->dataPacket($pk);
    }

    public function UpdateFloatingText($id, $player, $title, $pos) {

        $pk = new protocol\SetEntityDataPacket();
        $pk->eid = 1000000 + $id;
        $sdis = pow($pos->x - $player->x, 2) + pow($pos->y - $player->y, 2) + pow($pos->z - $player->z, 2);
        $distance = round(sqrt($sdis), 2);
        
        $flags = 0;
        @$flags |= 1 << Entity::DATA_FLAG_INVISIBLE;
		@$flags |= 1 << Entity::DATA_FLAG_CAN_SHOW_NAMETAG;
		@$flags |= 1 << Entity::DATA_FLAG_ALWAYS_SHOW_NAMETAG;
		@$flags |= 1 << Entity::DATA_FLAG_IMMOBILE;
        
        $pk->metadata = [
			Entity::DATA_FLAGS => [Entity::DATA_TYPE_LONG, $flags],
			Entity::DATA_NAMETAG => [Entity::DATA_TYPE_STRING, utils\TextFormat::GOLD."◆".utils\TextFormat::WHITE.$title."\n".utils\TextFormat::GREEN.$distance."m"],
			Entity::DATA_LEAD_HOLDER_EID => [Entity::DATA_TYPE_LONG,-1]
		];
        $player->dataPacket($pk);
    }

    public function RemoveFloatingText($id, $player) {

        $pk = new protocol\RemoveEntityPacket();
        $pk->eid = 1000000 + $id;
        $player->dataPacket($pk);
    }

    public function showText($player) {
        $world = $player->level->getFolderName();
        $name = $player->getName();
        $home = $this->getUserHome($name, $world);

        foreach($home as $h) {
            $this->SpawnFloatingText($h["id"], $h["title"], $player, new Position($h["x"], $h["y"], $h["z"]));
        }
    }

    public function updateText($player) {
        $world = $player->level->getFolderName();
        $name = $player->getName();
        $home = $this->getUserHome($name, $world);

        foreach($home as $h) {
            $this->UpdateFloatingText($h["id"], $player, $h["title"], new Position($h["x"], $h["y"], $h["z"]));
        }
    }

    public function removeText($player, $world) {
        $name = $player->getName();
        $home = $this->getUserHome($name, $world);
        foreach($home as $h) {
            $this->RemoveFloatingText($h["id"], $player);            
        }
    }

    public function getProperty($pro) {
        return isset($this->properties[$pro]) ? $this->properties[$pro] : null;
    }

    public function getMessage($mes) {
        return isset($this->message[$mes]) ? $this->message[$mes] : "error";
    }
}