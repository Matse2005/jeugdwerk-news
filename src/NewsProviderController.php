<?php

namespace Matsevh\JeugdwerkNews;

use App\Http\Controllers\Controller;
use Matsevh\JeugdwerkNews\NewsProvider;

enum TypeAllowed: string
{
    case rss = 'rss';
    case json = 'json';

    public static function containsValue($value): bool
    {
        foreach (self::cases() as $case) {
            if ($case->value === $value) {
                return true;
            }
        }
        return false;
    }
}

class NewsProviderController extends Controller
{
    public function create(
        int $link_to,
        string $name,
        string $type,
        string $link,
        ?array $sub = null,
        ?bool $truncate = false,
        ?array $authentication = null,
        ?array $fields = null
    ): ?object {
        if (!TypeAllowed::containsValue($type)) {
            return null;
        }

        $providerData = [
            'link_to' => $link_to,
            'name' => $name,
            'type' => $type,
            'link' => $link,
            'sub' => $sub ? json_encode($sub) : null,
            'truncate' => $truncate,
            'authentication' => $authentication ? json_encode($authentication) : null,
            'fields' => $fields ? json_encode($fields) : null
        ];

        return NewsProvider::create($providerData);
    }

    public function all(?int $link_to = null): object
    {
        return NewsProvider::when($link_to, fn ($query) => $query->where('link_to', $link_to))->get();
    }

    public function read(int $providerId): ?object
    {
        return NewsProvider::where('id', $providerId)->first();
    }

    public function update(
        int $providerId,
        ?int $link_to = null,
        ?string $name = null,
        ?string $type = null,
        ?string $link = null,
        ?array $sub = null,
        ?bool $truncate = null,
        ?array $authentication = null,
        ?array $fields = null
    ): ?object {
        if ($type !== null && !TypeAllowed::containsValue($type)) {
            return null;
        }

        $provider = NewsProvider::where('id', $providerId)->first();

        if ($provider) {
            $provider->update([
                'link_to' => $link_to ?? $provider->link_to,
                'name' => $name ?? $provider->name,
                'type' => $type ?? $provider->type,
                'link' => $link ?? $provider->link,
                'sub' => $sub ?? $provider->sub,
                'truncate' => $truncate ?? $provider->truncate,
                'authentication' => $authentication ?? $provider->authentication,
                'fields' => $fields ?? $provider->fields
            ]);
        }

        return $provider;
    }

    public function delete(int $providerId): bool
    {
        $provider = NewsProvider::where('id', $providerId)->first();

        return $provider ? $provider->delete() : false;
    }


    /*
        VERIFY
    */
    function verify_rss(array $provider)
    {
        if (!$this->verify_hostname($provider['link'])) {
            return [
                'ok' => false,
                'errors' => [
                    [
                        'item' => 'link',
                        'error' => 'The given link doesn\'t exist',
                        'localization' => 'JeugdwerkNews::verify.link-exists',
                        'variables' => []
                    ]
                ]
            ];
        }

        if (!$this->verify_url($provider['link'])) {
            return [
                'ok' => false,
                'errors' => [
                    [
                        'item' => 'link',
                        'error' => 'The given link doesn\'t exist',
                        'localization' => 'JeugdwerkNews::verify.link-exists',
                        'variables' => []
                    ]
                ]
            ];
        }

        $feed = simplexml_load_file($provider['link']);

        if ($feed === false) {
            return [
                'ok' => false,
                'errors' => [
                    [
                        'item' => 'link',
                        'error' => 'The given link is inaccessible',
                        'localization' => 'JeugdwerkNews::verify.link-inaccessible',
                        'variables' => []
                    ]
                ]
            ];
        }

        $feed = $this->verify_link_rss($feed);

        if (!$feed['ok'])
            return $feed;

        return ['ok' => true];
    }

    function verify_link_rss($feed)
    {
        if (!isset($feed->channel->item) and !isset($feed->entry))
            return [
                'ok' => false,
                'errors' => [
                    [
                        'item' => 'link',
                        'error' => 'The given link doesn\'t return a rss or atom feed.',
                        'localization' => 'JeugdwerkNews::verify.rss-feed',
                        'variables' => []
                    ]
                ]
            ];

        return ['ok' => true];
    }

