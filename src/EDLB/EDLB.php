<?php

namespace EDLB;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\utils\TextFormat as TE;
use pocketmine\utils\Config;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\scheduler\Task;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\level\Position;
use pocketmine\Player;
use pocketmine\entity\{Entity, Effect, EffectInstance};
use pocketmine\tile\Sign;
use pocketmine\level\Level;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use EDLB\{ResetMap, Entity\EntityManager, Entity\types\EntityHuman};
use EDLB\API\ScoreAPI;
use pocketmine\level\sound\PopSound;
use pocketmine\level\sound\AnvilUseSound;
use pocketmine\level\sound\GhastShootSound;
use pocketmine\block\Air;
use pocketmine\item\Item;
use pocketmine\event\entity\EntityLevelChangeEvent;
use pocketmine\server;
use pocketmine\block\Block;

class EDLB extends PluginBase implements Listener {

    public $prefix = TE::GRAY . "[" . TE::YELLOW. TE::RED . "2v" . TE::RED . "2" . TE::RESET . TE::GRAY . "]";
	public $mode = 0;
	public $arenas = array();
	public $currentLevel = "";
        public $op = array();
        public $score;
	
	public function onEnable(){
		  $this->getLogger()->info(TE::RED . "EDLB by Neckitta");
              
                $this->getServer()->getPluginManager()->registerEvents($this ,$this);
		@mkdir($this->getDataFolder());
		$config = new Config($this->getDataFolder() . "/config.yml", Config::YAML);
		if($config->get("arenas")!=null)
		{
			$this->arenas = $config->get("arenas");
		}
		foreach($this->arenas as $lev)
		{
			$this->getServer()->loadLevel($lev);
		}
		$config->save();
                $slots = new Config($this->getDataFolder() . "/slots.yml", Config::YAML);
                $slots->save();
                $this->score = new ScoreAPI($this);
        $this->getScheduler()->scheduleRepeatingTask(new GameSender($this), 20);
        $this->getScheduler()->scheduleRepeatingTask(new EntityUpdate($this), 20);
		$this->getScheduler()->scheduleRepeatingTask(new RefreshSigns($this), 20);
        Entity::registerEntity(EntityHuman::class, true);
	}
        
        public function onDisable() {
            $config = new Config($this->getDataFolder() . "/config.yml", Config::YAML);
            $slots = new Config($this->getDataFolder() . "/slots.yml", Config::YAML);
            if($config->get("arenas")!=null)
            {
                    $this->arenas = $config->get("arenas");
            }
            foreach($this->arenas as $arena)
            {
                $slots->set("slot1".$arena, 0);
                $slots->set("slot2".$arena, 0);
                $slots->set("slot3".$arena, 0);
                $slots->set("slot4".$arena, 0);
                $config->set($arena . "inicio", 0);
                $slots->save();
                $this->reload($arena);
            }
        }
        
		        public function onDam(EntityDamageEvent $event) {
            $player = $event->getEntity();
            $level = $player->getLevel()->getFolderName();
            if(in_array($level,$this->arenas))
            {
            if ($event instanceof EntityDamageByEntityEvent) {
                if ($player instanceof Player && $event->getDamager() instanceof Player) {
                $config = new Config($this->getDataFolder() . "/config.yml", Config::YAML);
                if($config->get($level . "PlayTime") != null)
                {
                        if($config->get($level . "PlayTime") > 514)
                        {
                                $event->setCancelled();
                        }
                        elseif ((strpos($player->getNameTag(), "§cred>") !== false) && (strpos($event->getDamager()->getNameTag(), "§cred>") !== false)) {
                        $event->setCancelled();
                        }
                        elseif ((strpos($player->getNameTag(), "§bblue>") !== false) && (strpos($event->getDamager()->getNameTag(), "§bblue>") !== false)) {
                        $event->setCancelled();
                        }
                        else
                        {
                        }
                }
                }
                }
            }
        }

        public function reload($lev)
        {
                if ($this->getServer()->isLevelLoaded($lev))
                {
                        $this->getServer()->unloadLevel($this->getServer()->getLevelByName($lev));
                }
                $zip = new \ZipArchive;
                $zip->open($this->getDataFolder() . 'arenas/' . $lev . '.zip');
                $zip->extractTo($this->getServer()->getDataPath() . 'worlds');
                $zip->close();
                unset($zip);
                return true;
        }
        
        public function getPlayers(string $arena) {
        	$players = [];
        	foreach ($this->getServer()->getLevelByName($arena)->getPlayers() as $player) {
        		if ($player->getGamemode() == 0 || $player->getGamemode() == 2) {
        			array_push($players, $player->getName());
        		}
        	}
        	return $players;
        }
        
        public function getSpecters(string $arena) {
        	$players = [];
        	foreach ($this->getServer()->getLevelByName($arena)->getPlayers() as $player) {
        		if ($player->getGamemode() == 3) {
        			array_push($players, $player->getName());
        		}
        	}
        	return $players;
        }

        public function getOpenArenas() {
            $config = new Config($this->getDataFolder() . "/config.yml", Config::YAML);
            $arenas = [];
            if (count($this->arenas) > 0) {
                foreach ($this->arenas as $arena) {
                    if ($config->get($arena . 'StartTime') >= 1 && $config->get($arena . 'StartTime') <= 30) {
                        $arenas[] = $arena;
                    } else {
                        $arenas = null;
                    }
                }
            } else {
                $arenas = null;
            }
            if ($arenas != null) {
                shuffle($arenas);
            }
            return $arenas;
        }

        public function getQueueArenas() {
            $config = new Config($this->getDataFolder() . "/config.yml", Config::YAML);
            $arenas = [];
            if (count($this->arenas) > 0) {
                foreach ($this->arenas as $arena) {
                    if ($config->get($arena . 'StartTime') >= 1 && $config->get($arena . 'StartTime') <= 30 && $this->getPlayers($arena) >= 1) {
                        $arenas[] = $arena;
                    } else {
                        $arenas = null;
                    }
                }
            } else {
                $arenas = null;
            }
            if ($arenas != null) {
                shuffle($arenas);
            }
            return $arenas;
        }

        public function getArenas() {
            $arena = $this->getOpenArenas();
            $arena_queue = $this->getQueueArenas();
            if ($arena != null) {
                return $this->getOpenArenas()[0];
            } else if ($arena_queue != null) {
                return $this->getQueueArenas()[0];
            } else {
                return null;
            }
        }

