<?php namespace MartinLindhe\Data\Currencies;

class CurrencyList
{
    /**
     * @return Currency[]
     */
    public static function all()
    {
        $fileName = __DIR__.'/../data/currencies.json';

        $data = file_get_contents($fileName);

        $list = [];
        foreach (json_decode($data) as $t) {
            $o = new Currency;
            foreach ($t as $key => $value) {
                $o->{$key} = $value;
            }
            $list[] = $o;
        }
        return $list;
    }
}
