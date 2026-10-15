<?php

namespace App\Http\Controllers;

use App\Finance\FinanceBackup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BackupController extends Controller
{
    public function download(FinanceBackup $backup)
    {
        return response($backup->create())->header('Content-Type', 'application/octet-stream')->header('Content-Disposition', 'attachment; filename="buksza-penzugyek-'.now()->format('Ymd-His').'.buksza"')->header('Cache-Control', 'private, no-store');
    }

    public function previous()
    {
        $files = Storage::disk('local')->files('finance-backups');
        rsort($files);
        abort_unless($files, 404);

        return response(Storage::disk('local')->get($files[0]))->header('Content-Type', 'application/octet-stream')->header('Content-Disposition', 'attachment; filename="buksza-visszaallitas-elotti.buksza"')->header('Cache-Control', 'private, no-store');
    }

    public function restore(Request $request, FinanceBackup $backup)
    {
        $request->validate(['backup' => 'required|file|max:10240', 'password' => 'required|current_password', 'confirmed' => 'accepted']);
        $data = $backup->decode($request->file('backup')->getContent());
        $backup->restore($data);

        return redirect('/beallitasok')->with('message', 'Pénzügyek visszaállítva. Előtte automatikus biztonsági mentés készült; a belépésed és eszközeid megmaradtak.');
    }
}
