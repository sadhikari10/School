<?php
// File: MeasurementHelper.php

class MeasurementHelper
{
    private $sessionKey = 'custom_measurements';

    public function __construct()
    {
        if (!isset($_SESSION[$this->sessionKey])) {
            $_SESSION[$this->sessionKey] = [];
        }
    }

    public function getItems(): array
    {
        return $_SESSION[$this->sessionKey];
    }

    public function addItem(string $name, array $measurements, float $price)
    {
        $name = trim($name);
        if ($name === '' || $price <= 0 || empty($measurements)) {
            return false;
        }

        $_SESSION[$this->sessionKey][] = [
            'name'          => $name,
            'measurements'  => $measurements,   // e.g. ['Collar' => '41', 'Waist' => '42']
            'price'         => $price,
            'quantity'      => 1
        ];
        return true;
    }

    public function removeItem(int $index)
    {
        if (isset($_SESSION[$this->sessionKey][$index])) {
            unset($_SESSION[$this->sessionKey][$index]);
            $_SESSION[$this->sessionKey] = array_values($_SESSION[$this->sessionKey]); // re-index
        }
    }

    public function clearAll()
    {
        $_SESSION[$this->sessionKey] = [];
    }

    // Used in bill.php
    public function getForOrder(): array
    {
        $out = [];
        foreach ($this->getItems() as $item) {
            $measText = [];
            foreach ($item['measurements'] as $k => $v) {
                $measText[] = "$k $v";
            }
            $sizeDisplay = !empty($measText) ? implode(', ', $measText) : 'N/A';

            $out[] = [
                'item_name' => $item['name'] . ' (Measurement)',
                'size'      => $sizeDisplay,
                'brand'     => 'Custom',
                'price'     => $item['price'],
                'quantity'  => 1
            ];
        }
        return $out;
    }

    // Render the modal (same style as your size modal)
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
                            <input type="text" placeholder="Value (e.g. 41" class="meas-value" style="width:100px;padding:12px;border-radius:8px;border:1px solid #ccc;">
                            <button type="button" onclick="this.parentElement.remove()" style="background:#e74c3c;color:white;border:none;padding:0 12px;border-radius:6px;">×</button>
                        </div>
                    </div>

                    <button type="button" onclick="addMeasField()" 
                            style="margin:10px 0;padding:10px 20px;background:#667eea;color:white;border:none;border-radius:8px;">
                        + Add Field
                    </button>

                    <input type="number" id="measPrice" placeholder="Price for this item" min="1" step="1"
                           style="width:100%;padding:14px;font-size:16px;border-radius:10px;border:1px solid #ccc;margin-top:10px;">

                    <button type="button" onclick="saveMeasurementItem()" 
                            style="margin-top:20px;width:100%;padding:15px;background:#27ae60;color:white;border:none;border-radius:10px;font-weight:600;font-size:17px;">
                        Add to Cart
                    </button>
                </div>

                <!-- List of added measurement items -->
                <div style="max-height:300px;overflow-y:auto;margin-top:20px;padding:10px;background:#f8f9fa;border-radius:10px;">
                    <?php if (empty($items)): ?>
                        <p style="text-align:center;color:#999;margin:20px 0;">No measurement items added</p>
                    <?php else: ?>
                        <?php foreach ($items as $idx => $item): ?>
                            <div style="background:white;padding:12px;margin-bottom:10px;border-radius:8px;display:flex;justify-content:space-between;align-items:center;">
                                <div>
                                    <strong><?php echo htmlspecialchars($item['name']); ?></strong><br>
                                    <small>
                                        <?php echo htmlspecialchars(implode(', ', array_map(fn($k,$v) => "$k $v", array_keys($item['measurements']), $item['measurements']))); ?>
                                        — Rs. <?php echo number_format($item['price']); ?>
                                    </small>
                                </div>
                                <button type="button" onclick="removeMeasItem(<?php echo $idx; ?>)" 
                                        style="background:#e74c3c;color:white;border:none;padding:6px 12px;border-radius:6px;">Remove</button>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }
}