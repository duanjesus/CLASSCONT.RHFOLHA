<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Domain\Competencia;
use App\Entity\Cargo;
use App\Entity\FechamentoCompetencia;
use App\Entity\Feriado;
use App\Entity\Funcionario;
use App\Entity\Justificativa;
use App\Entity\LinhaOnibus;
use App\Entity\RegistroPonto;
use App\Entity\Setor;
use App\Entity\SolicitacaoAuxilio;
use App\Enum\Sentido;
use App\Enum\TipoJustificativa;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Dados de demonstração relativos à data atual: mês anterior + mês corrente até ontem.
 * Todos os usuários usam a senha "senha123".
 */
final class AppFixtures extends Fixture
{
    public const SENHA_PADRAO = 'senha123';

    /** @var array<string, Funcionario> */
    private array $pessoas = [];
    /** @var array<array-key, LinhaOnibus> chave = código da linha (PHP converte "101" em int) */
    private array $linhas = [];
    private \DateTimeImmutable $hoje;

    public function __construct(
        private readonly UserPasswordHasherInterface $hasher,
        private readonly ClockInterface $clock,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        mt_srand(2026); // dados "aleatórios" porém reprodutíveis
        $this->hoje = \DateTimeImmutable::createFromInterface($this->clock->now())->setTime(0, 0);

        $feriados = $this->feriados($manager);
        $this->pessoasESetores($manager);
        $this->linhasDeOnibus($manager);

        $diasUteis = $this->diasUteisPassados($feriados);
        $n = \count($diasUteis);
        $faltaBruno = $diasUteis[$n - 3];
        $faltaCarla = $diasUteis[$n - 8];
        $incompletoAna = $diasUteis[$n - 5];
        $atestadoAna = $diasUteis[$n - 10];

        $this->batidas($manager, $diasUteis, [
            'bruno' => [$faltaBruno],
            'carla' => [$faltaCarla],
            'ana' => [$atestadoAna],
        ], $incompletoAna);

        $this->justificativas($manager, $faltaBruno, $faltaCarla, $atestadoAna);
        $this->auxilios($manager);

        $doisMesesAtras = Competencia::daData($this->hoje)->anterior()->anterior();
        $manager->persist(new FechamentoCompetencia((string) $doisMesesAtras, $this->pessoas['marina'], $this->hoje->modify('first day of this month')->modify('-1 month +3 days 10:00')));

        $manager->flush();
    }

    /** @return array<string, true> */
    private function feriados(ObjectManager $manager): array
    {
        $nacionais = [
            '01-01' => 'Confraternização Universal', '04-21' => 'Tiradentes', '05-01' => 'Dia do Trabalho',
            '09-07' => 'Independência do Brasil', '10-12' => 'Nossa Senhora Aparecida', '11-02' => 'Finados',
            '11-15' => 'Proclamação da República', '11-20' => 'Dia Nacional de Zumbi e da Consciência Negra',
            '12-25' => 'Natal',
        ];
        $moveis2026 = [
            '2026-02-16' => 'Carnaval', '2026-02-17' => 'Carnaval', '2026-04-03' => 'Sexta-feira Santa',
            '2026-06-04' => 'Corpus Christi',
        ];

        $mapa = [];
        $ano = (int) $this->hoje->format('Y');
        foreach ($nacionais as $mesDia => $descricao) {
            $mapa["$ano-$mesDia"] = $descricao;
        }
        if (2026 === $ano) {
            $mapa += $moveis2026;
        }

        foreach ($mapa as $data => $descricao) {
            $manager->persist((new Feriado())->setData(new \DateTimeImmutable($data))->setDescricao($descricao));
        }

        return array_map(static fn () => true, $mapa);
    }

