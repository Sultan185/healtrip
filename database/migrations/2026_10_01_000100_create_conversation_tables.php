<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Conversation state: an append-only transcript, an audit log of every tool
 * call, and the recommendations the agent presented.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('owner_hash', 64)->index();
            $table->string('locale', 2)->default('en');
            // The model provider this conversation runs on, fixed at creation:
            // a transcript holds provider-specific content (signed reasoning
            // blocks, tool-call IDs) that another provider cannot continue.
            $table->string('provider');
            $table->string('urgency')->nullable();
            $table->unsignedSmallInteger('turns')->default(0);
            $table->timestamps();
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('conversation_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('turn');
            $table->string('role');
            $table->string('kind');
            $table->json('content');
            $table->text('visible_text')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['conversation_id', 'id']);
        });

        Schema::create('tool_calls', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('conversation_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('turn');
            $table->string('tool_use_id');
            $table->string('name');
            $table->json('input');
            $table->json('output');
            $table->boolean('is_error')->default(false);
            $table->json('doctor_ids')->nullable();
            $table->json('hospital_ids')->nullable();
            $table->unsignedInteger('duration_ms');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['conversation_id', 'turn']);
        });

        Schema::create('recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('conversation_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('turn');
            $table->string('urgency');
            $table->string('care_path');
            $table->string('specialty')->nullable();
            $table->text('summary');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['conversation_id', 'turn']);
        });

        Schema::create('doctor_recommendation', function (Blueprint $table) {
            $table->foreignId('recommendation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('doctor_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('position');
            $table->primary(['recommendation_id', 'doctor_id']);
        });

        Schema::create('hospital_recommendation', function (Blueprint $table) {
            $table->foreignId('recommendation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('position');
            $table->primary(['recommendation_id', 'hospital_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hospital_recommendation');
        Schema::dropIfExists('doctor_recommendation');
        Schema::dropIfExists('recommendations');
        Schema::dropIfExists('tool_calls');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversations');
    }
};
