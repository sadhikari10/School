<?php
// shoe_selector.php
class ShoeSelector {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Get shoes with TWO SECTIONS: White and Black
     * Only show sizes where price is available
     */
    public function getShoes() {
        try {
            $stmt = $this->pdo->query("SELECT size, price_white, price_black FROM shoes ORDER BY size");
            $shoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $result = [];
            
            // Section 1: White shoes (only if price_white > 0)
            foreach ($shoes as $shoe) {
                if (!empty($shoe['price_white']) && $shoe['price_white'] > 0) {
                    $result[] = [
                        'size' => $shoe['size'],
                        'display_price' => $shoe['price_white'],
                        'section' => 'White'
                    ];
                }
            }
            
            // Section 2: Black shoes (only if price_black > 0)
            foreach ($shoes as $shoe) {
                if (!empty($shoe['price_black']) && $shoe['price_black'] > 0) {
                    $result[] = [
                        'size' => $shoe['size'],
                        'display_price' => $shoe['price_black'],
                        'section' => 'Black'
                    ];
                }
            }
            
            return $result;
        } catch(PDOException $e) {
            return [];
        }
    }
    
    /**
     * Get shoe price for specific size and color
     */
    public function getShoePrice($size, $section) {
        $shoes = $this->getShoes();
        foreach ($shoes as $shoe) {
            if ($shoe['size'] === $size && $shoe['section'] === $section) {
                return $shoe['display_price'];
            }
        }
        return 0;
    }
}
?>