<?php

namespace Matsevh\JeugdwerkNews;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Matsevh\JeugdwerkNews\ParsedownController;

class NewsController extends Controller
{
    public function get(int $link_to = null): object
    {
        $key = $link_to == null ? 'all_providers' : $link_to . '_providers';
        if (!Cache::store('file')->has($key)) {
            Cache::store('file')->put($key, $this->collect($link_to), 3600000);
        }
        return (object) Cache::store('file')->get($key);
    }

    public function collect(int $link_to = null)
    {
        $providerController = new NewsProviderController();
        $providers = $providerController->all($link_to);
        $items = [];

        foreach ($providers as $provider) {
            switch ($provider['type']) {
                case "rss":
                    $feed = simplexml_load_file($provider['link']);
                    if (isset($feed->channel->item)) {
                        $items = array_merge($items, $this->LoadRssItems($feed));
                    } else {
                        $items = array_merge($items, $this->LoadAtomItems($feed));
                    }
                    break;
                case 'json':
                    $items = array_merge($items, $this->LoadJsonItems($provider));
                    break;
            }
        }

        // Print as json
        usort($items, function ($a, $b) {
            return strtotime($b['published']) - strtotime($a['published']);
        });

        return $items;
    }

    // Combining function
    function LoadRssItems($feed)
    {
        $items = [];

        foreach ($feed->channel->item as $item) {
            $title = $this->item($item->title);
            $link = $this->item($item->link);
            $description = $this->item($item->description);
            $pubDate = $this->item($item->pubDate);
            $pubDate = $this->convertToISO8601($pubDate);

            $arr = [
                'title' => $title,
                'link' => $link,
                'summery' => $description,
                'published' => $pubDate
            ];

            $items[] = $arr;
        }

        return $items;
    }

    function LoadAtomItems($feed)
    {
        $items = [];

        foreach ($feed->entry as $entry) {
            $title = $this->item($entry->title);

            // Retrieve the first link in the entry's link elements
            $link = '';
            foreach ($entry->link as $entryLink) {
                if ($entryLink['rel'] == 'alternate') {
                    $link = str_replace('"', '', str_replace('href="', '', $entryLink['href']->asXML()));
                    break;
                }
            }

            $summery = '';
            if (isset($entry->summery)) {
                $summery = $this->item($entry->summery);
            } elseif (isset($entry->content)) {
                $summery = $this->item($entry->content);
            }

            $published = isset($entry->published) ? $entry->published : '';
            $published = is_object($published) ? $published->__toString() : $published;
            $published = $this->item($published);
            $published = $this->convertToISO8601($published);

            $arr = [
                'title' => $title,
                'link' => $this->item($link),
                'summery' => $summery,
                'published' => $published
            ];

            $items[] = $arr;
        }

        return $items;
    }

    function LoadJsonItems($provider)
    {
        $url = $provider['link'];
        $authentication = json_decode($provider['authentication']);
        $fields = json_decode($provider['fields']);

        // Initialize cURL session
        $ch = curl_init();

        // Set cURL options
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // Check if authentication is required
        if ($authentication && isset($authentication->type) && $authentication->type === 'bearer' && isset($authentication->key)) {
            $bearerToken = $authentication->key;

            // Set Bearer Token Authentication
            $headers = [
                'Authorization: Bearer ' . $bearerToken,
                'Content-Type: application/json', // Modify headers as needed
            ];

            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        if (strtolower(substr($_SERVER['HTTP_HOST'], 0, 9)) === 'localhost') {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }

        // Additional options like headers, etc., can be added here if needed

        // Execute the cURL request
        $response = curl_exec($ch);

        // Items
        $items = [];

        // Check for cURL errors
        if ($response === false) {
            echo 'cURL Error: ' . curl_error($ch);
        } else {
            // Decode the JSON response into an associative array
            $json = json_decode($response, true);

            // If successful, handle the received blog posts data
            if ($json !== null) {
                $data = $json;

                foreach (json_decode($provider['sub']) as $sub) {
                    $data = $data[$sub];
                }

                foreach ($data as $post) {
                    $arr = [
                        'title' => $this->item($post[$fields->title]),
                        'link' => $this->item($post[$fields->link]),
                        'summery' => $this->truncate($this->item($post[$fields->summery])),
                        'published' => $this->convertToISO8601($this->item($post[$fields->published]))
                    ];

                    $items[] = $arr;
                }
            } else {
                echo 'Error decoding JSON data.';
            }
        }

        // Close cURL session
        curl_close($ch);

        return $items;
    }

    function truncate($text, $truncate = true, $lenght = 200)
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

    function convertToISO8601($inputDate)
    {
        $date = new \DateTime($inputDate);
        return $date->format('Y-m-d\TH:i:s.v\Z');
    }

    function item($item)
    {
        return addslashes($this->stripMarkdown(trim(str_replace("\n", ' ', $item))));
    }

    function compareByPublishedDate($a, $b)
    {
        return strtotime($b['published']) - strtotime($a['published']);
    }

    function stripMarkdown($markdownText)
    {
        $parsedown = new ParsedownController();
        return strip_tags($parsedown->text($markdownText));
    }
}
