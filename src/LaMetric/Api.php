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
        private array $credentials = []
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
        $redisKey   = 'lametric:crypto-account:prices:' . strtolower($parameters['currency']);
        $jsonPrices = $this->redisClient->get($redisKey);

        if (!$jsonPrices) {
            $cmcApi     = self::CMC_API . strtolower($parameters['currency']);
            $res        = $this->httpClient->request('GET', $cmcApi);
            $jsonPrices = (string) $res->getBody();

            $this->redisClient->set($redisKey, $jsonPrices, 'ex', 120);
        }

        $prices = json_decode($jsonPrices, true);

        $api = new BinanceAPI(
            $parameters['api-key'],
            $parameters['secret-key']
        );

        $account = $api->account();

        $wallets = [];

        foreach ($account['balances'] as $balance) {
            if ($balance['free'] > 0 || $balance['locked'] > 0) {
                foreach ($prices['data'] as $crypto) {
                    if ($crypto['symbol'] === $balance['asset']) {
                        $binanceBalance = $balance['free'] + $balance['locked'];
                        if ($parameters['separate-assets'] === 'false') {
                            if (!isset($wallets['ALL'])) {
                                $wallets['ALL'] = 0;
                            }
                            $wallets['ALL'] += $crypto['quote'][strtoupper($parameters['currency'])]['price'] * $binanceBalance;
                        } else {
                            $price = $crypto['quote'][strtoupper($parameters['currency'])]['price'] * $binanceBalance;
                            if (($price > 1 && $parameters['hide-small-assets'] === 'true') || $parameters['hide-small-assets'] === 'false') {
                                $wallets[$crypto['symbol']] = $price;
                            }
                        }
                        break;
                    }
                }
            }
        }

        foreach ($wallets as &$wallet) {
            switch ($parameters['position']) {
                case 'hide':
                    $wallet = PriceHelper::round($wallet);
                    break;
                case 'after':
                    $wallet = PriceHelper::round($wallet) . SymbolHelper::getSymbol($parameters['currency']);
                    break;
                case 'before':
                default:
                    $wallet = SymbolHelper::getSymbol($parameters['currency']) . PriceHelper::round($wallet);
            }
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
}
