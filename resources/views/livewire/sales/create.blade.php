<?php

use App\Models\Inventory;
use App\Models\Transaction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    #[Validate('required|numeric|min:1|max:10000')]
    public float $quantity_kg = 1;

    #[Validate('required|numeric|min:1')]
    public float $total_amount = 1;

    #[Validate('nullable|string|max:255')]
    public ?string $customer_name = null;

    #[Validate('nullable|string|max:20')]
    public ?string $customer_phone = null;

    #[Validate('nullable|string|max:500')]
    public ?string $notes = null;

    #[Validate('required|in:cash,card,transfer')]
    public string $payment_type = 'cash';

    public $inventory;
    public $price_per_kg;
    public $showReceipt = false;
    public $currentTransaction = null;
    public $calculationMode = 'quantity'; // 'quantity' or 'total'
    
    public function mount()
    {
        $this->inventory = Inventory::first();
        $this->price_per_kg = $this->inventory->price_per_kg;
        $this->calculateTotal(); // Initialize the total amount
    }
    
    public function calculateTotal(): void
    {
        try {
            $quantity = $this->nullSafeFloat($this->quantity_kg);
            $price = $this->nullSafeFloat($this->price_per_kg);
            $this->total_amount = max(0, round($quantity * $price, 2));
            $this->quantity_kg = max(0,$this->total_amount / $this->price_per_kg);
        } catch (\Throwable $e) {
            $this->total_amount = 0.0;
            logger()->error('CalculateTotal error: ' . $e->getMessage());
        }
    }

    public function calculateQuantity(): void
    {
        try {
            $total = $this->nullSafeFloat($this->total_amount);
            $price = $this->nullSafeFloat($this->price_per_kg);
            
            $this->quantity_kg = $price > 0 
                ? max(0, round($total / $price, 3))
                : 0.0;
            $this->total_amount = max(0,$this->quantity_kg * $this->price_per_kg); 
        } catch (\Throwable $e) {
            $this->quantity_kg = 0.0;
            logger()->error('CalculateQuantity error: ' . $e->getMessage());
        }
    }

    protected function nullSafeFloat($value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }
        
        if (is_numeric($value)) {
            return (float)$value;
        }
        
        throw new \InvalidArgumentException("Invalid numeric value: " . print_r($value, true));
    }

    public function createSale()
    {
        $this->validate();

        // Check if quantity is greater than 0
        if ($this->quantity_kg <= 0) {
            $this->addError('quantity_kg', 'Quantity must be greater than 0.');
            return;
        }

        // Check if sufficient stock
        if ($this->quantity_kg > $this->inventory->current_stock) {
            $this->addError('quantity_kg', 'Insufficient stock. Available: ' . number_format($this->inventory->current_stock, 2) . ' kg');
            return;
        }

        try {
            DB::beginTransaction();

            // Create transaction
            $transaction = Transaction::create([
                'cashier_id' => Auth::id(),
                'quantity_kg' => $this->quantity_kg,
                'price_per_kg' => $this->price_per_kg,
                'total_amount' => $this->total_amount,
                'customer_name' => $this->customer_name,
                'customer_phone' => $this->customer_phone,
                'payment_type' => $this->payment_type,
                'notes' => $this->notes,
                'status' => 'completed',
            ]);

            // Update inventory
            $this->inventory->subtractStock(
                $this->quantity_kg,
                'Sale transaction: ' . $transaction->transaction_number,
                Auth::user(),
                $this->notes
            );

            DB::commit();

            // Show receipt
            $this->currentTransaction = $transaction;
            $this->showReceipt = true;

            // Reset form
            $this->reset(['quantity_kg', 'total_amount', 'customer_name', 'customer_phone', 'payment_type', 'notes']);
            $this->payment_type = 'cash'; // Ensure default is set after reset
            $this->calculateTotal();

            // Refresh inventory
            $this->inventory->refresh();

        } catch (\Exception $e) {
            DB::rollBack();
            $this->addError('general', 'Failed to create sale. Please try again.');
        }
    }

    public function printReceipt()
    {
        if (!$this->currentTransaction) {
            return;
        }

        // Generate receipt HTML for thermal printer
        $receiptHtml = $this->generateReceiptHtml();
        
        // Dispatch print event with receipt data
        $this->dispatch('print-receipt', [
            'html' => $receiptHtml,
            'transactionId' => $this->currentTransaction->id
        ]);
        
        // Log for debugging
        logger()->info('Print receipt called', [
            'transaction_id' => $this->currentTransaction->id,
            'html_length' => strlen($receiptHtml)
        ]);
    }

    private function generateReceiptHtml(): string
    {
        $companyName = \App\Services\CompanySettingsService::getCompanyName();
        $companyAddress = \App\Services\CompanySettingsService::getCompanyAddress();
        $companyPhone = \App\Services\CompanySettingsService::getCompanyPhone();
        $companyLogo = \App\Services\CompanySettingsService::getCompanyLogoUrl();
        $transaction = $this->currentTransaction;
        
        $customerName = $transaction->customer_name ?: 'Walk-in Customer';
        $customerPhone = $transaction->customer_phone ?: '';
        
        $logoHtml = $companyLogo ? "<img src='$companyLogo' style='width: 160px; height: 160px; object-fit: contain; margin: 0 auto 15px; display: block;' alt='Company Logo'>" : "";
        
        return "
        <div style='font-family: monospace; width: 56mm; max-width: 56mm; margin: 0 auto;'>
            <!-- Header -->
            <div style='text-align: center; margin-bottom: 15px;'>
                $logoHtml
                <h1 style='font-size: 16px; font-weight: bold; margin: 0;'>$companyName</h1>
                <p style='font-size: 12px; margin: 3px 0;'>$companyAddress</p>
                <p style='font-size: 12px; margin: 3px 0;'>$companyPhone</p>
            </div>
            
            <!-- Divider -->
            <div style='border-top: 1px dashed #000; margin: 10px 0;'></div>
            
            <!-- Transaction Info -->
            <div style='margin-bottom: 12px;'>
                <div style='display: flex; justify-content: space-between; font-size: 12px;'>
                    <span>Receipt #:</span>
                    <span>{$transaction->transaction_number}</span>
                </div>
                <div style='display: flex; justify-content: space-between; font-size: 12px;'>
                    <span>Date:</span>
                    <span>{$transaction->created_at->format('M d, Y H:i')}</span>
                </div>
                <div style='display: flex; justify-content: space-between; font-size: 12px;'>
                    <span>Cashier:</span>
                    <span>{$transaction->cashier->name}</span>
                </div>
            </div>
            
            <!-- Customer Info -->
            <div style='margin-bottom: 12px;'>
                <div style='font-size: 12px; font-weight: bold; margin-bottom: 6px;'>CUSTOMER:</div>
                <div style='font-size: 12px;'>$customerName</div>
                " . ($customerPhone ? "<div style='font-size: 12px;'>$customerPhone</div>" : "") . "
            </div>
            
            <!-- Divider -->
            <div style='border-top: 1px dashed #000; margin: 10px 0;'></div>
            
            <!-- Items -->
            <div style='margin-bottom: 12px;'>
                <div style='font-size: 12px; font-weight: bold; margin-bottom: 6px;'>ITEMS:</div>
                <div style='display: flex; justify-content: space-between; font-size: 12px;  font-weight: bold;'>
                    <span>LPG Gas</span>
                    <span>{$transaction->formatted_quantity}</span>
                </div>
                <div style='display: flex; justify-content: space-between; font-size: 12px;'>
                    <span>@ {$transaction->formatted_price_per_kg}</span>
                    <span></span>
                </div>
            </div>
            
            <!-- Divider -->
            <div style='border-top: 1px dashed #000; margin: 10px 0;'></div>
            
            <!-- Payment Info -->
            <div style='margin-bottom: 12px;'>
                <div style='display: flex; justify-content: space-between; font-size: 12px;'>
                    <span>Payment Method:</span>
                    <span>" . ucfirst($transaction->payment_type) . "</span>
                </div>
            </div>
            
            <!-- Total -->
            <div style='margin-bottom: 12px;'>
                <div style='display: flex; justify-content: space-between; font-size: 14px; font-weight: bold;'>
                    <span>TOTAL:</span>
                    <span>{$transaction->formatted_total}</span>
                </div>
            </div>
            
            " . ($transaction->notes ? "
            <!-- Notes -->
            <div style='margin-bottom: 12px;'>
                <div style='font-size: 12px; font-weight: bold; margin-bottom: 6px;'>NOTES:</div>
                <div style='font-size: 12px;'>{$transaction->notes}</div>
            </div>
            " : "") . "
            
            <!-- Divider -->
            <div style='border-top: 1px dashed #000; margin: 10px 0;'></div>
            
            <!-- Footer -->
            <div style='text-align: center; margin-top: 15px;'>
                <p style='font-size: 12px; margin: 3px 0;'>Thank you for your purchase!</p>
                <p style='font-size: 10px; margin: 3px 0;'>Please keep this receipt for your records</p>
                <p style='font-size: 10px; margin: 3px 0;'>For inquiries: $companyPhone</p>
            </div>
        </div>
        ";
    }

    public function closeReceipt()
    {
        $this->showReceipt = false;
        $this->currentTransaction = null;
    }

    public function testPrint()
    {
        $this->dispatch('test-print');
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-4 p-4 sm:gap-6 sm:p-6">
    <!-- Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-white sm:text-2xl">New Sale</h1>
            <p class="text-sm text-gray-600 dark:text-gray-400 sm:text-base">Create a new gas sale transaction</p>
        </div>
        <div class="text-right">
            <p class="text-xs text-gray-600 dark:text-gray-400 sm:text-sm">Available Stock</p>
            <p class="text-xl font-bold {{ $inventory->isLowStock() ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-white' }} sm:text-2xl">
                {{ number_format($inventory->current_stock, 2) }} kg
            </p>
        </div>
    </div>

    @if($showReceipt && $currentTransaction)
        <!-- Receipt Modal -->
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 p-4">
            <div class="w-full max-w-lg rounded-xl bg-white p-6 dark:bg-gray-800">
                <!-- Company Header -->
                <div class="border-b border-gray-200 pb-4 dark:border-gray-700">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            @if(\App\Services\CompanySettingsService::getCompanyLogoUrl())
                                <img src="{{ \App\Services\CompanySettingsService::getCompanyLogoUrl() }}" 
                                     alt="Company Logo" 
                                     class="h-12 w-12 rounded-lg object-cover">
                            @else
                                <div class="h-12 w-12 rounded-lg bg-blue-100 dark:bg-blue-900 flex items-center justify-center">
                                    <svg class="h-6 w-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                    </svg>
                                </div>
                            @endif
                            <div>
                                <h2 class="text-lg font-bold text-gray-900 dark:text-white">
                                    {{ \App\Services\CompanySettingsService::getCompanyName() }}
                                </h2>
                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                    {{ \App\Services\CompanySettingsService::getCompanyAddress() }}
                                </p>
                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                    {{ \App\Services\CompanySettingsService::getCompanyPhone() }}
                                </p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-xs text-gray-500 dark:text-gray-400">Transaction #</p>
                            <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $currentTransaction->transaction_number }}</p>
                        </div>
                    </div>
                </div>

                <!-- Transaction Details -->
                <div class="py-4">
                    <div class="mb-4">
                        <h3 class="text-sm font-medium text-gray-900 dark:text-white mb-2">Customer Information</h3>
                        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-3">
                            @if($currentTransaction->customer_name)
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-gray-600 dark:text-gray-400">Name:</span>
                                    <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $currentTransaction->customer_name }}</span>
                                </div>
                                @if($currentTransaction->customer_phone)
                                    <div class="flex justify-between items-center mt-1">
                                        <span class="text-sm text-gray-600 dark:text-gray-400">Phone:</span>
                                        <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $currentTransaction->customer_phone }}</span>
                                    </div>
                                @endif
                            @else
                                <div class="text-center">
                                    <span class="text-sm font-medium text-gray-900 dark:text-white">Walk-in Customer</span>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-gray-600 dark:text-gray-400">Quantity:</span>
                            <span class="font-medium text-gray-900 dark:text-white">{{ $currentTransaction->formatted_quantity }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600 dark:text-gray-400">Price per kg:</span>
                            <span class="font-medium text-gray-900 dark:text-white">{{ $currentTransaction->formatted_price_per_kg }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600 dark:text-gray-400">Payment Type:</span>
                            <span class="font-medium text-gray-900 dark:text-white">{{ ucfirst($currentTransaction->payment_type) }}</span>
                        </div>
                        @if($currentTransaction->notes)
                            <div class="pt-2 border-t border-gray-200 dark:border-gray-700">
                                <span class="text-sm text-gray-600 dark:text-gray-400">Notes:</span>
                                <p class="text-sm text-gray-900 dark:text-white mt-1">{{ $currentTransaction->notes }}</p>
                            </div>
                        @endif
                        <div class="flex justify-between border-t border-gray-200 pt-3 dark:border-gray-700">
                            <span class="text-lg font-medium text-gray-900 dark:text-white">Total:</span>
                            <span class="text-lg font-bold text-gray-900 dark:text-white">{{ $currentTransaction->formatted_total }}</span>
                        </div>
                    </div>

                    <div class="mt-4 text-center text-xs text-gray-500 dark:text-gray-400">
                        <p>Date: {{ $currentTransaction->created_at->format('M d, Y H:i') }}</p>
                        <p>Cashier: {{ $currentTransaction->cashier->name }}</p>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="border-t border-gray-200 pt-4 dark:border-gray-700">
                    <div class="flex gap-3">
                        <button 
                            x-data="{ printReceipt() { 
                                console.log('Alpine.js print function called');
                                const html = @js($this->generateReceiptHtml());
                                const transactionId = @js($currentTransaction->id);
                                this.printDirectly(html, transactionId);
                            },
                            printDirectly(html, transactionId) {
                                try {
                                    console.log('Creating iframe for printing...');
                                    const printFrame = document.createElement('iframe');
                                    printFrame.style.position = 'fixed';
                                    printFrame.style.right = '0';
                                    printFrame.style.bottom = '0';
                                    printFrame.style.width = '0';
                                    printFrame.style.height = '0';
                                    printFrame.style.border = '0';
                                    printFrame.style.visibility = 'hidden';
                                    
                                    document.body.appendChild(printFrame);
                                    
                                    const printContent = `<!DOCTYPE html>
<html>
<head>
    <title>Receipt - ${transactionId}</title>
    <style>
        @page { size: 56mm auto; margin: 0; }
        body { margin: 0; padding: 10px; font-family: monospace; background: white; width: 56mm; max-width: 56mm; font-size: 12px; }
        @media print { body { width: 56mm; max-width: 56mm; } }
    </style>
</head>
<body>
    ${html}
</body>
</html>`;
                                    
                                    printFrame.contentDocument.write(printContent);
                                    printFrame.contentDocument.close();
                                    
                                    printFrame.onload = function() {
                                        setTimeout(() => {
                                            printFrame.contentWindow.print();
                                            setTimeout(() => {
                                                document.body.removeChild(printFrame);
                                            }, 1000);
                                        }, 100);
                                    };
                                } catch (error) {
                                    console.error('Print error:', error);
                                    alert('Printing failed: ' + error.message);
                                }
                            } }"
                            @click="printReceipt()"
                            class="flex-1 rounded-lg bg-blue-600 px-4 py-2 text-white hover:bg-blue-700 transition-colors"
                        >
                            <svg class="inline-block w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                            </svg>
                            Print Receipt
                        </button>

                        <button wire:click="closeReceipt" class="flex-1 rounded-lg border border-gray-300 px-4 py-2 text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700 transition-colors">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Main Content -->
    <div class="flex flex-1 flex-col gap-4 lg:flex-row lg:gap-6">
        <!-- Form Section -->
        <div class="flex-1 space-y-4">
            <!-- Total Amount Display Card -->
            <div class="rounded-xl border-2 border-blue-200 bg-gradient-to-r from-blue-50 to-indigo-50 p-6 dark:border-blue-700 dark:from-blue-900/20 dark:to-indigo-900/20">
                <div class="text-center">
                    <h3 class="text-lg font-medium text-blue-900 dark:text-blue-100">Total Amount</h3>
                    <div class="mt-2">
                        <span class="text-4xl font-bold text-blue-600 dark:text-blue-400 sm:text-5xl lg:text-6xl">
                            ₦{{ number_format($total_amount, 2) }}
                        </span>
                    </div>
                    <p class="mt-2 text-sm text-blue-700 dark:text-blue-300">
                        {{ number_format($quantity_kg, 2) }} kg × ₦{{ number_format($price_per_kg, 2) }} per kg
                    </p>
                </div>
            </div>

                        <!-- Sale Form -->
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800 sm:p-6">
                <h3 class="mb-4 text-lg font-medium text-gray-900 dark:text-white">Sale Details</h3>

                <form wire:submit="createSale" id="sale-form" class="space-y-4">
                    <!-- Quantity and Total Amount Row -->
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                        <flux:input
                            wire:model.live.debounce.500ms="quantity_kg"
                            wire:change="calculateTotal"
                            label="Quantity (kg)"
                            type="number"
                            step="0.001"
                            min="1"
                            max="{{ $inventory->current_stock }}"
                            required
                            placeholder="Enter quantity"
                        />
                        @error('quantity_kg')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                        <div>
                        <div>
                            <flux:input
                                wire:model.live.debounce.500ms="total_amount"
                                wire:change="calculateQuantity"
                                label="Total Amount (₦)"
                                type="number"
                                step="0.01"
                                min="1"
                                required
                                placeholder="Enter total amount"
                            />
                            </div>
                            @error('total_amount')
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <!-- Price per kg (readonly) -->
                    <div>
                        <flux:input
                            wire:model="price_per_kg"
                            label="Price per kg (₦)"
                            type="number"
                            step="0.01"
                            readonly
                            class="bg-gray-50 dark:bg-gray-700"
                        />
                    </div>

                    <!-- Customer Information Row -->
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <flux:input
                                wire:model="customer_name"
                                label="Customer Name (Optional)"
                                type="text"
                                placeholder="Enter customer name"
                            />
                        </div>

                        <div>
                            <flux:input
                                wire:model="customer_phone"
                                label="Customer Phone (Optional)"
                                type="tel"
                                placeholder="Enter customer phone"
                            />
                        </div>
                    </div>

                    <!-- Payment Type -->
                    <div>
                        <flux:select wire:model="payment_type" label="Payment Type" required>
                            <option value="cash">Cash</option>
                            <option value="card">Card</option>
                            <option value="transfer">Transfer</option>
                        </flux:select>
                    </div>

                    <!-- Notes -->
                    <div>
                        <flux:textarea
                            wire:model="notes"
                            label="Notes (Optional)"
                            placeholder="Additional notes about this sale"
                            rows="3"
                        />
                    </div>

                    @error('general')
                        <div class="rounded-lg bg-red-50 p-4 dark:bg-red-900/20">
                            <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        </div>
                    @enderror
                </form>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="w-full space-y-4 lg:w-80">
            <!-- Complete Sale Button - Always Visible -->
            <div class="sticky top-4 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800 sm:p-6">
                <h3 class="mb-4 text-lg font-medium text-gray-900 dark:text-white">Complete Sale</h3>
                
                <flux:button
                    type="submit"
                    form="sale-form"
                    variant="primary"
                    class="w-full"
                    wire:loading.attr="disabled"
                >
                    <span wire:loading.remove>Complete Sale</span>
                    <span wire:loading>Processing...</span>
                </flux:button>

                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                    Click to process the sale and update inventory
                </p>
            </div>

            <!-- Quick Actions -->
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800 sm:p-6">
                <h3 class="mb-4 text-lg font-medium text-gray-900 dark:text-white">Quick Actions</h3>
                <div class="space-y-3">
                    <a href="{{ route('sales.history') }}" class="flex items-center justify-center rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700 transition-colors hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700">
                        <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        View Sales History
                    </a>
                    <a href="{{ route('dashboard') }}" class="flex items-center justify-center rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700 transition-colors hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700">
                        <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                        </svg>
                        Back to Dashboard
                    </a>
                </div>
            </div>

            <!-- Stock Summary -->
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800 sm:p-6">
                <h3 class="mb-4 text-lg font-medium text-gray-900 dark:text-white">Stock Summary</h3>
                <div class="space-y-3">
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600 dark:text-gray-400">Current Stock:</span>
                        <span class="font-medium text-gray-900 dark:text-white">{{ number_format($inventory->current_stock, 2) }} kg</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600 dark:text-gray-400">Minimum Stock:</span>
                        <span class="font-medium text-gray-900 dark:text-white">{{ number_format($inventory->minimum_stock, 2) }} kg</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600 dark:text-gray-400">Status:</span>
                        <span class="font-medium {{ $inventory->isLowStock() ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}">
                            {{ $inventory->isLowStock() ? 'Low Stock' : 'Good' }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


