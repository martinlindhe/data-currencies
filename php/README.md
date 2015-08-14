## Example seeding your laravel app

Seed your table by doing something like:

```php
foreach (MartinLindhe\Data\Currencies\CurrencyList::all() as $o) {
    Currency::create([
        'alpha3' => $o->alpha3,
        'name' => $o->name,
        'number' => $o->number,
        'decimals' => $o->decimals
    ]);
}
```
