<?php

declare(strict_types=1);

namespace ColinHDev\CPlot\commands\subcommands;

use ColinHDev\CPlot\commands\Subcommand;
use ColinHDev\CPlot\plots\BasePlot;
use ColinHDev\CPlot\plots\Plot;
use ColinHDev\CPlot\provider\DataProvider;
use ColinHDev\CPlot\provider\LanguageManager;
use ColinHDev\CPlot\tasks\async\PlotBiomeChangeAsyncTask;
use ColinHDev\CPlot\worlds\WorldSettings;
use Generator;
use pocketmine\command\CommandSender;
use pocketmine\data\bedrock\BiomeIds;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\world\World;
use ReflectionClass;
use SOFe\AwaitGenerator\Await;

/**
 * @phpstan-extends Subcommand<mixed, mixed, mixed, null>
 */
class BiomeSubcommand extends Subcommand {

    /** @phpstan-var array<string, BiomeIds::*> */
    private array $biomes;

    public function __construct(string $key) {
        parent::__construct($key);
        /** @phpstan-var array<string, BiomeIds::*> $biomes */
        $biomes = (new ReflectionClass(BiomeIds::class))->getConstants();
        $this->biomes = $biomes;
    }

    public function execute(CommandSender $sender, array $args) : Generator {
        if (!($sender instanceof Player)) {
            yield from LanguageManager::getInstance()->getProvider()->awaitMessageSendage($sender, ["prefix", "biome.senderNotOnline"]);
            return null;
        }

        $position = $sender->getPosition();
        $world = $position->world;
        assert($world instanceof World);
        if (!((yield DataProvider::getInstance()->awaitWorld($world->getFolderName())) instanceof WorldSettings)) {
            yield from LanguageManager::getInstance()->getProvider()->awaitMessageSendage($sender, ["prefix", "biome.noPlotWorld"]);
            return null;
        }

        if (count($args) === 0) {
            $biomeName = $this->getBiomeNameByID($world->getBiomeId($position->getFloorX(), $position->getFloorY(), $position->getFloorZ()));
            yield from LanguageManager::getInstance()->getProvider()->awaitMessageSendage($sender, ["prefix", "biome.plotBiome" => $biomeName]);
            return null;
        }
        $biomeName = strtoupper(implode("_", $args));
        if (!isset($this->biomes[$biomeName])) {
            $biomes = [];
            foreach ($this->biomes as $name => $ID) {
                $biomes[] = yield from LanguageManager::getInstance()->getProvider()->awaitTranslationForCommandSender(
                    $sender,
                    ["biome.list" => $name]
                );
            }
            /** @phpstan-var string $separator */
            $separator = yield from LanguageManager::getInstance()->getProvider()->awaitTranslationForCommandSender($sender, "biome.list.separator");
            $list = implode($separator, $biomes);
            yield from LanguageManager::getInstance()->getProvider()->awaitMessageSendage(
                $sender,
                [
                    "prefix",
                    "biome.invalidBiome" => [$biomeName, $list]
                ]
            );
            return null;
        }
        $biomeID = $this->biomes[$biomeName];

        $plot = yield Plot::awaitFromPosition($position);
        if (!($plot instanceof Plot)) {
            yield from LanguageManager::getInstance()->getProvider()->awaitMessageSendage($sender, ["prefix", "biome.noPlot"]);
            return null;
        }

        if (!$sender->hasPermission("cplot.admin.biome")) {
            if (!$plot->hasPlotOwner()) {
                yield from LanguageManager::getInstance()->getProvider()->awaitMessageSendage($sender, ["prefix", "biome.noPlotOwner"]);
                return null;
            }
            if (!$plot->isPlotOwner($sender)) {
                yield from LanguageManager::getInstance()->getProvider()->awaitMessageSendage($sender, ["prefix", "biome.notPlotOwner"]);
                return null;
            }
        }

        yield from LanguageManager::getInstance()->getProvider()->awaitMessageSendage($sender, ["prefix", "biome.start"]);
        /** @phpstan-var PlotBiomeChangeAsyncTask $task */
        $task = yield from Await::promise(
            static fn($resolve) => $plot->setBiome($biomeID, $resolve)
        );
        $plotCount = count($plot->getMergePlots()) + 1;
        $plots = array_map(
            static function (BasePlot $plot) : string {
                return $plot->toSmallString();
            },
            array_merge([$plot], $plot->getMergePlots())
        );
        $biomeID = $task->getBiomeID();
        $biomeName = $this->getBiomeNameByID($biomeID);
        $elapsedTimeString = $task->getElapsedTimeString();
        Server::getInstance()->getLogger()->debug(
            "Changing plot biome to " . $biomeName . "(ID: " . $biomeID . ") in world " . $world->getDisplayName() . " (folder: " . $world->getFolderName() . ") took " . $elapsedTimeString . " (" . $task->getElapsedTime() . "ms) for player " . $sender->getUniqueId()->getBytes() . " (" . $sender->getName() . ") for " . $plotCount . " plot" . ($plotCount > 1 ? "s" : "") . ": [" . implode(", ", $plots) . "]."
        );
        LanguageManager::getInstance()->getProvider()->sendMessage($sender, ["prefix", "biome.finish" => [$elapsedTimeString, $biomeName]]);
        return null;
    }

    /**
     * This method is used to get the name of a biome by its ID.
     */
    private function getBiomeNameByID(int $biomeID) : string {
        $biomeName = array_search($biomeID, $this->biomes, true);
        if (!is_string($biomeName)) {
            $biomeName = "Unknown (BiomeID: " . $biomeID . ")";
        }
        return $biomeName;
    }
}