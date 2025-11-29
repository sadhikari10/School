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
            $stmt = $this->pdo->query("
                SELECT 
                    size, 
                    price_timeline_devkota_winter,
                    price_lla_winter,
                    price_subha_rims_akashdep_winter,
                    price_other 
                FROM sweaters 
                ORDER BY CAST(size AS UNSIGNED)
            ");
            $sweaters = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($sweaters)) {
                return [];
            }
            
            $result = [];
            foreach ($sweaters as $sweater) {
                $display_price = 0;
                
                // **EXACT MATCHING AS REQUESTED**
                if ($this->schoolName === 'Timeline' || $this->schoolName === 'Devkota') {
                    $display_price = $sweater['price_timeline_devkota_winter'] ?? 0;
                } 
                elseif ($this->schoolName === 'LLA') {
                    $display_price = $sweater['price_lla_winter'] ?? 0;
                } 
                elseif ($this->schoolName === 'Subha' || $this->schoolName === 'Rims' || $this->schoolName === 'Akashdep') {
                    $display_price = $sweater['price_subha_rims_akashdep_winter'] ?? 0;
                } 
                else {
                    $display_price = $sweater['price_other'] ?? 0;
                }
                
                // Only include if price > 0
                if ($display_price > 0) {
                    $result[] = [
                        'size' => $sweater['size'],
                        'display_price' => $display_price,
                        'section' => 'Winter'
                    ];
                }
            }
            
            return $result;
            
        } catch(PDOException $e) {
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