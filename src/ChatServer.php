<?php
declare(strict_types=1);

namespace Chat;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use SQLite3;

class ChatServer implements MessageComponentInterface
{
    // All open connections: spl_object_id => ConnectionInterface
    private array $clients = [];

    // Which room each connection is in: spl_object_id => room_id
    private array $rooms = [];

    // Username per connection: spl_object_id => username
    private array $usernames = [];

    private SQLite3 $db;

    public function __construct(SQLite3 $db)
    {
        $this->db = $db;
        echo "Chat server started.\n";
    }

    // ── Connection opened ────────────────────────────────────────────────────

    public function onOpen(ConnectionInterface $conn): void
    {
        $id = spl_object_id($conn);
        $this->clients[$id] = $conn;
        echo "New connection: #{$id}\n";
    }

    // ── Message received ─────────────────────────────────────────────────────

    public function onMessage(ConnectionInterface $from, $rawMsg): void
    {
        $id      = spl_object_id($from);
        $payload = json_decode($rawMsg, true);

        if (!is_array($payload) || !isset($payload['type'])) {
            return;
        }

        match ($payload['type']) {
            'join'    => $this->handleJoin($from, $id, $payload),
            'message' => $this->handleMessage($from, $id, $payload),
            default   => null,
        };
    }

    // ── Connection closed ────────────────────────────────────────────────────

    public function onClose(ConnectionInterface $conn): void
    {
        $id       = spl_object_id($conn);
        $roomId   = $this->rooms[$id]    ?? null;
        $username = $this->usernames[$id] ?? null;

        unset($this->clients[$id], $this->rooms[$id], $this->usernames[$id]);

        if ($roomId && $username) {
            $this->broadcastToRoom($roomId, [
                'type'     => 'system',
                'body'     => "{$username} left the room",
                'online'   => $this->onlineInRoom($roomId),
            ], except: null);
        }

        echo "Connection #{$id} closed\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e): void
    {
        echo "Error: {$e->getMessage()}\n";
        $conn->close();
    }

    // ── Handlers ─────────────────────────────────────────────────────────────

    private function handleJoin(ConnectionInterface $conn, int $id, array $p): void
    {
        $username = $this->sanitise($p['username'] ?? '', 30);
        $roomId   = (int) ($p['room_id'] ?? 0);

        if ($username === '' || $roomId <= 0) {
            $conn->send(json_encode(['type' => 'error', 'body' => 'Invalid join payload']));
            return;
        }

        // Leave old room if switching
        if (isset($this->rooms[$id])) {
            $old = $this->rooms[$id];
            $this->broadcastToRoom($old, [
                'type'   => 'system',
                'body'   => "{$username} left",
                'online' => $this->onlineInRoom($old),
            ], except: $id);
        }

        $this->rooms[$id]     = $roomId;
        $this->usernames[$id] = $username;

        // Send message history to the joining client
        $history = $this->fetchHistory($roomId, 50);
        $conn->send(json_encode([
            'type'     => 'history',
            'messages' => $history,
            'online'   => $this->onlineInRoom($roomId),
        ]));

        // Notify others
        $this->broadcastToRoom($roomId, [
            'type'   => 'system',
            'body'   => "{$username} joined",
            'online' => $this->onlineInRoom($roomId),
        ], except: $id);

        echo "#{$id} ({$username}) joined room {$roomId}\n";
    }

    private function handleMessage(ConnectionInterface $from, int $id, array $p): void
    {
        $roomId   = $this->rooms[$id]    ?? null;
        $username = $this->usernames[$id] ?? null;

        if (!$roomId || !$username) {
            $from->send(json_encode(['type' => 'error', 'body' => 'Not in a room']));
            return;
        }

        $body = $this->sanitise($p['body'] ?? '', 500);
        if ($body === '') return;

        // Persist to DB
        $stmt = $this->db->prepare(
            'INSERT INTO messages (room_id, username, body) VALUES (:room, :user, :body)'
        );
        $stmt->bindValue(':room', $roomId,   SQLITE3_INTEGER);
        $stmt->bindValue(':user', $username, SQLITE3_TEXT);
        $stmt->bindValue(':body', $body,     SQLITE3_TEXT);
        $stmt->execute();

        $msgId     = $this->db->lastInsertRowID();
        $createdAt = time();

        $packet = [
            'type'       => 'message',
            'id'         => $msgId,
            'username'   => $username,
            'body'       => $body,
            'created_at' => $createdAt,
        ];

        // Broadcast to everyone in the room including sender
        $this->broadcastToRoom($roomId, $packet, except: null);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function broadcastToRoom(int $roomId, array $data, ?int $except): void
    {
        $json = json_encode($data);
        foreach ($this->clients as $cid => $client) {
            if (($this->rooms[$cid] ?? null) === $roomId && $cid !== $except) {
                $client->send($json);
            }
        }
    }

    private function onlineInRoom(int $roomId): array
    {
        $users = [];
        foreach ($this->rooms as $cid => $rid) {
            if ($rid === $roomId && isset($this->usernames[$cid])) {
                $users[] = $this->usernames[$cid];
            }
        }
        return array_values(array_unique($users));
    }

    private function fetchHistory(int $roomId, int $limit): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, username, body, created_at
               FROM messages
              WHERE room_id = :room
              ORDER BY id DESC
              LIMIT :limit'
        );
        $stmt->bindValue(':room',  $roomId, SQLITE3_INTEGER);
        $stmt->bindValue(':limit', $limit,  SQLITE3_INTEGER);
        $result = $stmt->execute();

        $rows = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $rows[] = $row;
        }
        return array_reverse($rows);
    }

    private function sanitise(string $s, int $max): string
    {
        return mb_substr(trim(htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')), 0, $max);
    }
}
