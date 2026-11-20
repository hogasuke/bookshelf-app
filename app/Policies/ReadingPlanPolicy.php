<?php

namespace App\Policies;

use App\Models\ReadingPlan;
use App\Models\User;

/**
 * 読書計画は計画を立てた本人だけが触れる。読了した計画は期日の変更・読了の押し直しをさせない（削除はできる）。
 */
class ReadingPlanPolicy
{
    public function update(User $user, ReadingPlan $plan): bool
    {
        return $this->owns($user, $plan) && ! $plan->isCompleted();
    }

    public function complete(User $user, ReadingPlan $plan): bool
    {
        return $this->update($user, $plan);
    }

    public function delete(User $user, ReadingPlan $plan): bool
    {
        return $this->owns($user, $plan);
    }

    private function owns(User $user, ReadingPlan $plan): bool
    {
        return (int) $user->id === (int) $plan->user_id;
    }
}