    function verify_json(array $provider)
    {
        if (!$this->verify_hostname($provider['link'])) {
            return [
                'ok' => false,
                'errors' => [
                    [
                        'item' => 'link',
                        'error' => 'The given link doesn\'t exist',
                        'localization' => 'JeugdwerkNews::verify.link-exists',
                        'variables' => []
                    ]
                ]
            ];
        }

        if (!$this->verify_url($provider['link'])) {
            return [
                'ok' => false,
                'errors' => [
                    [
                        'item' => 'link',
                        'error' => 'The given link doesn\'t exist',
                        'localization' => 'JeugdwerkNews::verify.link-exists',
                        'variables' => []
                    ]
                ]
            ];
        }

        $jsonData = file_get_contents($provider['link']);

        if ($jsonData === false) {
            return [
                'ok' => false,
                'errors' => [
                    [
                        'item' => 'link',
                        'error' => 'The given link is inaccessible',
                        'localization' => 'JeugdwerkNews::verify.link-inaccessible',
                        'variables' => []
                    ]
                ]
            ];
        }

        $data = (array) json_decode($jsonData);

        $data = $this->verify_link_json($data);

        if (!$data['ok'])
            return $data;

        $data = $this->verify_sub_json($data['data'], $provider['sub']);

        if (!$data['ok'])
            return $data;

        $data = $this->verify_fields_json($data['data'], $provider['fields']);

        if (!$data['ok'])
            return $data;

        return ['ok' => true];
    }

    function verify_link_json($data)
    {
        if ($data == null) {
            return [
                'ok' => false,
                'errors' => [
                    'link' => 'The given link doesn\'t return JSON.',
                    [
                        'item' => 'link',
                        'error' => 'The given link doesn\'t exist',
                        'localization' => 'JeugdwerkNews::verify.link-inaccessible',
                        'variables' => []
                    ]
                ]
            ];
        }

        return ['ok' => true, 'data' => $data];
    }

    function verify_sub_json($data, $subs)
    {
        foreach (json_decode($subs) as $sub) {
            if (isset($data[$sub]))
                $data = $data[$sub];
            else
                return [
                    'ok' => false,
                    'errors' => [
                        [
                            'item' => 'sub',
                            'error' => 'The given sublevel(s) doesn\'t exist',
                            'localization' => 'JeugdwerkNews::verify.sub-exist',
                            'variables' => []
                        ]
                    ]
                ];
        }

        return ['ok' => true, 'data' => $data];
    }

    function verify_fields_json($data, $fields)
    {
        $response = [
            'ok' => false,
            'errors' => []
        ];

        // dd($data);
        $first = (array) $data[0];
        foreach (json_decode($fields) as $key => $field) {
            if (!isset($first[$field]))
                $response['errors'][$key] = 'The field ' . $field . ' doesn\'t exist.';
            $response['errors'] =                         [
                'item' => 'sub',
                'error' => 'The field ' . $field . ' doesn\'t exist.',
                'localization' => 'JeugdwerkNews::verify.field-exist',
                'variables' => ['field' => $field]
            ];
        }

        return count($response['errors']) <= 0 ? ['ok' => true, 'data' => $data] : $response;
    }

    function verify_hostname($url)
    {
        $ipAddress = gethostbyname(parse_url($url, PHP_URL_HOST));

        if ($ipAddress == parse_url($url, PHP_URL_HOST))
            return false;

        return true;
    }

    function verify_url($url)
    {
        try {
            $response = file_get_contents($url);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /*
        VERIFY WITH CURL (Not in use at the moment)
    */
    function checkKeys_curl($url, $sub, $fields, $authentication = null)
    {
        $provider = [
            'link' => $url,
            'sub' => json_decode($sub),
            'fields' => json_decode($fields),
            'authentication' => $authentication !== null ? json_decode($authentication) : null
        ];

        $ch = $this->initCurlSession($provider['link'], null);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        $response = $this->executeCurlRequest($ch);

        if ($response === false) {
            $this->handleCurlError($ch);
            return [
                'status' => false,
            ];
        }

        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $header = substr($response, 0, $headerSize);
        $contentType = null;

        // Extract Content-Type from the response headers
        if (preg_match('/Content-Type:\s*([^;\s]+)/i', $header, $matches)) {
            $contentType = strtolower($matches[1]);
        }

        // Check if the Content-Type is application/json
        if ($contentType !== 'application/json' && $contentType !== 'application/feed+json') {
            return [
                'status' => false,
                'message' => "The URL does not return JSON data (Content-Type is not 'application/json or application/feed+json')."
            ];
        }

        $this->closeCurlSession($ch);

        // Now check if keys match the expected fields in the received data
        return $this->checkKeysMatchFields($provider);
    }

    function checkKeysMatchFields_curl($provider)
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

        // Extract keys from the received JSON response
        $receivedKeys = array_keys($data[0]);

        // Decode the fields
        $decodedFields = $provider['fields'];

        // Check if keys match field values
        foreach ($decodedFields as $fieldKey => $fieldValue) {
            if (!in_array($fieldValue, $receivedKeys)) {
                return [
                    'status' => false,
                    'message' => "Field '$fieldKey' (expected key: '$fieldValue') is not present in the received data.",
                    'key' => $fieldKey,
                    'value' => $fieldValue
                ];
            }
        }

        $this->closeCurlSession($ch);

        return [
            'status' => true,
            'message' => "Keys match the expected fields in the received data."
        ];
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

        foreach ($subValues as $sub) {
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
}
