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
            $this->storeInCache($key, $this->data($link_to));
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
    function createItem($title, $link, $summary, $published): array
    {
        return [
            'title' => $this->formatItem($title),
            'link' => $this->formatItem($link),
            'summary' => $this->truncateItem($this->formatItem($summary)),
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

    function convertToISO8601($inputDate)
    {
        $date = new \DateTime($inputDate);
        return $date->format('Y-m-d\TH:i:s.v\Z');
    }

    function formatItem($item): string|int|float
    {
        // Trim
        $item = trim($item);

        // Remove \n
        $item = str_replace("\n", " ", $item);

        // if (\str_contains($item, 'Ã©'))
        //     dd(mb_detect_encoding($item, mb_detect_order(), true));

        $item = htmlentities($item);

        // Encode
        $item = mb_convert_encoding($item, 'UTF-8');

        // Remove markdown
        $item = $this->removeMarkdown($item);

        // Decode 
        $item = html_entity_decode($item);

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

            $title = $entry->title;
            // Detect the encoding
            // $detected_encoding = mb_detect_encoding($title, mb_detect_order(), true);

            // // Convert to UTF-8 if the encoding is detected
            // if ($detected_encoding !== false) {
            //     $title = mb_convert_encoding($title, 'UTF-8', $detected_encoding);
            // } else {
            //     $title = "Unable to detect the encoding.";
            // }
            $summary = null;
            if (isset($entry->summary))
                $summary = $entry->summary;
            elseif (isset($entry->content))
                $summary = $entry->content;

            $published = isset($entry->published) ? $entry->published : '';
            $published = is_object($published) ? $published->__toString() : $published;

            $items[] = $this->createItem($title, $link, $summary, $published);
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
        $items = [];

        $json = file_get_contents($provider['link']);
        $data = json_decode($json);

        foreach (json_decode($provider['sub']) as $sub) {
            if (isset($data->{$sub}))
                $data = $data->{$sub};
        }

        $fields = json_decode($provider['fields']);
        foreach ($data as $post) {
            $items[] = $this->createItem($post->{$fields->title}, $post->{$fields->link}, $post->{$fields->summary}, $post->{$fields->published});
        }

        return $items;
    }

    function json_curl($provider)
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
            $items[] = $this->createItem($post[$fields->title], $post[$fields->link], $post[$fields->summary], $post[$fields->published]);
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
        // dd($_SERVER);
        return $_SERVER['SERVER_NAME'] == '127.0.0.1';
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
