<?php

/*
  _____   _         _
 |  __ \ | |       | |      /\
 | |__) || |  ___  | |_    /  \    _ __  ___   __ _
 |  ___/ | | / _ \ | __|  / /\ \  | '__|/ _ \ / _` |
 | |     | || (_) || |_  / ____ \ | |  |  __/| (_| |
 |_|     |_| \___/  \__|/_/    \_\|_|   \___| \__,_|
 */

namespace mohagames\PlotArea\events;

use mohagames\PlotArea\utils\Plot;
use pocketmine\event\Cancellable;
use pocketmine\Player;

class PlotSetGroupnameEvent extends PlotEvent implements Cancellable
{

    private $plot;
    private $grouname;
    private $player;

    public function __construct(Plot $plot, ?string $grouname, ?Player $executor = null)
    {
        $this->grouname = $grouname;
        $this->plot = $plot;
        $this->player = $executor;
    }

    public function getPlayer(): ?Player
    {
        return $this->player;
    }

    public function getPlot(): Plot
    {
        return $this->plot;
    }

    public function getGroupName(): ?string
    {
        return $this->grouname;
    }


}
