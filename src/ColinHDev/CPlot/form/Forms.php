<?php

declare(strict_types=1);

namespace ColinHDev\CPlot\form;

use jojoe77777\FormAPI\CustomForm;
use jojoe77777\FormAPI\SimpleForm;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat;

class Forms{

    public static function sendStartForm(Player $player) :void {
        $form = new SimpleForm(function(Player $player, ?int $data) :void {
            if(is_null($data)) return;
            if($data === 0) {
                Server::getInstance()->getCommandMap()->dispatch($player, "p home");
            }
            if($data === 1) {
                Server::getInstance()->getCommandMap()->dispatch($player, "p info");
            }
            if($data == 2) {
                self::managerPlot($player);
            }
        });
        $form->setTitle(TextFormat::colorize("SKYBLOCK"));
        $form->addButton(TextFormat::colorize("Về đảo"));
        $form->addButton(TextFormat::colorize("Thông tin đảo"));
        $form->addButton(TextFormat::colorize("Quản lý đảo"));
        $player->sendForm($form);
    }

    public static function managerPlot(Player $player) :void {
        $form = new SimpleForm(function(Player $player, ?int $data) {
            if(is_null($data)) return;
            if($data == 0) {
                $addHelperForm = new CustomForm(function(Player $player, ?array $data) {
                    if(is_null($data)) {
                        self::managerPlot($player);
                        return;
                    }
                    if(isset($data[0])) {
                        $target = $data[0];
                        if(Server::getInstance()->getPlayerExact($target) instanceof Player) {
                            Server::getInstance()->getCommandMap()->dispatch($player, "p add ". $target);
                            return;
                        }
                        $player->sendMessage(TextFormat::colorize("&cKhông tìm thấy người chơi này"));
                    }
                });
                $addHelperForm->setTitle(TextFormat::colorize("Thêm trợ giúp"));
                $addHelperForm->addInput(TextFormat::colorize("Nhập tên người chơi"));
                $player->sendForm($addHelperForm);
            }
            if($data == 1) {
                $removeHelperForm = new CustomForm(function(Player $player, ?array $data){
                    if(is_null($data)) {
                        self::managerPlot($player);
                        return;
                    }
                    if(isset($data[0])) {
                        $target = $data[0];
                        if(Server::getInstance()->getPlayerExact($target) instanceof Player) {
                            Server::getInstance()->getCommandMap()->dispatch($player, "p remove ". $target);
                            return;
                        }
                        $player->sendMessage(TextFormat::colorize("&cKhông tìm thấy người chơi này"));
                    }
                });
                $removeHelperForm->setTitle(TextFormat::colorize("Xóa trợ giúp"));
                $removeHelperForm->addInput(TextFormat::colorize("Nhập tên người chơi"));
                $player->sendForm($removeHelperForm);
            }
            if($data == 2) {
                Server::getInstance()->getCommandMap()->dispatch($player, "p spawn");
            }
        });
        $form->setTitle(TextFormat::colorize("Quản lý đảo"));
        $form->addButton(TextFormat::colorize("Thêm nguời vào đảo"));
        $form->addButton(TextFormat::colorize("Xóa nguời khỏi đảo"));
        //$form->addButton(TextFormat::colorize("Gọp đảo BETA"));
        $form->addButton(TextFormat::colorize("Đặt điểm dịch chuyển"));
        $player->sendForm($form);
    }
}