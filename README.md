# Slack Provider

## Installation

```
composer require socialite-manager/slack-provider
```

## Usage

```php
use Socialite\Provider\SlackProvider;
use Socialite\Socialite;

Socialite::driver(SlackProvider::class, $config);
```
