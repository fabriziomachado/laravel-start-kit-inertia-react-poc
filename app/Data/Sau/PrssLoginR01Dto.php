<?php

declare(strict_types=1);

namespace App\Data\Sau;

use Illuminate\Contracts\Support\Arrayable;

final readonly class PrssLoginR01Dto implements Arrayable
{
    public function __construct(
        public string $credentialId,
        public string $credentialType,
        public string $password,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'cd_pessoa_p' => $this->credentialId,
            'tp_cd_pessoa' => $this->credentialType,
            'senha' => $this->password,
        ];
    }
}
