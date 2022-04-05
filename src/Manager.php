<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Services;

use Spiral\Goridge\RPC\Codec\ProtobufCodec;
use Spiral\Goridge\RPC\Exception\ServiceException;
use Spiral\Goridge\RPC\RPCInterface;
use Spiral\RoadRunner\Services\DTO\V1\Create;
use Spiral\RoadRunner\Services\DTO\V1\PBList;
use Spiral\RoadRunner\Services\DTO\V1\Response;
use Spiral\RoadRunner\Services\DTO\V1\Service;
use Spiral\RoadRunner\Services\DTO\V1\Status;

final class Manager
{
    private RPCInterface $rpc;

    public function __construct(RPCInterface $rpc)
    {
        $this->rpc = $rpc->withCodec(new ProtobufCodec());
    }

    /**
     * Get list of all services.
     *
     * @return string[] Services name.
     * @throws Exception\ServiceException
     */
    public function list(): array
    {
        try {
            /** @var PBList $response */
            $response = $this->rpc->call('service.List', new Service(), PBList::class);

            return \iterator_to_array($response->getServices()->getIterator());
        } catch (ServiceException $e) {
            $this->handleError($e);
        }
    }

    /**
     * Create a new service.
     *
     * @param non-empty-string $name Service name.
     * @param non-empty-string $command Command to execute. There are no limitations on commands here. Here could be binary, PHP
     *     file, script, etc.
     * @param int $processNum Number of processes for the command to fire.
     * @param int $execTimeout Maximum allowed time to run for the process.
     * @param bool $remainAfterExit Remain process after exit.
     * @param array<non-empty-string, string> $env Environment variables to pass to the underlying process from the
     *     config.
     * @param int $restartSec Delay between process stop and restart.
     * @param string|null $workingDir
     * @return bool
     * @throws Exception\ServiceException
     *
     * @see https://roadrunner.dev/docs/beep-beep-service
     */
    public function create(
        string $name,
        string $command,
        int $processNum = 1,
        int $execTimeout = 0,
        bool $remainAfterExit = false,
        array $env = [],
        int $restartSec = 30,
        ?string $workingDir = null
    ): bool {
        \assert($processNum > 0, 'Process number must be greater than 0.');
        \assert($execTimeout >= 0, 'Execution timeout must be greater or equal to 0.');
        \assert($restartSec > 0, 'Restart delay must be greater than 0.');

        $create = (new Create())
            ->setName($name)
            ->setCommand($command)
            ->setProcessNum($processNum)
            ->setExecTimeout($execTimeout)
            ->setRemainAfterExit($remainAfterExit)
            ->setEnv($env)
            ->setRestartSec($restartSec)
            ->setWorkingDir($workingDir);

        try {
            /** @var Response $response */
            $response = $this->rpc->call('service.Create', $create, Response::class);

            return $response->getOk();
        } catch (ServiceException $e) {
            $this->handleError($e);
        }
    }

    /**
     * Remove a service.
     *
     * @param non-empty-string $name
     * @throws Exception\ServiceException
     */
    public function restart(string $name): bool
    {
        try {
            /** @var Response $response */
            $response = $this->rpc->call('service.Restart', new Service(['name' => $name]), Response::class);

            return $response->getOk();
        } catch (ServiceException $e) {
            $this->handleError($e);
        }
    }

    /**
     * Terminate service.
     *
     * @param non-empty-string $name Service name.
     * @throws Exception\ServiceException
     */
    public function terminate(string $name): bool
    {
        try {
            /** @var Response $response */
            $response = $this->rpc->call('service.Terminate', new Service(['name' => $name]), Response::class);

            return $response->getOk();
        } catch (ServiceException $e) {
            $this->handleError($e);
        }
    }

    /**
     * Get service status.
     *
     * @param non-empty-string $name Service name.
     * @return array{cpu_percent: float, pid: int, memory_usage: int, command: string}
     * @throws Exception\ServiceException
     */
    public function status(string $name): array
    {
        try {
            /** @var Status $response */
            $response = $this->rpc->call('service.Status', new Service(['name' => $name]), Status::class);

            return [
                'cpu_percent' => $response->getCpuPercent(),
                'pid' => $response->getPid(),
                'memory_usage' => $response->getMemoryUsage(),
                'command' => $response->getCommand(),
            ];
        } catch (ServiceException $e) {
            $this->handleError($e);
        }
    }

    /**
     * @param ServiceException $e
     * @throws Exception\ServiceException
     */
    private function handleError(ServiceException $e): void
    {
        $message = \str_replace(["\t", "\n"], ' ', $e->getMessage());

        throw new Exception\ServiceException($message, (int)$e->getCode(), $e);
    }
}
