<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->string('model', 100);
            $table->decimal('temperature', 3, 2);
            $table->text('system_prompt')->nullable();
            $table->timestamp('created_at', 6);
            $table->timestamp('updated_at', 6);

            $table->index(['updated_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
