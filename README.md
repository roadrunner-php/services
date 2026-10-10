<p align="center">
    <a href="https://roadrunner.dev"><picture>
        <source media="(prefers-color-scheme: dark)" srcset="https://github.com/roadrunner-server/.github/assets/8040338/e6bde856-4ec6-4a52-bd5b-bfe78736c1ff">
        <img alt="RoadRunner" src="https://github.com/roadrunner-server/.github/assets/8040338/040fb694-1dd3-4865-9d29-8e0748c2c8b8" style="width: 6in; display: block">
    </picture></a>
</p>

<p align="center">Manage RoadRunner services from PHP</p>

<div align="center">

[![Documentation](https://img.shields.io/badge/Documentation-blue?style=for-the-badge&logo=gitbook&logoColor=white)](https://docs.roadrunner.dev/docs/plugins/service)
[![Sponsor](https://img.shields.io/static/v1?style=for-the-badge&label=&message=Sponsor&logo=githubsponsors&logoColor=white&color=%23EA4AAA)](https://github.com/sponsors/roadrunner-server)

[![Psalm Level](https://shepherd.dev/github/roadrunner-php/services/level.svg)](https://shepherd.dev/github/roadrunner-php/services)
[![Type Coverage](https://shepherd.dev/github/roadrunner-php/services/coverage.svg)](https://shepherd.dev/github/roadrunner-php/services)
[![Codecov](https://codecov.io/gh/roadrunner-php/services/branch/2.x/graph/badge.svg)](https://codecov.io/gh/roadrunner-php/services/branch/2.x)
[![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Froadrunner-php%2Fservices%2F2.x)](https://dashboard.stryker-mutator.io/reports/github.com/roadrunner-php/services/2.x)

</div>

<br />

This package lets a PHP application create, restart, terminate and inspect [RoadRunner services](https://docs.roadrunner.dev/docs/plugins/service) at runtime over RPC.

## Get Started

### Installation

```bash
composer require roadrunner/services
```

[![PHP](https://img.shields.io/packagist/php-v/roadrunner/services.svg?style=flat-square&logo=php)](https://packagist.org/packages/roadrunner/services)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/roadrunner/services.svg?style=flat-square&logo=packagist)](https://packagist.org/packages/roadrunner/services)
[![License](https://img.shields.io/packagist/l/roadrunner/services.svg?style=flat-square)](LICENSE)
[![Total Downloads](https://img.shields.io/packagist/dt/roadrunner/services.svg?style=flat-square)](https://packagist.org/packages/roadrunner/services/stats)

### Configuration

Enable the RPC and service plugins in `.rr.yaml`:

```yaml
rpc:
  listen: tcp://127.0.0.1:6001

service: {}
```

### Connecting to RoadRunner

Create an instance of `Spiral\RoadRunner\Services\Manager`:

```php
use Spiral\RoadRunner\Services\Manager;
use Spiral\Goridge\RPC\RPC;

$rpc = RPC::create('tcp://127.0.0.1:6001');
$manager = new Manager($rpc);
```

## Managing Services

### Create a new service

```php
use Spiral\RoadRunner\Services\Exception\ServiceException;

try {
    $result = $manager->create(
        name: 'listen-jobs',
        command: 'php app.php queue:listen',
        processNum: 3,
        execTimeout: 0,
        remainAfterExit: false,
        env: ['APP_ENV' => 'production'],
        restartSec: 30,
        serviceNameInLogs: true,
        stopTimeout: 5,
    );

    if (!$result) {
        throw new ServiceException('Service creation failed.');
    }
} catch (ServiceException $e) {
    // handle exception
}
```

### Check service status

```php
use Spiral\RoadRunner\Services\Exception\ServiceException;

try {
    $status = $manager->statuses(name: 'listen-jobs');

    // Will return an array with statuses of every run process
    // [
    //    [
    //      'cpu_percent' => 59.5,
    //      'pid' => 33,
    //      'memory_usage' => 200,
    //      'command' => 'foo/bar',
    //      'error' => null,
    //    ],
    //    [
    //      'cpu_percent' => 60.2,
    //      'pid' => 34,
    //      'memory_usage' => 189,
    //      'command' => 'foo/bar',
    //      'error' => [
    //          'code' => 1,
    //          'message' => 'Process exited with code 1',
    //          'details' => [...] // array with details
    //      ]
    //    ],
    // ]
} catch (ServiceException $e) {
    // handle exception
}
```

### Restart service

```php
use Spiral\RoadRunner\Services\Exception\ServiceException;

try {
    $result = $manager->restart(name: 'listen-jobs');

    if (!$result) {
        throw new ServiceException('Service restart failed.');
    }
} catch (ServiceException $e) {
    // handle exception
}
```

### Terminate service

```php
use Spiral\RoadRunner\Services\Exception\ServiceException;

try {
    $result = $manager->terminate(name: 'listen-jobs');

    if (!$result) {
        throw new ServiceException('Service termination failed.');
    }
} catch (ServiceException $e) {
    // handle exception
}
```

### List of all services

```php
use Spiral\RoadRunner\Services\Exception\ServiceException;

try {
    $services = $manager->list();

    // Will return an array with services names
    // ['listen-jobs', 'websocket-connection']
} catch (ServiceException $e) {
    // handle exception
}
```
