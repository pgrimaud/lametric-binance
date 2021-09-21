<?php

declare(strict_types=1);

namespace LaMetric;

use Binance\API as BinanceAPI;
use LaMetric\Helper\{IconHelper, PriceHelper, SymbolHelper};
use Predis\Client as RedisClient;
use GuzzleHttp\Client as HttpClient;
use LaMetric\Response\{Frame, FrameCollection};

class Api
{
    public const CMC_API = 'https://web-api.coinmarketcap.com/v1/cryptocurrency/listings/latest?cryptocurrency_type=all&limit=4999&convert=';

    public function __construct(
        private HttpClient $httpClient,
        private RedisClient $redisClient,
    )
    {
    }

    /**
     * @param array $parameters
     *
     * @return FrameCollection
     */
    public function fetchData(array $parameters = []): FrameCollection
    {
        $redisKey   = 'lametric:cryptocurrencies:' . strtolower($parameters['currency']);
        $jsonPrices = $this->redisClient->get($redisKey);

        if (!$jsonPrices) {
            $cmcApi     = self::CMC_API . strtolower($parameters['currency']);
            $res        = $this->httpClient->request('GET', $cmcApi);
            $jsonPrices = (string) $res->getBody();

            $prices = $this->formatData(json_decode($jsonPrices, true), $parameters['currency']);

            $this->redisClient->set($redisKey, json_encode($prices), 'ex', 180);
        } else {
            $prices = json_decode($jsonPrices, true);
        }

        $api = new BinanceAPI(
            $parameters['api-key'],
            $parameters['secret-key']
        );

        $account = $api->account();

        $wallets = [];

        if (isset($account['balances'])) {
            foreach ($account['balances'] as $balance) {
                if ($balance['free'] > 0 || $balance['locked'] > 0) {
                    if (isset($prices[$balance['asset']])) {
                        $asset = $prices[$balance['asset']];

                        $binanceBalance = $balance['free'] + $balance['locked'];
                        if ($parameters['separate-assets'] === 'false') {
                            if (!isset($wallets['ALL'])) {
                                $wallets['ALL'] = 0;
                            }
                            $wallets['ALL'] += $asset['price'] * $binanceBalance;
                        } else {
                            $price = $asset['price'] * $binanceBalance;
                            if (($price > 1 && $parameters['hide-small-assets'] === 'true') || $parameters['hide-small-assets'] === 'false') {
                                $wallets[$asset['short']] = $price;
                            }
                        }
                    }
                }
            }
        }

        foreach ($wallets as &$wallet) {
            $wallet = match ($parameters['position']) {
                'hide' => PriceHelper::round($wallet),
                'after' => PriceHelper::round($wallet) . SymbolHelper::getSymbol($parameters['currency']),
                default => SymbolHelper::getSymbol($parameters['currency']) . PriceHelper::round($wallet),
            };
        }

        return $this->mapData($wallets);
    }

    /**
     * @param array $data
     *
     * @return FrameCollection
     */
    private function mapData(array $data = []): FrameCollection
    {
        $frameCollection = new FrameCollection();

        /**
         * Transform data as FrameCollection and Frame
         */
        foreach ($data as $key => $wallet) {
            $frame = new Frame();
            $frame->setText((string) $wallet);
            $frame->setIcon(IconHelper::getIcon($key));

            $frameCollection->addFrame($frame);
        }

        return $frameCollection;
    }

    /**
     * @param array  $sources
     * @param string $currencyToShow
     *
     * @return array
     */
    private function formatData(array $sources, string $currencyToShow): array
    {
        $data = [];

        foreach ($sources['data'] as $crypto) {
            // manage multiple currencies with the same symbol
            // & override VAL value
            if (!isset($data[$crypto['symbol']]) || $crypto['symbol'] === 'VAL') {

                // manage error on results // maybe next time?
                if (!isset($crypto['quote'][$currencyToShow]['price'])) {
                    exit;
                }

                $data[$crypto['symbol']] = [
                    'short'  => $crypto['symbol'],
                    'price'  => $crypto['quote'][$currencyToShow]['price'],
                    'change' => round((float) $crypto['quote'][$currencyToShow]['percent_change_24h'], 2),
                ];
            }
        }

        return $data;
    }
}