        public function getGameParty(Player $player) {
            $config = new Config($this->getDataFolder() . "/config.yml", Config::YAML);
            $slots = new Config($this->getDataFolder() . "/slots.yml", Config::YAML);
            $arena = $this->getArenas();
            if ($arena != null) {
                $level = $this->getServer()->getLevelByName($arena);
                if (count($this->getPlayers($arena)) < 18) {
                    $namemap = str_replace("§f", "", $arena);
                    if(strpos($player->getNameTag(), "§bblue>") !== false) {
                        $team = TE::GREEN."blue";
                        if($slots->get("slot1".$namemap)==null) {
                            $thespawn = $config->get($namemap . "Spawn2");
                            $slots->set("slot1".$namemap, $player->getName());
			} elseif($slots->get("slot4".$namemap)==null) {
                            $thespawn = $config->get($namemap . "Spawn2");
                            $slots->set("slot4".$namemap, $player->getName());
                        } else {
                            $player->sendMessage($this->prefix.TE::RED."blues teams are now full ");
                            goto sinequipo;
                        }
                    } elseif(strpos($player->getNameTag(), "§cred>") !== false) {
                        $team = TE::RED."red";
                        $thespawn = $config->get($namemap . "Spawn1");
						//---------------------------------------------------------------------------------
                    } elseif($slots->get("slot1".$namemap)==null) {
                        $thespawn = $config->get($namemap . "Spawn2");
                        $slots->set("slot1".$namemap, $player->getName());
                        $player->setNameTag("§bblue>".TE::AQUA.$player->getName());
                        $team = TE::GREEN."blue";
						//---------------------------------------------------------------------------------
                    } elseif($slots->get("slot2".$namemap)==null) {
                        $thespawn = $config->get($namemap . "Spawn1");
                        $slots->set("slot2".$namemap, $player->getName());
                        $player->setNameTag("§cred>".TE::GOLD.$player->getName());
                        $team = TE::RED."red";
						//--------------------------------------------------------------------------------
                    } elseif($slots->get("slot3".$namemap)==null) {
                        $thespawn = $config->get($namemap . "Spawn1");
                        $slots->set("slot3".$namemap, $player->getName());
                        $player->setNameTag("§cred>".TE::GOLD.$player->getName());
                        $team = TE::RED."red";
                    //------------------------------------------------------------------------------------
                    } elseif($slots->get("slot4".$namemap)==null) {
                        $thespawn = $config->get($namemap . "Spawn2");
                        $slots->set("slot4".$namemap, $player->getName());
                        $player->setNameTag("§bblue>".TE::AQUA.$player->getName());
                        $team = TE::GREEN."blue";
						//----------------------------------------------------------------------------
                    } else {
                        nohay:
                        $player->sendMessage($this->prefix.TE::RED."Server is full.");
                        goto sinequipo;
                    }
                    $slots->save();
                    $player->setGamemode(2);
					$player->getInventory()->clearAll();
					$player->getArmorInventory()->clearAll();
                    $player->removeAllEffects();
                    $player->setMaxHealth(20);
                    $player->setHealth(20);
                    $player->setFood(20);
                    $spawn = new Position($thespawn[0]+4,$thespawn[1],$thespawn[2]+4, $level);
					$level->loadChunk($spawn->getFloorX(), $spawn->getFloorZ());
					$player->teleport($spawn);
                    if(strpos($player->getNameTag(), "§cred>") !== false) {
                    Server::getInstance()->dispatchCommand($player, "khkit red");
                    }
                    if(strpos($player->getNameTag(), "§bblue>") !== false) {
                    Server::getInstance()->dispatchCommand($player, "khkit blue");
                    }
                    $player->addTitle("§eYou joined team\n §r".$team);
                    foreach($level->getPlayers() as $playersinarena) {
                        $playersinarena->sendMessage($player->getNameTag() .TE::YELLOW. " has joined the match");
                    }
                    sinequipo:
                } else {
                    $player->sendMessage(TE::RED . 'The server is full.');
                }
            } else {
                $player->sendMessage(TE::RED . 'No game to be found.');
            }
        }

        public function onHit(EntityDamageByEntityEvent $event) {
            if ($event->getEntity() instanceof EntityHuman) {
                $player = $event->getDamager();
                if ($player instanceof Player) {
                    $event->setCancelled(true);
                    $this->getGameParty($player);
                }
            }
        }
        
        public function onDeath(EntityDamageEvent $event) {
        	$player = $event->getEntity();
        	if ($player instanceof Player) {
        		$arena = $player->getLevel()->getFolderName();
        		$api = $this->score;
        		if (in_array($arena, $this->arenas)) {
        			switch ($event->getCause()) {
        				case EntityDamageEvent::CAUSE_ENTITY_ATTACK:
        					if ($event instanceof EntityDamageByEntityEvent) {
        						$damager = $event->getDamager();
        						if ($damager instanceof Player) {
        							if ($event->getFinalDamage() >= $player->getHealth()) {
        								$event->setCancelled();
        								foreach ($player->getLevel()->getPlayers() as $players) {
        									$players->sendMessage($player->getNameTag() . TE::YELLOW . " was killed by " . $damager->getNameTag());
        								}
        								$player->setGamemode(2);
							   		    $player->setHealth(20);
                           			    $player->setFood(20);
        								$player->setNameTag($player->getName());
        								$api->remove($player);
        								$player->removeAllEffects();
        								$player->getInventory()->clearAll();
							     		$player->addTitle("§l§cYOU DIED!");
										Server::getInstance()->dispatchCommand($player, "hub");
        								$player->getArmorInventory()->clearAll();
        							}
        						}
        					}
        				break;
       				    default:
       					    if ($event->getFinalDamage() >= $player->getHealth()) {
       						    foreach ($player->getLevel()->getPlayers() as $players) {
        					    }
       						    $event->setCancelled();
       						    $player->setGamemode(2);
							    $player->setHealth(20);
                           	    $player->setFood(20);
                           	    $player->setNameTag($player->getName());
        	   				    $api->remove($player);
        			   		    $player->removeAllEffects();
        	   				    $player->getInventory()->clearAll();
					     	    $player->addTitle("§l§cYOU DIED!");
								Server::getInstance()->dispatchCommand($player, "hub");
        	   				    $player->getArmorInventory()->clearAll();
       					    }
       				    break;
        			}
        		}
        	}
        }
        
        public function chang($pl) {
            $level = $pl->getLevel()->getFolderName();
            if(in_array($level,$this->arenas))
            {
                $slots = new Config($this->getDataFolder() . "/slots.yml", Config::YAML);
                for ($i = 1; $i <= 18; $i++) {
                    if($slots->get("slot".$i.$level)==$pl->getName())
                    {
                        $slots->set("slot".$i.$level, 0);
                    }
                }
                $slots->save();
            }
        }
	
