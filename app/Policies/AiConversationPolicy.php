<?php

namespace App\Policies;

use App\Models\AiConversation;
use App\Models\User;

/**
 * Conversation visibility is strictly per-user per-company.
 *
 * Every action requires (a) the user is a member of the conversation's
 * company AND (b) the user IS the conversation's owner. We do not leak
 * conversations between users — the whole point of the per-user scope
 * is privacy within a shared company workspace.
 */
class AiConversationPolicy
{
    public function view(User $user, AiConversation $conversation): bool
    {
        return $this->owns($user, $conversation);
    }

    public function update(User $user, AiConversation $conversation): bool
    {
        return $this->owns($user, $conversation);
    }

    public function delete(User $user, AiConversation $conversation): bool
    {
        return $this->owns($user, $conversation);
    }

    protected function owns(User $user, AiConversation $conversation): bool
    {
        return $conversation->user_id === $user->id
            && $user->hasCompany($conversation->company_id);
    }
}
