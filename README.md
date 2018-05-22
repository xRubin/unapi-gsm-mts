[![Build Status](https://travis-ci.org/xRubin/unapi-gsm-mts.svg?branch=master)](https://travis-ci.org/xRubin/unapi-gsm-mts)
# Unapi GSM MTS
Модуль для работы с мобильным API оператора [МТС](https://login.mts.ru/amserver/UI/Login)

Являтся частью библиотеки [Unapi](https://github.com/xRubin/unapi)

Для прохождения капчи нужен любой модуль, реализующий **unapi\anticaptcha\common\AnticaptchaInterface**, например [Unapi Antigate](https://github.com/xRubin/unapi-anticaptcha-antigate)

## Установка
```bash
$ composer require unapi/gsm-mts
```


### Подключение к сервису
```php
<?php
use unapi\gsm\mts\Service;
use unapi\gsm\mts\Anticaptcha;

$service = new Service([
    'anticaptcha' => new Anticaptcha(new AntigateService([...]),
]);
```

## Авторизация в личном кабинете
```php
<?php
    /** @var \Psr\Http\Message\ResponseInterface $response */
    $response = $service->auth('9250000000', 'password')->wait();
```

## Получение баланса счета (строго после авторизации)
```php
<?php
    /** @var float $balance */
    $balance = $service->getBalance()->wait();
```