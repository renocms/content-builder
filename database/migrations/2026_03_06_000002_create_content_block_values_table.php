<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Reno\Cms\Helpers\TablePrefixHelper;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(TablePrefixHelper::table('content_block_values'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('builder_id')
                ->constrained(TablePrefixHelper::table('content_builder'))
                ->cascadeOnDelete();
            $table->foreignId('block_id')
                ->constrained(TablePrefixHelper::table('content_blocks'))
                ->cascadeOnDelete();
            $table->unsignedBigInteger('resource_id')->index();
            $table->unsignedBigInteger('resource_field_id')->index();
            $table->json('values')->nullable();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(TablePrefixHelper::table('content_block_values'));
    }
};
