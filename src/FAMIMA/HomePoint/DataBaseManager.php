<?php

namespace FAMIMA\HomePoint;

class DataBaseManager {

    private $db;

    public function __construct(string $path) {
        $path = mb_convert_encoding($path, "UTF-8");
        $this->db = new \Sqlite3($path."home.sqlite3");
        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS home(
                id INTEGER primary key AUTOINCREMENT,
                owner TEXT not null,
                title TEXT not null,
                x INTEGER not null,
                y INTEGER not null,
                z INTEGER not null,
                world TEXT not null
            )"
        );
    }

    public function getUserHome($user, $world) {
        $user = addslashes($user);
        $world = addslashes($world);
        $stmt = $this->db->prepare("SELECT id, title, x, y, z from home WHERE owner = :owner AND world = :world");
        $stmt->bindValue(":owner", "$user", SQLITE3_TEXT);
        $stmt->bindValue(":world", "$world", SQLITE3_TEXT);
        $result = $stmt->execute();
        
        $returns = [];
        while ($data = $result->fetchArray(SQLITE3_ASSOC)) {
            $returns[] = $data;
        }

        return $returns;
    }

    public function addUserHome($user, $title, $x, $y, $z, $world) {
        $user = addslashes($user);
        $title = addslashes($title);
        $world = addslashes($world);

        if(!$this->isExists($user, $world, $title)) {
            $this->db->query("INSERT INTO home (owner, title, x, y, z, world) VALUES (\"$user\", \"$title\", $x, $y, $z, \"$world\")");
            return true;
        } else {
            return false;
        }
    }

    public function isExists($user, $world, $title) {

        $stmt = $this->db->prepare("SELECT id, title, x, y, z from home WHERE owner = :owner AND world = :world AND title = :title");
        $stmt->bindValue(":owner", "$user", SQLITE3_TEXT);
        $stmt->bindValue(":world", "$world", SQLITE3_TEXT);
        $stmt->bindValue(":title", "$title", SQLITE3_TEXT);
        $result = $stmt->execute();

        return $result->fetchArray() !== false;
    }

    public function deleteUserHome($user, $world, $title) {
        $user = addslashes($user);
        $title = addslashes($title);
        $world = addslashes($world);

        $stmt = $this->db->prepare("SELECT id from home WHERE owner = :owner AND world = :world AND title = :title");
        $stmt->bindValue(":owner", "$user", SQLITE3_TEXT);
        $stmt->bindValue(":world", "$world", SQLITE3_TEXT);
        $stmt->bindValue(":title", "$title", SQLITE3_TEXT);
        $result = $stmt->execute()->fetchArray();

        $this->db->exec("DELETE from home WHERE owner = \"$user\" AND title = \"$title\" AND world = \"$world\"");
        return $result;
    }
}