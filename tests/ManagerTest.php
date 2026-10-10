<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Services\Tests;

use Google\Protobuf\Any;
use Mockery as m;
use RoadRunner\Service\DTO\V1\Create;
use RoadRunner\Service\DTO\V1\PBList;
use RoadRunner\Service\DTO\V1\Response;
use RoadRunner\Service\DTO\V1\Service;
use RoadRunner\Service\DTO\V1\Status;
use RoadRunner\Service\DTO\V1\Statuses;
use Spiral\Goridge\RPC\Codec\ProtobufCodec;
use Spiral\Goridge\RPC\RPCInterface;
use Spiral\RoadRunner\Services\Exception\ServiceException;
use Spiral\RoadRunner\Services\Manager;
use Testo\Assert;
use Testo\Expect;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

#[Test]
final class ManagerTest
{
    private Manager $manager;
    private m\LegacyMockInterface|m\MockInterface|RPCInterface $rpc;

    public function testListServices(): void
    {
        $this->rpc
            ->shouldReceive('call')
            ->once()
            ->withArgs(static function (string $method, Service $in, string $response) {
                return $method === 'service.List'
                    && $response === PBList::class;
            })
            ->andReturn(new PBList(['services' => ['foo', 'bar', 'baz']]));

        $result = $this->manager->list();

        Assert::same($result, ['foo', 'bar', 'baz']);
    }

    public function testListServicesWithErrorsShouldThrowAnException(): void
    {
        Expect::exception(ServiceException::class)->withMessageContaining('Something went wrong');

        $this->rpc
            ->shouldReceive('call')
            ->once()
            ->andThrow(new \Spiral\Goridge\RPC\Exception\ServiceException('Something went wrong'));

        $this->manager->list();
    }

    public function testServiceShouldBeCreated(): void
    {
        $this->rpc
            ->shouldReceive('call')
            ->once()
            ->withArgs(static function (string $method, Create $in, string $response) {
                return $method === 'service.Create'
                    && $response === Response::class
                    && $in->getName() === 'foo'
                    && $in->getCommand() === 'bar'
                    && $in->getProcessNum() === 5
                    && $in->getExecTimeout() === 7
                    && $in->getRemainAfterExit() === true
                    && \iterator_to_array($in->getEnv()->getIterator()) === ['FOO' => 'bar', 'BAZ' => 'foo']
                    && $in->getRestartSec() === 50
                    && $in->getServiceNameInLogs() === true
                    && $in->getTimeoutStopSec() === 10;
            })
            ->andReturn(new Response(['ok' => true]));

        Assert::true($this->manager->create(
            'foo',
            'bar',
            5,
            7,
            true,
            ['FOO' => 'bar', 'BAZ' => 'foo'],
            50,
            true,
            10,
        ));
    }

    public function testServiceCreateWithErrorsShouldThrowAnException(): void
    {
        Expect::exception(ServiceException::class)->withMessageContaining('Something went wrong');

        $this->rpc
            ->shouldReceive('call')
            ->once()
            ->andThrow(new \Spiral\Goridge\RPC\Exception\ServiceException('Something went wrong'));

        $this->manager->create('foo', 'bar');
    }

    public function testServiceShouldBeRestarted(): void
    {
        $this->rpc
            ->shouldReceive('call')
            ->once()
            ->withArgs(static function (string $method, Service $in, string $response) {
                return $method === 'service.Restart'
                    && $response === Response::class
                    && $in->getName() === 'foo';
            })
            ->andReturn(new Response(['ok' => true]));

        Assert::true($this->manager->restart('foo'));
    }

    public function testServiceRestartWithErrorsShouldThrowAnException(): void
    {
        Expect::exception(ServiceException::class)->withMessageContaining('Something went wrong');

        $this->rpc
            ->shouldReceive('call')
            ->once()
            ->andThrow(new \Spiral\Goridge\RPC\Exception\ServiceException('Something went wrong'));

        $this->manager->restart('foo');
    }

    public function testServiceShouldBeTerminated(): void
    {
        $this->rpc
            ->shouldReceive('call')
            ->once()
            ->withArgs(static function (string $method, Service $in, string $response) {
                return $method === 'service.Terminate'
                    && $response === Response::class
                    && $in->getName() === 'foo';
            })
            ->andReturn(new Response(['ok' => true]));

        Assert::true($this->manager->terminate('foo'));
    }

    public function testServiceTerminateWithErrorsShouldThrowAnException(): void
    {
        Expect::exception(ServiceException::class)->withMessageContaining('Something went wrong');

        $this->rpc
            ->shouldReceive('call')
            ->once()
            ->andThrow(new \Spiral\Goridge\RPC\Exception\ServiceException('Something went wrong'));

        $this->manager->terminate('foo');
    }

