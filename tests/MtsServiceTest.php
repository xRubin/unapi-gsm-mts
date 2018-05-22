<?php

use unapi\gsm\mts\Service;
use unapi\gsm\mts\Client;
use PHPUnit\Framework\TestCase;
use unapi\anticaptcha\common\AnticaptchaInterface;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Promise\FulfilledPromise;
use unapi\anticaptcha\common\dto\CaptchaSolvedDto;

class mtsServiceTest extends TestCase
{
    protected function getAnticaptcha(): AnticaptchaInterface
    {
        /** @var AnticaptchaInterface|\PHPUnit\Framework\MockObject\MockObject $anticaptcha */
        $anticaptcha = $this->createMock(AnticaptchaInterface::class);
        $anticaptcha
            ->method('getAnticaptchaPromise')
            ->willReturn(
                new FulfilledPromise(
                    new CaptchaSolvedDto('03AJpayVE05gyYdQBeJGjjdxxeZSj1F8EVbyo98Sz-L1REwpzks5nIIriPpqT-370bUApt_TEtp1FfuW_QF7z78JWXmWFJkQ4clLqIfIgGv5H1ANcFAAPWMk9whfMdVk1P4OlDVbjhekqXZNPdsRBLY3r9R8CIcEUm-kZxqyPkQpv3BB2X_h-nwwFw8t09vKPD9AsH4dTLdGGhuV8e1kUg-F8BALIoTBStHCR9LaVsC-zxOM6Y4Otxowq_87aLfPnTl_GmUqsc-I1CaVz7jbDWtPFD7usVUaOVl1GbOHfrp8FlTNWX7mJsc1UwHIgqCIcgneQN38FCdfvLVvGP4A92q8wuOeMfPU_nWnQJJtsXiPqpUphu9obETFFMEpKliKew6etrYGO0VVzyW9r7WXZICYmC_b8D8t1a44IQ02m8Ep8hyQvhwEe-84delScrwBFFmCxAnYfrIsiu')
                )
            );

        return $anticaptcha;
    }

    public function testAuthAndBalance()
    {
        $handler = HandlerStack::create(new MockHandler([
            new Response(401, [], file_get_contents(__DIR__ . '/fixtures/auth/step1.html')),
            new Response(401, [], file_get_contents(__DIR__ . '/fixtures/auth/step2.html')),
            new Response(200, [], file_get_contents(__DIR__ . '/fixtures/auth/step3.html')),
            new Response(200, [], file_get_contents(__DIR__ . '/fixtures/balance/profile.html')),
        ]));

        $service = new Service([
            'client' => new Client(['handler' => $handler]),
            'anticaptcha' => $this->getAnticaptcha()
        ]);

        /** @var \Psr\Http\Message\ResponseInterface $response */
        $response = $service->auth('9250000000', 'password')->wait();
        $this->assertEquals(200, $response->getStatusCode());

        /** @var float $balance */
        $balance = $service->getBalance()->wait();
        $this->assertInternalType('float', $balance);
        $this->assertEquals(179.24, $balance, 'Wrong balance', 0.001);
    }
}