<?php
namespace unapi\gsm\mts;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Promise\FulfilledPromise;
use Psr\Http\Message\ResponseInterface;
use unapi\anticaptcha\common\AnticaptchaInterface;
use unapi\anticaptcha\common\AnticaptchaServiceInterface;
use unapi\anticaptcha\common\dto\CaptchaSolvedDto;
use unapi\anticaptcha\common\task\ReCaptcha2Task;
use GuzzleHttp\Promise\PromiseInterface;

class Anticaptcha implements AnticaptchaInterface
{
    /** @var AnticaptchaServiceInterface */
    private $service;

    public function __construct(AnticaptchaServiceInterface $service)
    {
        $this->service = $service;
    }

    /**
     * @param ClientInterface $client
     * @param ResponseInterface $response
     * @return PromiseInterface
     */
    public function getAnticaptchaPromise(ClientInterface $client, ResponseInterface $response): PromiseInterface
    {
        $body = $response->getBody()->getContents();
        preg_match("/data-sitekey=\"([^\"]*)\"/ims", $body, $matches);

        if (!array_key_exists(1, $matches) || empty($matches[1])) {
            return new FulfilledPromise(new CaptchaSolvedDto(''));
        }

        return $this->service->resolve(new ReCaptcha2Task([
            'siteUrl' => 'https://login.mts.ru/amserver/UI/Login',
            'siteKey' => $matches[1],
        ]));
    }
}