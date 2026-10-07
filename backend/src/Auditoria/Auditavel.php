<?php

declare(strict_types=1);

namespace App\Auditoria;

/**
 * Entidades que implementam esta interface têm criação, alteração e exclusão
 * registradas automaticamente na trilha de auditoria (ver AuditoriaListener).
 */
interface Auditavel
{
    /** Nome do tipo para leitura humana. Ex.: "Funcionário". */
    public static function tipoAuditoria(): string;

    /** Identifica o registro para quem lê o log. Ex.: "Ana Souza (100322)". */
    public function rotuloAuditoria(): string;
}
