<?php

namespace App\Core;

use PDO;
use PDOException;
use PDOStatement;

/**
 * PDO configurado para modo de exceção, com contratos de retorno estritos.
 *
 * A aplicação sempre usa PDO::ERRMODE_EXCEPTION. Portanto, query/prepare/exec
 * não devem propagar o ramo `false` dos tipos legados do PDO: uma falha é
 * convertida em exceção, mantendo o restante do código e a análise estática
 * coerentes com o comportamento real da conexão.
 */
final class StrictPDO extends PDO
{
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement
    {
        $statement = $fetchMode === null
            ? parent::query($query)
            : parent::query($query, $fetchMode, ...$fetchModeArgs);

        if ($statement === false) {
            throw new PDOException('Falha ao executar consulta no banco de dados.');
        }

        return $statement;
    }

    public function prepare(string $query, array $options = []): PDOStatement
    {
        $statement = parent::prepare($query, $options);
        if ($statement === false) {
            throw new PDOException('Falha ao preparar consulta no banco de dados.');
        }

        return $statement;
    }

    public function exec(string $statement): int
    {
        $result = parent::exec($statement);
        if ($result === false) {
            throw new PDOException('Falha ao executar comando no banco de dados.');
        }

        return $result;
    }
}
