<?php

declare(strict_types=1);

namespace PackioTest\Integration\Source\Infrastructure\Webhook;

use const JSON_THROW_ON_ERROR;

use Common\Branding\BrandingConfig;
use Common\Infrastructure\HttpClient\UpstreamHttpClient;
use Common\Security\SodiumSecretEncryptor;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use Packio\Source\Application\Security\SourceWebhookSecrets;
use Packio\Source\Application\Service\SecretResolverInterface;
use Packio\Source\Domain\Entity\ProviderAuthorization;
use Packio\Source\Domain\Entity\Source;
use Packio\Source\Domain\Entity\SourceOAuthAccount;
use Packio\Source\Infrastructure\Webhook\BitbucketWebhookProviderAdapter;
use Packio\Source\Infrastructure\Webhook\CodebergWebhookProviderAdapter;
use Packio\Source\Infrastructure\Webhook\GithubWebhookProviderAdapter;
use Packio\Source\Infrastructure\Webhook\GitlabSelfHostedWebhookProviderAdapter;
use Packio\Source\Infrastructure\Webhook\GitlabWebhookProviderAdapter;
use Packio\Source\Infrastructure\Webhook\WebhookHttpRequestor;
use Packio\Source\Infrastructure\Webhook\WebhookRegistrationTemplate;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Ramsey\Uuid\Uuid;

use function array_map;
use function bin2hex;
use function is_string;
use function json_decode;
use function json_encode;
use function random_bytes;
use function sprintf;

final class HttpWebhookRegistrarContractTest extends TestCase
{
    private const string SECRET         = 'webhook-secret';
    private const string ENCRYPTION_KEY = 'test-webhook-key';

    #[Test]
    public function githubRegistrarSkipsCreateWhenWebhookAlreadyRegistered(): void
    {
        $history                     = [];
        $source                      = $this->buildSource('github', 'org-one', 'repo-one');
        $webhookUrl                  = $this->expectedWebhookUrl('github', $source);
        $webhookRegistrationTemplate = $this->webhookTemplate('github', [
            [
                'status' => 200,
                'body'   => [
                    [
                        'id'     => 88,
                        'config' => [
                            'url' => $webhookUrl,
                        ],
                    ],
                ],
            ],
        ], $history, 'github-token');

        $webhookRegistrationResult = $webhookRegistrationTemplate->register($source);

        self::assertTrue($webhookRegistrationResult->success);
        self::assertTrue($webhookRegistrationResult->alreadyExists);
        self::assertSame('Webhook already registered.', $webhookRegistrationResult->message);

        self::assertCount(1, $history);
        self::assertSame('GET', $this->request($history, 0)->getMethod());
        self::assertSame('https://api.github.com/repos/org-one/repo-one/hooks', (string) $this->request($history, 0)->getUri());
    }

    #[Test]
    public function registrarSendsThePlaintextSecretWhenTheStoredSecretIsEncrypted(): void
    {
        $history = [];
        $source  = $this->buildSource('github', 'org-enc', 'repo-enc');
        $source->replaceProviderConfig([
            ...$source->providerConfig(),
            'webhook_secret' => new SodiumSecretEncryptor(self::ENCRYPTION_KEY)->encrypt(self::SECRET),
        ]);

        $webhookRegistrationResult = $this->webhookTemplate('github', [
            [
                'status' => 200,
                'body'   => [],
            ],
            [
                'status' => 201,
                'body'   => [
                    'id' => 556,
                ],
            ],
        ], $history, 'github-token')->register($source);

        self::assertTrue($webhookRegistrationResult->success);
        self::assertSame(self::SECRET, $this->payload($this->request($history, 1))['config']['secret']);
    }

