<?php

namespace Matsevh\JeugdwerkNews;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NewsProvider extends Model
{
    use HasFactory;

    protected $fillable = [
        'link_to',
        'name',
        'type',
        'link',
        'truncate',
        'sub',
        'authentication',
        'fields',
    ];
}
