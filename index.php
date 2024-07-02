<?php

require 'vendor/autoload.php';
require 'api/ApiInterface.php';
require 'api/CoinMarketCapApi.php';
require 'api/CoingeckoApi.php';
require 'storage/TransactionLogger.php';
require 'manager/CryptoManager.php';
require 'user/User.php';
require 'Controller/CryptoController.php';

use Dotenv\Dotenv;
use App\Controller\CryptoController;
use App\Service\CryptoManager;
use App\user\User;
use FastRoute\RouteCollector;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();
$apiKey = $_ENV['API_KEY'];

$dbFile = 'storage/database.sqlite';
$pdo = new PDO('sqlite:' . $dbFile);

$user = new User($pdo);
$user->id = 1;

$loader = new FilesystemLoader(__DIR__ . '/views');
$twig = new Environment($loader, [
      'cache' => false,
]);

$logger = new TransactionLogger($dbFile);

$api = new CoinMarketCapApi($apiKey);
$cryptoManager = new CryptoManager($api, $pdo, $user, $logger);
$cryptoController = new CryptoController($cryptoManager, $twig);

$dispatcher = FastRoute\simpleDispatcher(function(RouteCollector $r) {
    $routes = include('routes.php');
    foreach ($routes as $route) {
        [$method, $url, $controller] = $route;
        $r->addRoute($method, $url, $controller);
    }
});

$httpMethod = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];

if (false !== $pos = strpos($uri, '?')) {
    $uri = substr($uri, 0, $pos);
}
$uri = rawurldecode($uri);

$routeInfo = $dispatcher->dispatch($httpMethod, $uri);
switch ($routeInfo[0]) {
    case FastRoute\Dispatcher::NOT_FOUND:
        http_response_code(404);
        echo "404 Not Found";
        break;
    case FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
        $allowedMethods = $routeInfo[1];
        http_response_code(405);
        echo "405 Method Not Allowed";
        break;
    case FastRoute\Dispatcher::FOUND:
        $handler = $routeInfo[1];
        $vars = $routeInfo[2];

        [$controllerClass, $method] = $handler;
        $controller = new $controllerClass($cryptoManager, $twig);
        if (method_exists($controller, $method)) {
            $response = $controller->$method();
            if (isset($response['template'], $response['data'])) {
                echo $twig->render($response['template'], $response['data']);
            }
        }
        break;
}







