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

namespace mohagames\PlotArea;

use mohagames\PlotArea\listener\EventListener;
use mohagames\PlotArea\tasks\PositioningTask;
use mohagames\PlotArea\utils\Group;
use mohagames\PlotArea\utils\Member;
use mohagames\PlotArea\utils\PermissionManager;
use mohagames\PlotArea\utils\Plot;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\ConsoleCommandSender;
use pocketmine\event\Listener;
use pocketmine\item\ItemFactory;
use pocketmine\item\ItemIds;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use ReflectionException;
use SQLite3;

class Main extends PluginBase implements Listener
{
    public $pos_1 = array();
    public $pos_2 = array();
    public $db;
    public static $instance;
    public $item;


    public function onEnable(): void
    {

        Main::$instance = $this;

        $config = new Config($this->getDataFolder() . "config.yml", -1, array("item_id" => ItemIds::WOODEN_SHOVEL, "plot_popup" => true, "max_members" => 10));
        $config->save();
        $this->item = $config->get("item_id");
        $popup = $config->get("plot_popup");
        if ($popup) {
            $this->getScheduler()->scheduleRepeatingTask(new PositioningTask(), 30);
        }

        //Dit maakt de databases aan als ze nog niet bestaan
        $this->db = new SQLite3($this->getDataFolder() . "PlotArea.db");
        $this->db->query("CREATE TABLE IF NOT EXISTS plots(plot_id INTEGER PRIMARY KEY AUTOINCREMENT,plot_name TEXT,plot_owner TEXT, plot_members TEXT, plot_location TEXT, plot_world TEXT, plot_permissions TEXT default NULL,max_members INTEGER, group_name TEXT)");
        $this->db->query("CREATE TABLE IF NOT EXISTS groups(group_id INTEGER PRIMARY KEY AUTOINCREMENT, group_name TEXT, master_plot TEXT)");
        //dit registreert de events
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->getServer()->getPluginManager()->registerEvents(new EventListener(), $this);


        PermissionManager::$permission_list = [
            PermissionManager::PLOT_INTERACT_TRAPDOORS => true,
            PermissionManager::PLOT_INTERACT_GATES => true,
            PermissionManager::PLOT_INTERACT_CHESTS => true,
            PermissionManager::PLOT_INTERACT_DOORS => true,
            PermissionManager::PLOT_INTERACT_ITEMFRAMES => true,
            PermissionManager::PLOT_SET_PINCONSOLE => true
        ];


        PermissionManager::checkPermissionVersion();
    }

