<?php

/*
 *   ____                 _                         ____    _                     
 *  / ___|  _   _   ___  | |_    ___    _ __ ___   / ___|  | |__     ___    _ __  
 * | |     | | | | / __| | __|  / _ \  | '_ ` _ \  \___ \  | '_ \   / _ \  | '_ \ 
 * | |___  | |_| | \__ \ | |_  | (_) | | | | | | |  ___) | | | | | | (_) | | |_) |
 *  \____|  \__,_| |___/  \__|  \___/  |_| |_| |_| |____/  |_| |_|  \___/  | .__/ 
 *                                                                         |_|    
 * MADE BY SSUEE(DAYKOALA) © 2018
 * TWITTER: @DayKoala
 *
 */

namespace CustomShop;

use pocketmine\plugin\PluginBase;

use pocketmine\event\Listener;
use pocketmine\event\block\SignChangeEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerKickEvent;

use pocketmine\utils\Config;

use pocketmine\item\Item;

use pocketmine\tile\Sign;

use pocketmine\Player;

use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\NBT;

use onebone\economyapi\EconomyAPI;

class System extends PluginBase implements Listener{
    
    public $who;
    public $tap;
    
    private $sign = [];
    private $config;
    
    const SIGN_SHOP = 0;
    const SIGN_SELL = 1;
    const SIGN_CUSTOM = 2;
    
    public function onLoad(){
        $this->who = [];
        $this->tap = [];
    }
    
    public function onEnable(){
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        
        @mkdir($this->getDataFolder());
        $this->saveResource("Config.yml");
        $this->config = (new Config($this->getDataFolder() ."Config.yml", Config::YAML))->getAll();
        $this->sign = (new Config($this->getDataFolder() ."Signs.json", Config::JSON))->getAll();
    }
    
    public function onDisable(){
        if($this->who){
           foreach($this->who as $name => $about){
              $player = $this->getServer()->getPlayerExact($name);
              if(!$player){
                 continue;
              }
              $this->sendCustomSignShop($player, $about[0], false);
           }
        }
        $signs = new Config($this->getDataFolder() ."Signs.json", Config::JSON);
        $signs->setAll($this->sign);
        $signs->save();
    }
    
