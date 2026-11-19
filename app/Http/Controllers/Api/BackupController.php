<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\GerarBackup;
use App\Services\BackupService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Somente administradores */
class BackupController extends Controller
{
    public function index(BackupService $backup): JsonResponse
    {
        return response()->json($backup->listar());
    }

    public function store(): JsonResponse
    {
        GerarBackup::dispatch();

        return response()->json(['message' => 'Backup iniciado. Ele aparece na lista em alguns instantes.'], 202);
    }

    public function download(string $nome, BackupService $backup): BinaryFileResponse
    {
        $caminho = $backup->caminho($nome) ?? abort(404);

        return response()->download($caminho, $nome);
    }
}
