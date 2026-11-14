<?php
/**
 * Orchestra member, musician and project management application.
 *
 * @author Copilot <223556219+Copilot@users.noreply.github.com>
 * @copyright 2026 Claus-Justus Heine
 * @license AGPL-3.0-or-later
 */

namespace OCA\CAFEVDB\Middleware;

use OCA\CAFEVDB\Service\WopiTokenService;
use OCA\Richdocuments\Controller\DocumentController;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Middleware;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/** Provision database-mount credentials before returning Office tokens. */
class WopiTokenMiddleware extends Middleware
{
  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    private ContainerInterface $appContainer,
    private LoggerInterface $logger,
  ) {
  }
  // phpcs:enable

  /** {@inheritdoc} */
  public function afterController($controller, $methodName, Response $response)
  {
    if (!$controller instanceof DocumentController
        || !in_array($methodName, ['token', 'index', 'createFromTemplate'], true)
        || $response->getStatus() < 200 || $response->getStatus() >= 300) {
      return $response;
    }

    $data = match (true) {
      $response instanceof TemplateResponse => $response->getParams(),
      $response instanceof DataResponse => $response->getData(),
      default => null,
    };
    if (!is_array($data) || !is_string($data['token'] ?? null) || $data['token'] === '') {
      return $response;
    }

    try {
      $this->appContainer->get(WopiTokenService::class)->provideCredentials($data['token']);
    } catch (Throwable $exception) {
      // Provider exceptions can contain the token or its derived passphrase.
      $this->logger->error('Unable to provision WOPI credentials: ' . $exception::class);
      return new DataResponse(['message' => 'Unable to prepare database-backed document access.'], 500);
    }
    return $response;
  }
}
