<?php

declare(strict_types=1);

namespace LaMetric;

use GuzzleHttp\Client as HttpClient;
use LaMetric\Helper\PriceHelper;
use LaMetric\Helper\SymbolHelper;
use Predis\Client as RedisClient;
use LaMetric\Response\{Frame, FrameCollection};
use Binance\API as BinanceAPI;

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

        $totalBalance = 0;

        foreach ($account['balances'] as $balance) {
            if ($balance['free'] > 0 || $balance['locked'] > 0) {
                foreach ($prices['data'] as $crypto) {
                    if ($crypto['symbol'] === $balance['asset']) {
                        $binanceBalance = $balance['free'] + $balance['locked'];
                        $totalBalance   += $crypto['quote'][strtoupper($parameters['currency'])]['price'] * $binanceBalance;
                        break;
                    }
                }
            }
        }

        return $this->mapData([
            'total' => SymbolHelper::getSymbol($parameters['currency']) . PriceHelper::round($totalBalance),
        ]);
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
        $frame = new Frame();
        $frame->setText($data['total']);
        $frame->setIcon('43725');

        $frameCollection->addFrame($frame);

        return $frameCollection;
    }
}
