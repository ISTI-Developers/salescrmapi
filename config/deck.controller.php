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
        $query = "SELECT * FROM decks WHERE ID = ?;";
        $this->setStatement($query);
        $this->statement->execute([$deck_id]);
        return $this->statement->fetch();
    }
    public function update_deck($deck)
    {
        extract($deck);

        $query = "UPDATE decks SET title = ?, description = ?, thumbnail = ?, filters = ?, options = ? WHERE ID = ?";
        $this->setStatement($query);
        return $this->statement->execute([$title, $description, $thumbnail, $filters, $options, $ID]);
    }
    public function delete_deck($deck_id)
    {
        $query = "UPDATE decks SET status = 5 WHERE ID = ?";
        $this->setStatement($query);
        return $this->statement->execute([$deck_id]);
    }
}
