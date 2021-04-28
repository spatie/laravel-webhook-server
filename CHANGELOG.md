# Changelog

All notable changes to `laravel-webhook-server` will be documented in this file

## 1.13.0 - 2021-04-28

- add `dispatchSync`

## 1.12.0 - 2021-04-20

- pass Guzzle TransferStats into resulting Event (#81)

## 1.11.3 - 2021-04-02

- fix for missing default headers when using withHeaders (#79)

## 1.11.2 - 2021-03-17

- dispatch should return the PendingDispatch (#74)

## 1.11.1 - 2020-12-15

- fix exception name for invalid signers (#67)

## 1.11.0 - 2020-11-28

- add support for PHP 8

## 1.10.0 - 2020-10-04

- add `getUuid`

## 1.9.3 - 2020-09-09

- support Guzzle 7

## 1.9.2 - 2020-09-09

- support Laravel 8

## 1.9.1 - 2020-04-10

- do not use body in GET request (#43)

## 1.9.0 - 2020-03-19

- add `doNotSign`

## 1.8.1 - 2020-03-19

- fix `uuid`

## 1.8.0 - 2020-03-18

- add `uuid`

## 1.7.0 - 2020-03-05

- add `dispatchNow` (#39)

## 1.6.0 - 2020-03-02

- add support for Laravel 7

## 1.5.0 - 2019-12-08

- drop support for PHP 7.3

## 1.4.0 - 2019-09-05

- add error info to the dispatched event

## 1.3.1 - 2019-09-05

- remove duplicate line

## 1.3.0 - 2019-09-04

- do not release job on last attempt

## 1.2.0 - 2019-09-04

- add `getResponse`

## 1.1.0 - 2019-09-04

- Add Laravel 6 support

## 1.0.5 - 2019-07-24

- avoid sending unsuccessfull event when the final try of a job succeeds

## 1.0.4 - 2019-06-22

- remove constructor on `WebhookCallFailedEvent` so it inherits properties

## 1.0.3 - 2019-06-19

- add `ContentType` header with value `application/json` by default

## 1.0.2 - 2019-06-16

- move `test-time` to dev dependencies

## 1.0.1 - 2019-06-15

- fixed method names

## 1.0.0 - 2019-06-15

**contains bug, do not use**

- initial release