    #[Test]
    public function registrarRefusesAStoredSecretThatCannotBeDecryptedWithoutCallingTheProvider(): void
    {
        $history = [];
        $source  = $this->buildSource('github', 'org-rot', 'repo-rot');
        $source->replaceProviderConfig([
            ...$source->providerConfig(),
            'webhook_secret' => new SodiumSecretEncryptor('rotated-away-key')->encrypt(self::SECRET),
        ]);

        $webhookRegistrationResult = $this->webhookTemplate('github', [
            [
                'status' => 200,
                'body'   => [],
            ],
            [
                'status' => 201,
                'body'   => [
                    'id' => 557,
                ],
            ],
        ], $history, 'github-token')->register($source);

        self::assertFalse($webhookRegistrationResult->success);
        self::assertSame('Source has no webhook secret — cannot register webhook.', $webhookRegistrationResult->message);
        self::assertSame([], $history);
    }

    #[Test]
    public function githubRegistrarSendsExpectedHeadersAndPayload(): void
    {
        $history                     = [];
        $source                      = $this->buildSource('github', 'org-two', 'repo-two');
        $webhookRegistrationTemplate = $this->webhookTemplate('github', [
            [
                'status' => 200,
                'body'   => [],
            ],
            [
                'status' => 201,
                'body'   => [
                    'id' => 555,
                ],
            ],
        ], $history, 'github-token');

        $webhookRegistrationResult = $webhookRegistrationTemplate->register($source);

        self::assertTrue($webhookRegistrationResult->success);
        self::assertFalse($webhookRegistrationResult->alreadyExists);
        self::assertSame('Webhook registered successfully.', $webhookRegistrationResult->message);
        self::assertCount(2, $history);

        $listRequest = $this->request($history, 0);
        self::assertSame('GET', $listRequest->getMethod());
        self::assertSame('https://api.github.com/repos/org-two/repo-two/hooks', (string) $listRequest->getUri());
        self::assertSame('application/vnd.github+json', $listRequest->getHeaderLine('Accept'));
        self::assertSame('Packio/Tests', $listRequest->getHeaderLine('User-Agent'));
        self::assertSame('Bearer github-token', $listRequest->getHeaderLine('Authorization'));

        $createRequest = $this->request($history, 1);
        self::assertSame('POST', $createRequest->getMethod());
        self::assertSame('https://api.github.com/repos/org-two/repo-two/hooks', (string) $createRequest->getUri());
        self::assertSame('application/vnd.github+json', $createRequest->getHeaderLine('Accept'));
        self::assertSame('application/json', $createRequest->getHeaderLine('Content-Type'));
        self::assertSame('Packio/Tests', $createRequest->getHeaderLine('User-Agent'));
        self::assertSame('Bearer github-token', $createRequest->getHeaderLine('Authorization'));

        $payload = $this->payload($createRequest);
        self::assertSame('web', $payload['name']);
        self::assertSame(true, $payload['active']);
        self::assertSame($this->expectedWebhookUrl('github', $source), $payload['config']['url']);
        self::assertSame(self::SECRET, $payload['config']['secret']);
        self::assertSame(['push', 'create', 'release'], $payload['events']);
    }

    #[Test]
    public function gitlabRegistrarSkipsCreateWhenWebhookAlreadyRegistered(): void
    {
        $history                     = [];
        $source                      = $this->buildSource('gitlab', 'org-three', 'repo-three');
        $webhookUrl                  = $this->expectedWebhookUrl('gitlab', $source);
        $webhookRegistrationTemplate = $this->webhookTemplate('gitlab', [
            [
                'status' => 200,
                'body'   => [
                    [
                        'id'  => 999,
                        'url' => $webhookUrl,
                    ],
                ],
            ],
        ], $history, 'gitlab-token');

        $webhookRegistrationResult = $webhookRegistrationTemplate->register($source);

        self::assertTrue($webhookRegistrationResult->success);
        self::assertTrue($webhookRegistrationResult->alreadyExists);
        self::assertSame('Webhook already registered.', $webhookRegistrationResult->message);
        self::assertCount(1, $history);
        self::assertSame('GET', $this->request($history, 0)->getMethod());
        self::assertSame('https://gitlab.com/api/v4/projects/org-three%2Frepo-three/hooks', (string) $this->request($history, 0)->getUri());
    }

