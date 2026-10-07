<?php

declare(strict_types=1);

namespace App\Auditoria;

/**
 * Converte valores de entidade em algo legível e serializável em JSON
 * para o "antes → depois" da auditoria. Classe pura, sem dependências.
 */
final class NormalizadorDeValores
{
    /** Campos que nunca têm o conteúdo gravado no log. */
    public const CAMPOS_SENSIVEIS = ['password'];
    public const MASCARA = '••••••';

    /**
     * Recebe o changeset do Doctrine (campo => [antes, depois]) e devolve só o que
     * mudou de fato, já normalizado. Campos sensíveis saem mascarados.
     *
     * @param array<string, array{0: mixed, 1: mixed}> $changeSet
     *
     * @return array<string, array{0: mixed, 1: mixed}>
     */
    public function alteracoes(array $changeSet): array
    {
        $resultado = [];
        foreach ($changeSet as $campo => [$antes, $depois]) {
            if (\in_array($campo, self::CAMPOS_SENSIVEIS, true)) {
                // Registra QUE mudou, nunca o valor (nem o hash)
                if (null !== $antes) {
                    $resultado[$campo] = [self::MASCARA, self::MASCARA];
                }
                continue;
            }

            $a = $this->normalizar($antes);
            $d = $this->normalizar($depois);
            if ($a !== $d) {
                $resultado[$campo] = [$a, $d];
            }
        }

        return $resultado;
    }

    public function normalizar(mixed $valor): mixed
    {
        return match (true) {
            null === $valor, \is_scalar($valor) => $valor,
            $valor instanceof \DateTimeInterface => '00:00:00' === $valor->format('H:i:s')
                ? $valor->format('d/m/Y')
                : $valor->format('d/m/Y H:i'),
            $valor instanceof \BackedEnum => method_exists($valor, 'label') ? $valor->label() : $valor->value,
            $valor instanceof Auditavel => $valor->rotuloAuditoria(),
            $valor instanceof \Stringable => (string) $valor,
            \is_array($valor) => [] === $valor ? null : implode(', ', array_map(strval(...), array_filter($valor, \is_scalar(...)))),
            default => get_debug_type($valor),
        };
    }
}
