<?php
declare(strict_types=1);

$db = new SQLite3(__DIR__ . '/chat.db');
$db->enableExceptions(true);
$db->exec('PRAGMA journal_mode=WAL;');
$db->exec('PRAGMA synchronous=NORMAL;');

$db->exec("
    CREATE TABLE IF NOT EXISTS rooms (
        id        INTEGER PRIMARY KEY AUTOINCREMENT,
        name      TEXT    NOT NULL UNIQUE,
        created_at INTEGER NOT NULL DEFAULT (strftime('%s','now'))
    );

    CREATE TABLE IF NOT EXISTS messages (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        room_id    INTEGER NOT NULL REFERENCES rooms(id),
        username   TEXT    NOT NULL,
        body       TEXT    NOT NULL,
        created_at INTEGER NOT NULL DEFAULT (strftime('%s','now'))
    );

    CREATE TABLE IF NOT EXISTS presence (
        username   TEXT    NOT NULL,
        room_id    INTEGER NOT NULL REFERENCES rooms(id),
        last_ping  INTEGER NOT NULL DEFAULT (strftime('%s','now')),
        PRIMARY KEY (username, room_id)
    );

    CREATE INDEX IF NOT EXISTS idx_messages_room ON messages(room_id, id);

    INSERT OR IGNORE INTO rooms (name) VALUES ('General');
    INSERT OR IGNORE INTO rooms (name) VALUES ('Random');
    INSERT OR IGNORE INTO rooms (name) VALUES ('Tech');
");

echo "Database initialised.\n";
