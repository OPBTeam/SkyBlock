<?php

declare(strict_types=1);

namespace ColinHDev\CPlot\commands\subcommands;

use ColinHDev\CPlot\commands\PlotCommand;
use ColinHDev\CPlot\commands\Subcommand;
use ColinHDev\CPlot\player\PlayerData;
use ColinHDev\CPlot\plots\flags\Flags;
use ColinHDev\CPlot\plots\Plot;
use ColinHDev\CPlot\plots\PlotPlayer;
use ColinHDev\CPlot\provider\DataProvider;
use ColinHDev\CPlot\provider\LanguageManager;
use ColinHDev\CPlot\ResourceManager;
use ColinHDev\CPlot\worlds\WorldSettings;
use czechpmdevs\multiworld\util\WorldUtils;
use pocketmine\command\CommandSender;
use pocketmine\entity\Location;
use pocketmine\player\Player;
use pocketmine\Server;
use function is_string;

/**
 * @phpstan-extends Subcommand<mixed, mixed, mixed, null>
 */
class AutoSubcommand extends Subcommand {

    private bool $automaticClaim;
    private ?string $fallbackWorld;
    private PlotCommand $command;

    public function __construct(string $key, PlotCommand $command) {
        parent::__construct($key);
        $this->automaticClaim = match(ResourceManager::getInstance()->getConfig()->get("auto.automaticClaim", false)) {
            true, "true" => true,
            default => false
        };
        $fallbackWorld = ResourceManager::getInstance()->getConfig()->get("auto.fallbackWorld", false);
        if ($fallbackWorld === false || $fallbackWorld === "false" || !is_string($fallbackWorld)) {
            $this->fallbackWorld = null;
        } else {
            $this->fallbackWorld = $fallbackWorld;
        }
        $this->command = $command;
    }

    public function execute(CommandSender $sender, array $args) : \Generator {
        if (!$sender instanceof Player) {
            yield from LanguageManager::getInstance()->getProvider()->awaitMessageSendage($sender, ["prefix", "auto.senderNotOnline"]);
            return null;
        }

        $claimSubcommand = $this->command->getSubcommandByName("claim");
        assert($claimSubcommand instanceof ClaimSubcommand);
        if ($this->automaticClaim) {
            $playerData = yield DataProvider::getInstance()->awaitPlayerDataByPlayer($sender);
            if (!($playerData instanceof PlayerData)) {
                return null;
            }
            /** @phpstan-var array<string, Plot> $claimedPlots */
            $claimedPlots = yield DataProvider::getInstance()->awaitPlotsByPlotPlayer($playerData->getPlayerID(), PlotPlayer::STATE_OWNER);
            $claimedPlotsCount = count($claimedPlots);
            $maxPlots = $claimSubcommand->getMaxPlotsOfPlayer($sender);
            if ($claimedPlotsCount > $maxPlots) {
                yield from LanguageManager::getInstance()->getProvider()->awaitMessageSendage($sender, ["prefix", "auto.plotLimitReached" => [$claimedPlotsCount, $maxPlots]]);
                return null;
            }
        }

        $defaultWorld = Server::getInstance()->getWorldManager()->getDefaultWorld();

        $worldName = $sender->getWorld()->getFolderName();
        $worldSettings = yield from DataProvider::getInstance()->awaitWorld($worldName);
        if (!($worldSettings instanceof WorldSettings) && is_string($this->fallbackWorld)) {
            $worldName = $this->fallbackWorld;
            $worldSettings = yield from DataProvider::getInstance()->awaitWorld($worldName);
            $worldFallback = WorldUtils::getLoadedWorldByName($worldName);
            $sender->teleport($worldFallback?->getSafeSpawn());
        }

        /** @var Plot|null $plot */
        $plot = yield DataProvider::getInstance()->awaitNextFreePlot($worldName, $worldSettings);
        if ($plot === null) {
            yield from LanguageManager::getInstance()->getProvider()->awaitMessageSendage($sender, ["prefix", "auto.noPlotFound"]);
            $sender->teleport($defaultWorld->getSpawnLocation());
            return null;
        }

        if (!($plot->teleportTo($sender))) {
            yield from LanguageManager::getInstance()->getProvider()->awaitMessageSendage($sender, ["prefix", "auto.teleportError" => [$plot->getWorldName(), $plot->getX(), $plot->getZ()]]);
            $sender->teleport($defaultWorld->getSpawnLocation());
            return null;
        }
        $location = $sender->getLocation();
        $flag = Flags::SPAWN()->createInstance(Location::fromObject(
            $location->subtractVector($plot->getVector3()),
            $sender->getWorld(),
            $location->getYaw(),
            $location->getPitch()
        ));
        $plot->addFlag($flag);
        yield from LanguageManager::getInstance()->getProvider()->awaitMessageSendage($sender, ["prefix", "auto.success" => [$plot->getWorldName(), $plot->getX(), $plot->getZ()]]);
        if ($this->automaticClaim) {
            yield from $claimSubcommand->execute($sender, []);
        }
        return null;
    }
}