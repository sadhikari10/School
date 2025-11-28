<?php
// shirt_selector.php
class ShirtSelector {
    private $pdo, $schoolName, $schoolPricesSchools = ['Timeline', 'Ekata James', 'LLA', 'Devkota'];
    
    public function __construct($pdo, $schoolName) {
        $this->pdo = $pdo;
        $this->schoolName = $schoolName;
    }
    
    /**
     * Get shirts with school-specific pricing
     * Timeline, Ekata James, LLA, Devkota → school_prices
     * Others → price_other
     */
    public function getShirts() {
        try {
            $stmt = $this->pdo->query("SELECT size, price_other, school_prices FROM shirts ORDER BY CAST(size AS UNSIGNED)");
            $shirts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($shirts as &$shirt) {
                $shirt['display_price'] = in_array($this->schoolName, $this->schoolPricesSchools) 
                    ? $shirt['school_prices'] 
                    : $shirt['price_other'];
            }
            
            return $shirts;
        } catch(PDOException $e) {
            return [];
        }
    }
    
    /**
     * Get shirt price for specific size
     */
    public function getShirtPrice($size) {
        $shirts = $this->getShirts();
        foreach ($shirts as $shirt) {
            if ($shirt['size'] === $size) {
                return $shirt['display_price'];
            }
        }
        return 0;
    }
}
?>