<?php

require_once __DIR__ . '/../vendor/autoload.php';

use DI\ContainerBuilder;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\MemoryUsageProcessor;
use Swoole\Atomic;
use Swoole\Constant;
use Swoole\Coroutine;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server;
use Tomazt\OpenswooleMemory\SampleClass;

Coroutine::enableScheduler();

$counter = new Atomic();
$logger  = new Logger(
	'memory-tester-boot',
	[
		new StreamHandler('php://stdout'),
		new StreamHandler(fopen(__DIR__ . '/../server.log', 'w'))
	],
	[new MemoryUsageProcessor(true)]
);

$server = new Server('0.0.0.0', 8000);
$server->set([
	Constant::OPTION_ENABLE_COROUTINE    => true,
	Constant::OPTION_WORKER_NUM          => swoole_cpu_num(),
	Constant::OPTION_OPEN_TCP_NODELAY    => true,
	Constant::OPTION_MAX_COROUTINE       => 100000,
	Constant::OPTION_OPEN_HTTP2_PROTOCOL => true,
	Constant::OPTION_SOCKET_BUFFER_SIZE  => 2 * 1024 * 1024,
	Constant::OPTION_BUFFER_OUTPUT_SIZE  => 2 * 1024 * 1024,
]);
$server->on(Constant::EVENT_REQUEST, function (Request $req, Response $resp) use ($counter, $logger): void {
	$count = $counter->add();

	$logger->info('START REQUEST', [
		'stack' => memory_get_usage(),
		'sys' => memory_get_usage(true),
		'req_count' => $count,
	]);

	$builder = new ContainerBuilder();
	$builder->useAutowiring(true);
	$builder->useAttributes(false);
	$diContainer = $builder->build();
	$data = $diContainer->get(SampleClass::class)->getSample();

	$resp->setStatusCode(200);
	$resp->setHeader('Content-Type', 'application/json');
	$resp->end(json_encode($data));

	$logger->info('END REQUEST', [
		'data' => $data,
		'stack' => memory_get_usage(),
		'sys' => memory_get_usage(true),
		'req_count' => $count,
	]);
});
$logger->info('START SERVER localhost:8000');
$server->start();
