<?php
// skirt_selector.php
class SkirtSelector {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Get skirts with TWO SECTIONS for ALL schools
     * Section 1: Indian (price_indian)
     * Section 2: Nepali (price_nepali)
     */
    public function getSkirts() {
        try {
            $stmt = $this->pdo->query("SELECT size, price_indian, price_nepali FROM skirts ORDER BY size");
            $skirts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $result = [];
            
            // Section 1: Indian prices
            foreach ($skirts as $skirt) {
                $result[] = [
                    'size' => $skirt['size'],
                    'display_price' => $skirt['price_indian'],
                    'section' => 'Indian'
                ];
            }
            
            // Section 2: Nepali prices
            foreach ($skirts as $skirt) {
                $result[] = [
                    'size' => $skirt['size'],
                    'display_price' => $skirt['price_nepali'],
                    'section' => 'Nepali'
                ];
            }
            
            return $result;
        } catch(PDOException $e) {
            return [];
        }
    }
    
    /**
     * Get skirt price for specific size and section
     */
    public function getSkirtPrice($size, $section) {
        $skirts = $this->getSkirts();
        foreach ($skirts as $skirt) {
            if ($skirt['size'] === $size && $skirt['section'] === $section) {
                return $skirt['display_price'];
            }
        }
        return 0;
    }
}
?>