    public function onSignChange(SignChangeEvent $event){
        $player = $event->getPlayer();
        if(!$player->hasPermission("customshop.create")){
           return false;
        }
        $line = $event->getLines();
        switch(strtolower($line[0])){
           case 'shop':
           case 'sell':
              if(!is_numeric($line[1]) or !is_numeric($line[3])){
                 $player->sendMessage("Os argumentos não são numericos");
                 return false;
              }
              $item = Item::fromString($line[2]);
              if($item === false){
                 $player->sendMessage("Item não existe");
                 return false;
              }
              $config = $this->config[strtolower($line[0])];
              if($config == null){
                 $player->sendMessage("Erro em sua configuração");
                 return false;
              }
              $block = $event->getBlock();
              $this->sign[$block->x .":". $block->y .":". $block->z .":". $block->getLevel()->getFolderName()] = [
                          "x" => $block->x, 
                          "y" => $block->y, 
                          "z" => $block->z, 
                          "level" => $block->getLevel()->getFolderName(),
                          "type" => strtolower($line[0]) == "shop" ? self::SIGN_SHOP : self::SIGN_SELL,
                          "price" => $line[1], 
                          "itemId" => $item->getId(), 
                          "itemMeta" => $item->getDamage(), 
                          "itemCount" => $line[3],
                          "itemName" => $item->getName()];
              $player->sendMessage("Você criou uma placa de ". strtolower($line[0]) ." com sucesso");
                
              $new_line = [];
              for($index = 0; $index < count($line); $index++){
                 $new_line[] = $line[$index];
              }
                
              $event->setLine(0, str_replace(['%1', '%2', '%3'], [$new_line[1], $item->getName(), $new_line[3]], $config[0]));
              $event->setLine(1, str_replace(['%1', '%2', '%3'], [$new_line[1], $item->getName(), $new_line[3]], $config[1]));
              $event->setLine(2, str_replace(['%1', '%2', '%3'], [$new_line[1], $item->getName(), $new_line[3]], $config[2]));
              $event->setLine(3, str_replace(['%1', '%2', '%3'], [$new_line[1], $item->getName(), $new_line[3]], $config[3]));
              return true;
           break;
           case 'shop/sell':
              $list = [];
              foreach([$line[1], $line[3]] as $text){
                 $text = explode("/", $text);
                 if(!count($text) === 2){
                    $player->sendMessage("Argumento invalido");
                    return false;
                 }
                 $list[] = [$text[0], $text[1]];
              }
              if(!count($list) === 2){
                 $player->sendMessage("Argumentos invalidos");
                 return false;
              }
              if(!is_numeric($list[0][0]) or !is_numeric($list[0][1]) or !is_numeric($list[1][0]) or !is_numeric($list[1][1])){
                 $player->sendMessage("Os argumentos não são numericos");
                 return false;
              }
              $item = Item::fromString($line[2]);
              if($item === false){
                 $player->sendMessage("Item não existe");
                 return false;
              }
              $config = $this->config['shopC'];
              if($config == null){
                 $player->sendMessage("Erro em sua configuração");
                 return false;
              }
              $block = $event->getBlock();
              $this->sign[$block->x .":". $block->y .":". $block->z .":". $block->getLevel()->getFolderName()] = [
                          "x" => $block->x,
                          "y" => $block->y,
                          "z" => $block->z,
                          "level" => $block->getLevel()->getFolderName(),
                          "type" => self::SIGN_CUSTOM,
                          "shopPrice" => $list[0][0],
                          "sellPrice" => $list[0][1],
                          "itemId" => $item->getId(),
                          "itemMeta" => $item->getDamage(),
                          "shopCount" => $list[1][0],
                          "sellCount" => $list[1][1],
                          "itemName" => $item->getName()];
              $player->sendMessage("Você criou uma placa custom com sucesso");
             
              $event->setLine(0, str_replace(['%1', '%2', '%3'], [$list[0][0], $item->getName(), $list[1][0]], $config[0]));
              $event->setLine(1, str_replace(['%1', '%2', '%3'], [$list[0][0], $item->getName(), $list[1][0]], $config[1]));
              $event->setLine(2, str_replace(['%1', '%2', '%3'], [$list[0][0], $item->getName(), $list[1][0]], $config[2]));
              $event->setLine(3, str_replace(['%1', '%2', '%3'], [$list[0][0], $item->getName(), $list[1][0]], $config[3]));
              return true;
           break;
        }
    }

