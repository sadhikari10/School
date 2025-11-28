<?php
// pant_selector.php
class PantSelector {
    private $pdo, $schoolName;
    
    public function __construct($pdo, $schoolName) {
        $this->pdo = $pdo;
        $this->schoolName = $schoolName;
    }
    
    /**
     * Get pants with school-specific pricing
     * Timeline → price_timeline (single section)
     * Other schools → TWO SECTIONS: price_other and price_indian
     */
    public function getPants() {
        try {
            $stmt = $this->pdo->query("SELECT size, price_other, price_indian, price_timeline FROM pants ORDER BY size");
            $pants = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $result = [];
            
            if ($this->schoolName === 'Timeline') {
                // Timeline: Single section with price_timeline
                foreach ($pants as $pant) {
                    $pant['display_price'] = $pant['price_timeline'];
                    $pant['section'] = 'Timeline';
                    $result[] = $pant;
                }
            } else {
                // Other schools: TWO SECTIONS
                // Section 1: price_other
                foreach ($pants as $pant) {
                    $result[] = [
                        'size' => $pant['size'],
                        'display_price' => $pant['price_other'],
                        'section' => 'Other'
                    ];
                }
                
                // Section 2: price_indian
                foreach ($pants as $pant) {
                    $result[] = [
                        'size' => $pant['size'],
                        'display_price' => $pant['price_indian'],
                        'section' => 'Indian'
                    ];
                }
            }
            
            return $result;
        } catch(PDOException $e) {
            return [];
        }
    }
    
    /**
     * Get pant price for specific size and section
     */
    public function getPantPrice($size, $section = null) {
        $pants = $this->getPants();
        foreach ($pants as $pant) {
            if ($pant['size'] === $size && $pant['section'] === $section) {
                return $pant['display_price'];
            }
        }
        return 0;
    }
}
?>