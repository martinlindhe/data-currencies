<?php

// script to update data files, based on https://en.wikipedia.org/wiki/ISO_4217

require_once __DIR__.'/../vendor/autoload.php';

function cleanText($s)
{
    $s = trim($s);

    if ($s == '|-') {
        return '';
    }

    if (substr($s, 0, 2) == '| ') {
        $s = substr($s, 2);
    }

    $p1 = strpos($s, '<!--');
    if ($p1 !== false) {
        $p2 = strpos($s, '-->');
        if ($p2 !== false) {
            $s = substr($s, 0, $p1).substr($s, $p2 + strlen('-->'));
            return cleanText($s);
        }
        return '';
    }

    $p1 = strpos($s, '<ref>');
    if ($p1 !== false) {
        $p2 = strpos($s, '</ref>');
        if ($p2 !== false) {
            $s = substr($s, 0, $p1).substr($s, $p2 + strlen('</ref>'));
            return cleanText($s);
        }
        return '';
    }

    return $s;
}

function stripTrailingMediawikiTag($t)
{
    $pos = strpos($t, '{{');
    if ($pos !== false) {
        $pos2 = strpos($t, '}}', $pos);
        $t = substr($t, 0, $pos);
    }
    $n = explode('|', $t);
    if (!empty($n[2])) {
        $t = $n[2];
    }
    return $t;
}

function getRightSideOfMediawikiTag($t)
{
    $pos = mb_strpos($t, '{{');
    if ($pos === false) {
        return $t;
    }

    $pos2 = mb_strpos($t, '}}', $pos);
    if ($pos2 === false) {
        return $t;
    }

    $t = mb_substr($t, $pos, $pos2 - $pos);
    $n = explode('|', $t);

    if (!empty($n[1])) {
        $t = array_pop($n);

        if (substr($t, 0, 5) == 'name=') {
            $t = substr($t, 5);
        }
    }
    return $t;
}

function isAlpha3InList($alpha3, $list)
{
    foreach ($list as $o) {
        if ($o->alpha3 == $alpha3) {
            return true;
        }
    }
    return false;
}

function write_csv($fileName, $list)
{
    $csv = League\Csv\Writer::createFromFileObject(new SplTempFileObject());

    $csv->insertOne(['alpha3', 'number', 'decimals', 'name', 'countries']);

    foreach ($list as $o) {
        $csv->insertOne([$o->alpha3, $o->number, $o->decimals, $o->name, json_encode($o->countries)]);
    }

    file_put_contents($fileName, $csv->__toString());
}

function countryCodeFromName($name, array $countries)
{
    if ($name == 'U.S. Virgin Islands') {
        $name = 'United States Virgin Islands';
    }

    if ($name == 'Caribbean Netherlands') {
        $name = 'Bonaire, Sint Eustatius and Saba';
    }

    // find exact match
    foreach ($countries as $c) {
        if ($c->name == $name) {
            return $c->alpha3;
        }
    }

    // find line beginning with $name
    foreach ($countries as $c) {
        if (mb_substr($c->name, 0, mb_strlen($name)) == $name) {
            return $c->alpha3;
        }
    }

    return '';
}

function write_json($fileName, $list)
{
    $data = json_encode($list, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    file_put_contents($fileName, $data);
}

$res = (new MartinLindhe\MediawikiClient\Client)
    ->server('en.wikipedia.org')
    ->cacheTtlSeconds(3600) // 1 hour
    ->fetchArticle('ISO 4217');

$x = $res->data;

$start = "!! Currency !! Locations using this currency"."\n"."|-"."\n";


$pos = strpos($x, $start);
if ($pos === false) {
    echo "ERROR: didn't find start\n";
    exit;
}

$pos += strlen($start);


$end = "\n"."|-"."\n"."|-"."\n"."|}";
$pos2 = strpos($x, $end, $pos);
if ($pos2 === false) {
    echo "ERROR: didnt find end\n";
    exit;
}

$data = substr($x, $pos, $pos2 - $pos);

$allCountries = MartinLindhe\Data\Countries\Reader::asObjectList();

$list = [];
foreach (explode("\n", $data) as $row) {
    $row = cleanText($row);
    if (!$row) {
        continue;
    }

    $cols = explode('||', $row);
    if (count($cols) < 3) {
        d($row);
        die;
    }

    $o = new \MartinLindhe\Data\Currencies\Currency;
    $o->alpha3 = trim($cols[0]);
    $o->number = trim($cols[1]);
    $o->decimals = trim($cols[2]);
    if ($o->decimals == '.') {
        $o->decimals = '0';
    }
    if (substr($o->decimals, 0, 2) == '1*') {
        $o->decimals = '1';
    }

    $o->decimals = strip_tags($o->decimals);
    $o->decimals = stripTrailingMediawikiTag($o->decimals);

    $name = trim(strip_tags(\MartinLindhe\MediawikiClient\Client::stripMediawikiLinks($cols[3])));
    $name = stripTrailingMediawikiTag($name);
    $pos = strpos($name, '/');
    if ($pos !== false) {
        $name = substr($name, 0, $pos);
    }

    $pos = strpos($name, '|');
    if ($pos !== false) {
        $name = substr($name, $pos + 1);
    }

    $o->name = trim($name);

    // HACK
    $cols[4] = str_replace('[[Caribbean Netherlands]] (BQ - Bonaire, Sint Eustatius and Saba)', 'Caribbean Netherlands', $cols[4]);

    $countries = [];
    foreach (explode(', ', $cols[4]) as $c) {
        $c = \MartinLindhe\MediawikiClient\Client::stripMediawikiLinks($c);
        $c = trim(getRightSideOfMediawikiTag($c));
        if (!$c) {
            continue;
        }

        $code = countryCodeFromName($c, $allCountries);
        if (!$code) {
            err("WARNING: didn't find country code to ".$c);
        } else {
            $countries[] = $code;
        }
    }

    $o->countries = $countries;

    $list[] = $o;
}

if (!isAlpha3InList('BTC', $list)) {
    $o = new MartinLindhe\Data\Currencies\Currency;
    $o->alpha3 = 'BTC';
    $o->name = 'Bitcoin';
    $o->number = '000';
    $o->decimals = 2;
    $list[] = $o;
}

write_csv(__DIR__.'/../data/currencies.csv', $list);
write_json(__DIR__.'/../data/currencies.json', $list);
