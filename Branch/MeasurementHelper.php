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
        return $_SESSION[$this->sessionKey] ?? [];
    }

    public function addItem(string $name, array $measurements, float $price, int $quantity = 1): bool
    {
        $name = trim($name);
        if ($name === '' || $price <= 0 || empty($measurements) || $quantity < 1) {
            return false;
        }

        $cleanMeasurements = [];
        foreach ($measurements as $label => $value) {
            $label = trim($label);
            $value = trim($value);
            if ($label !== '' && $value !== '') {
                $cleanMeasurements[$label] = $value;
            }
        }

        if (empty($cleanMeasurements)) {
            return false;
        }

        $_SESSION[$this->sessionKey][] = [
            'name'         => $name,
            'measurements' => $cleanMeasurements,
            'price'        => $price,
            'quantity'     => $quantity,
            'added_at'     => date('Y-m-d H:i:s')
        ];

        return true;
    }

    public function removeItem(int $index): void
    {
        if (isset($_SESSION[$this->sessionKey][$index])) {
            unset($_SESSION[$this->sessionKey][$index]);
            $_SESSION[$this->sessionKey] = array_values($_SESSION[$this->sessionKey]);
        }
    }

    public function clearAll(): void
    {
        $_SESSION[$this->sessionKey] = [];
    }

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
                'item_name' => $item['name'] . ' (Custom Made)',
                'size'      => $sizeDisplay,
                'brand'     => 'Custom',
                'price'     => $item['price'],
                'quantity'  => $item['quantity']
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
                            <button type="button" onclick="this.parentElement.remove()" style="background:#e74c3c;color:white;border:none;padding:0 12px;border-radius:6px; cursor:pointer;">×</button>
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
                            <input type="number" id="measQuantity" placeholder="Quantity" min="1" step="1"
                                   style="width:100%;padding:14px;font-size:16px;border-radius:10px;border:1px solid #ccc;">
                        </div>
                    </div>

                    <button type="button" onclick="saveMeasurementItem()" 
                            style="margin-top:20px;width:100%;padding:15px;background:#27ae60;color:white;border:none;border-radius:10px;font-weight:600;font-size:17px;cursor:pointer;">
                        Add to Cart
                    </button>
                </div>

                <!-- List of added custom items -->
                <div style="max-height:300px;overflow-y:auto;margin-top:20px;padding:10px;background:#f9f9f9;border-radius:10px;">
                    <?php if (empty($items)): ?>
                        <p style="text-align:center;color:#999;margin:20px 0;">No custom items added yet</p>
                    <?php else: ?>
                        <?php foreach ($items as $idx => $item): ?>
                            <div style="background:white;padding:14px;margin-bottom:12px;border-radius:10px;display:flex;justify-content:space-between;align-items:flex-start;box-shadow:0 2px 10px rgba(0,0,0,0.08);">
                                <div style="flex:1;">
                                    <strong style="font-size:16px;color:#2c3e50;"><?php echo htmlspecialchars($item['name']); ?></strong>
                                    <br>
                                    <small style="color:#555;line-height:1.6;">
                                        <?php echo htmlspecialchars(implode(' | ', array_map(fn($k,$v) => "$k $v", array_keys($item['measurements']), $item['measurements']))); ?>
                                    </small>
                                    <br>
                                    <small style="color:#27ae60;font-weight:600;">
                                        Rs. <?php echo number_format($item['price']); ?> × <?php echo $item['quantity']; ?> pcs 
                                        = Rs. <?php echo number_format($item['price'] * $item['quantity']); ?>
                                    </small>
                                </div>
                                <button type="button" onclick="removeMeasItem(<?php echo $idx; ?>)"
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