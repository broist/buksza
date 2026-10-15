<?php

namespace App\Http\Controllers;

use App\Finance\AnnualExport;
use App\Finance\BusinessReportStore;
use Illuminate\Http\Request;

class ExportController extends Controller
{
    public function business(Request $request, BusinessReportStore $store, AnnualExport $export)
    {
        $data = $request->validate(['format' => 'required|in:csv,xlsx']);
        $report = $store->forRequest($request);
        $sheets = $store->sheets($report);
        $xlsx = $data['format'] === 'xlsx';

        return response($xlsx ? $export->xlsx($sheets) : $export->csv($sheets['Tételek']))
            ->header('Content-Type', $xlsx ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' : 'text/csv; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="buksza-riport-'.$report['from'].'-'.$report['to'].'.'.$data['format'].'"')
            ->header('Cache-Control', 'private, no-store');
    }

    public function download(Request $request, AnnualExport $export)
    {
        $data = $request->validate(['year' => 'required|integer|between:2000,2100', 'format' => 'required|in:csv,xlsx', 'sheet' => 'sometimes|in:Összegzés,Hónapok,Kategóriák,Tételek,Zsebpénzek,Örs babakötvényei,Nyilvántartások,Célátcsoportosítások']);
        $sheets = $export->sheets($data['year']);
        $xlsx = $data['format'] === 'xlsx';
        $body = $xlsx ? $export->xlsx($sheets) : $export->csv($sheets[$data['sheet'] ?? 'Tételek']);

        return response($body)->header('Content-Type', $xlsx ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' : 'text/csv; charset=UTF-8')->header('Content-Disposition', 'attachment; filename="buksza-'.$data['year'].'.'.$data['format'].'"')->header('Cache-Control', 'private, no-store');
    }
}