    public function onInteract(PlayerInteractEvent $event){
        $player = $event->getPlayer();
        if($event->getAction() !== PlayerInteractEvent::RIGHT_CLICK_BLOCK){
           return false;
        }
        $block = $event->getBlock();
        $position = $block->x .":". $block->y .":". $block->z .":". $block->getLevel()->getFolderName();
        if(!isset($this->sign[$position])){
           return false;
        }
        $data = $this->sign[$position];
        if($player->getGamemode() % 2 === 1){
           $player->sendMessage("Modo de jogo invalido");
           return false;
        }
        $hand = $event->getItem();
        switch($data['type']){
           case self::SIGN_SHOP:
              $item = Item::get($data['itemId'], $data['itemMeta'], $data['itemCount']);
              if($item == null){
                 $player->sendMessage($this->config['no-item']);
                 return false;
              }
              if(($money = EconomyAPI::getInstance()->myMoney($player)) < $data['price']){
                 $player->sendMessage($this->getMessage('no-money', [$money, $data['price'], ($data['price'] - $money), $item->getName()]));
                 return false;
              }
              if(!$player->getInventory()->canAddItem($item)){
                 $player->sendMessage($this->getMessage('inventory-full', [$item->getId(), $item->getDamage(), $item->getCount(), $item->getName()]));
                 return false;
              }
              if($this->config['double-tap']){
                 $now = microtime(true);
                 if(!isset($this->tap[strtolower($player->getName())]) or ($tap = $this->tap[strtolower($player->getName())])[0] !== $position or ($now - $tap[1]) >= 1.5){
                    $this->tap[strtolower($player->getName())] = [$position, $now, $hand->canBePlaced()];
                    $player->sendMessage($this->getMessage('tap-again-to-buy', [$item->getId(), $item->getDamage(), $item->getCount(), $item->getName(), $data['price']]));
                    return true;
                 }else{
                    unset($this->tap[strtolower($player->getName())]);
                 }
              }
              EconomyAPI::getInstance()->reduceMoney($player, $data['price']);
              $player->getInventory()->addItem($item);
              $player->sendMessage($this->getMessage('buyed', [$item->getId(), $item->getDamage(), $item->getCount(), $item->getName(), $data['price']]));
              if($hand->canBePlaced()){
                 $this->tap[strtolower($player->getName())] = true;
              }
              $event->setCancelled(true);
              return true;
           break;
           case self::SIGN_SELL:
              $item = Item::get($data['itemId'], $data['itemMeta'], $data['itemCount']);
              if($item == null){
                 $player->sendMessage($this->config['no-item']);
                 return false;
              }
              $count = 0;
              for($index = 0; $index <= $player->getInventory()->getSize(); $index++){
                 $item_index = $player->getInventory()->getItem($index);
                 if($item_index->hasCustomName()){
                    continue;
                 }
                 if($item->getId() == $item_index->getId() and $item->getDamage() == $item_index->getDamage()){
                    $count += $item_index->getCount();
                 }
              }
              if($count < $item->getCount()){
                 $player->sendMessage($this->getMessage('no-amount', [$item->getId(), $item->getDamage(), $item->getCount(), $item->getName(), $data['price']]));
                 return false;
              }
              if($item->getId() == $hand->getId() and $item->getDamage() == $hand->getDamage()){
                 $price = floor($count / $data['itemCount'] * $data['price']);
              }else{
                 $price = $data['price'];
              }
              if($this->config['double-tap']){
                 $now = microtime(true);
                 if(!isset($this->tap[strtolower($player->getName())]) or ($tap = $this->tap[strtolower($player->getName())])[0] !== $position or ($now - $tap[1]) >= 1.5){
                    $this->tap[strtolower($player->getName())] = [$position, $now, $hand->canBePlaced()];
                    if($item->getId() == $hand->getId() and $item->getDamage() == $hand->getDamage()){
                       $player->sendMessage($this->getMessage('tap-again-to-sell-all', [$item->getId(), $item->getDamage(), $count, $item->getName(), $price]));
                    }else{
                       $player->sendMessage($this->getMessage('tap-again-to-sell', [$item->getId(), $item->getDamage(), $item->getCount(), $item->getName(), $price]));
                    }
                    return true;
                 }else{
                    unset($this->tap[strtolower($player->getName())]);
                 }
              }
              EconomyAPI::getInstance()->addMoney($player, $price);
              if($item->getId() == $hand->getId() and $item->getDamage() == $hand->getDamage()){
                 $this->removeItem($player, $item, $count);
                 $player->sendMessage($this->getMessage('sold-all', [$item->getId(), $item->getDamage(), $count, $item->getName(), $price]));
              }else{
                 $this->removeItem($player, $item, $item->getCount());
                 $player->sendMessage($this->getMessage('sold', [$item->getId(), $item->getDamage(), $item->getCount(), $item->getName(), $price]));
              }
              if($hand->canBePlaced()){
                 $this->tap[strtolower($player->getName())] = true;
              }
              $event->setCancelled(true);
              return true;
           break;
           case self::SIGN_CUSTOM:
              if($player->isSneaking()){
                 $tile = $block->getLevel()->getTile($block);
                 if(isset($this->who[strtolower($player->getName())])){
                    $about = $this->who[strtolower($player->getName())];
                    $now = microtime(true);
                    if(!isset($this->tap[strtolower($player->getName())]) or ($tap = $this->tap[strtolower($player->getName())])[0] !== $position or ($now - $tap[1]) >= 1.5){
                       $message = $position !== $about[1] ? $this->config['tap-again-to-change-s'] : $this->config['tap-again-to-change-b'];
                       $this->tap[strtolower($player->getName())] = [$position, $now, $hand->canBePlaced()];
                       $player->sendMessage($message);
                       return true;
                    }else{
                       unset($this->tap[strtolower($player->getName())]);
                    }
                    if($position !== $about[1]){
                       $this->sendCustomSignShop($player, $about[0], false);
                       $this->who[strtolower($player->getName())] = [$tile, $position];
                       $this->sendCustomSignShop($player, $tile);
                    }else{
                       $this->sendCustomSignShop($player, $tile, false);
                       unset($this->who[strtolower($player->getName())]);
                    }
                    $message = $position !== $about[1] ? $this->config['to-sell'] : $this->config['to-buy'];
                    $player->sendMessage($message);
                    if($hand->canBePlaced()){
                       $this->tap[strtolower($player->getName())] = true;
                    }
                    $event->setCancelled(true);
                    return true;
                 }else{
                    $now = microtime(true);
                    if(!isset($this->tap[strtolower($player->getName())]) or ($tap = $this->tap[strtolower($player->getName())])[0] !== $position or ($now - $tap[1]) >= 1.5){
                       $this->tap[strtolower($player->getName())] = [$position, $now, $hand->canBePlaced()];
                       $player->sendMessage($this->config['tap-again-to-change-s']);
                       return true;
                    }else{
                       unset($this->tap[strtolower($player->getName())]);
                    }
                    $this->sendCustomSignShop($player, $tile);
                    $this->who[strtolower($player->getName())] = [$tile, $position];
                    $player->sendMessage($this->config['to-sell']);
                    if($hand->canBePlaced()){
                       $this->tap[strtolower($player->getName())] = true;
                    }
                    $event->setCancelled(true);
                    return true;
                 }
              }
              if(!isset($this->who[strtolower($player->getName())]) or $this->who[strtolower($player->getName())][1] !== $position){
                 $item = Item::get($data['itemId'], $data['itemMeta'], $data['shopCount']);
                 if($item == null){
                    $player->sendMessage($this->config['no-item']);
                    return false;
                 }
                 if(($money = EconomyAPI::getInstance()->myMoney($player)) < $data['shopPrice']){
                    $player->sendMessage($this->getMessage('no-money-c', [$money, $data['shopPrice'], ($data['shopPrice'] - $money), $item->getName()]));
                    return false;
                 }
                 if(!$player->getInventory()->canAddItem($item)){
                    $player->sendMessage($this->getMessage('inventory-full-c', [$item->getId(), $item->getDamage(), $item->getCount(), $item->getName()]));
                    return false;
                 }
                 if($this->config['double-tap']){
                    $now = microtime(true);
                    if(!isset($this->tap[strtolower($player->getName())]) or $this->tap[strtolower($player->getName())][0] !== $position or 
                       $now - $this->tap[strtolower($player->getName())][1] >= 1.5){
                       $this->tap[strtolower($player->getName())] = [$position, $now, $hand->canBePlaced()];
                       $player->sendMessage($this->getMessage('tap-again-to-buy-c', [$item->getId(), $item->getDamage(), $item->getCount(), $item->getName(), $data['shopPrice']]));
                       return true;
                    }else{
                       unset($this->tap[strtolower($player->getName())]);
                    }
                 }
                 EconomyAPI::getInstance()->reduceMoney($player, $data['shopPrice']);
                 $player->getInventory()->addItem($item);
                 $player->sendMessage($this->getMessage('buyed-c', [$item->getId(), $item->getDamage(), $item->getCount(), $item->getName(), $data['shopPrice']]));
                 if($hand->canBePlaced()){
                    $this->tap[strtolower($player->getName())] = true;
                 }
                 $event->setCancelled(true);
                 return true;
              }
              if($this->who[strtolower($player->getName())][1] == $position){
                 $item = Item::get($data['itemId'], $data['itemMeta'], $data['sellCount']);
                 if($item == null){
                    $player->sendMessage($this->config['no-item']);
                    return false;
                 }
                 $count = 0;
                 for($index = 0; $index <= $player->getInventory()->getSize(); $index++){
                    $item_index = $player->getInventory()->getItem($index);
                    if($item_index->hasCustomName()){
                       continue;
                    }
                    if($item->getId() == $item_index->getId() and $item->getDamage() == $item_index->getDamage()){
                       $count += $item_index->getCount();
                    }
                 }
                 if($count < $item->getCount()){
                    $player->sendMessage($this->getMessage('no-amount-c', [$item->getId(), $item->getDamage(), $item->getCount(), $item->getName(), $data['sellPrice']]));
                    return false;
                 }
                 if($item->getId() == $hand->getId() and $item->getDamage() == $hand->getDamage()){
                    $price = floor($count / $data['sellCount'] * $data['sellPrice']);
                 }else{
                    $price = $data['sellPrice'];
                 }
                 if($this->config['double-tap']){
                    $now = microtime(true);
                    if(!isset($this->tap[strtolower($player->getName())]) or ($tap = $this->tap[strtolower($player->getName())])[0] !== $position or ($now - $tap[1]) >= 1.5){
                       $this->tap[strtolower($player->getName())] = [$position, $now, $hand->canBePlaced()];
                       if($item->getId() == $hand->getId() and $item->getDamage() == $hand->getDamage()){
                          $player->sendMessage($this->getMessage('tap-again-to-sell-all-c', [$item->getId(), $item->getDamage(), $count, $item->getName(), $price]));
                       }else{
                          $player->sendMessage($this->getMessage('tap-again-to-sell-c', [$item->getId(), $item->getDamage(), $item->getCount(), $item->getName(), $price]));
                       }
                       return true;
                    }else{
                       unset($this->tap[strtolower($player->getName())]);
                    }
                 }
                 EconomyAPI::getInstance()->addMoney($player, $price);
                 if($item->getId() == $hand->getId() and $item->getDamage() == $hand->getDamage()){
                    $this->removeItem($player, $item, $count);
                    $player->sendMessage($this->getMessage('sold-all-c', [$item->getId(), $item->getDamage(), $count, $item->getName(), $price]));
                 }else{
                    $this->removeItem($player, $item, $item->getCount());
                    $player->sendMessage($this->getMessage('sold-c', [$item->getId(), $item->getDamage(), $item->getCount(), $item->getName(), $price]));
                 }
                 if($hand->canBePlaced()){
                    $this->tap[strtolower($player->getName())] = true;
                 }
                 $event->setCancelled(true);
                 return true;
              }
              $this->getLogger()->info("Um erro ocorreu");
              return false;
           break;
           default:
              unset($this->sign[$position]);
              return false;
           break;
        }
    }
    
