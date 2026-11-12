<?php

declare(strict_types=1);

namespace PackioTest\Feature\Rest;

use Packio\Identity\Api\IdentityApiFacadeInterface;
use PackioTest\Support\Base\FeatureTestCase;
use PackioTest\Support\Http\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use stdClass;

use function bin2hex;
use function random_bytes;

final class MappedRequestInputGuardFeatureTest extends FeatureTestCase
{
    private string $apiToken;

    protected function setUp(): void
    {
        parent::setUp();

        $identity = $this->getService(IdentityApiFacadeInterface::class);
        $userDto  = $identity->createUser(
            'mapper-guard-' . bin2hex(random_bytes(6)) . '@example.test',
            'Mapper Guard User',
            'Password1!',
            ['user'],
        );
        $identity->markUserEmailAsVerified($userDto->id);
        $this->apiToken = $identity
            ->issueApiAccessTokenForUser($userDto->id, 'Mapper guard token', ['write'])
            ->plainTextToken
        ;
    }

    #[Test]
    public function itRejectsInvalidUtf8InMappedBodyWithTheSafeValidationEnvelope(): void
    {
        // Form bodies are decoded by BodyParamsMiddleware without a UTF-8 check,
        // so the invalid byte reaches the mapper input guard. A JSON body cannot
        // carry invalid UTF-8 because the JSON strategy rejects it earlier.
        $testResponse = $this->runApplication(
            $this->buildRequest('POST', '/api/v1/organizations', [], $this->apiHeaders(), false)
                ->withParsedBody([
                    'name' => "bad \xB1\x31 value",
                ]),
        );

        $testResponse
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonPath('error.message', 'Invalid request data.')
            ->assertJsonPath('error.details.root.0', 'Request input contains invalid UTF-8.')
        ;
    }

    #[Test]
    public function itRejectsAnObjectParsedBodyWithTheSafeValidationEnvelope(): void
    {
        // The content type must bypass BodyParamsMiddleware strategies so the
        // pre-parsed object body survives to the mapper input guard.
        $headers = $this->apiHeaders();
        unset($headers['Content-Type']);

        $testResponse = $this->runApplication(
            $this->buildRequest('POST', '/api/v1/organizations', [], $headers, false)
                ->withParsedBody(new stdClass()),
        );
        $testResponse
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonPath('error.message', 'Invalid request data.')
            ->assertJsonPath('error.details.root.0', 'Parsed request body must be an array or null.')
        ;
    }

    /**
     * @return array<string, string>
     */
    private function apiHeaders(): array
    {
        return [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $this->apiToken,
            'Content-Type'  => 'application/json',
        ];
    }

    private function runApplication(ServerRequestInterface $serverRequest): TestResponse
    {
        $response = $this->application()->handle($serverRequest);

        return new TestResponse($response);
    }
}
