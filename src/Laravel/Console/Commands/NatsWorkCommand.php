<?php

declare(strict_types=1);

namespace LaravelNats\Laravel\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Queue\Worker;
use Illuminate\Queue\WorkerOptions;

/**
 * NATS-specific queue worker command (Phase 4.1 — Basic NATS Worker).
 *
 * Runs the same worker logic as queue:work but defaults to NATS connection,
 * supports PID file and worker name for process management. Signal handling
 * (SIGTERM, SIGINT, etc.) is provided by Laravel's Worker.
 */
class NatsWorkCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nats:work
                            {--connection=nats : NATS queue connection name}
                            {--queue=default : Queue name(s) to consume}
                            {--name=nats-worker : Worker name for identification}
                            {--pidfile= : Write PID to file for process management}
                            {--memory=128 : Memory limit in MB}
                            {--timeout=60 : Job timeout in seconds}
                            {--sleep=3 : Seconds to sleep when no job}
                            {--tries=3 : Max job attempts}
                            {--backoff=0 : Seconds before retry}
                            {--force : Run in maintenance mode}
                            {--once : Process only the next job}
                            {--max-jobs=0 : Stop after N jobs}
                            {--max-time=0 : Stop after N seconds}
                            {--rest=0 : Rest between jobs}
                            {--stop-when-empty : Stop when queue is empty}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run NATS queue worker (Phase 4: dedicated worker with PID file and signals)';

    /**
     * Path to PID file when --pidfile is set (removed on shutdown).
     *
     * @var string|null
     */
    protected ?string $pidFile = null;

    public function __construct(
        protected Worker $worker,
        protected Cache $cache,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $connection = (string) $this->option('connection');
        $queue = (string) $this->option('queue');
        $pidfile = $this->option('pidfile');

        if ($pidfile !== null && $pidfile !== '') {
            $this->pidFile = $pidfile;
            $this->writePidFile();
            register_shutdown_function([$this, 'removePidFile']);
        }

        $this->worker->setName((string) $this->option('name'));
        $this->worker->setCache($this->cache);

        $options = $this->gatherWorkerOptions();

        $this->info(sprintf('NATS worker [%s] processing queue "%s" on connection "%s".', $options->name, $queue, $connection));

        $method = $this->option('once') ? 'runNextJob' : 'daemon';
        $exitCode = $this->worker->{$method}($connection, $queue, $options);

        $this->removePidFile();

        return is_int($exitCode) ? $exitCode : 0; // Worker::EXIT_SUCCESS, EXIT_MEMORY_LIMIT, etc.
    }

    /**
     * Gather worker options from command options.
     *
     * @return WorkerOptions
     */
    protected function gatherWorkerOptions(): WorkerOptions
    {
        return new WorkerOptions(
            (string) $this->option('name'),
            (int) $this->option('backoff'),
            (int) $this->option('memory'),
            (int) $this->option('timeout'),
            (int) $this->option('sleep'),
            (int) $this->option('tries'),
            (bool) $this->option('force'),
            (bool) $this->option('stop-when-empty'),
            (int) $this->option('max-jobs'),
            (int) $this->option('max-time'),
            (int) $this->option('rest'),
        );
    }

    /**
     * Write current process PID to pidfile.
     */
    protected function writePidFile(): void
    {
        if ($this->pidFile === null) {
            return;
        }
        $pid = getmypid();
        if ($pid === false) {
            return;
        }
        if (@file_put_contents($this->pidFile, (string) $pid) === false) {
            $this->error('Could not write PID file: ' . $this->pidFile);

            exit(1);
        }
    }

    /**
     * Remove PID file (called on shutdown or before exit).
     * Safe to call when pidFile is null or file already removed.
     */
    public function removePidFile(): void
    {
        if ($this->pidFile !== null && is_file($this->pidFile)) {
            @unlink($this->pidFile);
            $this->pidFile = null;
        }
    }
}