    public function onBreak(BlockBreakEvent $event){
        $block = $event->getBlock();
        if($event->isCancelled()){
           return false;
        }
        $position = $block->x .":". $block->y .":". $block->z .":". $block->getLevel()->getFolderName();
        if(!isset($this->sign[$position])){
           return false;
        }
        $player = $event->getPlayer();
        if(!$player->hasPermission('customshop.remove')){
           $event->setCancelled(true);
           return false;
        }
        if($this->sign[$position]['type'] == self::SIGN_CUSTOM){
           if($this->who){
              foreach($this->who as $name => $about){
                 if($position !== $about[1]){
                    continue;
                 }
                 unset($this->who[$name]);
              }
           }
        }
        unset($this->sign[$position]);
        $player->sendMessage("Placa destruida");
        return true;
    }
    
    public function onPlace(BlockPlaceEvent $event){
        $player = $event->getPlayer();
        if(!isset($this->tap[strtolower($player->getName())])){
           return false;
        }
        if(is_array($this->tap[strtolower($player->getName())])){
           if(!$this->tap[strtolower($player->getName())][2]){
              return false;
           }
           $this->tap[strtolower($player->getName())][2] = false;       
        }else{
           unset($this->tap[strtolower($player->getName())]);
        }
        $event->setCancelled(true);
        return true;
    }
    
