<?php

namespace App\Mixins\Financial;

use App\Models\Currency;
use Illuminate\Support\Collection;

class MultiCurrency
{

    protected static $cachedCurrencies = null;

    public function getCurrencies(): Collection
    {
        if (self::$cachedCurrencies !== null) {
            return self::$cachedCurrencies;
        }

        $defaultCurrency = $this->getDefaultCurrency();
        $currencies = Currency::query()->orderBy('order', 'asc')->get();

        if ($currencies->isNotEmpty()) {
            $currencies->prepend($defaultCurrency);
            self::$cachedCurrencies = $currencies;
            return $currencies;
        }

        self::$cachedCurrencies = collect();
        return self::$cachedCurrencies;
    }

    public function getSpecificCurrency($currencySign)
    {
        $specificCurrency = null;
        $currencies = $this->getCurrencies();

        foreach ($currencies as $currency) {
            if ($currency->currency == $currencySign) {
                $specificCurrency = $currency;
            }
        }

        return $specificCurrency;
    }

    public function getDefaultCurrency()
    {
        $settings = getFinancialCurrencySettings();

        $defaultCurrency = new Currency();

        $defaultCurrency->currency = $settings['currency'] ?? 'USD';
        $defaultCurrency->currency_position = $settings['currency_position'] ?? 'left';
        $defaultCurrency->currency_separator = $settings['currency_separator'] ?? 'dot';
        $defaultCurrency->currency_decimal = $settings['currency_decimal'] ?? 0;
        $defaultCurrency->exchange_rate = null;

        return $defaultCurrency;
    }
}
