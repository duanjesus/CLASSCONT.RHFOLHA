<?php

declare(strict_types=1);

namespace App\Domain\Ponto;

use App\Enum\SituacaoDia;

final readonly class DiaEspelho
{
    /**
     * @param list<\DateTimeImmutable> $batidas
     */
    public function __construct(
        public \DateTimeImmutable $data,
        public array $batidas,
        public int $trabalhadoMinutos,
        public int $esperadoMinutos,
        public int $saldoMinutos,
        public SituacaoDia $situacao,
        public ?string $observacao = null,
    ) {
    }

    public function teveBatida(): bool
    {
        return [] !== $this->batidas;
    }
}
