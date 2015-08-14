<?php

require_once __DIR__.'/../vendor/autoload.php';

foreach (MartinLindhe\Data\Currencies\CurrencyList::all() as $o) {
    d($o);
}
