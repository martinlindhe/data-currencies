<?php namespace MartinLindhe\Data\Currencies;

class Currency
{
    var $alpha3;
    var $number;

    /** @var int number of digits after decimal separator */
    var $decimals;
    var $name;

    /** @var string[] array of Country codes (alpha3) where currency is used */
    var $countries = [];
}
