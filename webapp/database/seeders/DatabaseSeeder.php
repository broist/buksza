<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['élelmiszer', 'bolti bevásárlás|online rendelés|zöldség-gyümölcs|pék', '#367B5D'],
            ['étkezés kint', 'étterem|gyorsétterem|kávézó|kiszállítás', '#C97543'],
            ['lakhatás – rezsi', 'áram/gáz|lakásfelszerelés|karbantartás', '#537997'],
            ['utazás', 'tömegközlekedés|szállás|nyaralás/külföldi költés', '#568F89'],
            ['autó', 'üzemanyag|parkolás|autópálya-matrica|szerviz/javítás|autómosó', '#8C7D50'],
            ['tisztálkodás', 'fodrász|tisztítószer|drogéria', '#AC7C96'],
            ['ruházat', 'ruha|cipő', '#9D8260'],
            ['gyógyszer / vitamin', 'patika|vitamin', '#5D9289'],
            ['orvos / vizsgálat', 'labor|magánorvos|egyéb egészségügyi szolgáltatás', '#BF727A'],
            ['előfizetések', 'streaming|zene|Apple/felhő|AI és fejlesztői eszközök|tárhely/domain', '#7B78B0'],
            ['sportolás', 'edzés|egészség', '#6C9462'],
            ['önfejlesztés', 'tanfolyam|tanúsítvány/újraminősítés|könyv', '#838FA9'],
            ['hitel', 'törlesztés|extra törlesztés', '#A87B4C'],
            ['biztosítás', 'életbiztosítás|hitelfedezeti védelem|kezességvállalási díj|egyéb biztosítás', '#678493'],
            ['telefon', 'telefon|internet/TV', '#7A8B64'],
            ['család', 'rendszeres családi átutalás|gyerek|ajándék', '#B58B64'],
            ['szórakozás', 'mozi|programok|játék', '#9E79A7'],
            ['adó / hatósági / banki díj', 'adó|hatósági befizetés|csekk|banki költség', '#87928B'],
            ['készpénz (felosztandó)', 'ATM-felvét', '#9A8E72'],
            ['egyéb / besorolatlan', '', '#969B9A'],
            ['zsebpénz', 'Isti|Anna', '#C28C61'], ['adakozás', '', '#9085A1'], ['egyetem', '', '#70879C'],
        ];
        foreach ($categories as $i => [$name, $sub, $color]) {
            Category::firstOrCreate(['id' => $i + 1], ['name' => $name, 'color' => $color, 'position' => $i, 'active' => $i < 21, 'carryover' => true, 'subcategories' => $sub === '' ? [] : explode('|', $sub), 'default_amount' => 0]);
        }
        DB::table('accounts')->insertOrIgnore(['id' => 1, 'name' => 'CIB főszámla', 'owner' => 'Isti', 'kind' => 'bank', 'mode' => 'detailed', 'opening_balance' => 0, 'opening_date' => now()->startOfYear()->toDateString(), 'created_at' => now(), 'updated_at' => now()]);
    }
}
