<?php declare(strict_types=1);

namespace Drupal\mof;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\ClientException;
use Psr\Log\LoggerInterface;
use Drupal\Core\Url;

final class GitHubService {

  const GITHUB_API_URL = 'https://api.github.com';

  /**
   * Construct the GitHubService.
   */
  public function __construct(
    private readonly Client $httpClient,
    private readonly LoggerInterface $logger
  ) {}

  /**
   * Get the specified repository details.
   *
   * @todo Implement a simple repository interface/ class to replace stdClass.
   */
  public function getRepo(string $repo_name): ?\stdClass {
    $repo = $this->execute('/repos' . trim($repo_name));

    if (!$repo) {
      return NULL;
    }

    $repo = $this->execute('/repos' . trim($repo_name))->getBody()->getContents();
    return json_decode($repo);
  }

  /**
   * Build a tree of file paths.
   *
   * @todo Consider implementing StreamedJsonResponse for larger datasets(?)
   * @todo Implement handling if truncated is true in the response.
   */
  public function getTree(string $repo_name, string $branch): \stdClass {
    $endpoint = '/repos/' . trim($repo_name) . '/git/trees/' . trim($branch);
    $tree = $this->execute($endpoint, ['recursive' => 1])->getBody()->getContents();
    return json_decode($tree);
  }

  /**
   * Execute a github api call.
   */
  final protected function execute(string $endpoint, ?array $query = NULL, ?array $headers = NULL): ?Response {
    try {
      $headers ??= $this->setDefaultHeaders();

      $response = $this
        ->httpClient
        ->get(static::GITHUB_API_URL . $endpoint, [
          'headers' => $headers,
          'query' => $query !== NULL ? http_build_query($query) : NULL,
        ]);

      return $response->getStatusCode() === 200 ? $response : NULL;
    }
    catch (ClientException $e) {
      $this->logger->notice($e->getMessage());
    }
    catch (RuntimeException) {
      $this->logger->notice($e->getMessage());
    }

    return NULL;
  }

  /**
   * Return the default headers.
   */
  final protected function setDefaultHeaders(): array {
    return [
      'X-GitHub-Api-Version' => '2022-11-28',
      'Accept' => 'application/vnd.github+json',
    ];
  }

}

