import sqlite3
import sys
import os
import json
from datetime import datetime

class LocalDatabase:
    def __init__(self, db_path="desktop_pos/local_pos.db"):
        self.db_path = db_path
        os.makedirs(os.path.dirname(self.db_path), exist_ok=True)
        self.init_db()

    def get_connection(self):
        conn = sqlite3.connect(self.db_path)
        conn.row_factory = sqlite3.Row
        return conn

    def init_db(self):
        with self.get_connection() as conn:
            cursor = conn.cursor()
            
            # Key-value store for app configuration and tokens
            cursor.execute('''
                CREATE TABLE IF NOT EXISTS settings (
                    key TEXT PRIMARY KEY,
                    value TEXT NOT NULL
                )
            ''')

            # Cached pricing tiers from Laravel
            cursor.execute('''
                CREATE TABLE IF NOT EXISTS cached_pricing_tiers (
                    id INTEGER PRIMARY KEY,
                    name TEXT NOT NULL,
                    discount_per_kg REAL NOT NULL,
                    effective_price_per_kg REAL NOT NULL,
                    is_default INTEGER DEFAULT 0,
                    description TEXT
                )
            ''')

            # Offline transaction queue
            cursor.execute('''
                CREATE TABLE IF NOT EXISTS pending_transactions (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    transaction_number TEXT UNIQUE,
                    cashier_id INTEGER NOT NULL,
                    customer_discount_id INTEGER,
                    pricing_tier_name TEXT,
                    quantity_kg REAL NOT NULL,
                    price_per_kg REAL NOT NULL,
                    total_amount REAL NOT NULL,
                    customer_name TEXT,
                    customer_phone TEXT,
                    payment_type TEXT NOT NULL,
                    notes TEXT,
                    status TEXT DEFAULT 'completed',
                    cancellation_reason TEXT,
                    cancelled_at TEXT,
                    created_at TEXT NOT NULL,
                    sync_status TEXT DEFAULT 'pending',
                    sync_error TEXT
                )
            ''')

            # Ensure columns exist on legacy local DBs
            for col, col_def in [
                ("status", "TEXT DEFAULT 'completed'"),
                ("cancellation_reason", "TEXT"),
                ("cancelled_at", "TEXT")
            ]:
                try:
                    cursor.execute(f"ALTER TABLE pending_transactions ADD COLUMN {col} {col_def}")
                except Exception:
                    pass

            conn.commit()

    # Settings operations
    def set_setting(self, key, value):
        with self.get_connection() as conn:
            conn.cursor().execute(
                "INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)", 
                (key, str(value))
            )
            conn.commit()

    def get_setting(self, key, default=None):
        with self.get_connection() as conn:
            row = conn.cursor().execute(
                "SELECT value FROM settings WHERE key = ?", (key,)
            ).fetchone()
            return row['value'] if row else default

    # Pricing Tiers operations
    def cache_pricing_tiers(self, tiers_data):
        with self.get_connection() as conn:
            cursor = conn.cursor()
            cursor.execute("DELETE FROM cached_pricing_tiers")
            for tier in tiers_data:
                cursor.execute('''
                    INSERT INTO cached_pricing_tiers 
                    (id, name, discount_per_kg, effective_price_per_kg, is_default, description)
                    VALUES (?, ?, ?, ?, ?, ?)
                ''', (
                    tier['id'], 
                    tier['name'], 
                    tier['discount_per_kg'], 
                    tier['effective_price_per_kg'], 
                    1 if tier.get('is_default') else 0, 
                    tier.get('description', '')
                ))
            conn.commit()

    def get_cached_pricing_tiers(self):
        with self.get_connection() as conn:
            rows = conn.cursor().execute(
                "SELECT * FROM cached_pricing_tiers ORDER BY is_default DESC, name ASC"
            ).fetchall()
            return [dict(row) for row in rows]

    # Offline Transaction Queue
    def save_transaction(self, txn_data):
        with self.get_connection() as conn:
            cursor = conn.cursor()
            now_str = txn_data.get('created_at', datetime.now().strftime('%Y-%m-%d %H:%M:%S'))
            cursor.execute('''
                INSERT INTO pending_transactions (
                    transaction_number, cashier_id, customer_discount_id, pricing_tier_name,
                    quantity_kg, price_per_kg, total_amount, customer_name, customer_phone,
                    payment_type, notes, created_at, sync_status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
            ''', (
                txn_data.get('transaction_number'),
                txn_data['cashier_id'],
                txn_data.get('customer_discount_id'),
                txn_data.get('pricing_tier_name', 'Default'),
                txn_data['quantity_kg'],
                txn_data['price_per_kg'],
                txn_data['total_amount'],
                txn_data.get('customer_name', 'Walk-in Customer'),
                txn_data.get('customer_phone', ''),
                txn_data['payment_type'],
                txn_data.get('notes', ''),
                now_str
            ))
            conn.commit()
            return cursor.lastrowid

    def get_pending_transactions(self):
        with self.get_connection() as conn:
            rows = conn.cursor().execute(
                "SELECT * FROM pending_transactions WHERE sync_status = 'pending' ORDER BY id ASC"
            ).fetchall()
            return [dict(row) for row in rows]

    def mark_transaction_synced(self, local_id):
        with self.get_connection() as conn:
            conn.cursor().execute(
                "UPDATE pending_transactions SET sync_status = 'synced' WHERE id = ?", (local_id,)
            )
            conn.commit()

    def mark_transaction_failed(self, local_id, error_msg):
        with self.get_connection() as conn:
            conn.cursor().execute(
                "UPDATE pending_transactions SET sync_error = ? WHERE id = ?", (error_msg, local_id)
            )
            conn.commit()

    def cancel_transaction(self, txn_number, reason):
        with self.get_connection() as conn:
            now_str = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
            conn.cursor().execute('''
                UPDATE pending_transactions 
                SET status = 'cancelled', 
                    cancellation_reason = ?, 
                    cancelled_at = ?, 
                    sync_status = 'pending' 
                WHERE transaction_number = ?
            ''', (reason, now_str, txn_number))
            conn.commit()

    def get_all_transactions(self, limit=50):
        with self.get_connection() as conn:
            rows = conn.cursor().execute(
                "SELECT * FROM pending_transactions ORDER BY id DESC LIMIT ?", (limit,)
            ).fetchall()
            return [dict(row) for row in rows]
