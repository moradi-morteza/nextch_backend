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
        Schema::create('rates', function (Blueprint $table) {
            $table->id();
            $table->uuid('user_id'); // Who gave the rating (asker/starter)
            $table->uuid('rated_user_id'); // Who was rated (answerer/recipient)
            $table->uuid('conversation_id'); // Which conversation was rated
            $table->tinyInteger('rate')->unsigned(); // Rating value (0-5)
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('rated_user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('conversation_id')->references('id')->on('conversations')->onDelete('cascade');

            // Unique constraint to prevent duplicate ratings for same conversation
            $table->unique('conversation_id');
            
            // Index for faster queries
            $table->index(['rated_user_id', 'rate']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rates');
    }
};