    private function pessoasESetores(ObjectManager $manager): void
    {
        $cargos = [];
        foreach ([
            'coordenador' => ['Coordenador', '9800.00'],
            'analista' => ['Analista Administrativo', '6500.00'],
            'assistente' => ['Assistente Administrativo', '3800.00'],
            'tecnico' => ['Técnico de TI', '5200.00'],
        ] as $chave => [$nome, $salario]) {
            $cargos[$chave] = (new Cargo())->setNome($nome)->setSalarioBase($salario);
            $manager->persist($cargos[$chave]);
        }

        $setores = [];
        foreach ([
            'sgp' => ['SGP', 'Secretaria de Gestão de Pessoas'],
            'sti' => ['STI', 'Secretaria de Tecnologia da Informação'],
            'sof' => ['SOF', 'Secretaria de Orçamento e Finanças'],
            'spa' => ['SPA', 'Seção de Protocolo e Arquivo'],
        ] as $chave => [$sigla, $nome]) {
            $setores[$chave] = (new Setor())->setSigla($sigla)->setNome($nome);
            $manager->persist($setores[$chave]);
        }

        foreach ([
            // chave, matrícula, nome, e-mail, cargo, setor, jornada, RH?
            ['marina', '100201', 'Marina Costa', 'rh@classcont.local', 'coordenador', 'sgp', 480, true],
            ['joao', '100245', 'João Pereira', 'joao@classcont.local', 'assistente', 'sgp', 480, true],
            ['rafael', '100310', 'Rafael Lima', 'chefe.ti@classcont.local', 'coordenador', 'sti', 480, false],
            ['ana', '100322', 'Ana Souza', 'ana@classcont.local', 'analista', 'sti', 480, false],
            ['bruno', '100337', 'Bruno Alves', 'bruno@classcont.local', 'tecnico', 'sti', 480, false],
            ['paulo', '100410', 'Paulo Rocha', 'chefe.financeiro@classcont.local', 'coordenador', 'sof', 480, false],
            ['carla', '100428', 'Carla Mendes', 'carla@classcont.local', 'assistente', 'sof', 360, false],
            ['diego', '100503', 'Diego Martins', 'diego@classcont.local', 'assistente', 'spa', 360, false],
        ] as [$chave, $matricula, $nome, $email, $cargo, $setor, $jornada, $rh]) {
            $f = (new Funcionario())
                ->setMatricula($matricula)
                ->setNome($nome)
                ->setEmail($email)
                ->setCargo($cargos[$cargo])
                ->setSetor($setores[$setor])
                ->setJornadaDiariaMinutos($jornada)
                ->setDataAdmissao(new \DateTimeImmutable(\sprintf('20%02d-%02d-01', 18 + mt_rand(0, 6), mt_rand(1, 12))))
                ->setRh($rh);
            $f->setPassword($this->hasher->hashPassword($f, self::SENHA_PADRAO));
            $manager->persist($f);
            $this->pessoas[$chave] = $f;
        }

        // A chefia é definida no setor; ROLE_CHEFIA é derivado disso.
        $setores['sgp']->setChefe($this->pessoas['marina']);
        $setores['sti']->setChefe($this->pessoas['rafael']);
        $setores['sof']->setChefe($this->pessoas['paulo']);
        $this->pessoas['marina']->getSetoresChefiados()->add($setores['sgp']);
        $this->pessoas['rafael']->getSetoresChefiados()->add($setores['sti']);
        $this->pessoas['paulo']->getSetoresChefiados()->add($setores['sof']);
    }

    private function linhasDeOnibus(ObjectManager $manager): void
    {
        foreach ([
            ['101', 'Terminal Norte / Centro', '5.25'],
            ['202', 'Rodoviária / Esplanada', '5.25'],
            ['305', 'Bairro Novo / Centro', '4.80'],
            ['410', 'Circular Universitária', '4.50'],
            ['520', 'Metropolitana Leste (integração)', '7.30'],
        ] as [$codigo, $nome, $tarifa]) {
            $linha = (new LinhaOnibus())->setCodigo($codigo)->setNome($nome)->setTarifa($tarifa);
            $manager->persist($linha);
            $this->linhas[$codigo] = $linha;
        }
    }

    /**
     * Dias úteis do mês anterior até ontem.
     *
     * @param array<string, true> $feriados
     *
     * @return list<\DateTimeImmutable>
     */
    private function diasUteisPassados(array $feriados): array
    {
        $dias = [];
        $inicio = Competencia::daData($this->hoje)->anterior()->primeiroDia();
        for ($d = $inicio; $d < $this->hoje; $d = $d->modify('+1 day')) {
            if ((int) $d->format('N') < 6 && !isset($feriados[$d->format('Y-m-d')])) {
                $dias[] = $d;
            }
        }

        return $dias;
    }

