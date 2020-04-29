<?php

/**
 * _____   _         _
 * |  __ \ | |       | |      /\
 * | |__) || |  ___  | |_    /  \    _ __  ___   __ _
 * |  ___/ | | / _ \ | __|  / /\ \  | '__|/ _ \ / _` |
 * | |     | || (_) || |_  / ____ \ | |  |  __/| (_| |
 * |_|     |_| \___/  \__|/_/    \_\|_|   \___| \__,_|
 * @author Mohamed El Yousfi
 */

namespace mohagames\PlotArea\utils;

use mohagames\PlotArea\events\PlotAddMemberEvent;
use mohagames\PlotArea\events\PlotCreateEvent;
use mohagames\PlotArea\events\PlotDeleteEvent;
use mohagames\PlotArea\events\PlotRemoveMemberEvent;
use mohagames\PlotArea\events\PlotResetEvent;
use mohagames\PlotArea\events\PlotSetGroupnameEvent;
use mohagames\PlotArea\events\PlotSetOwnerEvent;
use mohagames\PlotArea\Main;
use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\Player;

/*
 *
 * TODO: Alle event methods fixen >_<
 */

class Plot extends PermissionManager
{
    protected $name;
    protected $owner;
    protected $level;
    protected $location;
    protected $members;
    protected $plot_id;
    public $db;
    public $main;

    /**
     * Plot constructor
     *
     * Do not create a new Plot instance! Creating one will result in an error.
     * To create a new plot please use Plot::save()
     *
     * @param Level $level
     * @param array $location
     * @param array $members
     *
     * To get an existing plot please use one of the supported Get methods
     * @see Plot::save()
     *
     */
    private function __construct($name, $owner, Level $level, array $location, array $members = array())
    {
        $this->name = $name;
        $this->level = $level;
        $this->owner = $owner;
        $this->location = new Location($location);
        $this->members = $members;
        $this->db = Main::getInstance()->db;
        $this->main = Main::getInstance();
        $this->plot_id = $this->getId();
        parent::__construct($this);
    }

    /**
     * This method is used for creating new Plots in the database.
     * @param $name
     * @param Level $level
     * @param array $location
     * @param string $owner
     * @param array $members
     * @param Player|null $executor
     * @return Plot
     */
    public static function save($name, Level $level, array $location, string $owner = null, array $members = array(), Player $executor = null)
    {
        if (is_null(Plot::getPlotByName($name))) {
            $db = Main::getInstance()->db;
            $members_ser = serialize($members);
            $location_ser = serialize($location);
            $max_members = Main::getInstance()->getConfig()->get("max_members");
            $levelname = $level->getFolderName();
            $stmt = $db->prepare("INSERT INTO plots (plot_name, plot_owner, plot_members, plot_location, plot_world, max_members) values(:plot_name, :plot_owner, :plot_members, :plot_location, :plot_world, :max_members)");
            $stmt->bindParam("plot_name", $name, SQLITE3_TEXT);
            $stmt->bindParam("plot_owner", $owner, SQLITE3_TEXT);
            $stmt->bindParam("plot_members", $members_ser, SQLITE3_TEXT);
            $stmt->bindParam("plot_location", $location_ser, SQLITE3_TEXT);
            $stmt->bindParam("plot_world", $levelname, SQLITE3_TEXT);
            $stmt->bindParam("max_members", $max_members, SQLITE3_INTEGER);
            $stmt->execute();
            $stmt->close();

            $plot = new Plot($name, $owner, $level, $location, $members);

            $ev = new PlotCreateEvent($plot, $executor);
            $ev->call();

            return $plot;


        }
    }

