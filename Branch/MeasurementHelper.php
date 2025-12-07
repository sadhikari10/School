<?php
// File: MeasurementHelper.php

class MeasurementHelper
{
    private $pdo;
    private $sessionId;
    private $outletId;
    private $schoolId;

    public function __construct($pdo)
    {
        $this->pdo       = $pdo;
        $this->sessionId = session_id() ?: uniqid('sess_', true);
        $this->outletId  = $_SESSION['outlet_id'] ?? 0;
        $this->schoolId  = $_SESSION['selected_school_id'] ?? null;

        // Auto-clean old temporary records (> 48 hours)
        $this->pdo->query("DELETE FROM temp_measurements WHERE created_at < DATE_SUB(NOW(), INTERVAL 48 HOUR)");
    }

    private function getSessionFilter(): string
    {
        return "session_id = " . $this->pdo->quote($this->sessionId);
    }

    public function getItems(): array
    {
        $sql  = "SELECT id, item_name, measurements, price, quantity 
                 FROM temp_measurements 
                 WHERE {$this->getSessionFilter()} 
                 ORDER BY created_at DESC";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addItem(string $name, array $measurements, float $price, int $quantity = 1): bool
    {
        $name = trim($name);
        if ($name === '' || $price <= 0 || empty($measurements) || $quantity < 1 || $this->outletId <= 0) {
            return false;
        }

        $clean = [];
        foreach ($measurements as $label => $value) {
            $label = trim($label);
            $value = trim($value);
            if ($label !== '' && $value !== '') {
                $clean[$label] = $value;
            }
        }

        if (empty($clean)) {
            return false;
        }

        $sql = "INSERT INTO temp_measurements 
                (session_id, outlet_id, school_id, item_name, measurements, price, quantity)
                VALUES (?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            $this->sessionId,
            $this->outletId,
            $this->schoolId,
            $name,
            json_encode($clean, JSON_UNESCAPED_UNICODE),
            $price,
            $quantity
        ]);
    }

    public function removeItem(int $id): bool
    {
        if ($id <= 0) return false;

        $sql = "DELETE FROM temp_measurements 
                WHERE id = ? AND {$this->getSessionFilter()}";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);

        return $stmt->rowCount() > 0;
    }

    public function clearAll(): void
    {
        $this->pdo->prepare("DELETE FROM temp_measurements WHERE {$this->getSessionFilter()}")->execute();
    }

    public function getForOrder(): array
    {
        $items = $this->getItems();
        $out   = [];

        foreach ($items as $item) {
            $meas = json_decode($item['measurements'], true);
            $parts = [];
            foreach ($meas as $k => $v) {
                $parts[] = "$k $v";
            }
            $sizeDisplay = !empty($parts) ? implode(', ', $parts) : 'N/A';

            $out[] = [
                'item_name' => $item['item_name'] . ' (Custom Made)',
                'size'      => $sizeDisplay,
                'brand'     => 'Custom',
                'price'     => (float)$item['price'],
                'quantity'  => (int)$item['quantity']
            ];
        }
        return $out;
    }

    public function renderModal()
{
    $items = $this->getItems();
    ?>
    <div class="modal" id="measurementModal">
        <div class="modal-content">
            <div class="modal-header">
                <div style="display:flex;align-items:center;gap:15px;flex:1;">
                    <h3 style="margin:0; font-size:24px;">Take Measurement</h3>
                </div>
                <button type="button" class="back-btn-modal" onclick="closeMeasurementModal()">Back</button>
            </div>

            <div style="padding:20px;">
                <input type="text" id="measItemName" placeholder="Item name (e.g. Full Suit, Blazer, Pant)" 
                       style="width:100%;padding:14px;font-size:16px;border-radius:10px;border:1px solid #ccc;margin-bottom:15px;">

                <div id="measFields">
                    <div class="meas-row" style="display:flex;gap:10px;margin-bottom:10px;">
                        <input type="text" placeholder="Field (e.g. Collar)" class="meas-label" style="flex:1;padding:12px;border-radius:8px;border:1px solid #ccc;">
                        <input type="text" placeholder="Value (e.g. 41)" class="meas-value" style="width:100px;padding:12px;border-radius:8px;border:1px solid #ccc;">
                        <button type="button" onclick="this.parentElement.remove()" style="background:#e74c3c;color:white;border:none;padding:0 12px;border-radius:6px;cursor:pointer;">×</button>
                    </div>
                </div>

                <button type="button" onclick="addMeasField()" 
                        style="margin:10px 0;padding:10px 20px;background:#667eea;color:white;border:none;border-radius:8px;cursor:pointer;">
                    + Add Field
                </button>

                <div style="display:flex;gap:15px;margin-top:10px;">
                    <div style="flex:1;">
                        <input type="number" id="measPrice" placeholder="Price per piece" min="1" step="1"
                               style="width:100%;padding:14px;font-size:16px;border-radius:10px;border:1px solid #ccc;">
                    </div>
                    <div style="flex:1;">
                        <input type="number" id="measQuantity" placeholder="Quantity" min="1" step="1" value="1"
                               style="width:100%;padding:14px;font-size:16px;border-radius:10px;border:1px solid #ccc;">
                    </div>
                </div>

                <button type="button" onclick="saveMeasurementItem()" 
                        style="margin-top:20px;width:100%;padding:15px;background:#27ae60;color:white;border:none;border-radius:10px;font-weight:600;font-size:17px;cursor:pointer;">
                    Add to Cart
                </button>
            </div>

            <!-- List of added custom items — YOUR ORIGINAL BEAUTIFUL STYLE -->
            <div style="max-height:300px;overflow-y:auto;margin-top:20px;padding:10px;background:#f9f9f9;border-radius:10px;">
                <?php if (empty($items)): ?>
                    <p style="text-align:center;color:#999;margin:20px 0;">No custom items added yet</p>
                <?php else: ?>
                    <?php foreach ($items as $item): ?>
                        <div style="background:white;padding:14px;margin-bottom:12px;border-radius:10px;display:flex;justify-content:space-between;align-items:flex-start;box-shadow:0 2px 10px rgba(0,0,0,0.08);">
                            <div style="flex:1;">
                                <strong style="font-size:16px;color:#2c3e50;"><?php echo htmlspecialchars($item['item_name']); ?></strong>
                                <br>
                                <?php 
                                    $meas = json_decode($item['measurements'], true) ?: [];
                                    $parts = [];
                                    foreach ($meas as $k => $v) $parts[] = "$k $v";
                                ?>
                                <small style="color:#555;line-height:1.6;">
                                    <?php echo htmlspecialchars(implode(' | ', $parts)); ?>
                                </small>
                                <br>
                                <small style="color:#27ae60;font-weight:600;">
                                    Rs. <?php echo number_format($item['price']); ?> × <?php echo $item['quantity']; ?> pcs 
                                    = Rs. <?php echo number_format($item['price'] * $item['quantity']); ?>
                                </small>
                            </div>
                            <button type="button" 
                                    onclick="removeMeasItem(<?php echo $item['id']; ?>)"
                                    style="background:#e74c3c;color:white;border:none;padding:8px 14px;border-radius:6px;font-size:14px;cursor:pointer;margin-left:10px;">
                                Remove
                            </button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}
}
?>