    #[Test]
    public function gitlabRegistrarSendsExpectedHeadersAndPayload(): void
    {
        $history                     = [];
        $source                      = $this->buildSource('gitlab', 'org-four', 'repo-four');
        $webhookRegistrationTemplate = $this->webhookTemplate('gitlab', [
            [
                'status' => 200,
                'body'   => [],
            ],
            [
                'status' => 201,
                'body'   => [
                    'id' => 999,
                ],
            ],
        ], $history, 'gitlab-token');

        $webhookRegistrationResult = $webhookRegistrationTemplate->register($source);

        self::assertTrue($webhookRegistrationResult->success);
        self::assertFalse($webhookRegistrationResult->alreadyExists);
        self::assertSame('Webhook registered successfully.', $webhookRegistrationResult->message);
        self::assertCount(2, $history);

        $listRequest = $this->request($history, 0);
        self::assertSame('GET', $listRequest->getMethod());
        self::assertSame('https://gitlab.com/api/v4/projects/org-four%2Frepo-four/hooks', (string) $listRequest->getUri());
        self::assertSame('application/json', $listRequest->getHeaderLine('Accept'));
        self::assertSame('Packio/Tests', $listRequest->getHeaderLine('User-Agent'));
        self::assertSame('Bearer gitlab-token', $listRequest->getHeaderLine('Authorization'));

        $createRequest = $this->request($history, 1);
        self::assertSame('POST', $createRequest->getMethod());
        self::assertSame('https://gitlab.com/api/v4/projects/org-four%2Frepo-four/hooks', (string) $createRequest->getUri());
        self::assertSame('application/json', $createRequest->getHeaderLine('Accept'));
        self::assertSame('Packio/Tests', $createRequest->getHeaderLine('User-Agent'));
        self::assertSame('Bearer gitlab-token', $createRequest->getHeaderLine('Authorization'));
        self::assertSame('application/json', $createRequest->getHeaderLine('Content-Type'));

        $payload = $this->payload($createRequest);
        self::assertSame($this->expectedWebhookUrl('gitlab', $source), $payload['url']);
        self::assertSame(self::SECRET, $payload['token']);
        self::assertTrue((bool) $payload['push_events']);
        self::assertTrue((bool) $payload['tag_push_events']);
        self::assertTrue((bool) $payload['enable_ssl_verification']);
    }

    #[Test]
    public function gitlabSelfHostedRegistrarIncludesNestedValidationMessage(): void
    {
        $history                              = [];
        $source                               = $this->buildSource('gitlab_self_hosted', 'org-self', 'repo-self');
        $webhookRegistrationTemplate          = $this->webhookTemplate('gitlab_self_hosted', [
            [
                'status' => 200,
                'body'   => [],
            ],
            [
                'status' => 422,
                'body'   => [
                    'message' => [
                        'url' => ['is blocked: Requests to the local network are not allowed'],
                    ],
                ],
            ],
        ], $history, 'gitlab-token');

        $webhookRegistrationResult = $webhookRegistrationTemplate->register($source);

        self::assertFalse($webhookRegistrationResult->success);
        self::assertSame(
            sprintf(
                'GitLab Self-Hosted webhook API error (422) at https://gitlab.company.test while registering callback %s: url is blocked: Requests to the local network are not allowed',
                $this->expectedWebhookUrl('gitlab_self_hosted', $source),
            ),
            $webhookRegistrationResult->message,
        );

        self::assertCount(2, $history);
        self::assertSame('GET', $this->request($history, 0)->getMethod());
        self::assertSame('https://gitlab.company.test/api/v4/projects/org-self%2Frepo-self/hooks', (string) $this->request($history, 0)->getUri());
        self::assertSame('POST', $this->request($history, 1)->getMethod());
        self::assertSame('https://gitlab.company.test/api/v4/projects/org-self%2Frepo-self/hooks', (string) $this->request($history, 1)->getUri());
    }

