<?php

use App\Models\CashMovement;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Backfill dedupe_hash on legacy rows that were imported before the hash was
     * persisted. Rows with a NULL hash never matched the import idempotency check,
     * so re-imports duplicated them. Recompute the stable hash from stored fields;
     * where two rows collapse to the same (account_id, hash) they are true
     * duplicates from an earlier double-import, so keep the first and delete the rest.
     */
    public function up(): void
    {
        $seen = [];

        CashMovement::whereNull('dedupe_hash')
            ->orderBy('id')
            ->cursor()
            ->each(function (CashMovement $movement) use (&$seen): void {
                $hash = CashMovement::makeDedupeHash(
                    $movement->occurred_at,
                    (string) $movement->description,
                    $movement->amount,
                    (string) $movement->currency,
                );

                $key = $movement->account_id.'|'.$hash;

                $collides = isset($seen[$key])
                    || CashMovement::where('account_id', $movement->account_id)
                        ->where('dedupe_hash', $hash)
                        ->exists();

                if ($collides) {
                    $movement->delete();

                    return;
                }

                $seen[$key] = true;
                $movement->dedupe_hash = $hash;
                $movement->saveQuietly();
            });
    }

    public function down(): void
    {
        // One-off data backfill; nothing to reverse.
    }
};