    /**
     * This method will search for a Plot at the given Position.
     *
     * When a plot is grouped this method will return the Master Plot by default
     * If you don't want to get the Master Plot then set the $grouping parameter to false.
     *
     * @param Position $position
     * @param bool $grouping
     * @return Plot|null
     */
    public static function get(Position $position, bool $grouping = true) : ?Plot {
        $main = Main::getInstance();
        $result = $main->db->query("SELECT * FROM plots");
        while ($row = $result->fetchArray()) {
            $plot_level = null;
            if ($main->getServer()->isLevelGenerated($row["plot_world"])) {
                if ($main->getServer()->isLevelLoaded($row["plot_world"])) {
                    $plot_level = $main->getServer()->getLevelByName($row["plot_world"]);
                } else {
                    if ($main->getServer()->loadLevel($row["plot_world"])) {
                        $plot_level = $main->getServer()->getLevelByName($row["plot_world"]);
                    }
                }
            }
            if ($plot_level !== null) {
                $plot = new Plot($row["plot_name"], $row["plot_owner"], $plot_level, unserialize($row["plot_location"]), unserialize($row["plot_members"]));

                $location = $plot->getLocation();
                $location = $location->calculateCoords();

                $pos1 = $location->getPos1();
                $pos2 = $location->getPos2();

                $p_x = $position->getFloorX();
                $p_y = $position->getFloorY();
                $p_z = $position->getFloorZ();
                $level = $position->getLevel();

                $res = $pos1->getY() == $pos2->getY();

                if (($p_x <= $pos1->getX() && $p_x >= $pos2->getX() && $p_z <= $pos1->getZ() && $p_z >= $pos2->getZ()) && (($p_y >= $pos1->getY() && $p_y < $pos2->getY()) || $res) && $plot->getLevel()->getFolderName() == $level->getFolderName()) {
                    $found_plot = $plot;
                    if ($plot->isGrouped() && $grouping) {
                        $found_plot = $plot->getGroup()->getMasterPlot();
                    }
                    break;
                }
            }
        }

        return isset($found_plot) ? $found_plot : null;

    }


    /**
     * This returns the name of the current plot.
     *
     * @return mixed
     */
    public function getName(){
        $plot_id = $this->getId();
        $stmt = $this->db->prepare("SELECT plot_name FROM plots WHERE plot_id = :plot_id");
        $stmt->bindParam("plot_id", $plot_id, SQLITE3_INTEGER);
        $res = $stmt->execute();
        while($row = $res->fetchArray()){
            $name = $row["plot_name"];
        }
        $stmt->close();

        return $name;
    }

    /**
     * This returns the name of the current plot owner
     *
     * @return mixed
     */
    public function getOwner(){
        $plot_id = $this->getId();

        $stmt = $this->db->prepare("SELECT plot_owner FROM plots WHERE plot_id = :plot_id");
        $stmt->bindParam("plot_id", $plot_id, SQLITE3_INTEGER);
        $res = $stmt->execute();
        while($row = $res->fetchArray()){
            $owner = $row["plot_owner"];
        }
        $stmt->close();
        return $owner;
    }

    /**
     * This method checks if the given player is the owner of the Plot.
     *
     * @param string $player
     * @return bool
     */
    public function isOwner(string $player) : bool{
        $owner = $this->getOwner();

        return strtolower($player) == $owner;
    }


    /**
     * This method returns the Level the plot is in.
     *
     * @return Level
     */
    public function getLevel(){
        return $this->level;
    }


    /**
     * This returns an instance of the Location class and contains all the location info from this Plot.
     *
     * @return Location
     */
    public function getLocation(){
        return $this->location;
    }

    /**
     * This method returns an array of all the Plot Members
     *
     * @return mixed
     */
    public function getMembers(){
        $plot_id = $this->getId();

        $stmt = $this->db->prepare("SELECT plot_members FROM plots WHERE plot_id = :plot_id");
        $stmt->bindParam("plot_id", $plot_id, SQLITE3_INTEGER);
        $res = $stmt->execute();
        while($row = $res->fetchArray()){
            $members = unserialize($row["plot_members"]);
        }
        $stmt->close();
        return $members;
    }

    /**
     * This method checks if the given player is an member of the Plot
     *
     * @param $member
     * @return bool
     */
    public function isMember($member) : bool{
        $members = $this->getMembers();

        $member = strtolower($member);
        return in_array($member, $members);
    }

    /**
     * This method returns a string of all the members of the plot
     *
     * @return bool|string
     */
    public function getMembersList(){
        $members = $this->getMembers();
        if (count($members) !== 0) {
            $leden = "";
            foreach ($members as $lid) {
                $leden .= "$lid, ";
            }
            $leden = rtrim($leden, ', ');
            return $leden;
        }
        return false;
    }

    /**
     * This method returns the size of the Plot
     *
     * @return array
     */
    public function getSize() : array {
        $loc = $this->getLocation();
        $loc = $loc->calculateCoords();
        $pos1 = $loc->getPos1();
        $pos2 = $loc->getPos2();

        $x_size = $pos1->getX() - $pos2->getX() + 1;
        $z_size = $pos1->getZ() - $pos2->getZ() + 1;

        return array($x_size, $z_size);

    }

