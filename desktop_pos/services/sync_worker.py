import time
import threading

class SyncWorker:
    def __init__(self, db_service, api_client, interval_seconds=10, on_sync_complete_callback=None):
        self.db = db_service
        self.api = api_client
        self.interval = interval_seconds
        self.on_sync_complete = on_sync_complete_callback
        self.running = False
        self.thread = None

    def start(self):
        if not self.running:
            self.running = True
            self.thread = threading.Thread(target=self._run, daemon=True)
            self.thread.start()

    def stop(self):
        self.running = False

    def trigger_sync_now(self):
        return self._do_sync()

    def _run(self):
        while self.running:
            self._do_sync()
            time.sleep(self.interval)

    def _do_sync(self):
        token = self.db.get_setting("auth_token")
        pending = self.db.get_pending_transactions()
        if not pending:
            return {"synced_count": 0, "status": "idle"}

        payload = []
        for txn in pending:
            payload.append({
                "transaction_number": txn['transaction_number'],
                "cashier_id": txn['cashier_id'],
                "customer_discount_id": txn['customer_discount_id'],
                "quantity_kg": txn['quantity_kg'],
                "price_per_kg": txn['price_per_kg'],
                "total_amount": txn['total_amount'],
                "customer_name": txn['customer_name'],
                "customer_phone": txn['customer_phone'],
                "payment_type": txn['payment_type'],
                "notes": txn['notes'],
                "status": txn.get('status', 'completed'),
                "cancellation_reason": txn.get('cancellation_reason'),
                "cancelled_at": txn.get('cancelled_at'),
                "created_at": txn['created_at'],
            })

        res = self.api.sync_transactions(payload, token=token)
        if res.get('success'):
            processed = res.get('data', {}).get('processed', [])
            for item in processed:
                txn_num = item.get('transaction_number')
                # Find matching local id
                for p in pending:
                    if p['transaction_number'] == txn_num:
                        self.db.mark_transaction_synced(p['id'])
            
            if self.on_sync_complete:
                self.on_sync_complete(len(processed), "success")
                
            return {"synced_count": len(processed), "status": "success"}
        else:
            err = res.get('message', 'Sync failed')
            for p in pending:
                self.db.mark_transaction_failed(p['id'], err)
                
            if self.on_sync_complete:
                self.on_sync_complete(0, "failed", err)
                
            return {"synced_count": 0, "status": "failed", "error": err}
