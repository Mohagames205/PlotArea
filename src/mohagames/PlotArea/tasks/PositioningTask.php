<?php

/*
  _____   _         _
 |  __ \ | |       | |      /\
 | |__) || |  ___  | |_    /  \    _ __  ___   __ _
 |  ___/ | | / _ \ | __|  / /\ \  | '__|/ _ \ / _` |
 | |     | || (_) || |_  / ____ \ | |  |  __/| (_| |
 |_|     |_| \___/  \__|/_/    \_\|_|   \___| \__,_|
 */

namespace mohagames\PlotArea\tasks;


use mohagames\PlotArea\events\PlotEnterEvent;
use mohagames\PlotArea\Main;
use mohagames\PlotArea\utils\Plot;
use pocketmine\scheduler\Task;
use pocketmine\utils\TextFormat;

class PositioningTask extends Task{

    public $main;
    public $isInPlot = array();

    public function __construct(){
        $this->main = Main::getInstance();
    }

    public function onRun(int $currentTick){

        foreach($this->main->getServer()->getOnlinePlayers() as $player){
            $plot = Plot::get($player);

            if($plot !== null){
                if(!isset($this->isInPlot[$player->getName()])){
                    $this->isInPlot[$player->getName()] = null;
                }
                $status = $this->isInPlot[$player->getName()];
                if($plot->getId() !== $status ){
                    $ev = new PlotEnterEvent($player, $plot);
                    $ev->call();
                    $text = $plot->getOwner() ? $plot->getOwner() : "§aGeen eigenaar";
                    $player->sendPopup(TextFormat::RED . $plot->getName() . "\n" . TextFormat::BLUE . $text);
                    $this->isInPlot[$player->getName()] = $plot->getId();
                }
            }
            else{
                $this->isInPlot[$player->getName()] = null;
            }
        }
    }


}