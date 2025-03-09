<?php

class MarginCalculator
{
    private $margin;

    public function __construct($margin)
    {
        $this->margin = (float)$margin;
    }


    public function calculatePriceWithMargin($basePrice)
    {
        $basePrice = (float)$basePrice;
        return round($basePrice * (1 + ($this->margin / 100)), 6);
    }

    public function hasPriceChanged($oldPrice, $newPrice)
    {
        return abs($oldPrice - $newPrice) > 0.001;
    }
}