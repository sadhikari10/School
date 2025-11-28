<?php
// tracksuit_selector.php
class TracksuitSelector {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Get tracksuits with MULTIPLE selectable items
     * 5 SECTIONS: 3-piece, 2-piece, T-shirt, Trouser, Jacket
     */
    public function getTracksuits() {
        try {
            $stmt = $this->pdo->query("SELECT size, price_3pic, price_2pic, price_tshirt, price_trouser, price_jacket FROM tracksuits ORDER BY CAST(size AS UNSIGNED)");
            $tracksuits = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $result = [];
            
            // Section 1: 3-piece
            foreach ($tracksuits as $tracksuit) {
                $result[] = [
                    'size' => $tracksuit['size'],
                    'display_price' => $tracksuit['price_3pic'],
                    'section' => '3-piece'
                ];
            }
            
            // Section 2: 2-piece
            foreach ($tracksuits as $tracksuit) {
                $result[] = [
                    'size' => $tracksuit['size'],
                    'display_price' => $tracksuit['price_2pic'],
                    'section' => '2-piece'
                ];
            }
            
            // Section 3: T-shirt
            foreach ($tracksuits as $tracksuit) {
                $result[] = [
                    'size' => $tracksuit['size'],
                    'display_price' => $tracksuit['price_tshirt'],
                    'section' => 'T-shirt'
                ];
            }
            
            // Section 4: Trouser
            foreach ($tracksuits as $tracksuit) {
                $result[] = [
                    'size' => $tracksuit['size'],
                    'display_price' => $tracksuit['price_trouser'],
                    'section' => 'Trouser'
                ];
            }
            
            // Section 5: Jacket
            foreach ($tracksuits as $tracksuit) {
                $result[] = [
                    'size' => $tracksuit['size'],
                    'display_price' => $tracksuit['price_jacket'],
                    'section' => 'Jacket'
                ];
            }
            
            return $result;
        } catch(PDOException $e) {
            return [];
        }
    }
    
    /**
     * Get tracksuit price for specific size and section
     */
    public function getTracksuitPrice($size, $section) {
        $tracksuits = $this->getTracksuits();
        foreach ($tracksuits as $tracksuit) {
            if ($tracksuit['size'] === $size && $tracksuit['section'] === $section) {
                return $tracksuit['display_price'];
            }
        }
        return 0;
    }
}
?>