    /**
     * This method returns the ID of the Plot, this method should only be used internally.
     *
     * @return int
     */
    public function getId() : int{
        $worldname = $this->level->getFolderName();
        $conn = $this->db->prepare("SELECT plot_id FROM plots WHERE plot_location = :location AND plot_world = :plot_world");
        $loc = serialize($this->getLocation()->getArrayedLocation());
        $conn->bindParam("location", $loc, SQLITE3_TEXT);
        $conn->bindParam("plot_world", $worldname, SQLITE3_TEXT);
        $result = $conn->execute();
        while($row = $result->fetchArray()){
            return $row["plot_id"];
            break;
        }
        $conn->close();
    }

    /**
     * This method sets a new owner in the Plot
     *
     * @param null $owner
     * @param Player|null $executor The Player who executed the command
     * @return bool
     * @throws \ReflectionException
     */
    public function setOwner($owner = null, Player $executor = null) : bool{
        $owner = $owner ? strtolower($owner) : null;
        if (Member::exists($owner) || is_null($owner)) {
            $ev = new PlotSetOwnerEvent($this, $owner, $executor);
            $ev->call();
            if ($ev->isCancelled()) {
                return true;
            }
            $plot_id = $this->getId();
            $stmt = $this->db->prepare("UPDATE plots SET plot_owner = :plot_owner WHERE plot_id = :plot_id");
            $stmt->bindParam("plot_owner", $owner, SQLITE3_TEXT);
            $stmt->bindParam("plot_id", $plot_id, SQLITE3_INTEGER);
            $stmt->execute();
            $stmt->close();
            return true;
        }
        else{
            return false;
        }
    }

    /**
     * This method adds an member to the Plot
     *
     * @param string $member
     * @param Player|null $executor The Player who executed the command
     * @return bool
     * @throws \ReflectionException
     */
    public function addMember(string $member, Player $executor = null) : bool{
        $member = strtolower($member);
        if (Member::exists($member)) {
            $members = $this->getMembers();
            $plot_id = $this->getId();
            $plot = $this;
            if (!empty($member)) {
                if (count($members) < $this->getMaxMembers() && !in_array($member, $members)) {
                    $ev = new PlotAddMemberEvent($this, $member, $executor);
                    $ev->call();
                    if ($ev->isCancelled()) {
                        return true;
                    }
                    array_push($members, $member);
                    $members = serialize($members);
                    $stmt = $this->db->prepare("UPDATE plots SET plot_members = :plot_members WHERE plot_id = :plot_id");
                    $stmt->bindParam("plot_id", $plot_id, SQLITE3_INTEGER);
                    $stmt->bindParam("plot_members", $members, SQLITE3_TEXT);
                    $stmt->execute();
                    $stmt->close();
                    if ($plot->getPlayerPermissions($member) == null) {
                        $plot->initPlayerPerms($member);
                    }

                    return true;
                } else {
                    return false;
                }
            } else {
                return false;
            }
        }
        else{
            return false;
        }
    }


    /**
     * This method returns the maximum number of members the plot can have.
     *
     * @return int
     */
    public function getMaxMembers() : int{
        $plot_id = $this->getId();
        $stmt = $this->db->prepare("SELECT max_members FROM plots WHERE plot_id = :plot_id");
        $stmt->bindParam("plot_id", $plot_id, SQLITE3_INTEGER);
        $res = $stmt->execute();
        while($row = $res->fetchArray()){
            return $row["max_members"];
        }
    }

    /**
     * This method sets the maximum number of members the plot can have.
     *
     * @param int $max_count
     */
    public function setMaxMembers(int $max_count){
        $plot_id = $this->getId();
        $stmt = $this->db->prepare("UPDATE plots SET max_members = :max_members WHERE plot_id = :plot_id");
        $stmt->bindParam("max_members", $max_count, SQLITE3_INTEGER);
        $stmt->bindParam("plot_id", $plot_id, SQLITE3_INTEGER);
        $stmt->execute();
        $stmt->close();
    }

