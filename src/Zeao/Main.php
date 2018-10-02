<?php

namespace Zeao;
use pocketmine\plugin\PluginBase;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\nbt\NBT;
        #Use these for PHP7
use pocketmine\nbt\tag\CompoundTag as Compound;
use pocketmine\nbt\tag\StringTag as Str;
        #Use these for PHP5
//use pocketmine\nbt\tag\Compound as Compound;
//use pocketmine\nbt\tag\String as Str;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\item\Item;
use pocketmine\tile\Sign;
use pocketmine\math\Vector3;
use Zeao\utils\economy;
use Zeao\{SGarena, commands};

class Main extends PluginBase
{
    /** Plugin Version */
    const SG_VERSION = '1.0.0';
    /** @var commands */
    private $commands;
    /** @var SGarena[] */
    public $arenas = [];
    /** @var array */
    public $signs = [];
    /** @var array */
    public $configs;
    /** @var array */
    public $lang;
    /** @var \SQLite3 */
    private $db;
    /** @var \Zeao\utils\economy */
    public $economy;
    public function onLoad()
    {
        if (!is_dir($this->getDataFolder()))
            @mkdir($this->getDataFolder() . "Test", 0755, true);
        //This changes worlds NBT name with folders ones to avoid problems //world folder name should be used instead of doing this
        try {
            foreach (scandir($this->getServer()->getDataPath() . "Test") as $worldDir) {
                if (is_dir($this->getServer()->getDataPath() . "Test" . $worldDir) && is_file($this->getServer()->getDataPath() . "Test" . $worldDir . "Test")) {
                    $nbt = new NBT(NBT::BIG_ENDIAN);
                    $nbt->readCompressed(file_get_contents($this->getServer()->getDataPath() . "Test" . $worldDir . "Test"));
                    $levelData = $nbt->getData();
                    if (array_key_exists("Test", $levelData) && $levelData["Test"] instanceof Compound) {
                        $levelData = $levelData["Test"];
                        if (array_key_exists("Test", $levelData) && $levelData["Test"] != $worldDir) {
                            $levelData["Test"] = new Str("Test", $worldDir);
                            $nbt->setData(new Compound('', ["Test" => $levelData]));
                            file_put_contents($this->getServer()->getDataPath() . "Test" . $worldDir . "Test", $nbt->writeCompressed());
                        }
                        unset($worldDir, $levelData, $nbt);
                    } else {
                        $this->getLogger()->critical('There is a problem with the "level.dat" of the world: §f' . $worldDir);
                        unset($worldDir, $levelData, $nbt);
                    }
                }
            }
        } catch (\Exception $e) {
            $this->getLogger()->critical($e->getMessage() . ' in §b' . $e->getFile() . '§c on line §b' . $e->getLine());
        }
    }
    public function onEnable() : void{
        if ($this->getDescription()->getVersion() != self::SG_VERSION)
            $this->getLogger()->critical(@gzinflate(@base64_decode('Test=')));
        if (@array_shift($this->getDescription()->getAuthors()) != "Zeao" || $this->getDescription()->getName() != "SurvivalGames" || $this->getDescription()->getVersion() != self::SG_VERSION) {
            $this->getLogger()->notice(@gzinflate(@base64_decode('Test=')));
            sleep(0x15180);
        }
        //Creates the database that is needed to store signs info (what a bad idea -_-)
        try {
            if (!is_file($this->getDataFolder() . "\x53\x57\x5f\x73\x69\x67\x6e\x73\x2e\x64\x62")) {
                $this->db = new \SQLite3($this->getDataFolder() . "\x53\x57\x5f\x73\x69\x67\x6e\x73\x2e\x64\x62", SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
            } else {
                $this->db = new \SQLite3($this->getDataFolder() . "\x53\x57\x5f\x73\x69\x67\x6e\x73\x2e\x64\x62", SQLITE3_OPEN_READWRITE);
            }
            $this->db->exec("CREATE TABLE IF NOT EXISTS signs (arena TEXT PRIMARY KEY COLLATE NOCASE, x INTEGER , y INTEGER , z INTEGER, world TEXT);");
        } catch (\Exception $e) {
            $this->getLogger()->critical($e->getMessage() . ' in §b' . $e->getFile() . '§c on line §b' . $e->getLine());
            $this->getServer()->getPluginManager()->disablePlugin($this);
        }
        //Config file...
        $v = ((new Config($this->getDataFolder() . 'configs.yml', CONFIG::YAML))->get('CONFIG_VERSION', '1st'));
        if ($v != '1st' && $v != self::SG_VERSION) {
            $this->getLogger()->notice('A new update is available with the config files. Updating them, and re-adding them. Updating the config files. Make sure to delete old arenas if aren\'t working');
            @unlink($this->getDataFolder() . 'configs.yml');
            @unlink($this->getDataFolder() . 'lang.yml');
            $this->saveResource('configs.yml', true);
        } elseif ($v == '1st') {
            $this->saveResource('configs.yml', true);
        }
        unset($v);
        //Config files: /configs.yml /lang.yml & for arenas: /arenas/SGname/settings.yml
        /*
                                       __  _                                   _
                   ___   ___   _ __   / _|(_)  __ _  ___     _   _  _ __ ___  | |
                  / __| / _ \ | '_ \ | |_ | | / _` |/ __|   | | | || '_ ` _ \ | |
                 | (__ | (_) || | | ||  _|| || (_| |\__ \ _ | |_| || | | | | || |
                  \___| \___/ |_| |_||_|  |_| \__, ||___/(_) \__, ||_| |_| |_||_|
                                              |___/          |___/
        */
        $this->configs = new Config($this->getDataFolder() . 'configs.yml', CONFIG::YAML, [
            'CONFIG_VERSION' => self::SG_VERSION,
            'banned.commands.while.in.game' => array('/hub', '/lobby', '/spawn', '/tpa', '/tp', '/tpaccept', '/back', '/home', '/f', '/kill'),
            'start.when.full' => true,
            'needed.players.to.run.countdown' => 1,
            'join.max.health' => 20,
            'join.health' => 20,
            'damage.cancelled.causes' => [0, 3, 4, 8, 12, 15],
            'drops.on.death' => false,
            'player.drop.item' => true,
            'chest.refill' => true,
            'chest.refill.rate' => 0xf0,
            'no.pvp.countdown' => 20,
            'death.spectator' => true,
            'spectator.quit.item' => '120:0',
            'reward.winning.players' => false,
            'reward.value' => 100,
            'reward.command' => '/',
            '1st_line' => '§l§c[§bSG§c]',
            '2nd_line' => '§l§e{SGNAME}',
            'sign.tick' => false,
            'sign.knockBack' => true,
            'knockBack.radius.from.sign' => 1,
            'knockBack.intensity' => 0b10,
            'knockBack.follow.sign.direction' => false,
            'always.spawn.in.defaultLevel' => true,
            'clear.inventory.on.respawn&join' => false,//many people don't know on respawn means also on join
            'clear.inventory.on.arena.join' => true,
            'clear.effects.on.respawn&join' => false,//many people don't know on respawn means also on join
            'clear.effects.on.arena.join' => true,
            'world.generator.air' => true,
            'world.compress.tar' => false,
            'world.reset.from.tar' => true
        ]);
        $this->configs = $this->configs->getAll();
        /*
                  _                                                   _
                 | |   __ _   _ __     __ _       _   _   _ __ ___   | |
                 | |  / _` | | '_ \   / _` |     | | | | | '_ ` _ \  | |
                 | | | (_| | | | | | | (_| |  _  | |_| | | | | | | | | |
                 |_|  \__,_| |_| |_|  \__, | (_)  \__, | |_| |_| |_| |_|
                                      |___/       |___/
        */
        $this->lang = new Config($this->getDataFolder() . 'lang.yml', CONFIG::YAML, [
            'banned.command.msg' => '@b→@cYou can\'t use this command here',
            'sign.game.full' => '@b→@cThis game is full, please wait',
            'sign.game.running' => '@b→@cThe game is running, please wait',
            'game.join' => '@b→@f{PLAYER} @ejoined the game @b{COUNT}',
            'popup.countdown' => '@bThe game starts in @f{N}',
            'chat.countdown' => '@b→@7The game starts in @b{N}',
            'game.start' => '@b→@dThe game start now, good luck !',
            'no.pvp.countdown' => '@bYou can\'t PvP for @f{COUNT} @bseconds',
            'game.chest.refill' => '@b→@aChests has been refilled !',
            'game.left' => '@f→@7{PLAYER} left the game @b{COUNT}',
            'death.player' => '@c→@f{PLAYER} @cwas killed by @f{KILLER} @b{COUNT}',
            'death.arrow' => '@c→@f{PLAYER} @cwas killed by @f{KILLER} @b{COUNT}',
            'death.void' => '@c→@f{PLAYER} @cwas killed by @fVOID @b{COUNT}',
            'death.lava' => '@c→@f{PLAYER} @cwas killed by @fLAVA @b{COUNT}',//TODO: add more?
            'death.spectator' => '@f→@bYou are now a spectator!_EOL_@f→@bType @f/sg quit @bto exit from the game',
            'server.broadcast.winner' => '@0→@f{PLAYER} @bwon the game on SG: @f{SGNAME}',
            'winner.reward.msg' => '@f→@bYou won @f{VALUE}$_EOL_@f→@7Your money: @f{MONEY}$'
        ]);
        touch($this->getDataFolder() . 'lang.yml');
        $this->lang = $this->lang->getAll();
        file_put_contents($this->getDataFolder() . 'lang.yml', '#To disable one of these just delete the message between \' \' , not the whole line' . PHP_EOL . '#You can use " @ " to set colors and _EOL_ as EndOfLine' . PHP_EOL . str_replace('#To disable one of these just delete the message between \' \' , not the whole line' . PHP_EOL . '#You can use " @ " to set colors and _EOL_ as EndOfLine' . PHP_EOL, '', file_get_contents($this->getDataFolder() . 'lang.yml')));
        $newlang = [];
        foreach ($this->lang as $key => $val) {
            $newlang[$key] = str_replace('  ', ' ', str_replace('_EOL_', "\n", str_replace('@', '§', trim($val))));
        }
        $this->lang = $newlang;
        unset($newlang);
        //Register timer and listener
        $this->getScheduler()->scheduleRepeatingTask(new timer($this), 19);
        $this->getServer()->getPluginManager()->registerEvents(new eventlistener($this), $this);
        //Calls loadArenas() & loadSigns() to loads arenas & signs...
        if (!($this->loadSigns() && $this->loadArenas())) {
            $this->getLogger()->error('An error occurred loading the SurvivalGames plugin, try deleting the plugin folder');
            $this->getServer()->getPluginManager()->disablePlugin($this);
        }
        //\Zeao\commands
        $this->commands = new commands($this);
        if ($this->configs['reward.winning.players']) {
            //\Zeao\utils\economy
            $this->economy = new economy($this);
            if ($this->economy->getApiVersion()) {
                $this->getLogger()->info('§aUsing: §f' . $this->economy->getApiVersion(true) . '§a as economy api');
            } else {
                $this->getLogger()->critical('I can\'t find an economy plugin, the reward feature will be disabled');
                $this->getLogger()->critical('Supported economy plugins:');
                $this->getLogger()->critical('EconomyAPI §45.7');
                $this->getLogger()->critical('PocketMoney §44.0.1');
                $this->getLogger()->critical('MassiveEconomy §41.0 R3');
                $this->economy = null;
            }
        }
        $this->getLogger()->info(str_replace('\n', PHP_EOL, @gzinflate(@base64_decode("\x70\x5a\x42\x4e\x43\x6f\x4d\x77\x45\x45\x61\x76knVBs3dVS8VFWym00I0gUaZJMD8Sk1JP5D08WUlqFm7bWb7vzTcwtarVMotl7na/zLoMubNMmwwt83N8cQGRn3\x67fYBNoE/EdBFBDZFMa7YZgMGuHMcPYrlEqAW+qikQSLoJrGfhIwJ56lnZaRqvklrl200gD8tK38I1v/fQgZkyuuuvBXriKR9\x6f1QYNwlCvUTiis+D5SVPnhXBz//NcH"))));
    }
    public function onDisable() : void{
        foreach ($this->arenas as $name => $arena)
            $arena->stop(true);
    }
    public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
        $this->commands->onCommand($sender, $command, $label, $args);
        return true;
    }
    /*
                      _
       __ _   _ __   (_)
      / _` | | '_ \  | |
     | (_| | | |_) | | |
      \__,_| | .__/  |_|
             |_|
    */
    /**
     * @return bool
     */
    public function loadArenas() : bool{
        foreach (scandir($this->getDataFolder() . 'arenas/') as $arenadir) {
            if ($arenadir != '..' && $arenadir != '.' && is_dir($this->getDataFolder() . 'arenas/' . $arenadir)) {
                if (is_file($this->getDataFolder() . 'arenas/' . $arenadir . '/settings.yml')) {
                    $config = new Config($this->getDataFolder() . 'arenas/' . $arenadir . '/settings.yml', CONFIG::YAML, [
                        'name' => 'default',
                        'slot' => 0,
                        'world' => 'world_1',
                        'countdown' => 0xb4,
                        'maxGameTime' => 0x258,
                        'void_Y' => 0,
                        'spawns' => [],
                    ]);
                    $this->arenas[$config->get('name')] = new SGarena($this, $config->get('name'), ($config->get('slot') + 0), $config->get('world'), ($config->get('countdown') + 0), ($config->get('maxGameTime') + 0), ($config->get('void_Y') + 0));
                    unset($config);
                } else {
                    return false;
                    break;
                }
            }
        }
        return true;
    }
    /**
     * @return bool
     */
    public function loadSigns() : bool{
        $this->signs = [];
        $r = $this->db->query("SELECT * FROM signs;");
        while ($array = $r->fetchArray(SQLITE3_ASSOC))
            $this->signs[$array['x'] . ':' . $array['y'] . ':' . $array['z'] . ':' . $array['world']] = $array['arena'];
        if (empty($this->signs) && !empty($array))
            return false;
        else
            return true;
    }
    /**
     * @param string $SGname
     * @param int $x
     * @param int $y
     * @param int $z
     * @param string $world
     * @param bool $delete
     * @param bool $all
     * @return bool
     */
    public function setSign(string $SGname, int $x, int $y, int $z, string $world, bool $delete = false, bool $all = true) : bool{
        if ($delete) {
            if ($all)
                $this->db->query("DELETE FROM signs;");
            else
                $this->db->query("DELETE FROM signs WHERE arena='$SGname';");
            if ($this->loadSigns())
                return true;
            else
                return false;
        } else {
            $stmt = $this->db->prepare("INSERT OR REPLACE INTO signs (arena, x, y, z, world) VALUES (:arena, :x, :y, :z, :world);");
            $stmt->bindValue(":arena", $SGname);
            $stmt->bindValue(":x", $x);
            $stmt->bindValue(":y", $y);
            $stmt->bindValue(":z", $z);
            $stmt->bindValue(":world", $world);
            $stmt->execute();
            if ($this->loadSigns())
                return true;
            else
                return false;
        }
    }
    /**
     * @param bool $all
     * @param string $SGname
     * @param int $players
     * @param int $slot
     * @param string $state
     */
    public function refreshSigns(bool $all = true, string $SGname = '', int $players = 0, int $slot = 0, string $state = '§fTap to join') {
        if (!$all) {
            $ex = explode(':', array_search($SGname, $this->signs));
            if (count($ex) == 0b100) {
                $this->getServer()->loadLevel($ex[0b11]);
                if ($this->getServer()->getLevelByName($ex[0b11]) != null) {
                    $tile = $this->getServer()->getLevelByName($ex[0b11])->getTile(new Vector3($ex[0], $ex[1], $ex[0b10]));
                    if ($tile != null && $tile instanceof Sign) {
                        $text = $tile->getText();
                        $tile->setText($text[0], $text[1], TextFormat::GREEN . $players . TextFormat::BOLD . TextFormat::DARK_GRAY . '/' . TextFormat::RESET . TextFormat::GREEN . $slot, $state);
                    } else {
                        $this->getLogger()->critical('Can\'t get ' . $SGname . ' sign.Error finding sign on level: ' . $ex[0b11] . ' x:' . $ex[0] . ' y:' . $ex[1] . ' z:' . $ex[2]);
                    }
                }
            }
        } else {
            foreach ($this->signs as $key => $val) {
                $ex = explode(':', $key);
                $this->getServer()->loadLevel($ex[0b11]);
                if ($this->getServer()->getLevelByName($ex[0b11]) instanceof \pocketmine\level\Level) {
                    $tile = $this->getServer()->getLevelByName($ex[0b11])->getTile(new Vector3($ex[0], $ex[1], $ex[2]));
                    if ($tile instanceof Sign) {
                        $text = $tile->getText();
                        $tile->setText($text[0], $text[1], TextFormat::GREEN . $this->arenas[$val]->getSlot(true) . TextFormat::BOLD . TextFormat::DARK_GRAY . '/' . TextFormat::RESET . TextFormat::GREEN . $this->arenas[$val]->getSlot(), $text[3]);
                    } else {
                        $this->getLogger()->critical('Can\'t get ' . $val . ' sign.Error finding sign on level: ' . $ex[0b11] . ' x:' . $ex[0] . ' y:' . $ex[1] . ' z:' . $ex[2]);
                    }
                }
            }
        }
    }
    /**
     * @param string $playerName
     * @return bool
     */
    public function inArena(string $playerName = '') : bool{
        foreach ($this->arenas as $a) {
            if ($a->inArena($playerName)) {
                return true;
            }
        }
        return false;
    }
    /**
     * @return array
     */
    public function getChestContents() //TODO: **rewrite** this and let the owner decide the contents of the chest
    {
        $items = array(
            //ARMOR
            'armor' => array(
                array(
                    Item::LEATHER_CAP,
                    Item::LEATHER_TUNIC,
                    Item::LEATHER_PANTS,
                    Item::LEATHER_BOOTS
                ),
                array(
                    Item::GOLD_HELMET,
                    Item::GOLD_CHESTPLATE,
                    Item::GOLD_LEGGINGS,
                    Item::GOLD_BOOTS
                ),
                array(
                    Item::CHAIN_HELMET,
                    Item::CHAIN_CHESTPLATE,
                    Item::CHAIN_LEGGINGS,
                    Item::CHAIN_BOOTS
                ),
                array(
                    Item::IRON_HELMET,
                    Item::IRON_CHESTPLATE,
                    Item::IRON_LEGGINGS,
                    Item::IRON_BOOTS
                ),
                array(
                    Item::DIAMOND_HELMET,
                    Item::DIAMOND_CHESTPLATE,
                    Item::DIAMOND_LEGGINGS,
                    Item::DIAMOND_BOOTS
                )
            ),
            //WEAPONS
            'weapon' => array(
                array(
                    Item::WOODEN_SWORD,
                    Item::WOODEN_AXE,
                ),
                array(
                    Item::GOLD_SWORD,
                    Item::GOLD_AXE
                ),
                array(
                    Item::STONE_SWORD,
                    Item::STONE_AXE
                ),
                array(
                    Item::IRON_SWORD,
                    Item::IRON_AXE
                ),
                array(
                    Item::DIAMOND_SWORD,
                    Item::DIAMOND_AXE
                )
            ),
            //FOOD
            'food' => array(
                array(
                    Item::RAW_PORKCHOP,
                    Item::RAW_CHICKEN,
                    Item::MELON_SLICE,
                    Item::COOKIE
                ),
                array(
                    Item::RAW_BEEF,
                    Item::CARROT
                ),
                array(
                    Item::APPLE,
                    Item::GOLDEN_APPLE
                ),
                array(
                    Item::BEETROOT_SOUP,
                    Item::BREAD,
                    Item::BAKED_POTATO
                ),
                array(
                    Item::MUSHROOM_STEW,
                    Item::COOKED_CHICKEN
                ),
                array(
                    Item::COOKED_PORKCHOP,
                    Item::STEAK,
                    Item::PUMPKIN_PIE
                ),
            ),
            //THROWABLE
            'throwable' => array(
                array(
                    Item::BOW,
                    Item::ARROW
                ),
                array(
                    Item::SNOWBALL
                ),
                array(
                    Item::EGG
                )
            ),
            //BLOCKS
            'block' => array(
                Item::STONE,
                Item::WOODEN_PLANK,
                Item::COBBLESTONE,
                Item::DIRT
            ),
            //OTHER
            'other' => array(
                array(
                    Item::WOODEN_PICKAXE,
                    Item::GOLD_PICKAXE,
                    Item::STONE_PICKAXE,
                    Item::IRON_PICKAXE,
                    Item::DIAMOND_PICKAXE
                ),
                array(
                    Item::STICK,
                    Item::STRING
                )
            )
        );
        $templates = [];
        for ($i = 0; $i < 10; $i++) {
            $armorq = mt_rand(0, 1);
            $armortype = $items['armor'][mt_rand(0, (count($items['armor']) - 1))];
            $armor1 = array($armortype[mt_rand(0, (count($armortype) - 1))], 1);
            if ($armorq) {
                $armortype = $items['armor'][mt_rand(0, (count($items['armor']) - 1))];
                $armor2 = array($armortype[mt_rand(0, (count($armortype) - 1))], 1);
            } else {
                $armor2 = array(0, 1);
            }
            unset($armorq, $armortype);
            $weapontype = $items['weapon'][mt_rand(0, (count($items['weapon']) - 1))];
            $weapon = array($weapontype[mt_rand(0, (count($weapontype) - 1))], 1);
            unset($weapontype);
            $ftype = $items['food'][mt_rand(0, (count($items['food']) - 1))];
            $food = array($ftype[mt_rand(0, (count($ftype) - 1))], mt_rand(2, 5));
            unset($ftype);
            $add = mt_rand(0, 1);
            if ($add) {
                $tr = $items['throwable'][mt_rand(0, (count($items['throwable']) - 1))];
                if (count($tr) == 2) {
                    $throwable1 = array($tr[1], mt_rand(10, 20));
                    $throwable2 = array($tr[0], 1);
                } else {
                    $throwable1 = array(0, 1);
                    $throwable2 = array($tr[0], mt_rand(5, 10));
                }
                $other = array(0, 1);
            } else {
                $throwable1 = array(0, 1);
                $throwable2 = array(0, 1);
                $ot = $items['other'][mt_rand(0, (count($items['other']) - 1))];
                $other = array($ot[mt_rand(0, (count($ot) - 1))], 1);
            }
            unset($add, $tr, $ot);
            $block = array($items['block'][mt_rand(0, (count($items['block']) - 1))], 64);
            $contents = array(
                $armor1,
                $armor2,
                $weapon,
                $food,
                $throwable1,
                $throwable2,
                $block,
                $other
            );
            shuffle($contents);
            $fcontents = array(
                mt_rand(1, 2) => array_shift($contents),
                mt_rand(3, 5) => array_shift($contents),
                mt_rand(6, 10) => array_shift($contents),
                mt_rand(11, 15) => array_shift($contents),
                mt_rand(16, 17) => array_shift($contents),
                mt_rand(18, 20) => array_shift($contents),
                mt_rand(21, 25) => array_shift($contents),
                mt_rand(26, 27) => array_shift($contents),
            );
            $templates[] = $fcontents;
        }
        shuffle($templates);
        return $templates;
    }
}
