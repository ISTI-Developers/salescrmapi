<?php
require_once __DIR__ . "/controller.php";

class DeckController extends Controller
{

    public function get_decks($user_id)
    {
        if (!$user_id) {
            return false;
        }
        $query = "SELECT * FROM decks WHERE user_id = ? AND status <> 5;";
        $this->setStatement($query);
        $this->statement->execute([$user_id]);
        return $this->statement->fetchAll();
    }
    public function get_deck($deck_id)
    {
        if (!$deck_id) {
            return false;
        }
        $query = "SELECT * FROM decks WHERE token = ?;";
        $this->setStatement($query);
        $this->statement->execute([$deck_id]);
        return $this->statement->fetch();
    }
    public function update_deck($deck)
    {
        extract($deck);
        $query = "INSERT INTO decks (user_id, token, title, description, thumbnail,sites, filters, options, status, modified_at)
            VALUES (?,?,?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE 
            title = VALUES(title),
            description = VALUES(description),
            thumbnail = VALUES(thumbnail),
            sites = VALUES(sites),
            filters = VALUES(filters),
            options = VALUES(options),
            status = VALUES(status),
            modified_at = VALUES(modified_at);";
        // $query = "UPDATE decks SET title = ?, description = ?, thumbnail = ?, sites = ?, filters = ?, options = ?, modified_at = ? WHERE ID = ?";
        $this->setStatement($query);
        return $this->statement->execute([$user_id, $token, $title, $description, $thumbnail, json_encode($sites), json_encode($filters), json_encode($options), 1, $modified_at]);
    }
    public function delete_deck($deck_id)
    {
        $query = "UPDATE decks SET status = 5 WHERE ID = ?";
        $this->setStatement($query);
        return $this->statement->execute([$deck_id]);
    }
}