    #[Test]
    public function gitlabSelfHostedRegistrarRejectsInvalidCallbackUrlBeforeCallingApi(): void
    {
        $history                              = [];
        $source                               = $this->buildSource('gitlab_self_hosted', 'org-self', 'repo-self');
        $webhookRegistrationTemplate          = $this->webhookTemplate('gitlab_self_hosted', [], $history, 'gitlab-token', new BrandingConfig([
            'app_url'         => 'app.example',
            'http_user_agent' => 'Packio/Tests',
        ]));

        $webhookRegistrationResult = $webhookRegistrationTemplate->register($source);

        self::assertFalse($webhookRegistrationResult->success);
        self::assertSame(
            sprintf(
                'GitLab Self-Hosted webhook callback URL is invalid: app.example/webhooks/gitlab_self_hosted/%s. Configure APP_URL as an absolute http(s) URL that GitLab can reach.',
                $source->getIdentifier()->toString(),
            ),
            $webhookRegistrationResult->message,
        );
        self::assertSame([], $history);
    }

    #[Test]
    public function gitlabSelfHostedRegistrarAddsOutboundFilteringHintForInvalidUrl(): void
    {
        $history                              = [];
        $source                               = $this->buildSource('gitlab_self_hosted', 'org-self', 'repo-self');
        $webhookRegistrationTemplate          = $this->webhookTemplate('gitlab_self_hosted', [
            [
                'status' => 200,
                'body'   => [],
            ],
            [
                'status' => 422,
                'body'   => [
                    'message' => 'Invalid url given',
                ],
            ],
        ], $history, 'gitlab-token', new BrandingConfig([
            'app_url'         => 'https://packio.us',
            'http_user_agent' => 'Packio/Tests',
        ]));

        $webhookRegistrationResult = $webhookRegistrationTemplate->register($source);

        self::assertFalse($webhookRegistrationResult->success);
        self::assertSame(
            sprintf(
                'GitLab Self-Hosted webhook API error (422) at https://gitlab.company.test while registering callback https://packio.us/webhooks/gitlab_self_hosted/%s: Invalid url given Check GitLab Admin Area outbound request filtering and allowlist settings for this callback host.',
                $source->getIdentifier()->toString(),
            ),
            $webhookRegistrationResult->message,
        );
    }

    #[Test]
    public function bitbucketRegistrarSkipsCreateWhenWebhookAlreadyRegistered(): void
    {
        $history                       = [];
        $source                        = $this->buildSource('bitbucket', 'workspace-one', 'repo-one');
        $webhookUrl                    = $this->expectedWebhookUrl('bitbucket', $source);
        $webhookRegistrationTemplate   = $this->webhookTemplate('bitbucket', [
            [
                'status' => 200,
                'body'   => [
                    'values' => [
                        [
                            'uuid' => '{hook-uuid}',
                            'url'  => $webhookUrl,
                        ],
                    ],
                ],
            ],
        ], $history, 'bitbucket-token');

        $webhookRegistrationResult = $webhookRegistrationTemplate->register($source);

        self::assertTrue($webhookRegistrationResult->success);
        self::assertTrue($webhookRegistrationResult->alreadyExists);
        self::assertSame('Webhook already registered.', $webhookRegistrationResult->message);
        self::assertSame('hook-uuid', $webhookRegistrationResult->externalId);

        self::assertCount(1, $history);
        self::assertSame('GET', $this->request($history, 0)->getMethod());
        self::assertSame('https://api.bitbucket.org/2.0/repositories/workspace-one/repo-one/hooks', (string) $this->request($history, 0)->getUri());
    }

