<?php
class UniformSelector {
    private $pdo;
    private $school_id;
    private $outlet_id;

    public function __construct($pdo, $school_id, $outlet_id) {
        $this->pdo = $pdo;
        $this->school_id = $school_id;
        $this->outlet_id = $outlet_id;
    }

    public function getItems() {
        $query = "
            SELECT 
                i.item_name,
                s.size_value AS size,
                sip.price,
                sip.brand_id
            FROM items i
            LEFT JOIN sizes s ON s.item_id = i.item_id
            LEFT JOIN school_item_prices sip 
                ON sip.item_id = i.item_id 
                AND sip.size_id = s.size_id 
                AND sip.school_id = ?
            WHERE i.outlet_id = ? AND sip.price IS NOT NULL
            ORDER BY 
                i.item_name,
                sip.brand_id ASC,
                s.size_value
        ";
        $stmt = $this->pdo->prepare($query);
        $stmt->execute([$this->school_id, $this->outlet_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $items = [];
        $brandCount = [];

        // ONLY CHANGE: Load brand names from database instead of hardcoding
        $brandStmt = $this->pdo->prepare("SELECT brand_id, brand_name FROM brands WHERE outlet_id = ?");
        $brandStmt->execute([$this->outlet_id]);
        $brandNames = $brandStmt->fetchAll(PDO::FETCH_KEY_PAIR); // This gives real names

        foreach ($rows as $row) {
            $name = trim($row['item_name']);
            $size = trim($row['size']);
            $brandId = (int)$row['brand_id'];
            $brandName = $brandNames[$brandId] ?? 'Unknown';

            if (!isset($items[$name])) {
                $items[$name] = [];
                $brandCount[$name] = [];
            }

            $rawPrice = str_replace(',', '', trim($row['price'] ?? '0'));
            $price = is_numeric($rawPrice) ? (float)$rawPrice : 0;

            $items[$name][] = [
                'size'     => $size,
                'price'    => (float)$row['price'],
                'brand'    => $brandName,
                'brand_id' => $brandId
            ];

            $brandCount[$name][$brandId] = true;
        }

        // Count brands per item
        foreach ($brandCount as $name => $brands) {
            $brandCount[$name] = count($brands);
        }

        // Sort sizes: bundles first, then numerically
        foreach ($items as $name => $sizes) {
            usort($sizes, function($a, $b) {
                $aHasComma = strpos($a['size'], ',') !== false;
                $bHasComma = strpos($b['size'], ',') !== false;

                if ($aHasComma && !$bHasComma) return -1;
                if (!$aHasComma && $bHasComma) return 1;

                $aNum = (float)preg_replace('/[^0-9.]/', '', $a['size']);
                $bNum = (float)preg_replace('/[^0-9.]/', '', $b['size']);
                return $aNum <=> $bNum;
            });
            $items[$name] = $sizes;
        }

        return [
            'items' => $items,
            'brandCount' => $brandCount
        ];
    }

    public function getEmoji($item_name) {
        $iconMap = [
            'Shirt' => '👕', 'Full Shirt' => '👕', 'Half Shirt' => '👕',
            'Pant' => '👖', 'Full Pant' => '👖', 'Half Pant' => '🩳',
            'Skirt' => '👗',
            'Coat' => '🧥', 'Blazer' => '🧥', 'Cardigan' => '🧥',
            'Tracksuit' => '🏃', 'Track Suit' => '🏃', 'Tracksuit 2 Piece' => '🏃',
            'Sweater' => '🧶',
            'Tie' => '👔',
            'Belt' => '🧢',
            'Shoe' => '👟',
            'Socks' => '🧦', 'Stocking' => '🧦',
            'Cap' => '🧢', 'Hat' => '👒',
            'Scarf' => '🧣',
            'Badge' => '🎖️', 'ID Card' => '🆔',
        ];

        $lower = strtolower(trim($item_name));
        foreach ($iconMap as $key => $emoji) {
            if (strpos($lower, strtolower($key)) !== false) {
                return $emoji;
            }
        }

        if (strpos($lower, 'shirt') !== false) return '👕';
        if (strpos($lower, 'pant') !== false) return '👖';
        if (strpos($lower, 'skirt') !== false) return '👗';
        if (strpos($lower, 'track') !== false) return '🏃';
        if (strpos($lower, 'sweater') !== false) return '🧶';
        if (strpos($lower, 'shoe') !== false) return '👟';
        if (strpos($lower, 'tie') !== false) return '👔';
        if (strpos($lower, 'sock') !== false) return '🧦';

        return '🎽';
    }
}
?>