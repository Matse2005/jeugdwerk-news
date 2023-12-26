<p align="center">
    <p align="center">
        <a href="https://packagist.org/packages/matsevh/jeugdwerk-news"><img alt="Total Downloads" src="https://img.shields.io/packagist/dt/matsevh/jeugdwerk-news"></a>
        <a href="https://packagist.org/packages/matsevh/jeugdwerk-news"><img alt="Latest Version" src="https://img.shields.io/packagist/v/matsevh/jeugdwerk-news"></a>
        <a href="https://packagist.org/packages/matsevh/jeugdwerk-news"><img alt="License" src="https://img.shields.io/github/license/matse2005/jeugdwerk-news"></a>
    </p>
</p>

------
**Jeugdwerk News** for Laravel is a package build for [Jeugdwerk](https//jeugdwerk.org) but usable in other projects. This package will make a json response created with sources you want with th possible option for rss, atom, json feed and a json api.

## Get Started

> **Requires [PHP 8.1+](https://php.net/releases/)**

First, install Jeugdwerk News via the [Composer](https://getcomposer.org/) package manager:

```bash
composer require matsevh/jeugdwerk-news
```

First you need to migrate the news provider table

```bash
php artisan migrate
```

after migrating you can create your news provider(s)

```php
use Matsevh\Jeugdwerk\NewsProviderController

$providerController = new NewsProviderController();
$provider = $providerController->create(
  link_to: 1, // The model id the provider is linked to
  name: 'First Provider', // The name of the provider
  /*
  The allowed and supported types are json and rss
  - rss for rss and atom feeds
  - json for json feeds and api's
  */ 
  type: 'json', 
  link: 'https://www.vrt.be/vrtnieuws/en.rss.articles.xml' // The link/url of the feed
  /*
  The next params are only used when using the type json
  */
  sub: [], // The levels that the will lower when reading the array
  truncate: true, // Truncate the summery to 100 characters
  authentication: [], // Only bearer is supported at the moment leave the array empty when no authentication required
  authentication: [
    'type' => 'bearer',
    'key' => 'Your Bearer Key'
  ], 
  // The field the json fields will turn into the keys are required
  fields: [
    "title" => '', // The news title
    "link" => '', // The link to the article
    "summery" => '', // The summery of the article
    'published' => '' // A Datetime of when the article was published
  ]
));
```

## Usage

### Providers

#### Updating
```php
use Matsevh\Jeugdwerk\NewsProviderController

$providerController = new NewsProviderController();
$provider = $providerController->update(
  providerId: 1 // The provider id you want to update
  // You only have to include the params you want to update
  name: 'Updated Name', // The name of the provider
  truncate: false // Truncate the summery to 100 characters
));
```

#### Removing
```php
use Matsevh\Jeugdwerk\NewsProviderController

$providerController = new NewsProviderController();
$provider = $providerController->delete(
  providerId: 1 // The provider id you want to update
));
```

### News

#### News from one link

```php
use Matsevh\Jeugdwerk\NewsController

$newsController = new NewsController();
$news = $newsController->get(
  link_to: 1 // The link where want all the news from
);
```

#### All the news

```php
use Matsevh\Jeugdwerk\NewsController

$newsController = new NewsController();
$news = $newsController->get();
```

---

Jeugdwerk News is an open-sourced software licensed under the **[MIT license](https://opensource.org/licenses/MIT)**.