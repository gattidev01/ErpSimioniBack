<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;

/** Próximo número sequencial (chamar dentro de DB::transaction) */
class Numeracao
{
    /** @param class-string<Model> $model */
    public static function proximo(string $model, int $inicial): int
    {
        // trava as linhas lidas para que duas gravações simultâneas não peguem o mesmo número
        $max = $model::query()->lockForUpdate()->max('numero');

        return $max ? ((int) $max) + 1 : $inicial;
    }
}
