<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Events\Dispatcher;

class PurchaseOrderEventListener
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Register the listeners for the subscriber.
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            'purchase-order.created' => 'handlePurchaseOrderCreated',
            'purchase-order.updated' => 'handlePurchaseOrderUpdated', 
            'purchase-order.status-changed' => 'handleStatusChanged',
            'purchase-order.items-received' => 'handleItemsReceived',
        ];
    }

    /**
     * Handle purchase order created event.
     */
    public function handlePurchaseOrderCreated(PurchaseOrder $purchaseOrder): void
    {
        Log::info('Processing purchase order created event', [
            'order_id' => $purchaseOrder->id,
            'order_number' => $purchaseOrder->order_number,
        ]);

        // Send notification to purchasing team
        if (config('inventory.notifications.purchase_order_events.created')) {
            $this->notifyPurchasingTeam('Purchase Order Created', $purchaseOrder, [
                'message' => "Purchase order {$purchaseOrder->order_number} has been created.",
                'total_amount' => $purchaseOrder->total_amount,
                'supplier' => $purchaseOrder->supplier->name,
            ]);
        }

        // Auto-confirm if configured
        if (config('inventory.purchase_orders.auto_confirm')) {
            $purchaseOrder->update(['status' => 'confirmed']);
            Log::info('Purchase order auto-confirmed', [
                'order_id' => $purchaseOrder->id,
            ]);
        }
    }

    /**
     * Handle purchase order updated event.
     */
    public function handlePurchaseOrderUpdated(PurchaseOrder $purchaseOrder): void
    {
        Log::info('Purchase order updated', [
            'order_id' => $purchaseOrder->id,
            'order_number' => $purchaseOrder->order_number,
        ]);

        // Additional logic for order updates can be added here
        // For example: notify supplier of changes, update integrated systems, etc.
    }

    /**
     * Handle purchase order status changed event.
     */
    public function handleStatusChanged(PurchaseOrder $purchaseOrder, string $oldStatus, string $newStatus): void
    {
        Log::info('Purchase order status changed', [
            'order_id' => $purchaseOrder->id,
            'order_number' => $purchaseOrder->order_number,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
        ]);

        // Handle specific status transitions
        match ($newStatus) {
            'confirmed' => $this->handleOrderConfirmed($purchaseOrder),
            'received' => $this->handleOrderReceived($purchaseOrder),
            'cancelled' => $this->handleOrderCancelled($purchaseOrder),
            'partially_received' => $this->handlePartiallyReceived($purchaseOrder),
            default => null,
        };
    }

    /**
     * Handle items received event.
     */
    public function handleItemsReceived(PurchaseOrder $purchaseOrder, array $receivedDetails, $location): void
    {
        Log::info('Purchase order items received', [
            'order_id' => $purchaseOrder->id,
            'order_number' => $purchaseOrder->order_number,
            'location' => $location->name,
            'items_count' => count($receivedDetails),
        ]);

        // Send notification about received items
        if (config('inventory.notifications.purchase_order_events.received')) {
            $this->notifyStockManagers('Items Received', $purchaseOrder, [
                'message' => "Items from purchase order {$purchaseOrder->order_number} have been received at {$location->name}.",
                'received_items' => $receivedDetails,
                'location' => $location->name,
            ]);
        }

        // Check for low stock items that might now be replenished
        $this->checkLowStockReplenishment($receivedDetails);
    }

    /**
     * Handle order confirmed.
     */
    private function handleOrderConfirmed(PurchaseOrder $purchaseOrder): void
    {
        if (config('inventory.notifications.purchase_order_events.confirmed')) {
            $this->notifySupplier($purchaseOrder, [
                'message' => "Purchase order {$purchaseOrder->order_number} has been confirmed.",
                'expected_date' => $purchaseOrder->expected_date?->format('Y-m-d'),
            ]);
        }

        // Set up overdue monitoring
        $this->scheduleOverdueCheck($purchaseOrder);
    }

    /**
     * Handle order received.
     */
    private function handleOrderReceived(PurchaseOrder $purchaseOrder): void
    {
        Log::info('Purchase order fully received', [
            'order_id' => $purchaseOrder->id,
            'order_number' => $purchaseOrder->order_number,
        ]);

        // Notify relevant teams
        $this->notifyPurchasingTeam('Order Completed', $purchaseOrder, [
            'message' => "Purchase order {$purchaseOrder->order_number} has been fully received.",
        ]);
    }

    /**
     * Handle order cancelled.
     */
    private function handleOrderCancelled(PurchaseOrder $purchaseOrder): void
    {
        Log::warning('Purchase order cancelled', [
            'order_id' => $purchaseOrder->id,
            'order_number' => $purchaseOrder->order_number,
        ]);

        // Notify supplier and internal teams
        $this->notifySupplier($purchaseOrder, [
            'message' => "Purchase order {$purchaseOrder->order_number} has been cancelled.",
        ]);
    }

    /**
     * Handle partially received order.
     */
    private function handlePartiallyReceived(PurchaseOrder $purchaseOrder): void
    {
        // Check if follow-up is needed
        $outstandingItems = $this->getOutstandingItems($purchaseOrder);
        
        if (count($outstandingItems) > 0) {
            $this->notifyPurchasingTeam('Partial Delivery', $purchaseOrder, [
                'message' => "Purchase order {$purchaseOrder->order_number} has been partially received.",
                'outstanding_items' => $outstandingItems,
            ]);
        }
    }

    /**
     * Notify purchasing team.
     */
    private function notifyPurchasingTeam(string $subject, PurchaseOrder $purchaseOrder, array $data): void
    {
        $recipients = $this->getPurchasingTeamEmails();
        
        if (empty($recipients)) {
            return;
        }

        // In a real app, you would send actual notifications/emails here
        Log::info('Notification sent to purchasing team', [
            'subject' => $subject,
            'order_number' => $purchaseOrder->order_number,
            'recipients' => $recipients,
            'data' => $data,
        ]);
    }

    /**
     * Notify stock managers.
     */
    private function notifyStockManagers(string $subject, PurchaseOrder $purchaseOrder, array $data): void
    {
        $recipients = $this->getStockManagerEmails();
        
        if (empty($recipients)) {
            return;
        }

        // In a real app, you would send actual notifications/emails here
        Log::info('Notification sent to stock managers', [
            'subject' => $subject,
            'order_number' => $purchaseOrder->order_number,
            'recipients' => $recipients,
            'data' => $data,
        ]);
    }

    /**
     * Notify supplier.
     */
    private function notifySupplier(PurchaseOrder $purchaseOrder, array $data): void
    {
        if (!$purchaseOrder->supplier->email) {
            return;
        }

        // In a real app, you would send actual emails to suppliers here
        Log::info('Notification sent to supplier', [
            'supplier_id' => $purchaseOrder->supplier->id,
            'supplier_email' => $purchaseOrder->supplier->email,
            'order_number' => $purchaseOrder->order_number,
            'data' => $data,
        ]);
    }

    /**
     * Schedule overdue check for purchase order.
     */
    private function scheduleOverdueCheck(PurchaseOrder $purchaseOrder): void
    {
        if (!$purchaseOrder->expected_date) {
            return;
        }

        // In a real app, you would schedule a job/notification for the expected date
        Log::info('Overdue check scheduled', [
            'order_id' => $purchaseOrder->id,
            'expected_date' => $purchaseOrder->expected_date,
        ]);
    }

    /**
     * Check if received items help with low stock situations.
     */
    private function checkLowStockReplenishment(array $receivedDetails): void
    {
        foreach ($receivedDetails as $detail) {
            // In a real app, you would check if this product was previously low stock
            // and notify if it's now above the threshold
            Log::debug('Checking low stock replenishment', [
                'product_id' => $detail['product_id'],
                'received_quantity' => $detail['received_quantity'],
            ]);
        }
    }

    /**
     * Get outstanding items for a purchase order.
     */
    private function getOutstandingItems(PurchaseOrder $purchaseOrder): array
    {
        $outstanding = [];
        
        foreach ($purchaseOrder->details as $detail) {
            // In a real app, you would calculate received vs ordered quantities
            // For now, we'll return a simplified structure
            $outstanding[] = [
                'product_id' => $detail->product_id,
                'product_name' => $detail->product->name,
                'ordered_quantity' => $detail->quantity,
                'outstanding_quantity' => $detail->quantity, // Simplified
            ];
        }
        
        return $outstanding;
    }

    /**
     * Get purchasing team email addresses.
     */
    private function getPurchasingTeamEmails(): array
    {
        $emails = [];
        
        if ($email = config('inventory.notifications.recipients.purchasing')) {
            $emails[] = $email;
        }
        
        if ($email = config('inventory.notifications.recipients.admin')) {
            $emails[] = $email;
        }
        
        return array_filter($emails);
    }

    /**
     * Get stock manager email addresses.
     */
    private function getStockManagerEmails(): array
    {
        $emails = [];
        
        if ($email = config('inventory.notifications.recipients.stock_manager')) {
            $emails[] = $email;
        }
        
        if ($email = config('inventory.notifications.recipients.admin')) {
            $emails[] = $email;
        }
        
        return array_filter($emails);
    }
} 