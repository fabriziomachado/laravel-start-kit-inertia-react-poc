<?php

declare(strict_types=1);

namespace App\Data\Sau;

use Uepg\LaravelSybase\Contracts\RpcResultDto;

/**
 *  "cd_retorno" => 0
 *  "msg_retorno" => "Login Válido."
 *  "cd_pessoa" => 38781
 *  "nome_pessoa" => "ALESSANDRA CAMPOS GOULART"
 *  "cpf_pessoa" => "02025153970"
 *  "dt_alteracao_senha" => null
 *  "dt_atualizacao_cadastro" => "2025-03-05 08:36:34"
 */
final readonly class PrssLoginR01ResultDto implements RpcResultDto
{
    public function __construct(
        public int $errorCode,
        public string $errorMessage,
        public ?string $idNumber,
        public ?string $name,
        public ?string $cpf,
        public ?string $lastPasswordChangeDate,
        public ?string $lastUpdateDatetime,
    ) {}

    public static function fromArray(array $row): self
    {
        return new self(
            errorCode: (int) ($row['cd_retorno'] ?? 0),
            errorMessage: (string) ($row['msg_retorno'] ?? ''),
            idNumber: ($row['cd_pessoa'] ?? null) !== null ? (string) $row['cd_pessoa'] : null,
            name: ($row['nome_pessoa'] ?? null) !== null ? (string) $row['nome_pessoa'] : null,
            cpf: ($row['cpf_pessoa'] ?? null) !== null ? (string) $row['cpf_pessoa'] : null,
            lastPasswordChangeDate: ($row['dt_alteracao_senha'] ?? null) !== null ? (string) $row['dt_alteracao_senha'] : null,
            lastUpdateDatetime: ($row['dt_atualizacao_cadastro'] ?? null) !== null ? (string) $row['dt_atualizacao_cadastro'] : null,
        );
    }
}
