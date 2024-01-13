<?php

use Matsevh\JeugdwerkNews\NewsController;
use Matsevh\JeugdwerkNews\NewsProviderController;


Route::prefix('api/jeugdwerk-news')->group(function () {
  Route::prefix('providers')->group(function () {
    Route::get('checkkeys', function () {
      $providerController = new NewsProviderController();
      // return response()->json($providerController->verify_json([
      //   'link' => "https://cropp.blog/feed.json",
      //   'sub' => json_encode([
      //     'items'
      //   ]),
      //   'fields' => json_encode([
      //     'title' => 'title',
      //     'link' => 'url',
      //     'summery' => 'content_html',
      //     'published' => 'date_published',
      //   ])
      // ]));

      return response()->json($providerController->verify_rss([
        'link' => "https://www.nieuwsblad.be/rss/section/5517e67-15a8-4ddd-a3d8-bfe5708f8932",
      ]));
    });

    Route::get('create', function () {
      $providerController = new NewsProviderController();
      return response()->json($providerController->create(
        1,
        'Update Test',
        'rss',
        'https://www.vrt.be/vrtnieuws/en.rss.articles.xml'
      ));
    });

    Route::get('update', function () {
      $providerController = new NewsProviderController();
      return response()->json($providerController->update(
        3,
        truncate: true
      ));
    });

    Route::get('delete', function () {
      $providerController = new NewsProviderController();
      return response()->json($providerController->delete(
        6
      ));
    });

    Route::get('all', function () {
      $providerController = new NewsProviderController();
      return response()->json($providerController->all());
    });

    Route::get('all/{link_to}', function ($link_to) {
      $providerController = new NewsProviderController();
      return response()->json($providerController->all($link_to));
    });

    Route::get('{id}', function ($id) {
      $providerController = new NewsProviderController();
      return response()->json($providerController->read($id));
    });
  });

  Route::prefix('news')->group(function () {
    Route::get('{link_to}', function ($link_to) {
      $newsController = new NewsController();
      // dd($newsController->get($link_to));
      return response()->json($newsController->get($link_to));
    });

    Route::get('all/{link_to}', function () {
      $providerController = new NewsProviderController();
      return response()->json($providerController->all(1));
    });
  });
});
