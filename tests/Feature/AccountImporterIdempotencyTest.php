<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\CashMovement;
use App\Services\Import\AccountImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountImporterIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    /** Write a DEGIRO account-ledger CSV to a temp file and return its path. */
    private function writeCsv(array $dataRows): string
    {
        $header = 'Datum,Tijd,Valutadatum,Product,ISIN,Omschrijving,FX,Mutatie,,Saldo,,Order Id';
        $path = tempnam(sys_get_temp_dir(), 'degiro').'.csv';
        file_put_contents($path, $header."\n".implode("\n", $dataRows)."\n");

        return $path;
    }

    public function test_reimporting_the_same_export_does_not_duplicate(): void
    {
        $account = Account::factory()->create();
        $rows = [
            '02-01-2024,10:00,02-01-2024,Some Stock,US1234567890,Dividend,,EUR,"10,00",EUR,"100,00",',
            '03-01-2024,09:00,03-01-2024,,,iDEAL Deposit,,EUR,"500,00",EUR,"600,00",',
        ];

        $first = (new AccountImporter)->import($account, $this->writeCsv($rows));
        $this->assertSame(2, $first->inserted);
        $this->assertSame(0, $first->skipped);

        $second = (new AccountImporter)->import($account, $this->writeCsv($rows));
        $this->assertSame(0, $second->inserted);
        $this->assertSame(2, $second->skipped);

        $this->assertDatabaseCount('cash_movements', 2);
        $this->assertNull(CashMovement::whereNull('dedupe_hash')->first());
    }

    public function test_reimport_with_reformatted_amount_still_dedupes(): void
    {
        $account = Account::factory()->create();

        (new AccountImporter)->import($account, $this->writeCsv([
            '02-01-2024,10:00,02-01-2024,Some Stock,US1234567890,Dividend,,EUR,"10,00",EUR,"100,00",',
        ]));

        // Same movement, but the re-export writes "10,0" instead of "10,00".
        $result = (new AccountImporter)->import($account, $this->writeCsv([
            '02-01-2024,10:00,02-01-2024,Some Stock,US1234567890,Dividend,,EUR,"10,0",EUR,"100,00",',
        ]));

        $this->assertSame(0, $result->inserted);
        $this->assertDatabaseCount('cash_movements', 1);
    }
}