        public function enCambioMundo(EntityLevelChangeEvent $event)
        {
            $pl = $event->getEntity();
            if($pl instanceof Player)
            {
                $lev = $event->getOrigin();
                if($lev instanceof Level && in_array($lev->getFolderName(),$this->arenas))
		{
                $level = $lev->getFolderName();
                $pl->removeAllEffects();
                $pl->getInventory()->clearAll();
                $pl->getArmorInventory()->clearAll();
                $pl->setNameTag($pl->getName());
                $api = $this->score;
                    $api->remove($pl);
                $slots = new Config($this->getDataFolder() . "/slots.yml", Config::YAML);
                for ($i = 1; $i <= 18; $i++) {
                    if($slots->get("slot".$i.$level)==$pl->getName())
                    {
                        $slots->set("slot".$i.$level, 0);
                    }
                }
                $slots->save();
                }
            }
        }

        public function onLog(PlayerLoginEvent $event)
	{
            $player = $event->getPlayer();
            if(in_array($player->getLevel()->getFolderName(),$this->arenas))
            {
            $player->getInventory()->clearAll();
            $player->getArmorInventory()->clearAll();
            $spawn = $this->getServer()->getDefaultLevel()->getSafeSpawn();
            $player->teleport($spawn);
            }
	}
        
        public function onQuit(PlayerQuitEvent $event)
        {
            $pl = $event->getPlayer();
            $level = $pl->getLevel()->getFolderName();
            if(in_array($level,$this->arenas))
            {
            $pl->getInventory()->clearAll();
            $pl->getArmorInventory()->clearAll();
            $pl->setNameTag($pl->getName());
            $this->chang($pl);
            $api = $this->score;
                    $api->remove($pl);
            }
        }
	
	public function onBlockBr(BlockBreakEvent $event)
	{
            $player = $event->getPlayer();
            $level = $player->getLevel()->getFolderName();
            if(in_array($level,$this->arenas))
            {
                $event->setCancelled(false);
            }
	}
        
    public function onBlockPl(BlockPlaceEvent $event) {
        $player = $event->getPlayer();
        $level = $player->getLevel()->getFolderName();
        if(in_array($level,$this->arenas)) {
            $event->setCancelled(false);
        }
	}
        
	public function onCommand(CommandSender $player, Command $command, string $label, array $args): bool{
        switch($command->getName()){
		    case "2vs2":
		      if($player instanceof Player){
		      if(empty($args[0])){
		        $player->sendMessage("/2vs2 join");
		        return true;
		      }
		      switch(strtolower($args[0])){
		        case "join":
		        $this->getGameParty($player);
		        break;
		      }
		     }
			return true;
			case "edlb":
                            if($player->isOp())
                            {
                                if(!empty($args[0]))
                                {
                                    if($args[0]=="make")
                                    {
                                        if(!empty($args[1]))
                                        {
                                            if(file_exists($this->getServer()->getDataPath() . "/worlds/" . $args[1]))
                                            {
                                                $this->getServer()->loadLevel($args[1]);
                                                $this->getServer()->getLevelByName($args[1])->loadChunk($this->getServer()->getLevelByName($args[1])->getSafeSpawn()->getFloorX(), $this->getServer()->getLevelByName($args[1])->getSafeSpawn()->getFloorZ());
                                                array_push($this->arenas,$args[1]);
                                                $this->currentLevel = $args[1];
                                                $this->mode = 1;
                                                $player->sendMessage($this->prefix . "Touch for set the Spawn for players");
                                                $player->setGamemode(1);
                                                array_push($this->op, $player->getName());
                                                $player->teleport($this->getServer()->getLevelByName($args[1])->getSafeSpawn(),0,0);
                                                $name = $args[1];
                                                $this->zipper($player, $name);
                                            }
                                            else
                                            {
                                                    $player->sendMessage($this->prefix . "ERROR missing world.");
                                            }
                                        }
                                        else
                                        {
                                                $player->sendMessage($this->prefix . "ERROR missing parameters.");
                                        }
                                    }
                                    else
                                    {
                                            $player->sendMessage($this->prefix . "Invalid Command.");
                                    }
                                }
                                else
                                {
                                 $player->sendMessage($this->prefix . "EscapaDeLaBestia Commands!");
                                 $player->sendMessage($this->prefix . "/edlb make [world]: Create a EdlB game!");
                                }
                            }
			return true;
                        
                        case "edlbstart":
                            if($player->isOp())
				{
                                if(!empty($args[0]))
					{
                                        $config = new Config($this->getDataFolder() . "/config.yml", Config::YAML);
                                        if($config->get($args[0] . "StartTime") != null)
                                        {
                                        $config->set($args[0] . "StartTime", 10);
                                        $config->save();
                                        $player->addTitle(TE::GOLD."Starting Match In 10...". TE::RESET);
                                        }
                                        }
                                        else
                                        {
                                            $level = $player->getLevel()->getFolderName();
                                            $config = new Config($this->getDataFolder() . "/config.yml", Config::YAML);
                                            if($config->get($level . "StartTime") != null)
                                            {
                                            $config->set($level . "StartTime", 10);
                                            $config->save();
                                            $player->addTitle(TE::GOLD."Starting Match In 10...". TE::RESET);
                                            }
                                        }
                                }
                                return true;
                                case 'edlbnpc':
                                    if ($player->isOp()) {
                                        $npc = new EntityManager($this);
                                        $npc->setEntity($player);
                                    }
                                return true;
	}
        }
	