    /**
     * This method removes a member from the plot
     *
     * @param string $member
     * @param Player|null $executor The Player who executed the command
     * @return bool
     * @throws \ReflectionException
     */
    public function removeMember(string $member, Player $executor = null) : bool{
        $member = strtolower($member);
            $old_members = $this->getMembers();
            $plot_id = $this->getId();

            if (in_array($member, $old_members)) {
                $ev = new PlotRemoveMemberEvent($this, $member, $executor);
                $ev->call();
                if ($ev->isCancelled()) {
                    return true;
                }
                $members = serialize(array_diff($old_members, array($member)));
                $stmt = $this->db->prepare("UPDATE plots SET plot_members = :plot_members WHERE plot_id = :plot_id");
                $stmt->bindParam("plot_members", $members, SQLITE3_TEXT);
                $stmt->bindParam("plot_id", $plot_id, SQLITE3_INTEGER);
                $stmt->execute();
                $stmt->close();
                $this->destructPlayerPerms($member);
                return true;
            } else {
                return false;
            }
    }

    /**
     * This method sets the group the Plot is in, do not use this method!
     *
     * @param string|null $name
     * @param Player|null $executor The Player who executed the command
     * @throws \ReflectionException
     * @see Group::addToGroup()
     *
     */
    public function setGroupName(?string $name, Player $executor = null): void
    {
        $ev = new PlotSetGroupnameEvent($this, $name, $executor);
        $ev->call();
        if (!$ev->isCancelled()) {
            $plot_id = $this->getId();
            $stmt = $this->db->prepare("UPDATE plots SET group_name = :group_name WHERE plot_id = :plot_id");
            $stmt->bindParam("group_name", $name, SQLITE3_TEXT);
            $stmt->bindParam("plot_id", $plot_id, SQLITE3_INTEGER);
            $stmt->execute();
            $stmt->close();
        }
    }

    /**
     * This method returns the name of the Group the Plot is currently member of.
     * @return bool
     */
    protected function getGroupName()
    {
        $plot_id = $this->getId();
        $stmt = $this->db->prepare("SELECT group_name FROM plots WHERE plot_id = :plot_id");
        $stmt->bindParam("plot_id", $plot_id, SQLITE3_INTEGER);
        $res = $stmt->execute();
        while($row = $res->fetchArray()){
            $group_name = $row["group_name"];
        }
        $stmt->close();

        if(isset($group_name) && !is_null($group_name)){
            return $group_name;
        }
        else{
            return false;
        }
    }

    /**
     * This method returns the name of the Group the Plot is member of
     *
     * @return Group|null
     */
    public function getGroup() : ?Group{
        $group_name = $this->getGroupName();
        $db = $this->db;
        $stmt = $db->prepare("SELECT * FROM groups WHERE group_name = :group_name");
        $stmt->bindParam("group_name", $group_name, SQLITE3_TEXT);
        $res = $stmt->execute();
        $group = null;
        while($row = $res->fetchArray()){
            $group = new Group($row["group_name"], Plot::getPlotByName($row["master_plot"]));
        }
        return $group;
    }


    /**
     * If the Plot is a member of a Group then this wil return True, otherwise it will return false
     *
     * @return bool
     */
    public function isGrouped(){
        return $this->getGroup() !== null;
    }

    /**
     * This method returns a bool depending on if the Plot is the Master Plot of the Group
     * If the Plot is not a member of a Group this method will return null
     *
     * @return bool|null
     */
    public function isMasterPlot() : ?bool{
        if($this->isGrouped()){
            $exp = $this->getGroup()->getMasterPlot()->getName() == $this->getName();
        }
        else{
            return null;
        }
        return $exp;
    }

    /**
     * This returns an array of all the plots
     *
     * @return Plot[]
     */
    public static function getPlots() : array{
        $res = Main::getInstance()->db->query("SELECT * FROM plots");
        $plots = [];
        while($row = $res->fetchArray()){

            $plots[] = Plot::getPlotById($row["plot_id"]);
        }
        return $plots;
    }

    /**
     * This method fetches the Plot with the corresponding ID
     *
     * @param int $id
     * @return Plot|null
     */
    public static function getPlotById(int $id) : ?Plot
    {
        $main = Main::getInstance();
        $stmt = $main->db->prepare("SELECT * FROM plots WHERE plot_id = :id");
        $stmt->bindParam("id", $id, SQLITE3_INTEGER);
        $res = $stmt->execute();
        $plot = null;
        while ($row = $res->fetchArray()) {
            $world = null;
            if ($main->getServer()->isLevelLoaded($row["plot_world"])) {
                $world = $main->getServer()->getLevelByName($row["plot_world"]);
            } elseif ($main->getServer()->isLevelGenerated($row["plot_world"])) {
                if ($main->getServer()->loadLevel($row["plot_world"])) {
                    $world = $main->getServer()->getLevelByName($row["plot_world"]);
                }
            }

            if ($world !== null) {
                $plot = new Plot($row["plot_name"], $row["plot_owner"], $world, unserialize($row["plot_location"]), unserialize($row["plot_members"]));
            }
        }
        return isset($plot) ? $plot : null;
    }

