<?php

namespace App\Policies;

use App\Models\ReadingPlan;
use App\Models\User;

/**
 * 読書計画は計画を立てた本人だけが触れる。読了した計画は期日の変更・読了の押し直しをさせない（削除はできる）。
 */
class ReadingPlanPolicy
{
    /**
     * 期日を変更できるのは計画を立てた本人で、読了していない計画だけ。
     *
     * @param  User  $user  操作する人
     * @param  ReadingPlan  $plan  対象の読書計画
     * @return bool 許可するなら true
     */
    public function update(User $user, ReadingPlan $plan): bool
    {
        return $this->owns($user, $plan) && ! $plan->isCompleted();
    }

    /**
     * 読了にできる人は、期日を変更できる人と同じ。
     *
     * @param  User  $user  操作する人
     * @param  ReadingPlan  $plan  対象の読書計画
     * @return bool 許可するなら true
     */
    public function complete(User $user, ReadingPlan $plan): bool
    {
        return $this->update($user, $plan);
    }

    /**
     * 削除は計画を立てた本人なら読了後もできる。
     *
     * @param  User  $user  操作する人
     * @param  ReadingPlan  $plan  対象の読書計画
     * @return bool 許可するなら true
     */
    public function delete(User $user, ReadingPlan $plan): bool
    {
        return $this->owns($user, $plan);
    }

    /**
     * 計画を立てた本人かを返す。
     *
     * @param  User  $user  操作する人
     * @param  ReadingPlan  $plan  対象の読書計画
     * @return bool 許可するなら true
     */
    private function owns(User $user, ReadingPlan $plan): bool
    {
        return (int) $user->id === (int) $plan->user_id;
    }
}
