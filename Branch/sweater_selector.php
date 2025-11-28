<?php
// sweater_selector.php
class SweaterSelector {
    private $pdo, $schoolName;
    
    public function __construct($pdo, $schoolName) {
        $this->pdo = $pdo;
        $this->schoolName = $schoolName;
    }
    
    /**
     * Get sweaters with school-specific pricing
     */
    public function getSweaters() {
        try {
            // Try multiple possible column names
            $columns = [
                'price_other',
                'price_timeline_devkota_winter', 
                'price_lla_winter',
                'price_subha_rims_akashdep_winter'
            ];
            
            $columnList = implode(', ', $columns);
            $stmt = $this->pdo->query("SELECT size, $columnList FROM sweaters ORDER BY CAST(size AS UNSIGNED)");
            $sweaters = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($sweaters)) {
                return [];
            }
            
            foreach ($sweaters as &$sweater) {
                if ($this->schoolName === 'LLA') {
                    $sweater['display_price'] = $sweater['price_lla_winter'] ?? $sweater['price_other'] ?? 0;
                } elseif (in_array($this->schoolName, ['Subha', 'Aakashdeep', 'Rims'])) {
                    $sweater['display_price'] = $sweater['price_subha_rims_akashdep_winter'] ?? $sweater['price_other'] ?? 0;
                } elseif (in_array($this->schoolName, ['Timeline', 'Devkota'])) {
                    $sweater['display_price'] = $sweater['price_timeline_devkota_winter'] ?? $sweater['price_other'] ?? 0;
                } else {
                    $sweater['display_price'] = $sweater['price_other'] ?? 0;
                }
                
                // Only include if price > 0
                if ($sweater['display_price'] > 0) {
                    $sweater['section'] = 'Winter';
                }
            }
            
            // Filter out zero prices
            $sweaters = array_filter($sweaters, function($sweater) {
                return $sweater['display_price'] > 0;
            });
            
            return array_values($sweaters);
        } catch(PDOException $e) {
            // Debug: Log the exact error
            error_log("Sweater query error: " . $e->getMessage());
            return [];
        }
    }
    
    public function getSweaterPrice($size) {
        $sweaters = $this->getSweaters();
        foreach ($sweaters as $sweater) {
            if ($sweater['size'] === $size) {
                return $sweater['display_price'];
            }
        }
        return 0;
    }
}
?>