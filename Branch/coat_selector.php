<?php
// coat_selector.php
class CoatSelector {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Get coats with simple size and price
     * No extra logic, no sections
     */
    public function getCoats() {
        try {
            $stmt = $this->pdo->query("SELECT size, price FROM coats ORDER BY size");
            $coats = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($coats as &$coat) {
                $coat['display_price'] = $coat['price'];
            }
            
            return $coats;
        } catch(PDOException $e) {
            return [];
        }
    }
    
    /**
     * Get coat price for specific size
     */
    public function getCoatPrice($size) {
        $coats = $this->getCoats();
        foreach ($coats as $coat) {
            if ($coat['size'] === $size) {
                return $coat['display_price'];
            }
        }
        return 0;
    }
}
?>