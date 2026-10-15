<?php

namespace App\Http\Controllers;

use App\Finance\AnnualExport;
use Illuminate\Http\Request;

class ExportController extends Controller
{
    public function download(Request $request, AnnualExport $export)
    {
        $data = $request->validate(['year' => 'required|integer|between:2000,2100', 'format' => 'required|in:csv,xlsx', 'sheet' => 'sometimes|in:Összegzés,Hónapok,Kategóriák,Tételek,Zsebpénzek,Örs babakötvényei']);
        $sheets = $export->sheets($data['year']);
        $xlsx = $data['format'] === 'xlsx';
        $body = $xlsx ? $export->xlsx($sheets) : $export->csv($sheets[$data['sheet'] ?? 'Tételek']);

        return response($body)->header('Content-Type', $xlsx ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' : 'text/csv; charset=UTF-8')->header('Content-Disposition', 'attachment; filename="buksza-'.$data['year'].'.'.$data['format'].'"')->header('Cache-Control', 'private, no-store');
    }
}
