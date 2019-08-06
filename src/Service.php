<?php

namespace unapi\gsm\mts;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use unapi\anticaptcha\common\AnticaptchaInterface;
use unapi\anticaptcha\common\dto\CaptchaSolvedInterface;
use unapi\gsm\mts\exceptions\AuthorizedException;
use unapi\gsm\mts\exceptions\BalanceNotFoundException;
use unapi\gsm\mts\exceptions\CsrfNotFoundException;
use unapi\interfaces\ServiceInterface;

class Service implements ServiceInterface, LoggerAwareInterface
{
    /** @var ClientInterface */
    private $client;
    /** @var LoggerInterface */
    private $logger;
    /** @var AnticaptchaInterface */
    private $anticaptcha;

    /**
     * @param array $config Service configuration settings.
     */
    public function __construct(array $config = [])
    {
        if (!isset($config['client'])) {
            $this->client = new Client();
        } elseif ($config['client'] instanceof ClientInterface) {
            $this->client = $config['client'];
        } else {
            throw new \InvalidArgumentException('Client must be instance of ClientInterface');
        }

        if (!isset($config['logger'])) {
            $this->logger = new NullLogger();
        } elseif ($config['logger'] instanceof LoggerInterface) {
            $this->setLogger($config['logger']);
        } else {
            throw new \InvalidArgumentException('Logger must be instance of LoggerInterface');
        }

        if (!isset($config['anticaptcha'])) {
            throw new \InvalidArgumentException('Anticaptcha required');
        } elseif ($config['anticaptcha'] instanceof AnticaptchaInterface) {
            $this->anticaptcha = $config['anticaptcha'];
        } else {
            throw new \InvalidArgumentException('Anticaptcha must be instance of AnticaptchaInterface');
        }
    }

    /**
     * @inheritdoc
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * @return LoggerInterface
     */
    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    /**
     * @return ClientInterface
     */
    public function getClient(): ClientInterface
    {
        return $this->client;
    }

    /**
     * @return AnticaptchaInterface
     */
    public function getAnticaptcha(): AnticaptchaInterface
    {
        return $this->anticaptcha;
    }

    /**
     * @param string $login
     * @param string $password
     * @return PromiseInterface
     */
    public function auth(string $login, string $password): PromiseInterface
    {
        return $this->getClient()->requestAsync('GET', '/amserver/UI/Login', [
            'http_errors' => false,
        ])->then(function (ResponseInterface $response) {
            if ($response->getStatusCode() !== 401)
                throw new AuthorizedException();

            return $this->getClient()->requestAsync('POST', '/amserver/UI/Login', [
                'http_errors' => false,
                'form_params' => $this->extractCsrf($response),
                'headers' => [
                    'Host' => 'login.mts.ru',
                ]
            ]);
        })->then(function (ResponseInterface $response) use ($login, $password) {
            $csrf = $this->extractCsrf($response);
            return $this->getAnticaptcha()->getAnticaptchaPromise($this->getClient(), $response)
                ->then(function (CaptchaSolvedInterface $solved) use ($login, $password, $csrf) {
                    return $this->getClient()->requestAsync('POST', '/amserver/UI/Login', [
                        'form_params' => [
                                'IDToken1' => $login,
                                'IDToken2' => $password,
                                'IDToken3' => $solved->getCode(),
                                'IDButton' => 'Submit',
                                'encoded' => 'false',
                                'loginURL' => '/amserver/UI/Login?gx_charset=UTF-8',
                            ] + $csrf,
                        'headers' => [
                            'Host' => 'login.mts.ru',
                        ]
                    ]);
                });
        });
    }

    /**
     * @param ResponseInterface $response
     * @return array
     */
    protected function extractCsrf(ResponseInterface $response): array
    {
        $page = $response->getBody()->getContents();

        preg_match("/<input type=\"hidden\" name=\"csrf\.sign\" value=\"([^\"]*)/ism", $page, $matches);
        if (!array_key_exists(1, $matches))
            throw new CsrfNotFoundException('csrf.sign not found');
        $csrfSign = $matches[1];

        preg_match("/<input type=\"hidden\" name=\"csrf\.ts\" value=\"([^\"]*)/ism", $page, $matches);
        if (!array_key_exists(1, $matches))
            throw new CsrfNotFoundException('csrf.ts not found');
        $csrfTs = $matches[1];

        $response->getBody()->rewind();

        return [
            'csrf.sign' => $csrfSign,
            'csrf.ts' => $csrfTs,
        ];
    }

    /**
     * @return PromiseInterface
     */
    public function getBalance(): PromiseInterface
    {
        return $this->getClient()->requestAsync('GET', '/profile/header', [
            'query' => ['ref' => 'https://lk.ssl.mts.ru/',
                'scheme' => 'https',
                'style' => '2015v2',
            ]
        ])->then(function (ResponseInterface $response) {
            $this->getLogger()->info($data = $response->getBody()->getContents());

            if (preg_match("/<strong>(.*)<\/strong> руб<\/span>/ims", $data, $matches))
                return new FulfilledPromise((float)$matches[1]);

            throw new BalanceNotFoundException();
        });
    }
}