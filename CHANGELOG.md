# Changelog

All notable changes to `laravel-webhook-server` will be documented in this file

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
