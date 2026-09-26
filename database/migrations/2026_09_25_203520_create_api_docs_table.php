<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_docs', function (Blueprint $table) {
            $table->id();
            $table->string('type')->index(); // operation|definition|tag
            $table->string('doc_key')->unique();
            $table->string('operation_id')->nullable()->index();
            $table->string('method')->nullable();
            $table->string('path')->nullable();
            $table->string('title');
            $table->longText('content');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->fullText(['title', 'content']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_docs');
    }
};
