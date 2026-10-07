<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\AcaoAuditoria;
use App\Repository\RegistroAuditoriaRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Uma linha da trilha de auditoria. Somente leitura para a aplicação:
 * quem grava é o AuditoriaListener, direto pelo DBAL, e não há setters.
 *
 * O nome do autor e o rótulo do registro são gravados como texto (fotografia do
 * momento): o log continua legível mesmo que o cadastro mude ou seja excluído.
 */
#[ORM\Entity(repositoryClass: RegistroAuditoriaRepository::class, readOnly: true)]
#[ORM\Index(name: 'idx_auditoria_ocorrido_em', columns: ['ocorrido_em'])]
#[ORM\Index(name: 'idx_auditoria_entidade', columns: ['entidade', 'entidade_id'])]
class RegistroAuditoria
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private \DateTimeImmutable $ocorridoEm;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Funcionario $autor = null;

    #[ORM\Column(length: 150)]
    private string $autorNome = '';

    #[ORM\Column(length: 20, enumType: AcaoAuditoria::class)]
    private AcaoAuditoria $acao;

    /** Tipo legível do registro alterado. Ex.: "Funcionário". */
    #[ORM\Column(length: 60)]
    private string $entidade = '';

    #[ORM\Column(length: 40)]
    private string $entidadeId = '';

    #[ORM\Column(length: 200)]
    private string $rotulo = '';

    /**
     * Campo => [valor antes, valor depois].
     *
     * @var array<string, array{0: mixed, 1: mixed}>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $alteracoes = [];

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ip = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOcorridoEm(): \DateTimeImmutable
    {
        return $this->ocorridoEm;
    }

    public function getAutor(): ?Funcionario
    {
        return $this->autor;
    }

    public function getAutorNome(): string
    {
        return $this->autorNome;
    }

    public function getAcao(): AcaoAuditoria
    {
        return $this->acao;
    }

    public function getEntidade(): string
    {
        return $this->entidade;
    }

    public function getEntidadeId(): string
    {
        return $this->entidadeId;
    }

    public function getRotulo(): string
    {
        return $this->rotulo;
    }

    /** @return array<string, array{0: mixed, 1: mixed}> */
    public function getAlteracoes(): array
    {
        return $this->alteracoes;
    }

    public function getIp(): ?string
    {
        return $this->ip;
    }
}