    /**
     * @param CommandSender $sender
     * @param Command $command
     * @param string $label
     * @param array $args
     * @return bool
     * @throws ReflectionException
     */
    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool
    {
        switch ($command->getName()) {
            case "plotwand":
                if (!$sender instanceof Player) return false;
                $item = ItemFactory::get($this->item);
                $item->setCustomName("Plot wand");
                $sender->getInventory()->addItem($item);
                $sender->sendMessage("§aU hebt een plot wand ontvangen");
                return true;

            case "saveplot":
                if (!$sender instanceof Player) return false;


                if (!isset($this->pos_1[$sender->getName()]) || !isset($this->pos_2[$sender->getName()])) {
                    $sender->sendMessage("U moet de positie van het plot nog bepalen.");
                    return true;
                }
                if (!isset($args[0])) {
                    $sender->sendMessage("§cGelieve een plotnaam op te geven. /saveplot <plotnaam>");
                    return true;
                }

                $p_name = $args[0];

                if (Plot::getPlotByName($p_name) !== null) {
                    $sender->sendMessage("§4Er bestaat al een plot met deze naam");
                    return true;
                }

                $pos1 = $this->pos_1[$sender->getName()];
                $pos2 = $this->pos_2[$sender->getName()];
                unset($this->pos_1[$sender->getName()]);
                unset($this->pos_2[$sender->getName()]);

                Plot::save($p_name, $sender->getLevel(), array($pos1, $pos2), null, [], $sender);
                $sender->sendMessage("§2Het plot §a$p_name §2is succesvol opgeslagen!");
                return true;

            case "plotinfo":
                if (!$sender instanceof Player) return false;
                $plot = Plot::get($sender);

                if (is_null($plot)) {
                    $sender->sendMessage("§cU staat niet op een plot");
                    return true;
                }

                $line = "\n§3----------------------------\n";
                $plot_name = $plot->getName();
                $owner = $plot->getOwner();
                $leden = $plot->getMembersList();

                if ($sender->hasPermission("pa.staff.devinfo")) {
                    $plot->isGrouped() ? $grpd = "\n§3Grouped: §a✓" : $grpd = "\n§3Grouped: §c✗";
                } else {
                    $grpd = null;
                }


                $owner = is_null($owner) ? "Dit plot is van niemand" : $owner;
                $leden = $leden ?: "Geen leden";

                $message = $line . "Plot informatie van plot: §b$plot_name\n§3Eigenaar: §b$owner\n§3Leden: §b$leden $grpd" . $line;
                $sender->sendMessage($message);

                return true;

            case "plot":
                if (!$sender instanceof Player) return false;
                if (isset($args[0])) {
                    switch ($args[0]) {
                        case "setowner":
                            $plot = Plot::get($sender);
                            if (is_null($plot)) {
                                $sender->sendMessage("§cU staat niet op een plot");
                                return true;
                            }
                            if (!$sender->hasPermission("pa.staff.plot.setowner")) {
                                $sender->sendMessage("§4U hebt geen permissie");
                                return true;
                            }

                            if (isset($args[1])) {
                                if (!empty($args[1])) {
                                    $owner = $args[1];
                                } else {
                                    $owner = null;
                                }

                            } else {
                                $owner = null;
                            }

                            if (!Member::exists($owner) && !is_null($owner)) {
                                $sender->sendMessage("§4Deze speler bestaat niet.");
                                return true;
                            }

                            $ans = $plot->setOwner($owner, $sender);

                            $sender->sendMessage(TextFormat::GREEN . $owner . " §2is nu de eigenaar van plot §a" . $plot->getName());

                            break;
                        case "addmember":
                            if (!isset($args[1])) {
                                $sender->sendMessage("§cU moet een lid naam opgeven.§4 " . $command->getUsage());
                                return true;
                            }
                            $plot = Plot::get($sender);
                            if (is_null($plot)) {
                                $sender->sendMessage("§4U staat niet op een plot");
                                return true;
                            }
                            if (!$sender->hasPermission("pa.staff.plot.addmember") && !$plot->isOwner($sender->getName())) {
                                $sender->sendMessage("§4U hebt geen permissie");
                                return true;
                            }
                            if (!Member::exists($args[1])) {
                                $sender->sendMessage("§4Deze speler bestaat niet.");
                                return true;
                            }
                            if ($plot->getMaxMembers() == count($plot->getMembers())) {
                                $sender->sendMessage("§4U kan geen leden meer toevoegen.");
                                return true;
                            }
                            if ($plot->isMember($args[1])) {
                                $sender->sendMessage("§4Deze speler is al lid van het plot.");
                                return true;
                            }

                            $ans = $plot->addMember($args[1], $sender);
                            $ans ? $sender->sendMessage("§aU hebt succesvol een lid toegevoegd") : $sender->sendMessage("§cEr is iets misgelopen!");

                            break;

                        case "removemember":
                            $plot = Plot::get($sender);
                            if (is_null($plot)) {
                                $sender->sendMessage("§4U staat niet op een plot");
                                return true;
                            }

                            if (!$sender->hasPermission("pa.staff.plot.removemember") && !$plot->isOwner($sender->getName())) {
                                $sender->sendMessage("§4U hebt geen permissies");
                                return true;
                            }

                            if (!isset($args[1])) {
                                $sender->sendMessage("§cU moet een lid naam opgeven.");
                                return true;
                            }

                            if (!$plot->isMember($args[1])) {
                                $sender->sendMessage("§4Deze speler is geen lid van het plot");
                                return true;
                            }

                            $ans = $plot->removeMember($args[1], $sender);
                            $ans ? $sender->sendMessage("§aU hebt het lid succesvol verwijderd") : $sender->sendMessage("§cEr is iets misgelopen!");


                            break;


                        case "delete":
                            $plot = Plot::get($sender, false);
                            if (!$sender->hasPermission("pa.staff.plot.delete")) {
                                $sender->sendMessage("§4U hebt geen permissies");
                                return true;
                            }
                            if (is_null($plot)) {
                                $sender->sendMessage("§4U staat niet op een plot.");
                                return true;
                            }

                            $plot->delete($sender);
                            $sender->sendMessage("§aHet plot is succesvol verwijderd");

                            break;

                        /**
                         * TODO: Al deze code cleanen
                         */
                        case "setflag":
                            if (!isset($args[1]) || !isset($args[2]) || !isset($args[3])) {
                                $sender->sendMessage("§4Ongeldige arguments opgegeven. §cCommandgebruik: /plot setflag [speler] [permission] [true/false]");
                                return true;
                            }
                            $plot = Plot::get($sender);
                            if (is_null($plot)) {
                                $sender->sendMessage("§4U staat niet op een plot.");
                                return true;
                            }
                            if (!$sender->hasPermission("pa.staff.plot.setflag") && !$plot->isOwner($sender->getName())) {
                                $sender->sendMessage("§4U hebt geen permissie");
                                return true;
                            }

                            if (!$plot->isMember($args[1])) {
                                $sender->sendMessage("§4U kan geen permissions aanpassen van een speler dat geen lid is van het plot.");
                                return true;
                            }

                            if (strtolower($args[3]) == "false") {
                                $bool = false;
                            }
                            elseif (strtolower($args[3]) == "true") {
                                $bool = true;
                            }
                            else{
                                $sender->sendMessage("§4Ongeldige arguments opgegeven. §cCommandgebruik: /plot setflag [speler] [permission] [true/false]");
                                return true;
                            }

                            $res = $plot->setPermission($args[1], $args[2], $bool);

                            if (!$res) {
                                $sender->sendMessage("§4Deze flag bestaat niet");
                            } elseif (is_null($res)) {
                                $sender->sendMessage("§4U kan geen permissions aanpassen van een speler dat geen lid is van het plot.");
                            } elseif ($res) {
                                $sender->sendMessage("§aDe permissie is succesvol aangepast!");
                            }
                            break;

                        case "flags":
                            $plot = Plot::get($sender);
                            if (is_null($plot)) {
                                $sender->sendMessage("§4U staat niet op een plot.");
                                return true;
                            }
                            if (!$sender->hasPermission("pa.staff.plot.flags") && !$plot->isOwner($sender->getName())) {
                                $sender->sendMessage("§4U hebt geen permissie");
                                return true;
                            }
                            $perms = PermissionManager::$permission_list;
                            $perms_text = "§bFlags die je per gebruiker kan instellen:\n";
                            foreach ($perms as $perm => $value) {
                                $perms_text .= TextFormat::DARK_AQUA . $perm . "\n";
                            }
                            $sender->sendMessage($perms_text);

                            break;

                        case "userinfo":
                            if (!isset($args[1])) {
                                $sender->sendMessage("§4Gelieve een spelernaam te geven.");
                                return true;
                            }

                            $plot = Plot::get($sender);

                            if (is_null($plot)) {
                                $sender->sendMessage("§4U staat niet op een plot.");
                                return true;
                            }

                            if (!$plot->isOwner($sender->getName()) && !$sender->hasPermission("pa.staff.plot.userinfo")) {
                                $sender->sendMessage("§4U hebt geen permissie");
                                return true;
                            }

                            $perms = $plot->getPlayerPermissions($args[1]);
                            $message = TextFormat::GREEN . $args[1] . ":\n";
                            if ($perms !== null) {
                                foreach ($perms as $key => $value) {
                                    $value ? $txt = "§a✓" : $txt = "§c✗";
                                    $message .= TextFormat::DARK_GREEN . $key . ": " . $txt . "\n";
                                }
                            } else {
                                $message = "§4De speler heeft geen permissions";
                            }

                            $sender->sendMessage($message);
                            break;


                        case "creategroup":
                            if (!$sender->hasPermission("pa.staff.plot.creategroup")) {
                                $sender->sendMessage("§4U hebt geen permissie");
                                return true;
                            }
                            if (!isset($args[1]) || !isset($args[2])) {
                                $sender->sendMessage("§4Gelieve een groepnaam en slavenplot op te geven. §c/plot creategroup [groepnaam] [slavenplot]");
                                return true;
                            }
                            $plot = Plot::get($sender);
                            $link_plot = Plot::getPlotByName($args[2]);
                            if (is_null($plot) || is_null($link_plot)) {
                                $sender->sendMessage("§4U staat niet op een plot of het opgegeven plot bestaat niet.");
                                return true;
                            }
                            if ($plot->isGrouped() || $link_plot->isGrouped() || Group::exists($args[1])) {
                                $sender->sendMessage("§4Gelieve een geldig plot en group naam op te geven.");
                                return true;
                            }

                            $res = Group::save($args[1], $plot, $link_plot);
                            $res ? $msg = "§aDe group is succesvol aangemaakt en het plot is toegevoegd bij de group." : $msg = "§4De masterplot en slavenplot kunnen niet hetzelfde zijn.";
                            $sender->sendMessage($msg);
                            break;

                        case "joingroup":
                            if ($sender->hasPermission("pa.staff.plot.joingroup")) {
                                $sender->sendMessage("§4U hebt geen permissie");
                                return true;
                            }
                            if (!isset($args[1])) {
                                $sender->sendMessage("§4Gelieve een groepnaam op te geven");
                                return true;
                            }
                            $plot = Plot::get($sender);
                            if (is_null($plot)) {
                                $sender->sendMessage("§4U staat niet op een plot");
                                return true;
                            }
                            if (!Group::exists($args[1])) {
                                $sender->sendMessage("§4De groep bestaat niet");
                                return true;
                            }
                            $group = Group::get($args[1]);
                            $group->addToGroup($plot);
                            $sender->sendMessage("§aHet plot is succesvol toegevoegd aan de groep.");
                            break;

                        case "leavegroup":
                            if ($sender->hasPermission("pa.staff.plot.leavegroup")) {
                                $sender->sendMessage("§4U hebt geen permissie");
                                return true;
                            }
                            $plot = Plot::get($sender);
                            if (is_null($plot) || !$plot->isGrouped()) {
                                $sender->sendMessage("§4U staat niet op een (grouped) plot.");
                                return true;
                            }
                            $plot->getGroup()->removeFromGroup($plot);
                            $sender->sendMessage("§aHet plot is succesvol verwijderd van de groep.");


                            break;


                        case "deletegroup":
                            if (!$sender->hasPermission("pa.staff.plot.deletegroup")) {
                                $sender->sendMessage("§4U hebt geen permissie");
                                return true;
                            }
                            if (!isset($args[1])) {
                                $plot = Plot::get($sender);
                                if ($plot !== null && $plot->isGrouped()) {
                                    $plot->getGroup()->delete();
                                    $sender->sendMessage("§aDe group is succesvol verwijderd.");
                                } else {
                                    $sender->sendMessage("§4U staat niet op een plot");
                                }
                            } else {
                                if (Group::exists($args[1])) {
                                    Group::get($args[1])->delete();
                                    $sender->sendMessage("§aDe group is succesvol verwijderd.");
                                }
                            }

                            break;

                        case "setmaxmembers":
                            if (!$sender->hasPermission("pa.staff.plot.setmaxmembers")) {
                                $sender->sendMessage("§4U hebt geen permissie");
                                return true;
                            }
                            if (!isset($args[1])) {
                                $sender->sendMessage("§4Gelieve een nummer in te geven.");
                                return true;
                            }
                            if (!is_numeric($args[1])) {
                                $sender->sendMessage("§4Gelieve een nummer in te geven.");
                                return true;
                            }
                            $plot = Plot::get($sender);
                            if (is_null($plot)) {
                                $sender->sendMessage("§4U  staat niet op een plot");
                                return true;
                            }
                            $plot->setMaxMembers($args[1]);
                            $sender->sendMessage("§aHet maximum aantal leden is succesvol aangepast naar " . TextFormat::DARK_GREEN . $args[1]);
                            break;

                        default:
                            $commands = "";
                            $plot = Plot::get($sender);
                            if ($plot !== null) {
                                if ($plot->isOwner($sender->getName()) || $sender->hasPermission("pa.staff")) {
                                    $commands .= "§c/plot flags §4Geeft een lijst van alle flags die je kan gebruiken. §c/plot publicchest §4Maakt een kist openbaar/privé\n §c/plot addmember [lid] §4Voegt een lid toe aan het plot\n§c/plot removemember [lid] §4Verwijdert een lid van het plot\n§c/plot setflag [speler] [flag] [true/false] §4Permissions per lid aanpassen\n§c/plot userinfo [speler] §4Dit heeft informatie over een bepaalde gebruiker.";
                                }
                            }
                            if ($sender->hasPermission("pa.staff")) {
                                $commands .= "§c/plot setowner [owner] §4Stelt de owner van een plot in\n§c/plot creategroup [groepnaam] [slavenplot] §4Maakt een group aan met het huidige plot als master plot\n§c/plot leavegroup §4Verwijdert het huidige plot van de group\n§c/plot deletegroup [groepnaam] §4Verwijdert een group \n§c/plot delete §4Verwijdert het plot";
                            }
                            if (empty($commands)) {
                                $commands = "§cOepsie! Er zijn geen commands die je kan gebruiken.";
                            }
                            $sender->sendMessage("§4Gelieve een geldige command te gebruiken.\n$commands");

                            break;
                    }
                } else {
                    $commands = "";
                    $plot = Plot::get($sender);
                    if ($plot !== null) {
                        if ($plot->isOwner($sender->getName()) || $sender->hasPermission("pa.staff")) {
                            $commands .= "§c/plot flags §4Geeft een lijst van alle flags die je kan gebruiken. §c/plot publicchest §4Maakt een kist openbaar/privé\n §c/plot addmember [lid] §4Voegt een lid toe aan het plot\n§c/plot removemember [lid] §4Verwijdert een lid van het plot\n§c/plot setflag [speler] [flag] [true/false] §4Permissions per lid aanpassen\n§c/plot userinfo [speler] §4Dit heeft informatie over een bepaalde gebruiker.";
                        }
                    }
                    if ($sender->hasPermission("pa.staff")) {
                        $commands .= "§c/plot setowner [owner] §4Stelt de owner van een plot in\n§c/plot creategroup [groepnaam] [slavenplot] §4Maakt een group aan met het huidige plot als master plot\n§c/plot leavegroup §4Verwijdert het huidige plot van de group\n§c/plot deletegroup [groepnaam] §4Verwijdert een group \n§c/plot delete §4Verwijdert het plot";
                    }
                    if (empty($commands)) {
                        $commands = "§cOepsie! Er zijn geen commands die je kan gebruiken.";
                    }
                    $sender->sendMessage("§4Gelieve een geldige command te gebruiken.\n$commands");
                }

                return true;

            case "flushperms":
                if ($sender instanceof ConsoleCommandSender) {
                    PermissionManager::resetAllPlotPermissions();
                    $sender->sendMessage("Perms have been cleared succesfully!");
                }
                return true;
            default:
                return false;
        }
    }

    /**
     * @return Main
     */
    public static function getInstance(): Main
    {
        return Main::$instance;
    }

    /**
     * @return SQLite3
     */
    public static function getDb(): SQLite3
    {
        return Main::getInstance()->db;
    }
}


