<?php
// sweater_selector.php
class SweaterSelector {
    private $pdo;
    private $schoolName;
    
    public function __construct($pdo, $schoolName) {
        $this->pdo = $pdo;
        $this->schoolName = $schoolName;
    }
    
    /**
     * Get sweaters with school-specific pricing
     * LLA → price_lla_winter
     * Subha/Askashdeep/Rims → price_subha_rims_akashdep_winter
     * Timeline/Devkota → price_tileline_devkota_winter
     * Others → price_other
     */
    public function getSweaters() {
        try {
            $stmt = $this->pdo->query("SELECT size, price_other, price_tileline_devkota_winter, price_lla_winter, price_subha_rims_akashdep_winter FROM sweaters ORDER BY CAST(size AS UNSIGNED)");
            $sweaters = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($sweaters as &$sweater) {
                if ($this->schoolName === 'LLA') {
                    $sweater['display_price'] = $sweater['price_lla_winter'];
                    $sweater['section'] = 'LLA Winter';
                } elseif (in_array($this->schoolName, ['Subha', 'Askashdeep', 'Rims'])) {
                    $sweater['display_price'] = $sweater['price_subha_rims_akashdep_winter'];
                    $sweater['section'] = 'Winter';
                } elseif (in_array($this->schoolName, ['Timeline', 'Devkota'])) {
                    $sweater['display_price'] = $sweater['price_tileline_devkota_winter'];
                    $sweater['section'] = 'Winter';
                } else {
                    $sweater['display_price'] = $sweater['price_other'];
                    $sweater['section'] = 'Regular';
                }
            }
            
            return $sweaters;
        } catch(PDOException $e) {
            return [];
        }
    }
    
    /**
     * Get sweater price for specific size
     */
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