    #[Test]
    public function bitbucketRegistrarSendsExpectedHeadersAndPayload(): void
    {
        $history                       = [];
        $source                        = $this->buildSource('bitbucket', 'workspace-two', 'repo-two');
        $webhookRegistrationTemplate   = $this->webhookTemplate('bitbucket', [
            [
                'status' => 200,
                'body'   => [
                    'values' => [],
                ],
            ],
            [
                'status' => 201,
                'body'   => [
                    'uuid' => '{created-hook-uuid}',
                ],
            ],
        ], $history, 'bitbucket-token');

        $webhookRegistrationResult = $webhookRegistrationTemplate->register($source);

        self::assertTrue($webhookRegistrationResult->success);
        self::assertFalse($webhookRegistrationResult->alreadyExists);
        self::assertSame('Webhook registered successfully.', $webhookRegistrationResult->message);
        self::assertSame('created-hook-uuid', $webhookRegistrationResult->externalId);
        self::assertCount(2, $history);

        $listRequest = $this->request($history, 0);
        self::assertSame('GET', $listRequest->getMethod());
        self::assertSame('https://api.bitbucket.org/2.0/repositories/workspace-two/repo-two/hooks', (string) $listRequest->getUri());
        self::assertSame('application/json', $listRequest->getHeaderLine('Accept'));
        self::assertSame('Packio/Tests', $listRequest->getHeaderLine('User-Agent'));
        self::assertSame('Bearer bitbucket-token', $listRequest->getHeaderLine('Authorization'));

        $createRequest = $this->request($history, 1);
        self::assertSame('POST', $createRequest->getMethod());
        self::assertSame('https://api.bitbucket.org/2.0/repositories/workspace-two/repo-two/hooks', (string) $createRequest->getUri());
        self::assertSame('application/json', $createRequest->getHeaderLine('Accept'));
        self::assertSame('application/json', $createRequest->getHeaderLine('Content-Type'));
        self::assertSame('Packio/Tests', $createRequest->getHeaderLine('User-Agent'));
        self::assertSame('Bearer bitbucket-token', $createRequest->getHeaderLine('Authorization'));

        $payload = $this->payload($createRequest);
        self::assertSame('Packio source synchronization', $payload['description']);
        self::assertSame($this->expectedWebhookUrl('bitbucket', $source), $payload['url']);
        self::assertSame(self::SECRET, $payload['secret']);
        self::assertSame(['repo:push'], $payload['events']);
        self::assertTrue((bool) $payload['active']);
    }

    #[Test]
    public function codebergRegistrarSkipsCreateWhenWebhookAlreadyRegistered(): void
    {
        $history                      = [];
        $source                       = $this->buildSource('codeberg', 'owner-one', 'repo-one');
        $webhookUrl                   = $this->expectedWebhookUrl('codeberg', $source);
        $webhookRegistrationTemplate  = $this->webhookTemplate('codeberg', [
            [
                'status' => 200,
                'body'   => [
                    [
                        'id'     => 42,
                        'config' => [
                            'url' => $webhookUrl,
                        ],
                    ],
                ],
            ],
        ], $history, 'codeberg-token');

        $webhookRegistrationResult = $webhookRegistrationTemplate->register($source);

        self::assertTrue($webhookRegistrationResult->success);
        self::assertTrue($webhookRegistrationResult->alreadyExists);
        self::assertSame('Webhook already registered.', $webhookRegistrationResult->message);
        self::assertSame('42', $webhookRegistrationResult->externalId);

        self::assertCount(1, $history);
        self::assertSame('GET', $this->request($history, 0)->getMethod());
        self::assertSame('https://codeberg.org/api/v1/repos/owner-one/repo-one/hooks', (string) $this->request($history, 0)->getUri());
    }

