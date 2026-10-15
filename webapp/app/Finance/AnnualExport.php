<?php

namespace App\Finance;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use ZipArchive;

final class AnnualExport
{
    public function sheets(int $year): array
    {
        app(PlanStore::class)->ensureYear($year);
        $categories = Category::orderBy('position')->get()->toArray();
        $accounts = Account::all()->keyBy('id');
        $transactions = Transaction::whereBetween('date', ["$year-01-01", "$year-12-31"])->orderBy('date')->orderBy('id')->get()->toArray();
        foreach ($transactions as &$tx) {
            $tx['person'] ??= $accounts[$tx['account_id']]->owner;
            $tx['fee_person'] = $accounts[$tx['account_id']]->owner;
        }
        unset($tx);
        $plans = [];
        foreach (DB::table('monthly_plans')->where('year', $year)->get() as $plan) {
            $plans[$plan->month][$plan->category_id] = $plan->amount;
        }
        $report = app(Calculator::class)->year($year, $categories, $plans, $transactions, (array) DB::table('year_settings')->where('year', $year)->first(), now()->toDateString());
        $a = $report['annual'];
        $exact = fn ($value) => $value instanceof Exact ? (string) $value->number : $value;
        $summary = [['Év', $year], ['Mértékegység', 'Ft; a törtek pontos értékek, kerekítés nélkül'], ['Éves bevétel', $a['income']], ['Éves kiadás', $a['expense']], ['Éves CF', $exact($a['cf'])], ['Havi CF-átlag', $exact($a['monthlyCf'])], ['Napi CF-átlag', $exact($a['dailyCf'])], ['Családi megtakarítási cél', $exact($a['savingsTarget'])], ['Tényleges családi félretétel', $a['actualSavings']], ['Örs külön befizetése', $a['childSavings']], ['Év végi terv szerinti maradék', $exact($a['closing'])]];
        foreach ($a['loanFlows'] as $type => $value) {
            $summary[] = [match ($type) {
                'loan_given' => 'Éves kölcsönadás (− CF)', 'loan_given_return' => 'Éves visszakapott tőke (+ CF)', 'loan_received' => 'Éves felvett kölcsön (+ CF)', default => 'Éves személyes törlesztés (− CF)'
            }, $value];
        }
        $monthly = [['Hónap', 'Bevétel', 'Kiadás', 'Havi terv', 'Családi megtakarítási cél', 'Tényleges félretétel', 'Örs befizetése', 'CF', 'Terv szerinti maradék', 'Adott kölcsön (− CF)', 'Visszakapott tőke (+ CF)', 'Kapott kölcsön (+ CF)', 'Személyes törlesztés (− CF)']];
        foreach ($report['months'] as $m) {
            $monthly[] = [sprintf('%04d-%02d', $year, $m['month']), $m['income'], $m['expense'], $m['plan'], $exact($m['savingsTarget']), $m['actualSavings'], $m['childSavings'], $exact($m['cf']), $exact($m['closing']), ...array_values($m['loanFlows'])];
        }
        $categoryRows = [['Kategória', 'Éves terv', 'Éves tény', 'Tény mínusz terv', 'Havi átlag', 'Napi átlag']];
        foreach ($a['categories'] as $c) {
            $categoryRows[] = [$c['name'], $c['plan'], $c['spent'], $c['difference'], $exact($c['monthlyAverage']), $exact($c['dailyAverage'])];
        }
        $categoryNames = array_column($categories, 'name', 'id');
        $linkedNames = DB::table('position_transactions as l')->join('finance_positions as p', 'p.id', '=', 'l.position_id')->pluck('p.name', 'l.transaction_id');
        $ledger = [['Azonosító', 'Dátum', 'Típus', 'Forrásszámla', 'Célszámla', 'Személy', 'Kategória', 'Összeg', 'Banki díj', 'Családi elszámolás', 'Belső összegkövetés', 'Üzleti', 'Megjegyzés', 'Kölcsön / cél neve']];
        foreach ($transactions as $tx) {
            $ledger[] = [$tx['id'], $tx['date'], $tx['type'], $accounts[$tx['account_id']]->name, isset($accounts[$tx['target_account_id']]) ? $accounts[$tx['target_account_id']]->name : '', $tx['person'], $categoryNames[$tx['category_id']] ?? '', $tx['amount'], $tx['fee_amount'], $tx['family_type'], $tx['informational'] ? 'igen' : 'nem', $tx['business'] ? 'igen' : 'nem', $tx['note'] ?? '', $linkedNames[$tx['id']] ?? ''];
        }

        $pockets = app(PhaseFourReports::class)->pockets($year, $transactions, $accounts->values()->toArray());
        $pocketRows = [['Hónap', ...$pockets['people']]];
        for ($month = 1; $month <= 12; $month++) {
            $pocketRows[] = [sprintf('%04d-%02d', $year, $month), ...array_map(fn ($person) => $pockets['months'][$month][$person] ?? 0, $pockets['people'])];
        }
        $pocketRows[] = ['Éves összeg', ...array_map(fn ($person) => $pockets['totals'][$person] ?? 0, $pockets['people'])];
        $investments = app(PhaseFourReports::class)->investments($year, 12, Transaction::where('date', '<=', "$year-12-31")->get()->toArray());
        $investmentRows = [['Babakötvény', 'Sorozat', 'Kedvezményezett', 'Éves befizetési terv', 'Éves tényleges befizetés', 'Nyilvántartott befizetett tőke év végéig', 'Legutóbbi érték', 'Érték dátuma']];
        foreach ($investments['items'] as $item) {
            $investmentRows[] = [$item['name'], $item['series'] ?? '', $item['beneficiary'], $item['annualPlan'], $item['annualActual'], $item['contributed'] ?? 'Még nem indult', $item['latest']?->amount ?? 'Ismeretlen', $item['latest']?->date ?? ''];
        }

        $positions = app(Positions::class)->report("$year-12-31");
        $positionRows = [['Típus', 'Név / partner', 'Kezdőnap', 'Korábbi nyitóösszeg', 'Év végéig fennálló összeg', 'Teljes célösszeg', 'Havi jövedelemalap', 'Határidő', 'Megjegyzés']];
        foreach ($positions['items'] as $p) {
            $positionRows[] = [match ($p['kind']) {
                'receivable' => 'Nekem tartoznak', 'payable' => 'Én tartozom', 'reserve' => 'Hathavi tartalék', default => 'Megtakarítási cél'
            }, $p['name'], $p['start_date'], $p['opening_amount'], $p['balance'], $p['target'], $p['monthly_income'], $p['due_date'] ?? '', $p['note'] ?? ''];
        }
        $allocationRows = [['Dátum', 'Forráscél', 'Fogadó cél', 'Összeg', 'Megjegyzés']];
        $names = array_column($positions['items'], 'name', 'id');
        foreach (DB::table('goal_transfers')->where('date', '<=', "$year-12-31")->orderBy('date')->get() as $t) {
            $allocationRows[] = [$t->date, $names[$t->from_id], $names[$t->to_id], (int) $t->amount, $t->note ?? ''];
        }

        return ['Összegzés' => $summary, 'Hónapok' => $monthly, 'Kategóriák' => $categoryRows, 'Tételek' => $ledger, 'Zsebpénzek' => $pocketRows, 'Örs babakötvényei' => $investmentRows, 'Nyilvántartások' => $positionRows, 'Célátcsoportosítások' => $allocationRows];
    }

