<?php

declare(strict_types=1);

namespace ColinHDev\CPlot\commands\subcommands;

use ColinHDev\CPlot\commands\Subcommand;
use ColinHDev\CPlot\plots\BasePlot;
use ColinHDev\CPlot\plots\Plot;
use ColinHDev\CPlot\provider\DataProvider;
use ColinHDev\CPlot\provider\EconomyManager;
use ColinHDev\CPlot\provider\EconomyProvider;
use ColinHDev\CPlot\provider\LanguageManager;
use ColinHDev\CPlot\provider\utils\EconomyException;
use ColinHDev\CPlot\tasks\async\PlotClearAsyncTask;
use ColinHDev\CPlot\worlds\WorldSettings;
use Generator;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\Server;
use SOFe\AwaitGenerator\Await;
use Throwable;

/**
 * @phpstan-extends Subcommand<mixed, mixed, mixed, null>
 */
class ClearSubcommand extends Subcommand {

    public function execute(CommandSender $sender, array $args) : Generator {
        if (!$sender instanceof Player) {
            yield from LanguageManager::getInstance()->getProvider()->awaitMessageSendage($sender, ["prefix", "clear.senderNotOnline"]);
            return null;
        }

        $worldSettings = yield DataProvider::getInstance()->awaitWorld($sender->getWorld()->getFolderName());
        if (!($worldSettings instanceof WorldSettings)) {
            yield from LanguageManager::getInstance()->getProvider()->awaitMessageSendage($sender, ["prefix", "clear.noPlotWorld"]);
            return null;
        }

        $plot = yield Plot::awaitFromPosition($sender->getPosition());
        if (!($plot instanceof Plot)) {
            yield from LanguageManager::getInstance()->getProvider()->awaitMessageSendage($sender, ["prefix", "clear.noPlot"]);
            return null;
        }

        if (!$sender->hasPermission("cplot.admin.clear")) {
            if (!$plot->hasPlotOwner()) {
                yield from LanguageManager::getInstance()->getProvider()->awaitMessageSendage($sender, ["prefix", "clear.noPlotOwner"]);
                return null;
            }
            if (!$plot->isPlotOwner($sender)) {
                yield from LanguageManager::getInstance()->getProvider()->awaitMessageSendage($sender, ["prefix", "clear.notPlotOwner"]);
                return null;
            }
        }

        $economyManager = EconomyManager::getInstance();
        $economyProvider = $economyManager->getProvider();
        if ($economyProvider instanceof EconomyProvider) {
            $price = $economyManager->getClearPrice();
            if ($price > 0.0) {
                yield from $economyProvider->awaitMoneyRemoval($sender, $price, $economyManager->getClearReason());
                yield from LanguageManager::getInstance()->getProvider()->awaitMessageSendage($sender, ["prefix", "clear.chargedMoney" => [$economyProvider->parseMoneyToString($price), $economyProvider->getCurrency()]]);
            }
        }

        yield from LanguageManager::getInstance()->getProvider()->awaitMessageSendage($sender, ["prefix", "clear.start"]);
        /** @phpstan-var PlotClearAsyncTask $task */
        $task = yield from Await::promise(
            static fn($resolve) => $plot->clear($resolve)
        );
        $world = $sender->getWorld();
        $plotCount = count($plot->getMergePlots()) + 1;
        $plots = array_map(
            static function (BasePlot $plot) : string {
                return $plot->toSmallString();
            },
            array_merge([$plot], $plot->getMergePlots())
        );
        $elapsedTimeString = $task->getElapsedTimeString();
        Server::getInstance()->getLogger()->debug(
            "Clearing plot" . ($plotCount > 1 ? "s" : "") . " in world " . $world->getDisplayName() . " (folder: " . $world->getFolderName() . ") took " . $elapsedTimeString . " (" . $task->getElapsedTime() . "ms) for player " . $sender->getUniqueId()->getBytes() . " (" . $sender->getName() . ") for " . $plotCount . " plot" . ($plotCount > 1 ? "s" : "") . ": [" . implode(", ", $plots) . "]."
        );
        yield from LanguageManager::getInstance()->getProvider()->awaitMessageSendage($sender, ["prefix", "clear.finish" => $elapsedTimeString]);
        return null;
    }

    public function onError(CommandSender $sender, Throwable $error) : void {
        if ($sender instanceof Player && !$sender->isConnected()) {
            return;
        }
        if ($error instanceof EconomyException) {
            LanguageManager::getInstance()->getProvider()->translateForCommandSender(
                $sender,
                $error->getLanguageKey(),
                static function(string $errorMessage) use($sender) : void {
                    $economyManager = EconomyManager::getInstance();
                    $economyProvider = $economyManager->getProvider();
                    // This exception should not be thrown if no economy provider is set.
                    assert($economyProvider instanceof EconomyProvider);
                    LanguageManager::getInstance()->getProvider()->sendMessage(
                        $sender,
                        [
                            "prefix",
                            "clear.chargeMoneyError" => [
                                $economyProvider->parseMoneyToString($economyManager->getClearPrice()),
                                $economyProvider->getCurrency(),
                                $errorMessage
                            ]
                        ]
                    );
                }
            );
        }
    }
}