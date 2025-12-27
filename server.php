<?php
require 'vendor/autoload.php';
require_once __DIR__ . '/db_connect.php';

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\Http\HttpServerInterface;
use Ratchet\WebSocket\WsServer;
use Psr\Http\Message\RequestInterface;
use GuzzleHttp\Psr7\Response;

class VideoChat implements MessageComponentInterface {
    protected $rooms;

    public function __construct() {
        $this->rooms = [];
    }

    public function onOpen(ConnectionInterface $conn) {
        $queryString = $conn->httpRequest->getUri()->getQuery();
        parse_str($queryString, $queryParams);
        $roomId = $queryParams['room'] ?? null;

        if ($roomId) {
            $conn->roomId = $roomId;
            if (!isset($this->rooms[$roomId])) {
                $this->rooms[$roomId] = new SplObjectStorage();
            }
            
            foreach ($this->rooms[$roomId] as $client) {
                $client->send(json_encode([
                    'type' => 'user-connected',
                    'userId' => $conn->resourceId
                ]));
            }

            $this->rooms[$roomId]->attach($conn);
            echo "New connection {$conn->resourceId} joined room {$roomId}\n";

            $conn->send(json_encode([
                'type' => 'me',
                'id' => $conn->resourceId
            ]));

        } else {
            echo "Connection {$conn->resourceId} rejected: Invalid or missing room ID '{$roomId}'.\n";
            $conn->close();
        }
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        if (isset($from->roomId) && isset($this->rooms[$from->roomId])) {
            $data = json_decode($msg);
            $payload = json_encode([
                'type' => 'signal',
                'sender' => $from->resourceId,
                'data' => $data
            ]);
            
            foreach ($this->rooms[$from->roomId] as $client) {
                if ($from !== $client) {
                    $client->send($payload);
                }
            }
        }
    }

    public function onClose(ConnectionInterface $conn) {
        if (isset($conn->roomId) && isset($this->rooms[$conn->roomId])) {
            $this->rooms[$conn->roomId]->detach($conn);
            
            if (count($this->rooms[$conn->roomId]) > 0) {
                 foreach ($this->rooms[$conn->roomId] as $client) {
                    $client->send(json_encode([
                        'type' => 'user-disconnected',
                        'userId' => $conn->resourceId
                    ]));
                }
            } else {
                unset($this->rooms[$conn->roomId]);
                echo "Room {$conn->roomId} is now empty and removed from memory.\n";
            }
            echo "Connection {$conn->resourceId} from room {$conn->roomId} has disconnected\n";
        }
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "An error has occurred: {$e->getMessage()}\n";
        $conn->close();
    }
}

class HttpRouter implements HttpServerInterface {
    protected $wsServer;

    public function __construct() {
        $this->wsServer = new WsServer(new VideoChat());
    }

    public function onOpen(ConnectionInterface $conn, RequestInterface $request = null) {
        $path = $request->getUri()->getPath();
        echo "Request: {$request->getMethod()} $path\n";

        if ($path === '/ws') {
            $this->wsServer->onOpen($conn, $request);
            return;
        }

        if ($request->getMethod() === 'OPTIONS') {
            $response = new Response(200, [
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, Authorization'
            ]);
            $conn->send($this->formatResponse($response));
            $conn->close();
            return;
        }

        if ($path === '/api/create-room.php') {
            $response = $this->createRoom();
            $conn->send($this->formatResponse($response));
            $conn->close();
            return;
        }

        if ($path === '/api/check-room.php') {
            $response = $this->checkRoom($request);
            $conn->send($this->formatResponse($response));
            $conn->close();
            return;
        }

        $response = new Response(404, [
            'Content-Type' => 'application/json',
            'Access-Control-Allow-Origin' => '*'
        ], json_encode(['error' => 'Not found']));
        $conn->send($this->formatResponse($response));
        $conn->close();
    }

