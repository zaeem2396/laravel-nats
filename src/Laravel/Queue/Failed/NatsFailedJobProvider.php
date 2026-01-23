<?php

declare(strict_types=1);

namespace LaravelNats\Laravel\Queue\Failed;

use Illuminate\Queue\Failed\FailedJobProviderInterface;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * NatsFailedJobProvider stores failed NATS queue jobs in the database.
 *
 * This provider integrates with Laravel's failed_jobs table and adds
 * NATS-specific metadata for debugging and troubleshooting.
 */
class NatsFailedJobProvider implements FailedJobProviderInterface
{
    /**
     * The database connection name.
     */
    protected string $connection;

    /**
     * The database table name.
     */
    protected string $table;

    /**
     * Create a new failed job provider instance.
     *
     * @param string $connection
     * @param string $table
     */
    public function __construct(string $connection, string $table)
    {
        $this->connection = $connection;
        $this->table = $table;
    }

    /**
     * Log a failed job into storage.
     *
     * @param string $connection
     * @param string $queue
     * @param string $payload
     * @param Throwable $exception
     *
     * @return int|string|null
     */
    public function log($connection, $queue, $payload, $exception)
    {
        $failedAt = now();

        return DB::connection($this->connection)->table($this->table)->insertGetId([
            'uuid' => $this->extractJobId($payload),
            'connection' => $connection,
            'queue' => $queue,
            'payload' => $payload,
            'exception' => (string) $exception,
            'failed_at' => $failedAt,
        ]);
    }

    /**
     * Get a list of all of the failed jobs.
     *
     * @return array<int, array<string, mixed>>
     */
    public function all()
    {
        return DB::connection($this->connection)
            ->table($this->table)
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($job) {
                return (array) $job;
            })
            ->all();
    }

    /**
     * Get a single failed job.
     *
     * @param mixed $id
     *
     * @return array<string, mixed>|null
     */
    public function find($id)
    {
        $job = DB::connection($this->connection)
            ->table($this->table)
            ->find($id);

        return $job ? (array) $job : null;
    }

    /**
     * Delete a single failed job from storage.
     *
     * @param mixed $id
     *
     * @return bool
     */
    public function forget($id)
    {
        return DB::connection($this->connection)
            ->table($this->table)
            ->where('id', $id)
            ->delete() > 0;
    }

    /**
     * Get a list of all of the failed job IDs.
     *
     * @param string|null $queue Optional queue name filter
     *
     * @return array<int>
     */
    public function ids($queue = null)
    {
        $query = DB::connection($this->connection)
            ->table($this->table)
            ->orderBy('id', 'desc');

        if ($queue !== null) {
            $query->where('queue', $queue);
        }

        return $query->pluck('id')->all();
    }

    /**
     * Flush all of the failed jobs from storage.
     *
     * @param int|null $hours Optional: only flush jobs older than N hours
     *
     * @return void
     */
    public function flush($hours = null)
    {
        $query = DB::connection($this->connection)->table($this->table);

        if ($hours !== null) {
            $query->where('failed_at', '<', now()->subHours($hours)->timestamp);
        }

        $query->delete();
    }

    /**
     * Extract the job ID from the payload.
     *
     * @param string $payload
     *
     * @return string
     */
    protected function extractJobId(string $payload): string
    {
        $decoded = json_decode($payload, true);

        if (! is_array($decoded)) {
            return (string) md5($payload);
        }

        return $decoded['uuid'] ?? $decoded['id'] ?? (string) md5($payload);
    }
}