    /**
     * @param list<\DateTimeImmutable>                $diasUteis
     * @param array<string, list<\DateTimeImmutable>> $ausencias
     */
    private function batidas(ObjectManager $manager, array $diasUteis, array $ausencias, \DateTimeImmutable $diaIncompletoAna): void
    {
        foreach ($this->pessoas as $chave => $pessoa) {
            foreach ($diasUteis as $dia) {
                if (\in_array($dia, $ausencias[$chave] ?? [], false)) {
                    continue;
                }

                $jornada = $pessoa->getJornadaDiariaMinutos();
                $entrada = $dia->setTime(8, 0)->modify(\sprintf('%+d minutes', mt_rand(-12, 12)));
                $intervalo = 480 === $jornada ? 60 : 15;
                $saidaAlmoco = $entrada->modify(\sprintf('+%d minutes', intdiv($jornada, 2) + mt_rand(-10, 10)));
                $retorno = $saidaAlmoco->modify(\sprintf('+%d minutes', $intervalo + mt_rand(-3, 6)));
                // Bruno costuma estender o expediente (banco de horas positivo)
                $extra = 'bruno' === $chave ? mt_rand(20, 70) : mt_rand(-8, 12);
                $saida = $entrada->modify(\sprintf('+%d minutes', $jornada + $intervalo + $extra));

                $batidas = [$entrada, $saidaAlmoco, $retorno, $saida];
                if ('ana' === $chave && $dia == $diaIncompletoAna) {
                    $batidas = [$entrada, $saidaAlmoco, $retorno]; // esqueceu a saída
                }
                foreach ($batidas as $momento) {
                    $manager->persist(new RegistroPonto($pessoa, $momento));
                }
            }
        }
    }

    private function justificativas(ObjectManager $manager, \DateTimeImmutable $faltaBruno, \DateTimeImmutable $faltaCarla, \DateTimeImmutable $atestadoAna): void
    {
        $manager->persist(new Justificativa(
            $this->pessoas['bruno'], $faltaBruno, TipoJustificativa::FaltaJustificada,
            'Acompanhei minha mãe em consulta médica pela manhã e não consegui retornar.',
            $faltaBruno->modify('+1 day 09:12'),
        ));

        $aprovada = new Justificativa(
            $this->pessoas['ana'], $atestadoAna, TipoJustificativa::AtestadoMedico,
            'Atestado médico de 1 dia (CID informado ao RH). Documento entregue na SGP.',
            $atestadoAna->modify('+1 day 08:40'),
        );
        $aprovada->aprovar($this->pessoas['rafael'], 'Atestado conferido.', $atestadoAna->modify('+1 day 14:05'));
        $manager->persist($aprovada);

        $recusada = new Justificativa(
            $this->pessoas['carla'], $faltaCarla, TipoJustificativa::ServicoExterno,
            'Estive no banco resolvendo pendências do setor financeiro.',
            $faltaCarla->modify('+2 days 10:00'),
        );
        $recusada->recusar($this->pessoas['paulo'], 'Não houve designação para serviço externo nesta data.', $faltaCarla->modify('+2 days 16:30'));
        $manager->persist($recusada);
    }

    private function auxilios(ObjectManager $manager): void
    {
        $inicioMesAnterior = Competencia::daData($this->hoje)->anterior()->primeiroDia();

        $ana = new SolicitacaoAuxilio($this->pessoas['ana'], $inicioMesAnterior->modify('-20 days 09:00'));
        $ana->adicionarTrajeto($this->linhas['520'], Sentido::Ida);
        $ana->adicionarTrajeto($this->linhas['101'], Sentido::Ida);
        $ana->adicionarTrajeto($this->linhas['101'], Sentido::Volta);
        $ana->adicionarTrajeto($this->linhas['520'], Sentido::Volta);
        $ana->aprovar($this->pessoas['rafael'], null, $inicioMesAnterior->modify('-19 days 11:00'));
        $manager->persist($ana);

        $carla = new SolicitacaoAuxilio($this->pessoas['carla'], $inicioMesAnterior->modify('-40 days 10:00'));
        $carla->adicionarTrajeto($this->linhas['520'], Sentido::Ida);
        $carla->adicionarTrajeto($this->linhas['202'], Sentido::Ida);
        $carla->adicionarTrajeto($this->linhas['202'], Sentido::Volta);
        $carla->adicionarTrajeto($this->linhas['520'], Sentido::Volta);
        $carla->aprovar($this->pessoas['paulo'], null, $inicioMesAnterior->modify('-39 days 15:00'));
        $manager->persist($carla);

        $bruno = new SolicitacaoAuxilio($this->pessoas['bruno'], $this->hoje->modify('-2 days 08:30'));
        $bruno->adicionarTrajeto($this->linhas['305'], Sentido::Ida);
        $bruno->adicionarTrajeto($this->linhas['410'], Sentido::Ida);
        $bruno->adicionarTrajeto($this->linhas['410'], Sentido::Volta);
        $bruno->adicionarTrajeto($this->linhas['305'], Sentido::Volta);
        $manager->persist($bruno);

        $diego = new SolicitacaoAuxilio($this->pessoas['diego'], $this->hoje->modify('-1 day 13:10'));
        $diego->adicionarTrajeto($this->linhas['202'], Sentido::Ida);
        $diego->adicionarTrajeto($this->linhas['202'], Sentido::Volta);
        $manager->persist($diego);
    }
}
