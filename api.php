<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

$action = $_GET['action'] ?? '';

match ($action) {
    'rooms'  => action_rooms(),
    default  => json_out(['error' => 'unknown action'], 400),
};

function action_rooms(): never
{
    $db   = get_db();
    $rows = $db->query('SELECT id, name FROM rooms ORDER BY id');
    $rooms = [];
    while ($r = $rows->fetchArray(SQLITE3_ASSOC)) {
        $rooms[] = $r;
    }
    json_out($rooms);
}