	public function onInteract(PlayerInteractEvent $event)
	{
		$player = $event->getPlayer();
		$block = $event->getBlock();
		$tile = $player->getLevel()->getTile($block);
		
		if($tile instanceof Sign) 
		{
			if(($this->mode==26)&&(in_array($player->getName(), $this->op)))
			{
				$tile->setText(TE::AQUA . "[JOIN]".TE::GREEN  . "0 / 18","§f" . $this->currentLevel,$this->prefix);
				$this->refreshArenas();
				$this->currentLevel = "";
				$this->mode = 0;
				$player->sendMessage($this->prefix . "Arena Registered!");
                                array_shift($this->op);
			}
			else
			{
				$text = $tile->getText();
				if($text[3] == $this->prefix)
				{
                                        if($text[0]==TE::GOLD . "[FULL]")
                                        {
                                            $config = new Config($this->getDataFolder() . "/config.yml", Config::YAML);
                                            $namemap = str_replace("§f", "", $text[2]);
                                            $level = $this->getServer()->getLevelByName($namemap);
                                            if(strpos($player->getNameTag(), "§cred>") !== false)
                                            {
                                                $team = TE::RED."red";
                                                $thespawn = $config->get($namemap . "Spawn1");
                                            }
                                            elseif(strpos($player->getNameTag(), "§bblue>") !== false)
                                            {
                                                $team = TE::GREEN."blue";
                                                $thespawn = $config->get($namemap . "Spawn2");
                                            }
                                            else
                                            {
                                                $player->sendMessage($this->prefix.TE::RED."Server full.");
                                                goto sinequipo;
                                            }
                                            $player->setGamemode(2);
                                            $player->getInventory()->clearAll();
                                            $player->getArmorInventory()->clearAll();
                                                $player->removeAllEffects();
                                                $player->setMaxHealth(20);
                                                $player->setHealth(20);
                                                $player->setFood(20);
                                                $spawn = new Position($thespawn[0]+4,$thespawn[1],$thespawn[2]+4,$level);
						$level->loadChunk($spawn->getFloorX(), $spawn->getFloorZ());
						$player->teleport($spawn);
                                                if(strpos($player->getNameTag(), "§cred>") !== false)
                                                {
                                                }
                                                else
                                                {
                  
                                                }
                                                $player->addTitle("§You Joined Team\n ".$team);
                                                foreach($level->getPlayers() as $playersinarena)
                                                {
                                                $playersinarena->sendMessage($player->getNameTag() . " §ehas joined the match");
                                                }
                                        }
					elseif($text[0]==TE::AQUA . "[JOIN]")
					{
						$config = new Config($this->getDataFolder() . "/config.yml", Config::YAML);
                                                $slots = new Config($this->getDataFolder() . "/slots.yml", Config::YAML);
                                                $namemap = str_replace("§f", "", $text[2]);
						$level = $this->getServer()->getLevelByName($namemap);
                                                if(strpos($player->getNameTag(), "§bblue>") !== false)
                                                {
                                                    $team = TE::GREEN."blue";
                                                    if($slots->get("slot1".$namemap)==null)
                                                    {
                                                        $thespawn = $config->get($namemap . "Spawn2");
                                                        $slots->set("slot1".$namemap, $player->getName());
                                                    }
                                                    elseif($slots->get("slot4".$namemap)==null)
                                                    {
                                                        $thespawn = $config->get($namemap . "Spawn2");
                                                        $slots->set("slot4".$namemap, $player->getName());

                                                    }
                                                    else
                                                    {
                                                        $player->sendMessage($this->prefix.TE::RED."blues teams are full");
                                                        goto sinequipo;
                                                    }
                                                }
                                                elseif(strpos($player->getNameTag(), "§cred>") !== false)
                                                {
                                                    $team = TE::RED."red";
                                                    $thespawn = $config->get($namemap . "Spawn1");
                                                }
                                                elseif($slots->get("slot1".$namemap)==null)
                                                {
                                                        $thespawn = $config->get($namemap . "Spawn2");
                                                        $slots->set("slot1".$namemap, $player->getName());
                                                        $player->setNameTag("§bblue>".TE::AQUA.$player->getName());
                                                        $team = TE::GREEN."blue";
                                                }
                                                elseif($slots->get("slot2".$namemap)==null)
                                                {
                                                        $thespawn = $config->get($namemap . "Spawn1");
                                                        $slots->set("slot2".$namemap, $player->getName());
                                                        $player->setNameTag("§cred>".TE::GOLD.$player->getName());
                                                        $team = TE::RED."red";
                                                }
                                                elseif($slots->get("slot3".$namemap)==null)
                                                {
                                                        $thespawn = $config->get($namemap . "Spawn1");
                                                        $slots->set("slot3".$namemap, $player->getName());
                                                        $player->setNameTag("§cred>".TE::GOLD.$player->getName());
                                                        $team = TE::RED."red";
                                                }
                                                elseif($slots->get("slot4".$namemap)==null)
                                                {
                                                        $thespawn = $config->get($namemap . "Spawn1");
                                                        $slots->set("slot4".$namemap, $player->getName());
                                                        $player->setNameTag("§bblue>".TE::AQUA.$player->getName());
                                                        $team = TE::GREEN."blue";
														}
                                                else
                                                {
                                                    nohay:
                                                    $player->sendMessage($this->prefix.TE::RED."The server is full.");
                                                    goto sinequipo;
                                                }
                                                $slots->save();
                                                $player->setGamemode(2);
						$player->getInventory()->clearAll();
						$player->setHealth("20");
						$player->setFood("20");
						$player->getArmorInventory()->clearAll();
						
                                                $player->removeAllEffects();
                                                $player->setMaxHealth(20);
                                                $player->setHealth(20);
                                                $player->setFood(20);
                                                $spawn = new Position($thespawn[0]+0.4, $thespawn[1], $thespawn[2]+0.4, $level);
						$level->loadChunk($spawn->getFloorX(), $spawn->getFloorZ());
						$player->teleport($spawn);
                                                if(strpos($player->getNameTag(), "§cred>") !== false)
                                                {
                                                }
                                                else
                                                {
                                                }
                                                $player->addTitle("§eYou joined team §r".$team);
                                                foreach($level->getPlayers() as $playersinarena)
                                                {
                                                $playersinarena->sendMessage($player->getNameTag() .TE::GOLD. " has joined the match.");
                                                }
                                                sinequipo:
					}
					else
					{
						$player->sendMessage($this->prefix . "You cannot enter");
					}
				}
			}
		}
		elseif(in_array($player->getName(), $this->op)&&$this->mode==1)
		{
			$config = new Config($this->getDataFolder() . "/config.yml", Config::YAML);
			$config->set($this->currentLevel . "Spawn" . $this->mode, array($block->getX(),$block->getY()+4,$block->getZ()));
			$player->sendMessage($this->prefix . "Spawn Corredores registrado!");
			$this->mode++;
			$config->save();
		}
		elseif(in_array($player->getName(), $this->op)&&$this->mode==2)
		{
			$config = new Config($this->getDataFolder() . "/config.yml", Config::YAML);
			$config->set($this->currentLevel . "Spawn" . $this->mode, array($block->getX(),$block->getY()+4,$block->getZ()));
			
			$player->sendMessage($this->prefix . "Spawn Bestia Registrado!");
			$config->set("arenas",$this->arenas);
                        $config->set($this->currentLevel . "inicio", 0);
			$player->sendMessage($this->prefix . "Toca un cartel para registrar Arena!");
			$spawn = $this->getServer()->getDefaultLevel()->getSafeSpawn();
			$this->getServer()->getDefaultLevel()->loadChunk($spawn->getFloorX(), $spawn->getFloorZ());
			$player->teleport($spawn);
			$config->save();
			$this->mode=26;
		}
	}
	
