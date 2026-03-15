<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fcm_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->longText('token');
            $table->boolean('is_valid')->default(true);
            $table->boolean('is_primary')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            // Index pour les recherches
            $table->index(['tokenable_type', 'tokenable_id', 'is_valid']);
            $table->unique(['tokenable_type', 'tokenable_id', 'token'], 'fcm_tokens_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fcm_tokens');
    }
};
