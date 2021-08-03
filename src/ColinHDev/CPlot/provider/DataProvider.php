<?php

namespace ColinHDev\CPlot\provider;

use ColinHDev\CPlot\worlds\WorldSettings;
use ColinHDev\CPlotAPI\flags\BaseFlag;
use ColinHDev\CPlotAPI\Plot;

abstract class DataProvider {

    /** @var WorldSettings[] */
    private array $worldCache = [];
    private int $worldCacheSize = 16;

    /** @var Plot[] */
    private array $plotCache = [];
    private int $plotCacheSize = 128;

    abstract public function getWorld(string $name) : ?WorldSettings;
    abstract public function addWorld(string $name, WorldSettings $settings) : bool;

    abstract public function getPlot(string $worldName, int $x, int $z) : ?Plot;
    abstract public function getPlotsByOwnerUUID(string $ownerUUID) : array;
    abstract public function getPlotByAlias(string $alias) : ?Plot;

    abstract public function getPlotFlags(Plot $plot) : ?Plot;
    abstract public function savePlotFlag(Plot $plot, BaseFlag $flag) : bool;
    abstract public function deletePlotFlag(Plot $plot, string $flagID) : bool;
    abstract public function deletePlotFlags(Plot $plot) : bool;

    abstract public function close() : bool;

    /**
     * @param string $name
     * @return WorldSettings | null
     */
    protected function getWorldFromCache(string $name) : ?WorldSettings {
        if ($this->worldCacheSize <= 0) return null;
        if (!isset($this->worldCache[$name])) return null;
        return $this->worldCache[$name];
    }

    /**
     * @param string $name
     * @param WorldSettings $settings
     */
    protected function cacheWorld(string $name, WorldSettings $settings) : void {
        if ($this->worldCacheSize <= 0) return;
        if (isset($this->worldCache[$name])) {
            unset($this->worldCache[$name]);
        } else if ($this->worldCacheSize <= count($this->worldCache)) {
            array_shift($this->worldCache);
        }
        $this->worldCache = array_merge([$name => clone $settings], $this->worldCache);
    }

    /**
     * @param string    $worldName
     * @param int       $x
     * @param int       $z
     * @return Plot | null
     */
    protected function getPlotFromCache(string $worldName, int $x, int $z) : ?Plot {
        if ($this->plotCacheSize <= 0) return null;
        $key = $worldName . ";" . $x . ";" . $z;
        if (!isset($this->plotCache[$key])) return null;
        return $this->plotCache[$key];
    }

    /**
     * @param Plot $plot
     */
    protected function cachePlot(Plot $plot) : void {
        if ($this->plotCacheSize <= 0) return;
        $key = $plot->toString();
        if (isset($this->plotCache[$key])) {
            unset($this->plotCache[$key]);
        } else if ($this->plotCache <= count($this->plotCache)) {
            array_shift($this->plotCache);
        }
        $this->plotCache = array_merge([$key => clone $plot], $this->plotCache);
    }
}