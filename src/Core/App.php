<?php
declare(strict_types=1);

namespace Ramingo\Core;

use Ramingo\Models\AnnotationRepository;
use Ramingo\Models\ArtifactUserRepository;
use Ramingo\Models\DashboardRepository;
use Ramingo\Models\DatasetRepository;
use Ramingo\Models\ReportRepository;
use Ramingo\Models\SearchRepository;
use Ramingo\Models\SyncRunRepository;
use Ramingo\Models\TagRepository;
use Ramingo\Models\WorkspaceRepository;
use Ramingo\Services\Auth\AccessService;
use Ramingo\Services\Http\HttpClient;
use Ramingo\Services\Insight\HeuristicInsightGenerator;
use Ramingo\Services\Insight\InsightService;
use Ramingo\Services\Logger;
use Ramingo\Services\PowerBi\FixturePowerBiClient;
use Ramingo\Services\PowerBi\PowerBiClient;
use Ramingo\Services\PowerBi\PowerBiUrls;
use Ramingo\Services\PowerBi\RestPowerBiClient;
use Ramingo\Services\PowerBi\TokenProvider;
use Ramingo\Services\Search\QueryInterpreter;
use Ramingo\Services\Search\SearchIndexer;
use Ramingo\Services\Search\SearchService;
use Ramingo\Services\Sync\CatalogSync;
use Ramingo\Services\Sync\ScanResultImporter;

/**
 * Contenitore dei servizi condivisi (creati una volta sola, in modo pigro).
 * È l'unico posto in cui si decide "quale implementazione usare":
 * ad esempio client Power BI reale oppure dati di esempio.
 */
final class App
{
    private static ?App $instance = null;
    /** @var array<string, object> */
    private array $services = [];
    private ?array $vocabulary = null;

    public function __construct(private readonly Config $config)
    {
        self::$instance = $this;
    }

    public static function instance(): self
    {
        return self::$instance ?? throw new \LogicException('App non inizializzata.');
    }

    public function config(): Config
    {
        return $this->config;
    }

    public function db(): Database
    {
        return $this->service('db', fn () => new Database(
            $this->path((string) $this->config->get('database.path', 'storage/ramingo.sqlite'))
        ));
    }

    public function logger(): Logger
    {
        return $this->service('logger', fn () => new Logger($this->path('storage/logs')));
    }

    public function http(): HttpClient
    {
        return $this->service('http', fn () => new HttpClient(
            (array) $this->config->get('http', []),
            $this->logger()
        ));
    }

    public function powerBi(): PowerBiClient
    {
        return $this->service('powerbi', function (): PowerBiClient {
            $cfg = $this->powerBiSettings()->all();
            if ($cfg['mode'] === 'fixture') {
                return new FixturePowerBiClient($this->path('database/fixtures/powerbi.sample.json'));
            }
            return new RestPowerBiClient($this->http(), new TokenProvider($this->http(), $cfg), $cfg);
        });
    }

    /** Impostazioni Power BI effettive (config.php + pannello Amministrazione). */
    public function powerBiSettings(): \Ramingo\Services\PowerBi\PowerBiSettings
    {
        return $this->service('powerbi.settings', fn () => new \Ramingo\Services\PowerBi\PowerBiSettings(
            $this->config,
            $this->repo(\Ramingo\Models\SettingRepository::class),
            $this->secretBox(),
        ));
    }

    /** Cifratura dei segreti salvati nel database (chiave: security.app_key). */
    public function secretBox(): \Ramingo\Services\Ai\SecretBox
    {
        return $this->service('secretbox', fn () => new \Ramingo\Services\Ai\SecretBox((string) $this->config->get('security.app_key', '')));
    }

    public function backgroundSync(): \Ramingo\Services\BackgroundSync
    {
        return $this->service('bgsync', fn () => new \Ramingo\Services\BackgroundSync($this->config->get('app.php_cli')));
    }

