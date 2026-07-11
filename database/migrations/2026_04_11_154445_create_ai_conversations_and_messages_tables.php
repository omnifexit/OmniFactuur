<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_conversations', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('user_id');
            $table->string('title')->nullable();
            $table->string('model', 100)->nullable();
            $table->timestamps();

            // List my conversations, most recently updated first
            $table->index(['company_id', 'user_id', 'updated_at']);
        });

        Schema::create('ai_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')
                ->constrained('ai_conversations')
                ->cascadeOnDelete();

            // OpenAI chat message roles. Persisted as string (not enum) so future
            // roles don't require a migration — the application layer validates.
            $table->string('role', 20);

            $table->longText('content')->nullable();

            // For role=tool messages: which tool_call_id from the assistant turn this answers.
            $table->string('tool_call_id')->nullable();

            // For role=assistant messages that requested tool execution: the parsed tool_calls array.
            $table->json('tool_calls')->nullable();

            // Which model produced this turn (nullable for user/tool messages).
            $table->string('model', 100)->nullable();

            // For future cost tracking dashboards.
            $table->unsignedInteger('tokens_in')->nullable();
            $table->unsignedInteger('tokens_out')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['conversation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_messages');
        Schema::dropIfExists('ai_conversations');
    }
};