    public function onQuit(PlayerQuitEvent $event){
        $player = $event->getPlayer();
        if(!isset($this->who[strtolower($player->getName())])){
           return false;
        }
        $old = $this->who[strtolower($player->getName())];
        $this->sendCustomSignShop($player, $old[0], false);
        unset($this->who[strtolower($player->getName())]);
        return true;
    }
    
    public function onKick(PlayerKickEvent $event){
        $player = $event->getPlayer();
        if(!isset($this->who[strtolower($player->getName())])){
           return false;
        }
        $old = $this->who[strtolower($player->getName())];
        $this->sendCustomSignShop($player, $old[0], false);
        unset($this->who[strtolower($player->getName())]);
        return true;
    }
    
    public function removeItem(Player $player, Item $item, $count = 0){
        for($index = 0; $index <= $player->getInventory()->getSize(); $index++){
           $item_index = $player->getInventory()->getItem($index);
           if($item_index->hasCustomName()){
              continue;
           }
           if($item->getId() == $item_index->getId() and $item->getDamage() == $item_index->getDamage()){
              if($count == $item_index->getCount()){
                 $player->getInventory()->setItem($index, Item::get(0));
                 break;
              }
              if($count < $item_index->getCount()){
                 $item_index->setCount($item_index->getCount() - $count);
                 $player->getInventory()->setItem($index, $item_index);
                 break;
              }
              if($count > $item_index->getCount()){
                 $count -= $item_index->getCount();
                 $player->getInventory()->setItem($index, Item::get(0));
              }
           }
        }
        return true;
    }
    
