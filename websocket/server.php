<?php
require_once '../vendor/autoload.php';

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\Factory;
use React\Socket\SecureServer;
use React\Socket\Server;

class PixelVerseWebSocket implements \Ratchet\MessageComponentInterface {
    protected $clients;
    protected $loop;

    public function __construct($loop) {
        $this->clients = new \SplObjectStorage;
        $this->loop = $loop;
    }

    public function onOpen(\Ratchet\ConnectionInterface $conn) {
        $this->clients->attach($conn);
        echo "New connection! ({$conn->resourceId})\n";
    }

    public function onMessage(\Ratchet\ConnectionInterface $from, $msg) {
        // Handle incoming messages (if needed)
        $data = json_decode($msg, true);
        
        // Broadcast the message to all connected clients
        foreach ($this->clients as $client) {
            $client->send($msg);
        }
    }

    public function onClose(\Ratchet\ConnectionInterface $conn) {
        $this->clients->detach($conn);
        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    public function onError(\Ratchet\ConnectionInterface $conn, \Exception $e) {
        echo "An error has occurred: {$e->getMessage()}\n";
        $conn->close();
    }

    // Method to broadcast message to all clients
    public function broadcast($message) {
        foreach ($this->clients as $client) {
            $client->send($message);
        }
    }
}

// Create event loop
$loop = Factory::create();

// Create WebSocket server
$webSocket = new PixelVerseWebSocket($loop);
$wsServer = new WsServer($webSocket);
$httpServer = new HttpServer($wsServer);

// Create socket server
$socket = new Server('0.0.0.0:8080', $loop);

// Create the server
$server = new IoServer($httpServer, $socket, $loop);

// Create HTTP endpoint for broadcasting messages
$http = new React\Http\Server($loop, function (Psr\Http\Message\ServerRequestInterface $request) use ($webSocket) {
    if ($request->getMethod() === 'POST' && $request->getUri()->getPath() === '/broadcast') {
        $body = (string) $request->getBody();
        $webSocket->broadcast($body);
        return new React\Http\Response(200, ['Content-Type' => 'application/json'], '{"status":"ok"}');
    }
    return new React\Http\Response(404, ['Content-Type' => 'text/plain'], 'Not found');
});

$socket = new Server(8081, $loop);
$http->listen($socket);

echo "WebSocket server running on port 8080\n";
echo "HTTP broadcast endpoint running on port 8081\n";

$loop->run();