    public function csv(array $rows): string
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, "\xEF\xBB\xBF");
        foreach ($rows as $row) {
            $safe = array_map(fn ($value) => is_string($value) && preg_match('/^[\s]*[=+\-@]|^\d+\/\d+$/u', $value) ? "'".$value : $value, $row);
            fputcsv($stream, $safe, ';', '"', '', "\r\n");
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return $csv;
    }

    public function xlsx(array $sheets): string
    {
        $path = tempnam(sys_get_temp_dir(), 'buksza-xlsx-');
        try {
            $zip = new ZipArchive;
            if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException('Nem készíthető Excel-export.');
            }
            $escape = fn ($text) => htmlspecialchars(preg_replace('/[^\P{C}\t\n\r]/u', '', (string) $text), ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $workbook = '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';
            $rels = '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
            $types = '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>';
            $index = 0;
            foreach ($sheets as $name => $rows) {
                $index++;
                $workbook .= '<sheet name="'.$escape($name).'" sheetId="'.$index.'" r:id="rId'.$index.'"/>';
                $rels .= '<Relationship Id="rId'.$index.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$index.'.xml"/>';
                $types .= '<Override PartName="/xl/worksheets/sheet'.$index.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
                $xml = '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
                foreach ($rows as $row) {
                    $xml .= '<row>';
                    foreach ($row as $value) {
                        $xml .= is_int($value) ? '<c t="n"><v>'.$value.'</v></c>' : '<c t="inlineStr"><is><t xml:space="preserve">'.$escape($value).'</t></is></c>';
                    }
                    $xml .= '</row>';
                }
                $zip->addFromString('xl/worksheets/sheet'.$index.'.xml', $xml.'</sheetData></worksheet>');
            }
            $zip->addFromString('[Content_Types].xml', $types.'</Types>');
            $zip->addFromString('xl/workbook.xml', $workbook.'</sheets></workbook>');
            $zip->addFromString('xl/_rels/workbook.xml.rels', $rels.'</Relationships>');
            $zip->addFromString('_rels/.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
            $zip->close();

            return file_get_contents($path);
        } finally {
            unlink($path);
        }
    }
}
