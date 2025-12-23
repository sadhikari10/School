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
        $lower = strtolower(trim($item_name));

        // HIGH PRIORITY: specific items
        if (strpos($lower, 'hoodie') !== false || strpos($lower, 'hoodi') !== false) return '🧥'; // outerwear for hoodie
        if (strpos($lower, 'jersey') !== false) return '🎽';
        if (strpos($lower, 'tunic') !== false) return '👗';
        if (strpos($lower, 'muffler') !== false || strpos($lower, 'scarf') !== false) return '🧣';
        if (strpos($lower, 'bag') !== false) return '🎒';
        if (strpos($lower, 'belt') !== false) return '➖'; // simple placeholder for belt

        // FORMAL OUTERWEAR
        if (strpos($lower, 'coat') !== false) return '🧥';
        if (strpos($lower, 'blazer') !== false) return '🧥';
        if (strpos($lower, 'cardigan') !== false) return '🧥';

        // TOPS
        if (strpos($lower, 'shirt') !== false) return '👕';
        if (strpos($lower, 'sweater') !== false) return '🧶';

        // BOTTOMS
        if (strpos($lower, 'pant') !== false) return '👖';
        if (strpos($lower, 'skirt') !== false) return '👗';

        // SPORTSWEAR
        if (strpos($lower, 'track') !== false) return '🏃';

        // ACCESSORIES
        if (strpos($lower, 'tie') !== false) return '👔';
        if (strpos($lower, 'shoe') !== false) return '👟';
        if (strpos($lower, 'sock') !== false || strpos($lower, 'stocking') !== false) return '🧦';
        if (strpos($lower, 'cap') !== false) return '🧢';
        if (strpos($lower, 'hat') !== false) return '👒';
        if (strpos($lower, 'badge') !== false) return '🎖️';
        if (strpos($lower, 'id') !== false) return '🆔';

        // DEFAULT
        return '🎽';
    }


}
?>