    public function onClose(ConnectionInterface $conn) {
        if (isset($conn->WebSocket)) {
            $this->wsServer->onClose($conn);
        }
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "Error: {$e->getMessage()}\n";
        $conn->close();
    }

    public function onMessage(ConnectionInterface $conn, $msg) {
        $this->wsServer->onMessage($conn, $msg);
    }

    private function createRoom() {
        try {
            $conn = getDatabaseConnection();
            $expirationLimit = date('Y-m-d H:i:s', strtotime('-24 hours'));
            $cleanupSql = "DELETE FROM rooms WHERE created_at < '$expirationLimit'";
            $conn->query($cleanupSql);

            $maxRetries = 5;
            $roomId = '';
            $inserted = false;

            for ($i = 0; $i < $maxRetries; $i++) {
                $roomId = bin2hex(random_bytes(8));
                $stmt = $conn->prepare("INSERT INTO rooms (room_id) VALUES (?)");
                $stmt->bind_param("s", $roomId);

                try {
                    if ($stmt->execute()) {
                        $inserted = true;
                        break;
                    }
                } catch (mysqli_sql_exception $e) {
                    continue;
                }
            }

            $conn->close();

            if ($inserted) {
                echo "Created room: $roomId\n";
                return new Response(200, [
                    'Content-Type' => 'application/json',
                    'Access-Control-Allow-Origin' => '*'
                ], json_encode(['roomId' => $roomId]));
            } else {
                return new Response(500, [
                    'Content-Type' => 'application/json',
                    'Access-Control-Allow-Origin' => '*'
                ], json_encode(['error' => 'Failed to generate unique room ID']));
            }
        } catch (\Exception $e) {
            echo "Error creating room: {$e->getMessage()}\n";
            return new Response(500, [
                'Content-Type' => 'application/json',
                'Access-Control-Allow-Origin' => '*'
            ], json_encode(['error' => $e->getMessage()]));
        }
    }

    private function checkRoom(RequestInterface $request) {
        try {
            $body = (string) $request->getBody();
            $data = json_decode($body, true);
            $roomId = $data['roomId'] ?? '';

            if (empty($roomId)) {
                return new Response(400, [
                    'Content-Type' => 'application/json',
                    'Access-Control-Allow-Origin' => '*'
                ], json_encode(['valid' => false, 'error' => 'Room ID is required']));
            }

            $conn = getDatabaseConnection();
            $stmt = $conn->prepare("SELECT id FROM rooms WHERE room_id = ?");
            $stmt->bind_param("s", $roomId);
            $stmt->execute();
            $result = $stmt->get_result();
            $conn->close();

            if ($result->num_rows > 0) {
                echo "Room $roomId is valid\n";
                return new Response(200, [
                    'Content-Type' => 'application/json',
                    'Access-Control-Allow-Origin' => '*'
                ], json_encode(['valid' => true]));
            } else {
                return new Response(200, [
                    'Content-Type' => 'application/json',
                    'Access-Control-Allow-Origin' => '*'
                ], json_encode(['valid' => false, 'error' => 'Room not found']));
            }
        } catch (\Exception $e) {
            echo "Error checking room: {$e->getMessage()}\n";
            return new Response(500, [
                'Content-Type' => 'application/json',
                'Access-Control-Allow-Origin' => '*'
            ], json_encode(['error' => $e->getMessage()]));
        }
    }

    private function formatResponse(Response $response) {
        $output = sprintf("HTTP/%s %d %s\r\n",
            $response->getProtocolVersion(),
            $response->getStatusCode(),
            $response->getReasonPhrase()
        );

        foreach ($response->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                $output .= sprintf("%s: %s\r\n", $name, $value);
            }
        }

        $output .= "\r\n";
        $output .= $response->getBody();

        return $output;
    }
}

$port = getenv('PORT') ?: 8080;
echo "Server starting on port {$port}...\n";

$server = IoServer::factory(
    new HttpServer(new HttpRouter()),
    $port
);

$server->run();