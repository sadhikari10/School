<?php
// stocking_selector.php
class StockingSelector {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Get stockings with simple size and price
     * No extra logic, no sections
     */
    public function getStockings() {
        try {
            $stmt = $this->pdo->query("SELECT name as size, price FROM stockings ORDER BY name");
            $stockings = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($stockings as &$stocking) {
                $stocking['display_price'] = $stocking['price'];
            }
            
            return $stockings;
        } catch(PDOException $e) {
            return [];
        }
    }
    
    /**
     * Get stocking price for specific size
     */
    public function getStockingPrice($size) {
        $stockings = $this->getStockings();
        foreach ($stockings as $stocking) {
            if ($stocking['size'] === $size) {
                return $stocking['display_price'];
            }
        }
        return 0;
    }
}
?>