    /**
     * Repository condiviso: $app->repo(ReportRepository::class).
     * @template T of \Ramingo\Models\Repository  @param class-string<T> $class  @return T
     */
    public function repo(string $class): object
    {
        return $this->service($class, fn () => new $class($this->db()));
    }

    public function urls(): PowerBiUrls
    {
        return $this->service('urls', fn () => new PowerBiUrls(
            (string) $this->config->get('powerbi.web_base'),
            (string) ($this->powerBiSettings()->all()['tenant_id'] ?? '')
        ));
    }

    public function vocabulary(): array
    {
        return $this->vocabulary ??= require $this->path('config/synonyms.php');
    }

    public function search(): SearchService
    {
        return $this->service('search', fn () => new SearchService(
            new QueryInterpreter($this->vocabulary()),
            $this->repo(SearchRepository::class)
        ));
    }

    public function indexer(): SearchIndexer
    {
        return $this->service('indexer', fn () => new SearchIndexer($this->db(), $this->repo(SearchRepository::class), $this->repo(\Ramingo\Models\GlossaryRepository::class)));
    }

    public function insights(): InsightService
    {
        return $this->service('insights', fn () => new InsightService(
            new HeuristicInsightGenerator($this->vocabulary()),
            $this->repo(ReportRepository::class),
            $this->repo(DatasetRepository::class),
            $this->repo(AnnotationRepository::class),
            $this->repo(TagRepository::class),
            $this->repo(\Ramingo\Models\GlossaryRepository::class),
        ));
    }

    public function access(): AccessService
    {
        return $this->service('access', fn () => new AccessService($this->repo(ArtifactUserRepository::class)));
    }

    public function catalogSync(): CatalogSync
    {
        return $this->service('sync', fn () => new CatalogSync(
            $this->powerBi(),
            new ScanResultImporter(
                $this->db(),
                $this->repo(WorkspaceRepository::class),
                $this->repo(DatasetRepository::class),
                $this->repo(ReportRepository::class),
                $this->repo(DashboardRepository::class),
                $this->repo(ArtifactUserRepository::class),
                $this->urls(),
                (array) $this->config->get('sync', []),
            ),
            $this->repo(WorkspaceRepository::class),
            $this->repo(ReportRepository::class),
            $this->repo(SyncRunRepository::class),
            $this->insights(),
            $this->indexer(),
            $this->repo(\Ramingo\Models\DatasetRefreshRepository::class),
            $this->logger(),
            (array) $this->config->get('sync', []),
        ));
    }

    public function aiConnections(): \Ramingo\Services\Ai\AiConnections
    {
        return $this->service('ai.connections', fn () => new \Ramingo\Services\Ai\AiConnections(
            $this->config,
            $this->repo(\Ramingo\Models\UserAiSettingRepository::class),
            $this->secretBox(),
            $this->http(),
        ));
    }

    public function aiAssistant(): \Ramingo\Services\Ai\AiAssistant
    {
        return $this->service('ai.assistant', fn () => new \Ramingo\Services\Ai\AiAssistant(
            $this->aiConnections(),
            $this->repo(\Ramingo\Models\AiRequestRepository::class),
            $this->repo(ReportRepository::class),
            $this->repo(DatasetRepository::class),
            $this->repo(AnnotationRepository::class),
            $this->config,
            $this->repo(\Ramingo\Models\GlossaryRepository::class),
        ));
    }

    /** Percorso assoluto a partire dalla root del progetto. */
    public function path(string $relative): string
    {
        if (preg_match('~^([a-zA-Z]:)?[\\\\/]~', $relative)) {
            return $relative; // già assoluto
        }
        return RAMINGO_ROOT . '/' . ltrim($relative, '/');
    }

    /** @template T of object  @param callable():T $factory  @return T */
    private function service(string $id, callable $factory): object
    {
        return $this->services[$id] ??= $factory();
    }
}
