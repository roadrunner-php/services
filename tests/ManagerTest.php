<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Services\Tests;

use Google\Protobuf\Any;
use Mockery as m;
use Spiral\Goridge\RPC\Codec\ProtobufCodec;
use Spiral\Goridge\RPC\RPCInterface;
use RoadRunner\Service\DTO\V1\Create;
use RoadRunner\Service\DTO\V1\PBList;
use RoadRunner\Service\DTO\V1\Response;
use RoadRunner\Service\DTO\V1\Service;
use RoadRunner\Service\DTO\V1\Status;
use RoadRunner\Service\DTO\V1\Statuses;
use Spiral\RoadRunner\Services\Exception\ServiceException;
use Spiral\RoadRunner\Services\Manager;

final class ManagerTest extends TestCase
{
    private Manager $manager;
    private m\LegacyMockInterface|m\MockInterface|RPCInterface $rpc;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rpc = m::mock(RPCInterface::class);

        $this->rpc
            ->shouldReceive('withCodec')
            ->once()
            ->withArgs(static fn($codec): bool => $codec instanceof ProtobufCodec)
            ->andReturnSelf();

        $this->manager = new Manager($this->rpc);
    }

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

        $this->assertSame(['foo', 'bar', 'baz'], $result);
    }

    public function testListServicesWithErrorsShouldThrowAnException(): void
    {
        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('Something went wrong');

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

        $this->assertTrue(
            $this->manager->create(
                'foo',
                'bar',
                5,
                7,
                true,
                ['FOO' => 'bar', 'BAZ' => 'foo'],
                50,
                true,
                10
            )
        );
    }

    public function testServiceCreateWithErrorsShouldThrowAnException(): void
    {
        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('Something went wrong');

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

        $this->assertTrue($this->manager->restart('foo'));
    }

    public function testServiceRestartWithErrorsShouldThrowAnException(): void
    {
        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('Something went wrong');

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

        $this->assertTrue($this->manager->terminate('foo'));
    }

    public function testServiceTerminateWithErrorsShouldThrowAnException(): void
    {
        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('Something went wrong');

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
                            'status' => new \RoadRunner\Shared\DTO\V1\Status([
                                'code' => 100,
                                'message' => 'Running',
                                'details' => [
                                    new Any(['type_url' => 'foo', 'value' => 'bar']),
                                ],
                            ]),
                        ]),
                    ],
                ])
            );

        $status = $this->manager->statuses('foo');

        $this->assertSame([
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
        ], $status);
    }
}