    /**
     * @param string $name
     * @return Plot|null
     */
    public static function getPlotByName(string $name): ?Plot
    {
        $main = Main::getInstance();
        $name = strtolower($name);
        $stmt = $main->db->prepare("SELECT * FROM plots WHERE lower(plot_name) = :plot_name");
        $stmt->bindParam("plot_name", $name, SQLITE3_TEXT);
        $res = $stmt->execute();
        while ($row = $res->fetchArray()) {
            $world = null;
            if ($main->getServer()->isLevelLoaded($row["plot_world"])) {
                $world = $main->getServer()->getLevelByName($row["plot_world"]);
            } elseif ($main->getServer()->isLevelGenerated($row["plot_world"])) {
                if ($main->getServer()->loadLevel($row["plot_world"])) {
                    $world = $main->getServer()->getLevelByName($row["plot_world"]);
                }
            }
            if ($world !== null) {
                $plot = new Plot($row["plot_name"], $row["plot_owner"], $world, unserialize($row["plot_location"]), unserialize($row["plot_members"]));
            }
        }

        return isset($plot) ? $plot : null;

    }

    /**
     * @param Player $player
     * @return Plot[] | null
     */
    public static function getUserPlots(Player $player)
    {
        $main = Main::getInstance();
        $stmt = $main->db->prepare("SELECT * FROM plots WHERE plot_owner = :owner");
        $player_name = strtolower($player->getName());
        $stmt->bindParam("owner", $player_name, SQLITE3_TEXT);
        $result = $stmt->execute();
        $plots = null;

        while ($row = $result->fetchArray()) {
            if ($main->getServer()->isLevelLoaded($row["plot_world"])) {
                $world = $main->getServer()->getLevelByName($row["plot_world"]);
            } elseif ($main->getServer()->isLevelGenerated($row["plot_world"])) {
                if ($main->getServer()->loadLevel($row["plot_world"])) {
                    $world = $main->getServer()->getLevelByName($row["plot_world"]);
                }
            }
            if ($world !== null) {
                $plots[] = new Plot($row["plot_name"], $row["plot_owner"], $world, unserialize($row["plot_location"]), unserialize($row["plot_members"]));
            }
        }
        return $plots;
    }


    /**
     * This method deletes the plot
     *
     * If the plot is the master plot this method will delete all the Plots in the group and the Group itself
     * If the plot is just a member of a Group, the Plot will be removed from the Group and then deleted.
     *
     *
     *
     * @param Player|null $executor
     * @return mixed
     * @throws \ReflectionException
     */
    public function delete(Player $executor = null): void
    {
        $ev = new PlotDeleteEvent($this, $executor);
        $ev->call();
        if ($ev->isCancelled()) {
            return;
        }
        if ($this->isGrouped()) {
            if ($this->isMasterPlot()) {
                foreach ($this->getGroup()->getPlots() as $plot) {
                    if ($plot !== null) {
                        if (!$plot->isMasterPlot()) {
                            $plot->delete();
                        }
                    }
                }
                $this->getGroup()->delete();
            }
            else {
                $this->getGroup()->removeFromGroup($this);
            }
        }
        $plot_id = $this->getId();
        $stmt = $this->db->prepare("DELETE FROM plots WHERE plot_id = :plot_id");
        $stmt->bindParam("plot_id", $plot_id, SQLITE3_INTEGER);
        $stmt->execute();
        $stmt->close();
    }


    /**
     * This method resets all the Plot settings
     *
     * @param Player|null $executor
     * @throws \ReflectionException
     */
    public function reset(Player $executor = null)
    {
        $ev = new PlotResetEvent($this, $executor);
        $ev->call();
        if (!$ev->isCancelled()) {
            $this->setOwner();
            foreach ($this->getMembers() as $member) {
                $this->removeMember($member);
            }
        }
    }
}
