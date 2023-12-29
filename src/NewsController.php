<?php

namespace Matsevh\JeugdwerkNews;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use DateTime;
use Illuminate\Support\Facades\Cache;
use Matsevh\JeugdwerkNews\ParsedownController;
use PhpOption\None;

class NewsController extends Controller
{
    /*
        NEWS
    */
    function get($link_to = null): object
    {
        return (object) $this->cache($link_to);
    }

    /*
        CACHE
    */
    public function cache(int $link_to = null): array
    {
        $key = $this->makeKey($link_to);
        if (!$this->existInCache($key)) {
            $this->storeInCache($key, $this->data());
        }

        return $this->getFromCache($key);
    }

    public function data(int $link_to = null): array
    {
        $providerController = new NewsProviderController();
        $providers = $providerController->all($link_to);
        $items = [];

        foreach ($providers as $provider) {
            switch ($provider['type']) {
                case "rss":
                    $feed = simplexml_load_file($provider['link']);
                    $items = array_merge($items, $this->rssOrAtom($feed));
                    break;
                case 'json':
                    $items = array_merge($items, $this->json($provider));
                    break;
            }
        }

        $items = $this->sort($items);

        return $items;
    }

    function existInCache($key): bool
    {
        return Cache::store('file')->has($key);
    }

    function storeInCache($key, $data, $time = 3600): void
    {
        Cache::store('file')->put($key, $data, 3600);
    }

    function getFromCache($key): array
    {
        return Cache::store('file')->get($key);
    }

    function makeKey($link_to = null): string
    {
        return $link_to == null ? 'all_providers' : $link_to . '_providers';
    }

    /*
        SORTING
    */
    function sort($items): array
    {
        usort($items, function ($a, $b) {
            return strtotime($b['published']) - strtotime($a['published']);
        });

        return $items;
    }

    /*
        DATA
    */
    function createItem($title, $link, $summery, $published): array
    {
        return [
            'title' => $this->formatItem($title),
            'link' => $this->formatItem($link),
            'summery' => $this->truncateItem($this->formatItem($summery)),
            'published' => $this->convertToISO8601($published)
        ];
    }

    function truncateItem($text, $truncate = true, $lenght = 200): string
    {
        $response = $text;
        if ($truncate) {
            if (strlen($response) > $lenght) {
                $response = $response = substr($text, 0, $lenght) . '...';
            } else {
                $response = substr($text, 0, $lenght);
            }
        }

        return $response;
    }

    function removeMarkdown($markdownText): string
    {
        $parsedown = new ParsedownController();
        return $parsedown->text($markdownText);
    }

    function formatItem($item): string|int|float
    {
        // Trim
        $item = trim($item);

        // Remove \n
        $item = str_replace("\n", ' ', $item);

        // Remove markdown
        $item = $this->removeMarkdown($item);

        // Remove html
        $item = strip_tags($item);

        // Decode 
        $item = html_entity_decode($item);

        return $item;
    }

    /*
        LOAD
    */
    function atom($feed): array
    {
        $items = [];

        foreach ($feed->entry as $entry) {

            // Retrieve the first link in the entry's link elements
            $link = '';
            foreach ($entry->link as $entryLink) {
                if ($entryLink['rel'] == 'alternate') {
                    $link = str_replace('"', '', str_replace('href="', '', $entryLink['href']->asXML()));
                    break;
                }
            }

            $summery = null;
            if (isset($entry->summery))
                $summery = $entry->summery;
            elseif (isset($entry->content))
                $summery = $entry->content;

            $published = isset($entry->published) ? $entry->published : '';
            $published = is_object($published) ? $published->__toString() : $published;

            $items[] = $this->createItem($entry->title, $link, $summery, $published);
        }

        return $items;
    }

    function rss($feed)
    {
        $items = [];

        foreach ($feed->channel->item as $item) {
            $items[] = $this->createItem($item->title, $item->link, $item->description, $item->pubDate);
        }
        return $items;
    }

    function json($provider)
    {
        $ch = $this->initCurlSession($provider['link'], $provider['authentication']);
        $response = $this->executeCurlRequest($ch);

        if ($response === false) {
            $this->handleCurlError($ch);
            return [];
        }

        $json = $this->decodeJsonResponse($response);

        if ($json === null) {
            $this->handleJsonDecodingError();
            return [];
        }

        $data = $this->extractNestedData($json, $provider['sub']);

        $fields = json_decode($provider['fields']);
        foreach ($data as $post) {
            $items[] = $this->createItem($post[$fields->title], $post[$fields->link], $post[$fields->summery], $post[$fields->published]);
        }

        $this->closeCurlSession($ch);

        return $items;
    }

    /*
        CURL
    */
    function initCurlSession($url, $authentication)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        if ($this->isBearerAuthentication($authentication)) {
            $this->setBearerTokenAuthentication($ch, $authentication);
        }

        if ($this->isLocalhost()) {
            $this->disableSslVerification($ch);
        }

        return $ch;
    }

    function executeCurlRequest($ch)
    {
        return curl_exec($ch);
    }

    function handleCurlError($ch)
    {
        echo 'cURL Error: ' . curl_error($ch);
    }

    function decodeJsonResponse($response)
    {
        return json_decode($response, true);
    }

    function handleJsonDecodingError()
    {
        echo 'Error decoding JSON data.';
    }

    function extractNestedData($json, $subValues)
    {
        $data = $json;

        foreach (json_decode($subValues) as $sub) {
            $data = $data[$sub] ?? [];
        }

        return $data;
    }

    function closeCurlSession($ch)
    {
        curl_close($ch);
    }

    /*
        HELPER
    */
    function isBearerAuthentication($authentication)
    {
        $decodedAuth = json_decode($authentication);
        return $decodedAuth && isset($decodedAuth->type) && $decodedAuth->type === 'bearer' && isset($decodedAuth->key);
    }

    function setBearerTokenAuthentication($ch, $authentication)
    {
        $decodedAuth = json_decode($authentication);
        $bearerToken = $decodedAuth->key;

        $headers = [
            'Authorization: Bearer ' . $bearerToken,
            'Content-Type: application/json',
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }

    function isLocalhost()
    {
        return strtolower(substr($_SERVER['HTTP_HOST'], 0, 9)) === 'localhost';
    }

    function disableSslVerification($ch)
    {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    }

    /*
        DETECT
    */
    function rssOrAtom($feed): array
    {
        if (isset($feed->channel->item)) {
            return $this->rss($feed);
        } else {
            return $this->atom($feed);
        }
    }
}
