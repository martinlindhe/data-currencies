<?php namespace MartinLindhe\Data\Currencies;

class Reader
{
    /**
     * @return Currency[]
     */
    public static function all()
    {
        $fileName = __DIR__.'/../data/currencies.csv';
        $csv = \League\Csv\Reader::createFromPath($fileName);

        $csv->setOffset(1); //skip header

        $list = [];
        $csv->each(function ($c) use (&$list) {

            if (!$c[0]) {
                return true;
            }

            $o = new Currency;
            $o->alpha3 = $c[0];
            $o->number = $c[1];
            $o->decimals = $c[2];
            $o->name = $c[3];
            $list[] = $o;
            return true;
        });

        return $list;
    }
}
