<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('news_providers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('link_to');
            $table->string('name');
            $table->enum('type', ['rss', 'json']);
            $table->string('link');
            $table->json('sub')->nullable();
            $table->boolean('truncate')->default(false)->nullable();
            $table->json('authentication')->nullable();
            $table->json('fields')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('news_providers');
    }
};