	public function refreshArenas()
	{
		$config = new Config($this->getDataFolder() . "/config.yml", Config::YAML);
		$config->set("arenas",$this->arenas);
		foreach($this->arenas as $arena)
		{
			$config->set($arena . "PlayTime", 515);
            $config->set($arena . "StartTime", 30);
            $config->set($arena . "EndTime", 6);
		}
		$config->save();
	}
        
        public function zipper($player, $name)
        {
        $path = realpath($player->getServer()->getDataPath() . 'worlds/' . $name);
				$zip = new \ZipArchive;
				@mkdir($this->getDataFolder() . 'arenas/', 0755);
				$zip->open($this->getDataFolder() . 'arenas/' . $name . '.zip', $zip::CREATE | $zip::OVERWRITE);
				$files = new \RecursiveIteratorIterator(
					new \RecursiveDirectoryIterator($path),
					\RecursiveIteratorIterator::LEAVES_ONLY
				);
                                foreach ($files as $datos) {
					if (!$datos->isDir()) {
						$relativePath = $name . '/' . substr($datos, strlen($path) + 1);
						$zip->addFile($datos, $relativePath);
					}
				}
				$zip->close();
				$player->getServer()->loadLevel($name);
				unset($zip, $path, $files);
        }
}

class RefreshSigns extends Task {
    public $prefix = TE::GRAY . "[" . TE::YELLOW. TE::RED . "2v" . TE::RED . "2" . TE::RESET . TE::GRAY . "]";
	public function __construct($plugin)
	{
		$this->plugin = $plugin;
		//parent::__construct($plugin);
	}
  
	public function onRun(int $currentTick)
	{
		$allplayers = $this->plugin->getServer()->getOnlinePlayers();
		$level = $this->plugin->getServer()->getDefaultLevel();
		$tiles = $level->getTiles();
		foreach($tiles as $t) {
			if($t instanceof Sign) {	
				$text = $t->getText();
				if($text[3]==$this->prefix)
				{
					$aop = 0;
                                        $namemap = str_replace("§f", "", $text[2]);
					foreach($allplayers as $player){if($player->getLevel()->getFolderName()==$namemap){$aop=$aop+1;}}
					$ingame = TE::AQUA . "[JOIN]";
					$config = new Config($this->plugin->getDataFolder() . "/config.yml", Config::YAML);
					if($config->get($namemap . "PlayTime")!=515)
					{
						$ingame = TE::DARK_PURPLE . "[INGAME]";
					}
					elseif($aop>=24)
					{
						$ingame = TE::GOLD . "[FULL]";
					}
                                        $t->setText($ingame,TE::GREEN  . $aop . " / 24",$text[2],$this->prefix);
				}
			}
		}
	}
}

class EntityUpdate extends Task {
    public $plugin;

	public function __construct($plugin) {
		$this->plugin = $plugin;
    }

    public function onRun(int $currentTick) {
        foreach ($this->plugin->getServer()->getDefaultLevel()->getEntities() as $entity) {
            if ($entity instanceof EntityHuman) {
                $entity->setNameTag($this->getName());
            }
        }
    }

    public function getName() : string {
        $title = TE::BOLD . TE::YELLOW . '' . TE::WHITE . '§c2v2' . "\n";
        $subtitle = TE::AQUA . $this->getPlaying() . ' playing';
        return $title . $subtitle;
    }

    public function getPlaying() : int {
        $players = [];
        foreach ($this->plugin->arenas as $arena) {
            if ($this->plugin->getServer()->getLevelByName($arena) !== null) {
                foreach ($this->plugin->getServer()->getLevelByName($arena)->getPlayers() as $player) {
                    array_push($players, $player->getName());
                }
            }
        }
        return count($players);
    }
}

class GameSender extends Task {
    public $prefix = "";
    public $plugin;
	public function __construct($plugin)
	{
		$this->plugin = $plugin;
                $this->prefix = $this->plugin->prefix;
		//parent::__construct($plugin);
	}
        
        public function getResetmap() {
        return new ResetMap($this);
        }

