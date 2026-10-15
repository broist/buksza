<?php

namespace Tests\Feature;

use App\Finance\AnnualExport;
use App\Finance\FinanceBackup;
use App\Finance\PhaseFourReports;
use App\Models\Account;
use App\Models\Investment;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PhaseFourTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->withoutVite();
        $this->owner = User::factory()->create(['password' => 'phase-four-password']);
        $this->actingAs($this->owner);
        $this->travelTo(now()->setDate(2026, 10, 15));
    }

    private function tx(array $extra = []): Transaction
    {
        $data = ['client_id' => (string) Str::uuid(), 'date' => '2026-10-15', 'type' => 'expense', 'amount' => 1000, 'category_id' => 1, 'business' => false, 'account_id' => 1, 'fee_amount' => 0, ...$extra];
        $this->post('/tetelek', $data)->assertSessionHasNoErrors();

        return Transaction::where('client_id', $data['client_id'])->firstOrFail();
    }

    private function investment(): Investment
    {
        $this->post('/befektetesek', ['account_id' => 8, 'name' => 'Örs 2044', 'series' => '2044/S', 'beneficiary' => 'Örs', 'start_date' => '2026-01-01', 'opening_amount' => 50000])->assertSessionHasNoErrors();

        return Investment::latest('id')->first();
    }

    public function test_pockets_count_funding_and_direct_anna_once_but_internal_cash_separately(): void
    {
        $this->tx(['type' => 'transfer', 'amount' => 30000, 'target_account_id' => 4, 'fee_amount' => 200]);
        $this->tx(['account_id' => 4, 'amount' => 3456]);
        $this->tx(['person' => 'Anna', 'category_id' => 21, 'amount' => 5000]);
        $r = app(PhaseFourReports::class)->pockets(2026, Transaction::all()->toArray(), Account::all()->toArray());
        $this->assertSame(35000, $r['total']);
        $this->assertSame(3456, $r['internalTotal']);
        $this->assertSame(30000, $r['months'][10]['Isti']);
        $this->assertSame(5000, $r['totals']['Anna']);
        $this->get('/eves?year=2026&month=10')->assertOk()->assertInertia(fn ($page) => $page->where('annual.expense', 35200)->where('pockets.total', 35000)->etc());
    }

    public function test_investments_link_existing_payments_once_keep_plan_history_and_value_separate(): void
    {
        $i = $this->investment();
        $tx = $this->tx(['type' => 'child_saving', 'target_account_id' => 8, 'amount' => 12345]);
        $this->post("/befektetesek/$i->id/kapcsolat", ['transaction_id' => $tx->id])->assertSessionHasNoErrors();
        $this->post("/befektetesek/$i->id/kapcsolat", ['transaction_id' => $tx->id])->assertSessionHasNoErrors();
        $this->post("/befektetesek/$i->id/terv", ['effective_date' => '2026-01-01', 'amount' => 10000])->assertSessionHasNoErrors();
        $this->post("/befektetesek/$i->id/terv", ['effective_date' => '2026-10-01', 'amount' => 12000])->assertSessionHasNoErrors();
        $this->post("/befektetesek/$i->id/ertek", ['date' => '2026-10-15', 'amount' => 70000])->assertSessionHasNoErrors();
        $report = app(PhaseFourReports::class)->investments(2026, 10, Transaction::all()->toArray());
        $this->assertDatabaseCount('transactions', 1);
        $this->assertSame(12345, $report['annualActual']);
        $this->assertSame(70000, $report['value']);
        $this->assertSame(62345, $report['items'][0]['contributed']);
        $this->assertSame(10000, $report['items'][0]['months'][9]['plan']);
        $this->assertSame(12000, $report['items'][0]['months'][10]['plan']);
        $this->assertSame(0, $report['items'][0]['months'][9]['actual']);
        $old = app(PhaseFourReports::class)->investments(2026, 9, Transaction::all()->toArray());
        $this->assertNull($old['value']);
        $this->assertSame(50000, $old['items'][0]['contributed']);
        $this->delete("/tetelek/$tx->id")->assertSessionHasNoErrors();
        $this->assertSame(0, app(PhaseFourReports::class)->investments(2026, 10, Transaction::all()->toArray())['annualActual']);
    }

    public function test_bond_links_reject_wrong_accounts_and_block_incompatible_edits(): void
    {
        $i = $this->investment();
        $expense = $this->tx();
        $this->post("/befektetesek/$i->id/kapcsolat", ['transaction_id' => $expense->id])->assertUnprocessable();
        $tx = $this->tx(['type' => 'child_saving', 'target_account_id' => 8]);
        $this->post("/befektetesek/$i->id/kapcsolat", ['transaction_id' => $tx->id])->assertSessionHasNoErrors();
        $this->put("/tetelek/$tx->id", ['client_id' => $tx->client_id, 'date' => $tx->date, 'amount' => 1000, 'account_id' => 1, 'category_id' => 1, 'type' => 'expense', 'business' => false])->assertSessionHasErrors('type');
        $this->post("/befektetesek/$i->id/terv", ['effective_date' => '2026-10-15', 'amount' => 10000])->assertUnprocessable();
        $this->post("/befektetesek/$i->id/ertek", ['date' => '2026-10-16', 'amount' => 10000])->assertSessionHasErrors('date');
    }

    public function test_exports_preserve_exact_cf_and_escape_csv_formulas_and_excel_strings(): void
    {
        $this->tx(['type' => 'income', 'source' => 'Munka', 'amount' => 10001, 'note' => '=HYPERLINK("test")']);
        DB::table('year_settings')->updateOrInsert(['year' => 2026], ['opening_amount' => 0, 'savings_rate' => '12.3456', 'created_at' => now(), 'updated_at' => now()]);
        $export = app(AnnualExport::class);
        $sheets = $export->sheets(2026);
        $this->assertSame('136973696/15625', $sheets['Összegzés'][4][1]);
        $csv = $this->get('/export?year=2026&format=csv')->assertOk()->assertHeader('Cache-Control', 'no-store, private')->getContent();
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $xlsx = $this->get('/export?year=2026&format=xlsx')->assertOk()->getContent();
        $path = tempnam(sys_get_temp_dir(), 'test-export-');
        file_put_contents($path, $xlsx);
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($path));
        $xml = $zip->getFromName('xl/worksheets/sheet4.xml');
        $this->assertStringContainsString('t="inlineStr"', $xml);
        $this->assertStringContainsString('=HYPERLINK', $xml);
        $this->assertStringNotContainsString('<f>', $xml);
        $zip->close();
        unlink($path);
    }

    public function test_encrypted_backup_restore_is_atomic_preserves_login_and_makes_previous_copy(): void
    {
        Storage::fake('local');
        $i = $this->investment();
        $tx = $this->tx(['type' => 'child_saving', 'target_account_id' => 8]);
        $this->post("/befektetesek/$i->id/kapcsolat", ['transaction_id' => $tx->id])->assertSessionHasNoErrors();
        $original = app(FinanceBackup::class)->create();
        $this->assertStringNotContainsString('Örs 2044', $original);
        $this->tx(['amount' => 7777]);
        $upload = UploadedFile::fake()->createWithContent('penzugyek.buksza', $original);
        $this->post('/visszaallitas', ['backup' => $upload, 'confirmed' => true, 'password' => 'phase-four-password'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('transactions', 1);
        $this->assertDatabaseHas('transactions', ['id' => $tx->id, 'investment_id' => $i->id]);
        $this->assertAuthenticatedAs($this->owner);
        $this->assertCount(1, Storage::disk('local')->files('finance-backups'));
        $this->get('/mentes/elozo')->assertOk();
        $this->assertEmpty(DB::select('PRAGMA foreign_key_check'));
    }

    public function test_restore_rejects_wrong_password_corruption_and_rolls_back_invalid_references(): void
    {
        Storage::fake('local');
        $tx = $this->tx();
        $backup = app(FinanceBackup::class);
        $contents = $backup->create();
        $this->post('/visszaallitas', ['backup' => UploadedFile::fake()->createWithContent('x', $contents), 'confirmed' => true, 'password' => 'wrong'])->assertSessionHasErrors('password');
        $this->post('/visszaallitas', ['backup' => UploadedFile::fake()->createWithContent('x', $contents.'bad'), 'confirmed' => true, 'password' => 'phase-four-password'])->assertSessionHasErrors('backup');
        $data = $backup->decode($contents);
        $data['tables']['transactions'][0]['account_id'] = 999999;
        $bad = Crypt::encryptString(gzencode(json_encode($data)));
        $this->post('/visszaallitas', ['backup' => UploadedFile::fake()->createWithContent('x', $bad), 'confirmed' => true, 'password' => 'phase-four-password'])->assertSessionHasErrors('backup');
        $this->assertDatabaseHas('transactions', ['id' => $tx->id, 'account_id' => 1]);
    }

    public function test_phase_four_writes_and_downloads_require_authentication(): void
    {
        auth()->logout();
        foreach (['/eves', '/zsebpenzek', '/befektetesek', '/mentes', '/export?year=2026&format=csv'] as $url) {
            $this->get($url)->assertRedirect('/belepes');
        }
        $this->post('/visszaallitas')->assertRedirect('/belepes');
    }

    public function test_restore_stops_if_the_safety_copy_cannot_be_written(): void
    {
        $tx = $this->tx();
        $backup = app(FinanceBackup::class);
        $data = $backup->decode($backup->create());
        $this->tx(['amount' => 7777]);
        $disk = \Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('put')->once()->andReturn(false);
        Storage::shouldReceive('disk')->with('local')->once()->andReturn($disk);
        try {
            $backup->restore($data);
            $this->fail('A missing safety copy must stop restore.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('backup', $e->errors());
        }
        $this->assertDatabaseCount('transactions', 2);
        $this->assertDatabaseHas('transactions', ['id' => $tx->id]);
    }

    public function test_multiple_bonds_keep_unassigned_payments_and_historical_unknown_values_separate(): void
    {
        $one = $this->investment();
        $two = $this->investment();
        $a = $this->tx(['type' => 'child_saving', 'target_account_id' => 8, 'amount' => 8000]);
        $b = $this->tx(['type' => 'child_saving', 'target_account_id' => 8, 'amount' => 4000]);
        $this->tx(['type' => 'child_saving', 'target_account_id' => 8, 'amount' => 3000]);
        $this->post("/befektetesek/$one->id/kapcsolat", ['transaction_id' => $a->id]);
        $this->post("/befektetesek/$two->id/kapcsolat", ['transaction_id' => $b->id]);
        $this->post("/befektetesek/$two->id/kapcsolat", ['transaction_id' => $a->id])->assertUnprocessable();
        $this->post("/befektetesek/$one->id/ertek", ['date' => '2026-10-15', 'amount' => 60000]);
        $reports = app(PhaseFourReports::class);
        $r = $reports->investments(2026, 10, Transaction::all()->toArray());
        $this->assertSame(15000, $r['annualActual']);
        $this->assertSame(8000, $r['items'][0]['annualActual']);
        $this->assertSame(4000, $r['items'][1]['annualActual']);
        $this->assertCount(1, $r['unassigned']);
        $this->assertNull($r['value']);
        $this->post("/befektetesek/$two->id/ertek", ['date' => '2026-10-15', 'amount' => 40000]);
        $this->assertSame(100000, $reports->investments(2026, 10, Transaction::all()->toArray())['value']);
        $old = $reports->investments(2025, 12, Transaction::all()->toArray());
        $this->assertNull($old['items'][0]['contributed']);
        $this->assertNull($old['value']);
        $this->assertSame(0, $old['annualActual']);
    }
}
