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
    public function typeAllowed()
    {
        return TypeAllowed::cases();
    }

    public function create(int $link_to, string $name, string $type, string $link, array|null $sub = NULL, bool|null $truncate = false, array|bool|null $authentication = NULL, array|null $fields = NULL): object|bool
    {
        // Verify the type
        if (!TypeAllowed::containsValue($type)) {
            return false;
        }

        $provider = NewsProvider::create([
            'link_to' => $link_to,
            'name' => $name,
            'type' => $type,
            'link' => $link,
            'sub' => $sub !== NULL ? json_encode($sub) : NULL,
            'truncate' => $truncate,
            'authentication' => $authentication !== NULL ? json_encode($authentication) : NULL,
            'fields' => $fields !== NULL ? json_encode($fields) : NULL
        ]);

        return $provider;
    }

    public function all($link_to = null): object
    {
        if ($link_to !== null) {
            $providers = NewsProvider::where('link_to', $link_to)->get();
        } else {
            $providers = NewsProvider::all();
        }
        return $providers;
    }



    public function read(int $id): object|bool
    {
        $provider = NewsProvider::where('id', $id)->first();
        if (!$provider) return false;
        return $provider;
    }

    public function update(int $providerId, int|null $link_to = NULL, string|null $name = NULL, string|null $type = NULL, string|null $link = NULL, array|null $sub = NULL, bool|null $truncate = NULL, array|bool|null $authentication = NULL, array|null $fields = NULL): object|bool
    {
        // Verify the type
        if ($type !== null && !TypeAllowed::containsValue($type)) {
            return false;
        }

        $provider = NewsProvider::where('id', $providerId)->first();

        $provider->update([
            'link_to' => $link_to !== NULL ? $link_to : $provider->link_to,
            'name' => $name !== NULL ? $name : $provider->name,
            'type' => $type !== NULL ? $type : $provider->type,
            'link' => $link !== NULL ? $link : $provider->link,
            'sub' => $sub !== NULL ? $sub : $provider->sub,
            'truncate' => $truncate !== NULL ? $truncate : $provider->truncate,
            'authentication' => $authentication !== NULL ? $authentication : $provider->authentication,
            'fields' => $fields !== NULL ? $fields : $provider->fields
        ]);

        return $provider;
    }

    public function delete(int $providerId)
    {
        $provider = NewsProvider::where('id', $providerId)->first();
        return $provider->delete();
    }

    function isValueInEnum(string $value, string $enumClass): bool
    {
        try {
            $enum = new \ReflectionClass($enumClass);
            $constants = $enum->getConstants();

            return in_array($value, $constants);
        } catch (\ReflectionException $e) {
            return false;
        }
    }
}