	public function onRun(int $currentTick)
	{
		$config = new Config($this->plugin->getDataFolder() . "/config.yml", Config::YAML);
		$arenas = $config->get("arenas");
		if(!empty($arenas))
		{
			foreach($arenas as $arena)
			{
				$time = $config->get($arena . "PlayTime");
                $timeToStart = $config->get($arena . "StartTime");
                $timeToEnd = $config->get($arena . "EndTime");
				$levelArena = $this->plugin->getServer()->getLevelByName($arena);
				if($levelArena instanceof Level)
				{
					$playersArena = $this->plugin->getPlayers($arena);
					if(count($playersArena)==0)
					{
						$config->set($arena . "PlayTime", 515);
                        $config->set($arena . "StartTime", 30);
                        $config->set($arena . "EndTime", 6);
                                                $config->set($arena . "inicio", 0);
					}
					else
					{
                                                if(count($playersArena)>=4)
                                                {
                                                    $config->set($arena . "inicio", 1);
                                                    $config->save();
                                                }
						if($config->get($arena . "inicio")==1)
						{
							if($timeToStart>0)
							{
								$timeToStart--;
								foreach($levelArena->getPlayers() as $pl)
								{
                                                                        $pl->sendPopup(TE::GREEN. $timeToStart .TE::GREEN." secoonds to start ". TE::RESET);
									if($timeToStart<=5)
                                                                        {
                                                                        $levelArena->addSound(new PopSound($pl));
                                                                        }
                           
								}
                                                                if($timeToStart==10)
                                                                {
                                                                      $pl->addTitle(TE::RED. "10" .TE::YELLOW."\n§rPrepare to Fight! ". TE::RESET);
                                                                }
							        if($timeToStart==5)
                                                                {
                                                                      $pl->addTitle(TE::RED. "5" .TE::YELLOW."\n§rPrepare to Fight! ". TE::RESET);
                                                                }
							        if($timeToStart==4)
                                                                {
                                                                      $pl->addTitle(TE::RED. "4" .TE::YELLOW."\n§rPrepare to Fight! ". TE::RESET);
								}
                                                                if($timeToStart==3)
                                                                {
                                                                      $pl->addTitle(TE::RED. "3" .TE::YELLOW."\n§rPrepare to Fight! ". TE::RESET);
                                                                }
                                                                if($timeToStart==2)
                                                                {
                                                                      $pl->addTitle(TE::RED. "2" .TE::YELLOW."\n§rPrepare to Fight! ". TE::RESET);
                                                                }
							        if($timeToStart==1)
                                                                {
                                                                      $pl->addTitle(TE::RED. "1" .TE::YELLOW."\n§rPrepare to Fight! ". TE::RESET);
                                                                }
								if($timeToStart<=0)
								{
								 
                                                                    $tiles = $levelArena->getTiles();
                                                                    foreach ($tiles as $tile) {
                                                                        if ($tile instanceof Sign) {
                                                                            $text = $tile->getText();
                                                                            if (strtolower($text[0]) == "blue") {
                                                                                $levelArena->setBlock($tile->add(0, 2, 0), new Air());
                                                                                $levelArena->setBlock($tile->add(0, 3, 0), new Air());
                                                                            }
                                                                        }
                                                                    }
								}
								$config->set($arena . "StartTime", $timeToStart);
							}
							else
							{
                                                                $colors = array();
                                                                foreach($levelArena->getPlayers() as $pl)
                                                                {
                                                                array_push($colors, $pl->getNameTag());
                                                                }
                                                                $names = implode("-", $colors);
                                                                $bestia = substr_count($names, "§bblue>");
                                                                $corredor = substr_count($names, "§cred>");
                                                                foreach($levelArena->getPlayers() as $pla)
                                                                {
                                                                    if(strpos($pla->getNameTag(), "§bblue>") !== false)
                                                                    {
                                                                        $x = $pla->x;
                                                                        $z = $pla->z;
                                                                    }
                                                                }
                                                                foreach($levelArena->getPlayers() as $pla)
                                                                {
                                                                    $x1 = $pla->x;
                                                                    $z1 = $pla->z;
                                                                    if(strpos($pla->getNameTag(), "§cred>") !== false)
                                                                    {
                                                                        $x3 = pow($x1 - $x1, 2);
                                                                        $z3 = pow($z1 - $z1, 2);
                                                                        $lol = $x3 + $z3;
                                                                        $dist = intval(sqrt($lol));
                                                                        $api = $this->plugin->score;
                                                                        $api->new($pla, $pla->getName(), TE::BOLD.TE::RED."§l§b2VS2");
                                                                        $i = 0;
                                                                        $lines = [
                                                                        TE::WHITE."   ",
                                                                        TE::WHITE."§3blue Left: ".TE::WHITE.$bestia,
                                                                        TE::WHITE."§3red Left: ".TE::WHITE.$corredor,
                                                                        TE::WHITE."   ",
									TE::WHITE."§3You are the ".TE::RED."red",
									TE::WHITE."§3Mode: ".TE::WHITE."2v2",
                                                                        TE::WHITE."§3Time left: ".TE::WHITE.$time,
                                                                        TE::WHITE."   ",
                                                                        TE::YELLOW."§b§lCrystalMC.tk ",
                                                                        ];
                                                                        foreach($lines as $line){
                                                                        	if($i < 15){
                                                                        	$i++;
                                                                   	     $api->setLine($pla, $i, $line);
                                                                        	}
                                                                        }
                                                                      //  $pla->sendTip(TE::RED."Bestia:" . $bestia .TE::AQUA. " blue:" . $corredor.TE::YELLOW." Dist.Bestia:".TE::LIGHT_PURPLE.$dist.TE::YELLOW." Tiempo: ".TE::GREEN.$time. TE::RESET);
                                                                    }
                                                                    else
                                                                    {
                                                                    	$api = $this->plugin->score;
                                                                        $api->new($pla, $pla->getName(), TE::BOLD.TE::RED."§l§b2VS2");
                                                                        $i = 0;
                                                                        $lines = [
                                                                        TE::WHITE."   ",
                                                                        TE::WHITE."§3blue Left: ".TE::WHITE.$bestia,
                                                                        TE::WHITE."§3red Left ".TE::WHITE.$corredor,
                                                                        TE::WHITE."    ",
									TE::WHITE."§3You are the ".TE::BLUE."blue",
									TE::WHITE."§3Mode: ".TE::WHITE."2v2",
                                                                        TE::WHITE."§3Time left: ".TE::WHITE.$time,
                                                                        TE::WHITE."   ",
                                                                        TE::YELLOW."§l§bCrystalMC.tk ",
                                                                        ];
                                                                        foreach($lines as $line){
                                                                        	if($i < 15){
                                                                        	$i++;
                                                                   	     $api->setLine($pla, $i, $line);
                                                                        	}
                                                                        }
                                                                          //  $pla->sendTip(TE::RED."Bestia:" . $bestia .TE::AQUA. " blues:" . $corredor.TE::YELLOW." Tiempo: ".TE::GREEN.$time. TE::RESET);
                                                                    }
                                                                }
                                                                $winner = null;
                                                                $winners = array();
                                                                if($bestia!=0 && $corredor==0)
                                                                {
                                                                    $winner = TE::YELLOW.">".TE::GOLD." CONGRATS! To the blues!  ".TE::GOLD."For winning against the reds!";
                                                                    foreach($levelArena->getPlayers() as $pl)
                                                                    {
                                                                        if(strpos($pl->getNameTag(), "§bblue>") !== false)
                                                                        {
                                                                            array_push($winners, $pl->getNameTag());
                                                                        }
                                                                    }
                                                                }
                                                                if($bestia==0 && $corredor!=0)
                                                                {
                                                                    $winner = TE::YELLOW."WC>".TE::GOLD." CONGRATS! To the reds!  ".TE::GOLD."For winning against the blues";
                                                                    foreach($levelArena->getPlayers() as $pl)
                                                                    {
                                                                        if(strpos($pl->getNameTag(), "§cred>") !== false)
                                                                        {
                                                                            array_push($winners, $pl->getNameTag());
                                                                        }
                                                                    }
                                                                }
                                                                if($winner!=null)
                                                                {
                                                                    $timeToEnd--;
                                                                    $config->set($arena . "EndTime", $timeToEnd);
                                                                    $config->save();
                                                                    $namewin = implode(", ", $winners);
																	//----------------------
                                                                        if ($timeToEnd == 5) {
                                                                            $pl->sendTip(TE::WHITE . 'Restarting in ' . TE::GREEN . '5');
                                                                            $pl->addTitle(TE::GOLD . TE::BOLD . '§6§lVICTORY!\n§rYou Are The Best Team');
																			Server::getInstance()->broadcastMessage("§b[§62v2§b]§a the winners are :");
																		}
																	//----------------------
                                                                    foreach($levelArena->getPlayers() as $pl)
                                                                    {
                                                                        if ($timeToEnd == 5) {
                                                                            $pl->sendTip(TE::WHITE . 'Restarting in ' . TE::GREEN . '5');
                                                                            $pl->addTitle(TE::GOLD . TE::BOLD . '§6§lVICTORY!\n§rYou Are The Best Team');
																			Server::getInstance()->broadcastMessage("- ".$pl->getNameTag()."");
                                                                        } else if ($timeToEnd == 4) {
                                                                            $pl->sendTip(TE::WHITE . 'Restarting in' . TE::GREEN . ' 4');
                                                                        } else if ($timeToEnd == 3) {
                                                                            $pl->sendTip(TE::WHITE . 'Restarting in ' . TE::GREEN . ' 3');
                                                                        } else if ($timeToEnd == 2) {
                                                                            $pl->sendTip(TE::WHITE . 'Restarting in ' . TE::GREEN . ' 2');
                                                                        } else if ($timeToEnd == 1) {
                                                                            $pl->sendTip(TE::WHITE . 'Restarting in ' . TE::GREEN . ' 1');
																			$pl->teleport($this->plugin->getServer()->getDefaultLevel()->getSafeSpawn());
                                                                        } else if ($pl->getGamemode() == 2 || $pl->getGamemode() == 0) {
                                                                        } else if ($timeToEnd == 0) {
                                                                            $pl->setGamemode(2);
                                                                            $pl->getInventory()->clearAll();
                                                                            $pl->getArmorInventory()->clearAll();
                                                                            $pl->removeAllEffects();
                                                                            $pl->teleport($this->plugin->getServer()->getDefaultLevel()->getSafeSpawn());
                                                                            $pl->setNameTag($pl->getName());
                                                                            $config->set($arena . "PlayTime", 515);
                                                                            $config->set($arena . "StartTime", 30);
                                                                            $config->set($arena . "EndTime", 6);
                                                                            $config->set($arena . "inicio", 0);
                                                                            $config->save();
                                                                            $api = $this->plugin->score;
                                                                            $api->remove($pl);
                                                                            $this->getResetmap()->reload($arena);
                                                                        }
                                                                    }
                                                                }
								$time--;
								if($time == 514)
								{
                                                                        $slots = new Config($this->plugin->getDataFolder() . "/slots.yml", Config::YAML);
                                                                        for ($i = 1; $i <= 24; $i++) {
                                                                        $slots->set("slot".$i.$arena, 0);
                                                                        }
                                                                        $slots->save();
									foreach($levelArena->getPlayers() as $pl)
									{
                                           $pl->sendMessage("§bThe Game Started");
                                           $pl->sendMessage("§l§a--------------------
§r§bCross teaming you can get ban or kick because cross team is not allowed in this game fight and help your team for win the game !
§a§l--------------------");
$player = $pl;
$player->getInventory()->setItem(3, Item::get(Item::COOKED_BEEF, 0, 15));
                                             $player->getInventory()->setItem(2, Item::get(Item::EGG, 0, 16));
                                             $player->getInventory()->setItem(1, Item::get(Item::GOLDEN_APPLE, 0, 15));
                                             $player->getInventory()->setItem(4, Item::get(Item::BOW, 0, 1));
                                             $player->getInventory()->setItem(8, Item::get(Item::ARROW, 0, 32));
                                             $player->getInventory()->setItem(0, Item::get(Item::IRON_SWORD, 0, 1));
                                             $player->getArmorInventory()-setHelemet(Item::get(Item::DIAMOND_HELMET));
                                             $player->getArmorInventory()-setChestplate(Item::get(Item::DIAMOND_CHESTPLATE));
                                             $player->getArmorInventory()-setLeggings(Item::get(Item::DIAMOND_LEGGINGS));
                                             $player->getArmorInventory()-setHelemet(Item::get(Item::DIAMOND_BOOTS));
									       $pl->setGamemode(2);
										   $player = $pl;
										   $level = $player->getLevel();
										   $player->teleport(new Position($player->x, $player->y - 4, $player->z, $level));
        $block = $player->getPlayer()->getLevel()->getBlock($player->getPlayer()->floor()->subtract(0, 1));
        if ($block->getId() == 20){
		$pos11 = $player->getPosition()->add(-1, 0, 1);
        $pos12 = $player->getPosition()->add(-1, 1, 1);
		$pos13 = $player->getPosition()->add(1, 0, 1);
		$pos14 = $player->getPosition()->add(1, 1, 1);
		$pos15 = $player->getPosition()->add(1, 0, -1);
		$pos16 = $player->getPosition()->add(1, 1, -1);
		$pos17 = $player->getPosition()->add(-1, 0, -1);
		$pos18 = $player->getPosition()->add(-1, 1, -1);
		$pos19 = $player->getPosition()->add(-1, -1, -1);
		$pos20 = $player->getPosition()->add(1, -1, -1);
		$pos21 = $player->getPosition()->add(1, -1, 1);
        $pos22 = $player->getPosition()->add(-1, -1, 1);
		$pos23 = $player->getPosition()->add(-1, -2, -1);
		$pos24 = $player->getPosition()->add(1, -2, -1);
		$pos25 = $player->getPosition()->add(1, -2, 1);
        $pos26 = $player->getPosition()->add(-1, -2, 1);
        $pos27 = $player->getPosition()->add(1, -1, 0);
        $pos28 = $player->getPosition()->add(0, -1, 1);
        $pos29 = $player->getPosition()->add(-1, -1, 0);
        $pos31 = $player->getPosition()->add(0, -1, -1);
        $pos32 = $player->getPosition()->add(1, -2, 0);
        $pos33 = $player->getPosition()->add(0, -2, 1);
        $pos34 = $player->getPosition()->add(-1, -2, 0);
        $pos35 = $player->getPosition()->add(0, -2, -1);
		$pos1 = $player->getPosition()->add(0, -1, 0);
        $pos2 = $player->getPosition()->add(1, 0, 0);
        $pos3 = $player->getPosition()->add(-1, 0, 0); 
        $pos4 = $player->getPosition()->add(0, 0, 1);
        $pos5 = $player->getPosition()->add(0, 0, -1);
        $pos6 = $player->getPosition()->add(-1, 1, 0);
        $pos7 = $player->getPosition()->add(1, 1, 0);
        $pos8 = $player->getPosition()->add(0, 1, 1);
        $pos9 = $player->getPosition()->add(0, 1, -1);
        $pos10 = $player->getPosition()->add(0, -2, 0);
		
}
									}
								}
                                                                if($time == 514)
								{
                                                                    foreach($levelArena->getPlayers() as $pl)
                                                                    {
                                                                        $levelArena->addSound(new GhastShootSound($pl));
									$pl->sendTip(TE::GREEN.">> ".TE::RED." fight ".TE::GREEN."<< ".TE::RESET);
									$player = $pl;
                                                                    }
//-------------------------------------------------------------------------------------------
$player = $pl;

if(strpos($player->getNameTag(), "§cred>") !== false) {
}
if(strpos($player->getNameTag(), "§bblue>") !== false) {
}
//--------------------------------------------------------------------------------------------
                                                                    $tiles = $levelArena->getTiles();
                                                                    foreach ($tiles as $tile) {
                                                                        if ($tile instanceof Sign) {
                                                                            $text = $tile->getText();
                                                                            if (strtolower($text[0]) == "beast") {
                                                                                $levelArena->setBlock($tile->add(0, 2, 0), new Air());
                                                                                $levelArena->setBlock($tile->add(0, 3, 0), new Air());
                                                                            }
                                                                        }
                                                                    }
								}
                                                                if($time <= 0)
                                                                {
                                                                    foreach($levelArena->getPlayers() as $pl)
                                                                    {
                                                                        $pl->setGamemode(2);
                                                                            $pl->getInventory()->clearAll();
                                                                  		    $pl->getArmorInventory()->clearAll();
                                                                            $pl->removeAllEffects();
                                                                            $pl->setFood(20);
                                                                            $pl->setHealth(20);
                                                                            $pl->setNameTag($pl->getName());
                                                                            $config->set($arena . "starting", 0);
                                                                            $config->save();
                                                                            $api = $this->plugin->score;
                                                                    	    $api->remove($pl);
                                                                    }
                                                                    $winner = TE::YELLOW."WC>".TE::GOLD." CONGRATS! To the blues!  ".TE::GOLD."For wining in".TE::GOLD." arena ";
																	Server::getInstance()->broadcastMessage($winner);
                                                                    $time = 515;
                                                                    $this->getResetmap()->reload($arena);
                                                                }
								$config->set($arena . "PlayTime", $time);
							}
						}
						else
						{
                                                    foreach($levelArena->getPlayers() as $pl)
                                                    {
                                                        $pl->sendTip(TE::LIGHT_PURPLE . "Missing one more player" .TE::RESET);
                                                    }
                                                    $config->set($arena . "PlayTime", 515);
                                                    $config->set($arena . "StartTime", 30);
                                                    $config->set($arena . "EndTime", 6);
						}
					}
				}
			}
		}
		$config->save();
	}
    public function setred(Player $player){
		$pos1 = $player->getPosition()->add(0, -1, 0);
        $player->getLevel()->setBlock($pos1, Block::get(Block::REDSTONE_BLOCK));
        $pos2 = $player->getPosition()->add(1, 0, 0);
        $player->getLevel()->setBlock($pos2, Block::get(Block::REDSTONE_BLOCK));
        $pos3 = $player->getPosition()->add(-1, 0, 0);
        $player->getLevel()->setBlock($pos3, Block::get(Block::REDSTONE_BLOCK));
        $pos4 = $player->getPosition()->add(0, 0, 1);
        $player->getLevel()->setBlock($pos4, Block::get(Block::REDSTONE_BLOCK));
        $pos5 = $player->getPosition()->add(0, 0, -1);
        $player->getLevel()->setBlock($pos5, Block::get(Block::REDSTONE_BLOCK));
        $pos6 = $player->getPosition()->add(-1, 1, 0);
        $player->getLevel()->setBlock($pos6, Block::get(Block::AIR));
        $pos7 = $player->getPosition()->add(1, 1, 0);
        $player->getLevel()->setBlock($pos7, Block::get(Block::AIR));
        $pos8 = $player->getPosition()->add(0, 1, 1);
        $player->getLevel()->setBlock($pos8, Block::get(Block::AIR));
        $pos9 = $player->getPosition()->add(0, 1, -1);
        $player->getLevel()->setBlock($pos9, Block::get(Block::AIR));
        $pos10 = $player->getPosition()->add(0, 2, 0);
    }

    public function setblue(Player $player){
        $pos1 = $player->getPosition()->add(0, -1, 0);
        $player->getLevel()->setBlock($pos1, Block::get(Block::DIAMOND_BLOCK));
        $pos2 = $player->getPosition()->add(1, 0, 0);
        $player->getLevel()->setBlock($pos2, Block::get(Block::DIAMOND_BLOCK));
        $pos3 = $player->getPosition()->add(-1, 0, 0);
        $player->getLevel()->setBlock($pos3, Block::get(Block::DIAMOND_BLOCK));
        $pos4 = $player->getPosition()->add(0, 0, 1);
        $player->getLevel()->setBlock($pos4, Block::get(Block::DIAMOND_BLOCK));
        $pos5 = $player->getPosition()->add(0, 0, -1);
        $player->getLevel()->setBlock($pos5, Block::get(Block::DIAMOND_BLOCK));
        $pos6 = $player->getPosition()->add(-1, 1, 0);
        $player->getLevel()->setBlock($pos6, Block::get(Block::AIR));
        $pos7 = $player->getPosition()->add(1, 1, 0);
        $player->getLevel()->setBlock($pos7, Block::get(Block::AIR));
        $pos8 = $player->getPosition()->add(0, 1, 1);
        $player->getLevel()->setBlock($pos8, Block::get(Block::AIR));
        $pos9 = $player->getPosition()->add(0, 1, -1);
        $player->getLevel()->setBlock($pos9, Block::get(Block::AIR));
        $pos10 = $player->getPosition()->add(0, 2, 0);
        $player->getLevel()->setBlock($pos10, Block::get(Block::DIAMOND_BLOCK));
    }

    public function removeCage(Player $player){
        $pos1 = $player->getPosition()->add(0, -1, 0);
        $player->getLevel()->setBlock($pos1, Block::get(Block::AIR));
        $pos2 = $player->getPosition()->add(1, -1, 0);
        $player->getLevel()->setBlock($pos2, Block::get(Block::AIR));
        $pos3 = $player->getPosition()->add(-1, -1, 0);
        $player->getLevel()->setBlock($pos3, Block::get(Block::AIR));
        $pos4 = $player->getPosition()->add(0, -1, 1);
        $player->getLevel()->setBlock($pos4, Block::get(Block::AIR));
        $pos5 = $player->getPosition()->add(0, -1, -1);
        $player->getLevel()->setBlock($pos5, Block::get(Block::AIR));
		$pos6 = $player->getPosition()->add(1, -1, -1);
        $player->getLevel()->setBlock($pos5, Block::get(Block::AIR));
        $pos7 = $player->getPosition()->add(1, -1, 1);
        $player->getLevel()->setBlock($pos5, Block::get(Block::AIR));
        $pos8 = $player->getPosition()->add(-1, -1, -1);
        $player->getLevel()->setBlock($pos5, Block::get(Block::AIR));
        $pos9 = $player->getPosition()->add(-1, -1, 1);
        $player->getLevel()->setBlock($pos5, Block::get(Block::AIR));
    }
}