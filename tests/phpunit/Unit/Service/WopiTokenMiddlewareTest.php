<?php
/**
 * Orchestra member, musician and project management application.
 *
 * @copyright 2026 Claus-Justus Heine
 * @license AGPL-3.0-or-later
 */

namespace OCA\CAFEVDB\Tests\Unit\Service;

use OCA\CAFEVDB\Middleware\WopiTokenMiddleware;
use OCA\CAFEVDB\Service\WopiTokenService;
use OCA\Richdocuments\Controller\DocumentController;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\TemplateResponse;
use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use stdClass;

class TokenDocumentControllerFixture
{
}

#[Attributes\CoversClass(WopiTokenMiddleware::class)]
#[Attributes\RunTestsInSeparateProcesses]
#[Attributes\PreserveGlobalState(false)]
#[Attributes\AllowMockObjectsWithoutExpectations]
class WopiTokenMiddlewareTest extends TestCase
{
  private WopiTokenMiddleware $middleware;
  private ContainerInterface $container;
  private WopiTokenService $service;
  private LoggerInterface $logger;
  private object $controller;

  protected function setUp(): void
  {
    if (!class_exists(DocumentController::class)) {
      class_alias(TokenDocumentControllerFixture::class, DocumentController::class);
    }
    $this->controller = $this->createStub(DocumentController::class);
    $this->service = $this->createMock(WopiTokenService::class);
    $this->container = $this->createMock(ContainerInterface::class);
    $this->container->method('get')->willReturn($this->service);
    $this->logger = $this->createMock(LoggerInterface::class);
    $this->middleware = new WopiTokenMiddleware($this->container, $this->logger);
  }

  public function testTokenAndBothTemplateEndpointsAreIntercepted(): void
  {
    $this->service->expects($this->exactly(3))->method('provideCredentials')->with('issued-token');
    foreach (['token', 'index', 'createFromTemplate'] as $method) {
      $data = ['token' => 'issued-token', 'token_ttl' => 2000000000];
      $response = $method === 'token'
        ? new DataResponse($data)
        : new TemplateResponse('richdocuments', 'documents', $data);
      self::assertSame($response, $this->middleware->afterController($this->controller, $method, $response));
    }
  }

  public function testUnrelatedFailedAndFederatedResponsesAreUntouched(): void
  {
    $this->container->expects($this->never())->method('get');
    foreach ([
      [new stdClass(), 'token', new DataResponse(['token' => 'ignored'])],
      [$this->controller, 'heartbeat', new DataResponse(['token' => 'ignored'])],
      [$this->controller, 'token', new DataResponse(['token' => 'ignored'], 500)],
      [$this->controller, 'token', new DataResponse(['federatedUrl' => 'https://example.org'])],
      [$this->controller, 'index', new RedirectResponse('https://example.org')],
    ] as [$controller, $method, $response]) {
      self::assertSame($response, $this->middleware->afterController($controller, $method, $response));
    }
  }

  public function testProvisioningFailureDoesNotReturnOrLogToken(): void
  {
    $this->service->method('provideCredentials')->willThrowException(new RuntimeException('secret-token'));
    $this->logger->expects($this->once())->method('error')
      ->with('Unable to provision WOPI credentials: ' . RuntimeException::class);
    $response = $this->middleware->afterController($this->controller, 'token', new DataResponse(['token' => 'secret-token']));
    self::assertSame(500, $response->getStatus());
    self::assertStringNotContainsString('secret-token', json_encode($response->getData()));
  }
}
