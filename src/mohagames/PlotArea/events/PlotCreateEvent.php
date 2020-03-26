<?php

namespace mohagames\PlotArea\events;

use mohagames\PlotArea\utils\Plot;
use pocketmine\Player;

class PlotCreateEvent extends PlotEvent
{


    private $plot;
    private $player;


    /**
     * PlotCreateEvent constructor.
     * @param Plot $plot Plot
     * @param Player|null $player Executor
     */

    public function __construct(Plot $plot, ?Player $player)
    {
        $this->plot = $plot;
        $this->player = $player;
    }

    public function getPlot(): Plot
    {
        return $this->plot;
    }

    public function getPlayer(): ?Player
    {
        return $this->player;
    }
}