    #[Test]
    public function codebergRegistrarSendsExpectedHeadersAndPayload(): void
    {
        $history                      = [];
        $source                       = $this->buildSource('codeberg', 'owner-two', 'repo-two');
        $webhookRegistrationTemplate  = $this->webhookTemplate('codeberg', [
            [
                'status' => 200,
                'body'   => [],
            ],
            [
                'status' => 201,
                'body'   => [
                    'id' => 77,
                ],
            ],
        ], $history, 'codeberg-token');

        $webhookRegistrationResult = $webhookRegistrationTemplate->register($source);

        self::assertTrue($webhookRegistrationResult->success);
        self::assertFalse($webhookRegistrationResult->alreadyExists);
        self::assertSame('Webhook registered successfully.', $webhookRegistrationResult->message);
        self::assertSame('77', $webhookRegistrationResult->externalId);
        self::assertCount(2, $history);

        $listRequest = $this->request($history, 0);
        self::assertSame('GET', $listRequest->getMethod());
        self::assertSame('https://codeberg.org/api/v1/repos/owner-two/repo-two/hooks', (string) $listRequest->getUri());
        self::assertSame('application/json', $listRequest->getHeaderLine('Accept'));
        self::assertSame('Packio/Tests', $listRequest->getHeaderLine('User-Agent'));
        self::assertSame('Bearer codeberg-token', $listRequest->getHeaderLine('Authorization'));

        $createRequest = $this->request($history, 1);
        self::assertSame('POST', $createRequest->getMethod());
        self::assertSame('https://codeberg.org/api/v1/repos/owner-two/repo-two/hooks', (string) $createRequest->getUri());
        self::assertSame('application/json', $createRequest->getHeaderLine('Accept'));
        self::assertSame('application/json', $createRequest->getHeaderLine('Content-Type'));
        self::assertSame('Packio/Tests', $createRequest->getHeaderLine('User-Agent'));
        self::assertSame('Bearer codeberg-token', $createRequest->getHeaderLine('Authorization'));

        $payload = $this->payload($createRequest);
        self::assertSame('forgejo', $payload['type']);
        self::assertSame($this->expectedWebhookUrl('codeberg', $source), $payload['config']['url']);
        self::assertSame('json', $payload['config']['content_type']);
        self::assertSame(self::SECRET, $payload['config']['secret']);
        self::assertSame(['push', 'create', 'delete'], $payload['events']);
        self::assertTrue((bool) $payload['active']);
    }

    #[Test]
    public function codebergRegistrarRetriesManualTokenAuthorizationOnAuthFailure(): void
    {
        $history                      = [];
        $source                       = $this->buildSource('codeberg', 'owner-three', 'repo-three');
        $webhookRegistrationTemplate  = $this->webhookTemplate('codeberg', [
            [
                'status' => 403,
                'body'   => [
                    'message' => 'Forbidden',
                ],
            ],
            [
                'status' => 200,
                'body'   => [],
            ],
            [
                'status' => 201,
                'body'   => [
                    'id' => 101,
                ],
            ],
        ], $history, 'codeberg-token');

        $webhookRegistrationResult = $webhookRegistrationTemplate->register($source);

        self::assertTrue($webhookRegistrationResult->success);
        self::assertSame('101', $webhookRegistrationResult->externalId);
        self::assertCount(3, $history);
        self::assertSame('Bearer codeberg-token', $this->request($history, 0)->getHeaderLine('Authorization'));
        self::assertSame('token codeberg-token', $this->request($history, 1)->getHeaderLine('Authorization'));
        self::assertSame('Bearer codeberg-token', $this->request($history, 2)->getHeaderLine('Authorization'));
    }

    private function brandingConfig(): BrandingConfig
    {
        return new BrandingConfig([
            'app_url'         => 'https://app.example',
            'http_user_agent' => 'Packio/Tests',
        ]);
    }

    /**
     * @param list<array{status: int, body: mixed}> $responses
     * @param list<array<string, mixed>>            $history
     */
    private function httpClient(array $responses, array &$history = []): UpstreamHttpClient
    {
        $mockHandler = new MockHandler(array_map(
            fn (array $response): Response => new Response(
                $response['status'],
                [],
                null === $response['body'] ? '' : (is_string($response['body']) ? $response['body'] : json_encode($response['body'], JSON_THROW_ON_ERROR)),
            ),
            $responses,
        ));
        $handlerStack = HandlerStack::create($mockHandler);
        $handlerStack->push(Middleware::tap(
            static function(RequestInterface $request, array $options) use (&$history): void {
                $history[] = [
                    'request' => $request,
                    'options' => $options,
                ];
            },
        ));

        return new UpstreamHttpClient(new Client([
            'handler' => $handlerStack,
        ]));
    }

