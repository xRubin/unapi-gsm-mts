<?php

namespace unapi\gsm\mts;

class Client extends \GuzzleHttp\Client
{
    /**
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        $config['base_uri'] = 'https://login.mts.ru';
        $config['cookies'] = true;

        if (!isset($config['headers']['User-Agent'])) {
            $config['headers']['User-Agent'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/66.0.3359.181 Safari/537.36';
        }

        if (!isset($config['headers']['Accept-Language'])) {
            $config['headers']['Accept-Language'] = 'ru-RU,ru;q=0.9,en-US;q=0.8,en;q=0.7';
        }

        parent::__construct($config);
    }
}