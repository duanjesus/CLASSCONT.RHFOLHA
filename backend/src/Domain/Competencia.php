<?php

declare(strict_types=1);

namespace App\Domain;

/** Value object para um mês de referência da folha (ex.: "2026-09"). */
final readonly class Competencia implements \Stringable
{
    private function __construct(
        public int $ano,
        public int $mes,
    ) {
        if ($mes < 1 || $mes > 12 || $ano < 2000 || $ano > 2100) {
            throw new \InvalidArgumentException('Competência inválida.');
        }
    }

    public static function de(int $ano, int $mes): self
    {
        return new self($ano, $mes);
    }

    /** @throws \InvalidArgumentException */
    public static function fromString(string $valor): self
    {
        if (!preg_match('/^(\d{4})-(\d{2})$/', $valor, $m)) {
            throw new \InvalidArgumentException('Competência deve estar no formato AAAA-MM.');
        }

        return new self((int) $m[1], (int) $m[2]);
    }

    public static function daData(\DateTimeInterface $data): self
    {
        return new self((int) $data->format('Y'), (int) $data->format('n'));
    }

    public function primeiroDia(): \DateTimeImmutable
    {
        return new \DateTimeImmutable(\sprintf('%04d-%02d-01 00:00:00', $this->ano, $this->mes));
    }

    public function ultimoDia(): \DateTimeImmutable
    {
        return $this->primeiroDia()->modify('last day of this month');
    }

    /** Instante exclusivo de fim do mês, para consultas "momento < fim". */
    public function inicioDoProximoMes(): \DateTimeImmutable
    {
        return $this->primeiroDia()->modify('first day of next month');
    }

    /** @return \Generator<\DateTimeImmutable> */
    public function dias(): \Generator
    {
        $fim = $this->inicioDoProximoMes();
        for ($d = $this->primeiroDia(); $d < $fim; $d = $d->modify('+1 day')) {
            yield $d;
        }
    }

    public function contem(\DateTimeInterface $data): bool
    {
        return $data->format('Y-m') === (string) $this;
    }

    public function anterior(): self
    {
        return self::daData($this->primeiroDia()->modify('-1 month'));
    }

    public function posterior(): self
    {
        return self::daData($this->primeiroDia()->modify('+1 month'));
    }

    public function antesDe(self $outra): bool
    {
        return (string) $this < (string) $outra;
    }

    public function __toString(): string
    {
        return \sprintf('%04d-%02d', $this->ano, $this->mes);
    }
}