    /**
     * @param list<array<string, mixed>> $history
     */
    private function request(array $history, int $index): RequestInterface
    {
        self::assertInstanceOf(RequestInterface::class, $history[$index]['request'] ?? null);

        return $history[$index]['request'];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(RequestInterface $request): array
    {
        $payload = json_decode((string) $request->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);

        return $payload;
    }

    private function buildSource(string $provider, string $owner, string $repo): Source
    {
        $host = match ($provider) {
            'github'    => 'github.com',
            'bitbucket' => 'bitbucket.org',
            'codeberg'  => 'codeberg.org',
            default     => 'gitlab.com',
        };

        $source = Source::register(
            'org-' . bin2hex(random_bytes(3)),
            $provider,
            sprintf('https://%s/%s/%s.git', $host, $owner, $repo),
            sprintf('%s/%s', $owner, $repo),
            'main',
            'public',
            null,
            null,
            [
                'owner'          => $owner,
                'repository'     => $repo,
                ...('gitlab_self_hosted' === $provider ? [
                    'base_url' => 'https://gitlab.company.test',
                ] : []),
                'webhook_secret' => self::SECRET,
            ],
        );
        $source->setIdentifier(Uuid::uuid7());

        return $source;
    }

    private function expectedWebhookUrl(string $provider, Source $source): string
    {
        $sourceId = $source->getIdentifier()->toString();

        return sprintf(
            'https://app.example/webhooks/%s/%s',
            $provider,
            $sourceId,
        );
    }

    /**
     * @param list<array{status: int, body: mixed}> $responses
     * @param list<array<string, mixed>>            $history
     */
    private function webhookTemplate(
        string $provider,
        array $responses,
        array &$history,
        string $token,
        ?BrandingConfig $brandingConfig = null,
    ): WebhookRegistrationTemplate {
        $brandingConfig ??= $this->brandingConfig();
        $webhookProviderAdapter = match ($provider) {
            'github'             => new GithubWebhookProviderAdapter($brandingConfig),
            'gitlab'             => new GitlabWebhookProviderAdapter($brandingConfig),
            'gitlab_self_hosted' => new GitlabSelfHostedWebhookProviderAdapter($brandingConfig),
            'bitbucket'          => new BitbucketWebhookProviderAdapter($brandingConfig),
            'codeberg'           => new CodebergWebhookProviderAdapter($brandingConfig),
            default              => throw new InvalidArgumentException(sprintf('Unsupported webhook provider "%s".', $provider)),
        };

        return new WebhookRegistrationTemplate(
            $brandingConfig,
            $this->secretResolver($token),
            new WebhookHttpRequestor($this->httpClient($responses, $history)),
            $webhookProviderAdapter,
            new SourceWebhookSecrets(new SodiumSecretEncryptor(self::ENCRYPTION_KEY)),
        );
    }

    private function secretResolver(string $token): SecretResolverInterface
    {
        return new readonly class($token) implements SecretResolverInterface {
            public function __construct(private string $token) {}

            public function resolveSourceAuthorizationSecret(string $id, string $key = 'token'): string
            {
                return $this->token;
            }

            public function resolveAuthorizationSecret(string $id): string
            {
                return $this->token;
            }

            public function resolveAuthorizationEntitySecret(
                ProviderAuthorization $providerAuthorization,
                ?SourceOAuthAccount $sourceOAuthAccount = null
            ): string {
                return $this->token;
            }

            public function resolveWebhookSecret(string $id, string $organizationId): ?string
            {
                return null;
            }
        };
    }
}
