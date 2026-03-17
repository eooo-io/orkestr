<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\DataSource;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DataSourceController extends Controller
{
    /**
     * GET /api/projects/{project}/data-sources
     */
    public function index(Project $project): JsonResponse
    {
        $dataSources = DataSource::where('project_id', $project->id)
            ->withCount('agents')
            ->orderBy('name')
            ->get()
            ->map(fn (DataSource $ds) => $this->formatDataSource($ds));

        return response()->json(['data' => $dataSources]);
    }

    /**
     * POST /api/projects/{project}/data-sources
     */
    public function store(Request $request, Project $project): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:' . implode(',', DataSource::validTypes()),
            'connection_config' => 'nullable|array',
            'access_mode' => 'nullable|string|in:read_only,read_write',
            'enabled' => 'nullable|boolean',
        ]);

        $dataSource = DataSource::create([
            'project_id' => $project->id,
            'name' => $validated['name'],
            'type' => $validated['type'],
            'connection_config' => $validated['connection_config'] ?? [],
            'access_mode' => $validated['access_mode'] ?? 'read_only',
            'enabled' => $validated['enabled'] ?? true,
        ]);

        $dataSource->loadCount('agents');

        return response()->json(['data' => $this->formatDataSource($dataSource)], 201);
    }

    /**
     * PUT /api/data-sources/{dataSource}
     */
    public function update(Request $request, DataSource $dataSource): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'type' => 'nullable|string|in:' . implode(',', DataSource::validTypes()),
            'connection_config' => 'nullable|array',
            'access_mode' => 'nullable|string|in:read_only,read_write',
            'enabled' => 'nullable|boolean',
        ]);

        $dataSource->update(array_filter($validated, fn ($v) => $v !== null));
        $dataSource->loadCount('agents');

        return response()->json(['data' => $this->formatDataSource($dataSource)]);
    }

    /**
     * DELETE /api/data-sources/{dataSource}
     */
    public function destroy(DataSource $dataSource): JsonResponse
    {
        $dataSource->delete();

        return response()->json(['message' => 'Data source deleted']);
    }

    /**
     * POST /api/data-sources/{dataSource}/test
     */
    public function test(DataSource $dataSource): JsonResponse
    {
        $config = $dataSource->connection_config ?? [];
        $status = 'unknown';
        $message = '';

        try {
            switch ($dataSource->type) {
                case 'postgres':
                case 'mysql':
                    $driver = $dataSource->type === 'postgres' ? 'pgsql' : 'mysql';
                    $connConfig = [
                        'driver' => $driver,
                        'host' => $config['host'] ?? '127.0.0.1',
                        'port' => $config['port'] ?? ($driver === 'pgsql' ? 5432 : 3306),
                        'database' => $config['database'] ?? '',
                        'username' => $config['username'] ?? '',
                        'password' => $config['password'] ?? '',
                    ];

                    config(["database.connections.ds_test_{$dataSource->id}" => $connConfig]);

                    $pdo = DB::connection("ds_test_{$dataSource->id}")->getPdo();
                    $status = 'healthy';
                    $message = 'Connection successful';
                    DB::purge("ds_test_{$dataSource->id}");
                    break;

                case 'redis':
                    $redisConfig = [
                        'client' => 'phpredis',
                        'default' => [
                            'host' => $config['host'] ?? '127.0.0.1',
                            'password' => $config['password'] ?? null,
                            'port' => $config['port'] ?? 6379,
                            'database' => $config['database'] ?? 0,
                        ],
                    ];

                    config(["database.redis.ds_test_{$dataSource->id}" => $redisConfig['default']]);

                    $redis = app('redis')->connection("ds_test_{$dataSource->id}");
                    $redis->ping();
                    $status = 'healthy';
                    $message = 'Redis connection successful';
                    break;

                case 'minio':
                case 's3':
                    // Test by trying to list (limited) objects
                    $diskConfig = [
                        'driver' => 's3',
                        'key' => $config['access_key'] ?? $config['key'] ?? '',
                        'secret' => $config['secret_key'] ?? $config['secret'] ?? '',
                        'region' => $config['region'] ?? 'us-east-1',
                        'bucket' => $config['bucket'] ?? '',
                        'endpoint' => $config['endpoint'] ?? null,
                        'use_path_style_endpoint' => $config['use_path_style_endpoint'] ?? ($dataSource->type === 'minio'),
                    ];

                    config(["filesystems.disks.ds_test_{$dataSource->id}" => $diskConfig]);

                    $disk = \Storage::disk("ds_test_{$dataSource->id}");
                    $disk->directories('/');
                    $status = 'healthy';
                    $message = 'S3/MinIO connection successful';
                    break;

                case 'filesystem':
                    $path = $config['path'] ?? '';
                    if (! empty($path) && is_dir($path) && is_readable($path)) {
                        $status = 'healthy';
                        $message = 'Directory accessible';
                    } else {
                        $status = 'unhealthy';
                        $message = 'Directory not accessible';
                    }
                    break;

                default:
                    $status = 'unknown';
                    $message = "Unsupported type: {$dataSource->type}";
            }
        } catch (\Throwable $e) {
            $status = 'unhealthy';
            $message = $e->getMessage();
        }

        $dataSource->update([
            'health_status' => $status,
            'last_health_check' => now(),
        ]);

        return response()->json([
            'data' => [
                'status' => $status,
                'message' => $message,
            ],
        ]);
    }

    /**
     * PUT /api/projects/{project}/agents/{agent}/data-sources
     */
    public function bindToAgent(Request $request, Project $project, Agent $agent): JsonResponse
    {
        $validated = $request->validate([
            'data_source_ids' => 'required|array',
            'data_source_ids.*' => 'integer|exists:data_sources,id',
        ]);

        $pivotData = [];
        foreach ($validated['data_source_ids'] as $dsId) {
            $ds = DataSource::find($dsId);
            $pivotData[$dsId] = [
                'project_id' => $project->id,
                'access_mode' => $ds?->access_mode ?? 'read_only',
            ];
        }

        $agent->dataSources()->sync($pivotData);

        return response()->json([
            'data' => [
                'agent_id' => $agent->id,
                'data_source_ids' => $validated['data_source_ids'],
            ],
        ]);
    }

    private function formatDataSource(DataSource $ds): array
    {
        return [
            'id' => $ds->id,
            'project_id' => $ds->project_id,
            'name' => $ds->name,
            'type' => $ds->type,
            'connection_config' => $ds->maskedConfig(),
            'access_mode' => $ds->access_mode,
            'enabled' => $ds->enabled,
            'health_status' => $ds->health_status,
            'last_health_check' => $ds->last_health_check?->toIso8601String(),
            'agents_count' => $ds->agents_count ?? 0,
            'created_at' => $ds->created_at?->toIso8601String(),
            'updated_at' => $ds->updated_at?->toIso8601String(),
        ];
    }
}