    public function testServiceStatusesShouldBeReturned(): void
    {
        $this->rpc
            ->shouldReceive('call')
            ->once()
            ->withArgs(static function (string $method, Service $in, string $response) {
                return $method === 'service.Statuses'
                    && $response === Statuses::class
                    && $in->getName() === 'foo';
            })
            ->andReturn(
                new Statuses([
                    'status' => [
                        new Status([
                            'cpu_percent' => 59.5,
                            'pid' => 33,
                            'memory_usage' => 200,
                            'command' => 'foo/bar',
                            'status' => new \RoadRunner\Common\DTO\V1\Status([
                                'code' => 100,
                                'message' => 'Running',
                                'details' => [
                                    new Any(['type_url' => 'foo', 'value' => 'bar']),
                                ],
                            ]),
                        ]),
                    ],
                ]),
            );

        $status = $this->manager->statuses('foo');

        Assert::same($status, [
            [
                'cpu_percent' => 59.5,
                'pid' => 33,
                'memory_usage' => 200,
                'command' => 'foo/bar',
                'error' => [
                    'code' => 100,
                    'message' => 'Running',
                    'details' => [
                        ['message' => 'bar', 'type_url' => 'foo'],
                    ],
                ],
            ],
        ]);
    }

    public function testListReturnsEmptyArrayWhenNoServicesAreRunning(): void
    {
        $this->rpc
            ->shouldReceive('call')
            ->once()
            ->andReturn(new PBList());

        Assert::same($this->manager->list(), []);
    }

    public function testServiceCreatedWithDefaultOptions(): void
    {
        $this->rpc
            ->shouldReceive('call')
            ->once()
            ->withArgs(static fn(string $method, Create $in, string $response): bool => $method === 'service.Create'
                && $in->getName() === 'foo'
                && $in->getCommand() === 'bar'
                && $in->getProcessNum() === 1
                && $in->getExecTimeout() === 0
                && $in->getRemainAfterExit() === false
                && \count($in->getEnv()) === 0
                && $in->getRestartSec() === 30
                && $in->getServiceNameInLogs() === false
                && $in->getTimeoutStopSec() === 5)
            ->andReturn(new Response(['ok' => true]));

        Assert::true($this->manager->create('foo', 'bar'));
    }

    public function testServiceCreateReturnsFalseWhenRoadRunnerRejectsIt(): void
    {
        $this->rpc
            ->shouldReceive('call')
            ->once()
            ->andReturn(new Response(['ok' => false]));

        Assert::false($this->manager->create('foo', 'bar'));
    }

    public function testServiceRestartReturnsFalseWhenRoadRunnerRejectsIt(): void
    {
        $this->rpc
            ->shouldReceive('call')
            ->once()
            ->andReturn(new Response(['ok' => false]));

        Assert::false($this->manager->restart('foo'));
    }

    public function testServiceTerminateReturnsFalseWhenRoadRunnerRejectsIt(): void
    {
        $this->rpc
            ->shouldReceive('call')
            ->once()
            ->andReturn(new Response(['ok' => false]));

        Assert::false($this->manager->terminate('foo'));
    }

    public function testServiceStatusesWithErrorsShouldThrowAnException(): never
    {
        Expect::exception(ServiceException::class)->withMessage('Something went wrong');

        $this->rpc
            ->shouldReceive('call')
            ->once()
            ->andThrow(new \Spiral\Goridge\RPC\Exception\ServiceException('Something went wrong'));

        $this->manager->statuses('foo');
    }

    public function testRpcErrorIsWrappedOnOneLineKeepingCodeAndPrevious(): never
    {
        $previous = new \Spiral\Goridge\RPC\Exception\ServiceException("first line\nsecond\tline", 42);
        Expect::exception(ServiceException::class)
            ->withMessage('first line second line')
            ->withCode(42)
            ->withPrevious($previous);

        $this->rpc
            ->shouldReceive('call')
            ->once()
            ->andThrow($previous);

        $this->manager->list();
    }

    public function testServiceStatusWithoutErrorHasNullError(): void
    {
        $this->rpc
            ->shouldReceive('call')
            ->once()
            ->andReturn(new Statuses([
                'status' => [
                    new Status([
                        'cpu_percent' => 1.5,
                        'pid' => 10,
                        'memory_usage' => 100,
                        'command' => 'php worker.php',
                    ]),
                ],
            ]));

        Assert::same($this->manager->statuses('foo'), [
            [
                'cpu_percent' => 1.5,
                'pid' => 10,
                'memory_usage' => 100,
                'command' => 'php worker.php',
                'error' => null,
            ],
        ]);
    }

    public function testServiceStatusesReturnEmptyArrayWhenServiceHasNoProcesses(): void
    {
        $this->rpc
            ->shouldReceive('call')
            ->once()
            ->andReturn(new Statuses());

        Assert::same($this->manager->statuses('foo'), []);
    }

    #[BeforeTest]
    protected function setUp(): void
    {
        $this->rpc = m::mock(RPCInterface::class);

        $this->rpc
            ->shouldReceive('withCodec')
            ->once()
            ->withArgs(static fn($codec): bool => $codec instanceof ProtobufCodec)
            ->andReturnSelf();

        $this->manager = new Manager($this->rpc);
    }
}
