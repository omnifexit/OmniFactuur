<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One message in an AI conversation.
 *
 * Column shapes match OpenAI's chat message format exactly so that
 * AiAssistantService can serialize a conversation into an API request
 * payload with no translation layer. Supported roles:
 *
 *   - user       — human-authored prompt
 *   - assistant  — model-generated reply (may carry tool_calls instead of content)
 *   - tool       — result of a tool invocation, tied to an assistant's tool_call_id
 *   - system     — persisted system prompts (not typically stored; reserved for future)
 */
class AiMessage extends Model
{
    use HasFactory;

    public const ROLE_USER = 'user';

    public const ROLE_ASSISTANT = 'assistant';

    public const ROLE_TOOL = 'tool';

    public const ROLE_SYSTEM = 'system';

    public const UPDATED_AT = null;  // created_at only — messages are immutable once written

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'tool_calls' => 'array',
            'tokens_in' => 'integer',
            'tokens_out' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'conversation_id');
    }
}
