<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Reno\Cms\Helpers\TablePrefixHelper;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(TablePrefixHelper::table('content_blocks'), function (Blueprint $table) {
            $table->id();
            $table->string('class');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(TablePrefixHelper::table('content_blocks'));
    }
};
