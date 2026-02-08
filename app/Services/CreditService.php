<?php

namespace App\Services;

use App\Models\User;
use App\Models\CreditTransaction;
use Illuminate\Support\Facades\DB;
use Exception;

class CreditService
{
    /**
     * Deduct credits from user balance defined in integer amount.
     * Uses atomic locking to prevent race conditions.
     */
    public function withdraw(User $user, int $amount, string $type = 'usage', array $metadata = []): CreditTransaction
    {
        return DB::transaction(function () use ($user, $amount, $type, $metadata) {
            // Lock user row for update
            $user = User::where('id', $user->id)->lockForUpdate()->first();

            if ($user->credit_balance < $amount) {
                throw new Exception("Insufficient credits. Balance: {$user->credit_balance}, Required: {$amount}");
            }

            $user->credit_balance -= $amount;
            $user->save();

            return CreditTransaction::create([
                'user_id' => $user->id,
                'amount' => -$amount,
                'type' => $type,
                'balance_after' => $user->credit_balance,
                'metadata' => $metadata,
            ]);
        });
    }

    /**
     * Add credits to user balance.
     */
    public function deposit(User $user, int $amount, string $type = 'purchase', array $metadata = []): CreditTransaction
    {
        return DB::transaction(function () use ($user, $amount, $type, $metadata) {
            $user = User::where('id', $user->id)->lockForUpdate()->first();

            $user->credit_balance += $amount;
            $user->save();

            return CreditTransaction::create([
                'user_id' => $user->id,
                'amount' => $amount,
                'type' => $type,
                'balance_after' => $user->credit_balance,
                'metadata' => $metadata,
            ]);
        });
    }
}