    private function sendCustomSignShop(Player $player, Sign $tile, $type = true){
        $about = $this->sign[$tile->x .":". $tile->y .":". $tile->z .":". $tile->getLevel()->getFolderName()];
        if($about == null){
           return false;
        }
        $compound = $tile->getSpawnCompound();
        switch($type){
           case true:
              $config = $this->config['sellC'];
              $compound->Text1 = new StringTag("Text1", str_replace(['%1', '%2', '%3'], [$about['sellPrice'], $about['itemName'], $about['sellCount']], $config[0]));
              $compound->Text2 = new StringTag("Text2", str_replace(['%1', '%2', '%3'], [$about['sellPrice'], $about['itemName'], $about['sellCount']], $config[1]));
              $compound->Text3 = new StringTag("Text3", str_replace(['%1', '%2', '%3'], [$about['sellPrice'], $about['itemName'], $about['sellCount']], $config[2]));
              $compound->Text4 = new StringTag("Text4", str_replace(['%1', '%2', '%3'], [$about['sellPrice'], $about['itemName'], $about['sellCount']], $config[3]));
           break;
           case false:
              $config = $this->config['shopC'];
              $compound->Text1 = new StringTag("Text1", str_replace(['%1', '%2', '%3'], [$about['shopPrice'], $about['itemName'], $about['shopCount']], $config[0]));
              $compound->Text2 = new StringTag("Text2", str_replace(['%1', '%2', '%3'], [$about['shopPrice'], $about['itemName'], $about['shopCount']], $config[1]));
              $compound->Text3 = new StringTag("Text3", str_replace(['%1', '%2', '%3'], [$about['shopPrice'], $about['itemName'], $about['shopCount']], $config[2]));
              $compound->Text4 = new StringTag("Text4", str_replace(['%1', '%2', '%3'], [$about['shopPrice'], $about['itemName'], $about['shopCount']], $config[3]));
           break;
           default:
              $this->getLogger()->info("Um erro ocorreu");
              return false;
           break;
        }
        $nbt = new NBT(NBT::LITTLE_ENDIAN);
        $nbt->setData($compound);
        
        if(!version_compare(\pocketmine\API_VERSION, "3.0.0") >= 0){
           $pk = new \pocketmine\network\protocol\BlockEntityDataPacket();
        }else{
           $pk = new \pocketmine\network\mcpe\protocol\BlockEntityDataPacket();
        }
        
        $pk->x = $tile->x;
        $pk->y = $tile->y;
        $pk->z = $tile->z;
        $pk->namedtag = $nbt->write();
        
        $player->dataPacket($pk);
        return true;
    }
    
    public function getMessage($type, $keys){
        $message = $this->config[$type];
        if($message == null){
           return '';
        }
        $i = 1;
        foreach($keys as $key){
           $message = str_replace("%". $i, $key, $message);
           $i++;
        }
        return $message;
    }
}