<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\DataFixtures\AppFixtures;
use App\Entity\Funcionario;
use App\Repository\FuncionarioRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Base dos testes funcionais. Usa o banco de teste carregado com os fixtures;
 * o DAMADoctrineTestBundle envolve cada teste numa transação desfeita ao final.
 */
abstract class ApiTestCase extends WebTestCase
{
    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    /**
     * Gera o JWT direto pelo serviço do Lexik (o login real é testado em AutenticacaoTest;
     * aqui evitamos dezenas de logins que acionariam o login_throttling).
     */
    protected function token(string $email): string
    {
        return static::getContainer()->get(JWTTokenManagerInterface::class)->create($this->funcionario($email));
    }

    /** Login real pelo endpoint POST /api/login. */
    protected function login(string $email, string $senha = AppFixtures::SENHA_PADRAO): void
    {
        $this->client->jsonRequest('POST', '/api/login', ['email' => $email, 'password' => $senha]);
    }

    /**
     * @param array<string, mixed> $corpo
     *
     * @return array<mixed>
     */
    protected function api(string $metodo, string $url, string $email, array $corpo = []): array
    {
        $this->client->jsonRequest($metodo, $url, $corpo, ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token($email)]);

        return $this->json();
    }

    /** @return array<mixed> */
    protected function json(): array
    {
        $conteudo = (string) $this->client->getResponse()->getContent();

        return '' === $conteudo ? [] : json_decode($conteudo, true, flags: \JSON_THROW_ON_ERROR);
    }

    protected function funcionario(string $email): Funcionario
    {
        $f = static::getContainer()->get(FuncionarioRepository::class)->findOneBy(['email' => $email]);
        self::assertNotNull($f);

        return $f;
    }
}
