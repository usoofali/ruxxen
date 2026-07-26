import sys
import os
import uuid
from datetime import datetime

try:
    from PySide6.QtWidgets import (
        QMainWindow, QWidget, QVBoxLayout, QHBoxLayout, QLabel, QLineEdit, 
        QPushButton, QComboBox, QTableWidget, QTableWidgetItem, QHeaderView,
        QMessageBox, QFrame, QSplitter, QGroupBox, QTextEdit, QCheckBox, QDialog, QScrollArea
    )
    from PySide6.QtCore import Qt, QTimer
    from PySide6.QtGui import QFont, QColor, QIcon, QPixmap
    PYSIDE_AVAILABLE = True
except ImportError:
    PYSIDE_AVAILABLE = False
    QMainWindow = object
    import tkinter as tk
    from tkinter import ttk, messagebox


class PySidePosWindow(QMainWindow):
    def __init__(self, api_client, db_service, printer_service, sync_worker, on_logout_callback=None):
        super().__init__()
        self.api = api_client
        self.db = db_service
        self.printer = printer_service
        self.sync_worker = sync_worker
        self.on_logout_callback = on_logout_callback

        self.cashier_name = self.db.get_setting("cashier_name", "Cashier")
        self.cashier_id = int(self.db.get_setting("cashier_id", 1))

        self.base_price = 0.00
        self.current_stock = 0.00
        self.pricing_tiers = []
        self.selected_tier = None
        self.company_info = {}

        asset_dir = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", "assets"))
        logo_png = os.path.join(asset_dir, "logo.png")
        if os.path.exists(logo_png):
            try:
                self.setWindowIcon(QIcon(logo_png))
            except Exception:
                pass

        self.init_ui()
        self.load_initial_data()

        # Start background sync
        self.sync_worker.start()

        # Periodic stock & status refresh timer (every 15s)
        self.timer = QTimer(self)
        self.timer.timeout.connect(self.refresh_stock)
        self.timer.start(15000)

    def init_ui(self):
        self.setWindowTitle(f"Ruxxen Gas POS Terminal - Logged in as: {self.cashier_name}")
        self.setMinimumSize(960, 640)
        self.resize(1180, 750)
        self.setStyleSheet("""
            QMainWindow, QWidget {
                background-color: #0f172a;
                color: #f8fafc;
                font-family: 'Segoe UI', Inter, Arial, sans-serif;
            }
            QGroupBox {
                font-weight: bold;
                font-size: 14px;
                color: #38bdf8;
                border: 1px solid #334155;
                border-radius: 10px;
                margin-top: 10px;
                padding-top: 15px;
            }
            QGroupBox::title {
                subcontrol-origin: margin;
                left: 12px;
                padding: 0 6px;
            }
            QLineEdit, QComboBox {
                background-color: #1e293b;
                border: 1.5px solid #475569;
                border-radius: 8px;
                padding: 9px;
                font-size: 14px;
                color: #ffffff;
            }
            QLineEdit:focus, QComboBox:focus {
                border: 1.5px solid #38bdf8;
                background-color: #131d31;
            }
            QPushButton#preset_btn {
                background-color: #1e293b;
                color: #38bdf8;
                border: 1.5px solid #38bdf8;
                border-radius: 16px;
                padding: 10px;
                font-size: 14px;
                font-weight: bold;
            }
            QPushButton#preset_btn:hover {
                background-color: #0284c7;
                color: #ffffff;
                border: 1.5px solid #38bdf8;
            }
            QPushButton#checkout_btn {
                background-color: #16a34a;
                color: white;
                font-size: 16px;
                font-weight: bold;
                border-radius: 8px;
                padding: 14px;
            }
            QPushButton#checkout_btn:hover {
                background-color: #15803d;
            }
            QPushButton#summary_btn {
                background-color: #8b5cf6;
                color: white;
                font-size: 12px;
                font-weight: bold;
                border-radius: 6px;
                padding: 7px 14px;
            }
            QPushButton#summary_btn:hover {
                background-color: #7c3aed;
            }
            QPushButton#sync_btn {
                background-color: #0284c7;
                color: white;
                font-size: 12px;
                font-weight: bold;
                border-radius: 6px;
                padding: 7px 14px;
            }
            QPushButton#sync_btn:hover {
                background-color: #0369a1;
            }
            QPushButton#logout_btn {
                background-color: #ef4444;
                color: white;
                font-size: 12px;
                font-weight: bold;
                border-radius: 6px;
                padding: 7px 14px;
            }
            QPushButton#logout_btn:hover {
                background-color: #dc2626;
            }
            QFrame#copy_box {
                background-color: #1e293b;
                border: 1px solid #334155;
                border-radius: 8px;
            }
            QCheckBox#single_copy_chk {
                font-size: 13px;
                font-weight: 600;
                color: #cbd5e1;
            }
            QCheckBox#single_copy_chk::indicator {
                width: 18px;
                height: 18px;
                border: 1.5px solid #475569;
                border-radius: 4px;
                background-color: #0f172a;
            }
            QCheckBox#single_copy_chk::indicator:hover {
                border: 1.5px solid #38bdf8;
            }
            QCheckBox#single_copy_chk::indicator:checked {
                background-color: #0284c7;
                border: 1.5px solid #38bdf8;
            }
            QTableWidget {
                background-color: #1e293b;
                gridline-color: #334155;
                border: 1px solid #334155;
                border-radius: 6px;
            }
            QHeaderView::section {
                background-color: #0f172a;
                color: #38bdf8;
                padding: 8px;
                font-weight: bold;
                border: none;
            }
        """)

        central_widget = QWidget()
        main_layout = QVBoxLayout(central_widget)

        # Header Bar
        header_frame = QFrame()
        header_frame.setStyleSheet("background-color: #1e293b; border-radius: 10px; padding: 10px;")
        header_layout = QHBoxLayout(header_frame)

        asset_dir = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", "assets"))
        logo_png = os.path.join(asset_dir, "logo.png")
        if os.path.exists(logo_png):
            try:
                logo_hdr = QLabel()
                pix = QPixmap(logo_png).scaled(45, 45, Qt.KeepAspectRatio, Qt.SmoothTransformation)
                logo_hdr.setPixmap(pix)
                header_layout.addWidget(logo_hdr)
            except Exception:
                pass

        self.lbl_title = QLabel("RUXXEN GAS POS TERMINAL")
        self.lbl_title.setStyleSheet("font-size: 19px; font-weight: 800; color: #38bdf8; letter-spacing: 0.5px;")

        self.lbl_cashier = QLabel(f"Cashier: {self.cashier_name}")
        self.lbl_cashier.setStyleSheet("font-size: 14px; color: #cbd5e1; font-weight: 600;")

        self.lbl_stock = QLabel("Stock: --- kg")
        self.lbl_stock.setStyleSheet("font-size: 14px; font-weight: bold; color: #f59e0b;")

        btn_summary = QPushButton("MY DAILY SALES")
        btn_summary.setObjectName("summary_btn")
        btn_summary.clicked.connect(self.show_daily_summary_dialog)

        btn_sync = QPushButton("SYNC NOW")
        btn_sync.setObjectName("sync_btn")
        btn_sync.clicked.connect(self.trigger_manual_sync)

        btn_logout = QPushButton("LOGOUT")
        btn_logout.setObjectName("logout_btn")
        btn_logout.clicked.connect(self.handle_logout)

        header_layout.addWidget(self.lbl_title)
        header_layout.addStretch()
        header_layout.addWidget(self.lbl_cashier)
        header_layout.addSpacing(15)
        header_layout.addWidget(self.lbl_stock)
        header_layout.addSpacing(15)
        header_layout.addWidget(btn_summary)
        header_layout.addSpacing(8)
        header_layout.addWidget(btn_sync)
        header_layout.addSpacing(8)
        header_layout.addWidget(btn_logout)

        main_layout.addWidget(header_frame)

        # Body Layout: Left (Sales Entry), Right (Recent Sales & Checkbox)
        body_layout = QHBoxLayout()

        # Left Panel (Sales Entry)
        left_panel = QWidget()
        left_layout = QVBoxLayout(left_panel)
        left_layout.setContentsMargins(0, 0, 0, 0)

        grp_sale = QGroupBox("New Gas Sale Entry")
        grp_layout = QVBoxLayout(grp_sale)

        # Preset weight buttons
        lbl_presets = QLabel("Quick Weight Selector (kg):")
        lbl_presets.setStyleSheet("font-weight: 600; color: #cbd5e1;")
        grp_layout.addWidget(lbl_presets)

        preset_layout = QHBoxLayout()
        weights = [2.0, 3.0, 6.0, 12.5, 25.0, 50.0]
        for w in weights:
            btn = QPushButton(f"{w} kg")
            btn.setObjectName("preset_btn")
            btn.clicked.connect(lambda _, weight=w: self.set_weight(weight))
            preset_layout.addWidget(btn)
        grp_layout.addLayout(preset_layout)

        # Weight & Pricing Tier Input
        inputs_layout = QHBoxLayout()

        v_qty = QVBoxLayout()
        lbl_w = QLabel("Gas Weight (kg):")
        lbl_w.setStyleSheet("font-weight: 600; color: #cbd5e1;")
        v_qty.addWidget(lbl_w)
        self.txt_weight = QLineEdit()
        self.txt_weight.setPlaceholderText("e.g. 12.5")
        self.txt_weight.textChanged.connect(self.calculate_total)
        v_qty.addWidget(self.txt_weight)

        v_tier = QVBoxLayout()
        lbl_t = QLabel("Pricing Tier:")
        lbl_t.setStyleSheet("font-weight: 600; color: #cbd5e1;")
        v_tier.addWidget(lbl_t)
        self.combo_tier = QComboBox()
        self.combo_tier.currentIndexChanged.connect(self.on_tier_changed)
        v_tier.addWidget(self.combo_tier)

        inputs_layout.addLayout(v_qty)
        inputs_layout.addLayout(v_tier)
        grp_layout.addLayout(inputs_layout)

        # Price Display Box
        price_box = QFrame()
        price_box.setStyleSheet("background-color: #0f172a; border-radius: 8px; padding: 15px; border: 1.5px solid #38bdf8;")
        pbox_layout = QVBoxLayout(price_box)

        self.lbl_rate_info = QLabel("Base Price: NGN 0.00/kg  |  Discount: NGN 0.00/kg")
        self.lbl_rate_info.setStyleSheet("font-size: 13px; color: #94a3b8;")

        self.lbl_effective_rate = QLabel("Effective Unit Price: NGN 0.00 / kg")
        self.lbl_effective_rate.setStyleSheet("font-size: 14px; font-weight: bold; color: #38bdf8;")

        self.lbl_total_price = QLabel("TOTAL: NGN 0.00")
        self.lbl_total_price.setStyleSheet("font-size: 26px; font-weight: bold; color: #4ade80;")

        pbox_layout.addWidget(self.lbl_rate_info)
        pbox_layout.addWidget(self.lbl_effective_rate)
        pbox_layout.addWidget(self.lbl_total_price)

        grp_layout.addWidget(price_box)

        # Customer & Payment Details
        cust_layout = QHBoxLayout()

        v_cust_name = QVBoxLayout()
        lbl_cn = QLabel("Customer Name:")
        lbl_cn.setStyleSheet("font-weight: 600; color: #cbd5e1;")
        v_cust_name.addWidget(lbl_cn)
        self.txt_cust_name = QLineEdit()
        self.txt_cust_name.setPlaceholderText("Walk-in Customer")
        v_cust_name.addWidget(self.txt_cust_name)

        v_cust_phone = QVBoxLayout()
        lbl_cp = QLabel("Customer Phone:")
        lbl_cp.setStyleSheet("font-weight: 600; color: #cbd5e1;")
        v_cust_phone.addWidget(lbl_cp)
        self.txt_cust_phone = QLineEdit()
        self.txt_cust_phone.setPlaceholderText("Optional phone number")
        v_cust_phone.addWidget(self.txt_cust_phone)

        v_payment = QVBoxLayout()
        lbl_pm = QLabel("Payment Method:")
        lbl_pm.setStyleSheet("font-weight: 600; color: #cbd5e1;")
        v_payment.addWidget(lbl_pm)
        self.combo_payment = QComboBox()
        self.combo_payment.addItems(["Cash", "Card", "Transfer"])
        v_payment.addWidget(self.combo_payment)

        cust_layout.addLayout(v_cust_name)
        cust_layout.addLayout(v_cust_phone)
        cust_layout.addLayout(v_payment)

        grp_layout.addLayout(cust_layout)

        # Checkout Button
        btn_checkout = QPushButton("PRINT RECEIPT & COMPLETE SALE")
        btn_checkout.setObjectName("checkout_btn")
        btn_checkout.clicked.connect(self.process_sale)
        grp_layout.addWidget(btn_checkout)

        left_layout.addWidget(grp_sale)

        # Right Panel (Recent Sales Table & Copy Settings)
        right_panel = QWidget()
        right_layout = QVBoxLayout(right_panel)
        right_layout.setContentsMargins(0, 0, 0, 0)
        right_layout.setSpacing(10)

        # Single Receipt Checkbox Container (MOVED ON TOP OF TERMINAL RECENT SALES)
        copy_box = QFrame()
        copy_box.setObjectName("copy_box")
        copy_layout = QHBoxLayout(copy_box)
        copy_layout.setContentsMargins(12, 10, 12, 10)

        self.chk_single_copy = QCheckBox("Print 1 Copy Only (Uncheck for 2 Copies)")
        self.chk_single_copy.setObjectName("single_copy_chk")
        self.chk_single_copy.setChecked(False)
        copy_layout.addWidget(self.chk_single_copy)

        right_layout.addWidget(copy_box)

        # Terminal Recent Sales Group Box
        grp_history = QGroupBox(f"Terminal Recent Sales ({self.cashier_name})")
        h_layout = QVBoxLayout(grp_history)

        self.table_sales = QTableWidget()
        self.table_sales.setColumnCount(5)
        self.table_sales.setHorizontalHeaderLabels(["TXN #", "Weight", "Tier", "Total (NGN)", "Status"])
        self.table_sales.horizontalHeader().setSectionResizeMode(QHeaderView.Stretch)
        self.table_sales.setVerticalScrollBarPolicy(Qt.ScrollBarAsNeeded)
        self.table_sales.setHorizontalScrollBarPolicy(Qt.ScrollBarAsNeeded)
        h_layout.addWidget(self.table_sales)

        right_layout.addWidget(grp_history)

        body_layout.addWidget(left_panel, 3)
        body_layout.addWidget(right_panel, 2)

        main_layout.addLayout(body_layout)

        # Wrap in scroll area to guarantee responsive scaling and window maximization without clipping
        scroll_area = QScrollArea()
        scroll_area.setWidgetResizable(True)
        scroll_area.setFrameShape(QFrame.NoFrame)
        scroll_area.setStyleSheet("QScrollArea { background-color: #0f172a; border: none; }")
        scroll_area.setWidget(central_widget)

        self.setCentralWidget(scroll_area)

    def set_weight(self, w):
        self.txt_weight.setText(str(w))

    def load_initial_data(self):
        token = self.db.get_setting("auth_token")
        res = self.api.get_initial_data(token)

        if res.get("success"):
            data = res.get("data", {})
            self.base_price = data.get("base_price_per_kg", 0.00)
            self.current_stock = data.get("current_stock_kg", 0.00)
            self.pricing_tiers = data.get("pricing_tiers", [])
            self.company_info = data.get("company", {})

            # Cache tiers locally for offline use
            self.db.cache_pricing_tiers(self.pricing_tiers)
        else:
            # Fallback to local SQLite cache
            self.pricing_tiers = self.db.get_cached_pricing_tiers()

        co_name = self.company_info.get("name", "Ruxxen Gas").upper()
        self.lbl_title.setText(f"{co_name} POS TERMINAL")
        self.setWindowTitle(f"{co_name} POS Terminal - Cashier: {self.cashier_name}")
        self.lbl_stock.setText(f"Stock: {self.current_stock:,.2f} kg")

        # Populate pricing tiers dropdown
        self.combo_tier.clear()
        for tier in self.pricing_tiers:
            self.combo_tier.addItem(f"{tier['name']} (NGN {tier['effective_price_per_kg']:,.2f}/kg)", tier)

        self.refresh_sales_table()

    def refresh_stock(self):
        token = self.db.get_setting("auth_token")
        res = self.api.check_stock(token)
        if res.get("success"):
            data = res.get("data", {})
            self.current_stock = data.get("current_stock_kg", 0.00)
            self.lbl_stock.setText(f"Stock: {self.current_stock:,.2f} kg")

    def on_tier_changed(self, idx):
        if idx >= 0 and idx < len(self.pricing_tiers):
            self.selected_tier = self.pricing_tiers[idx]
        else:
            self.selected_tier = None
        self.calculate_total()

    def calculate_total(self):
        weight_str = self.txt_weight.text().strip()
        try:
            qty = float(weight_str) if weight_str else 0.0
        except ValueError:
            qty = 0.0

        if self.selected_tier:
            eff_rate = self.selected_tier.get("effective_price_per_kg", self.base_price)
            discount = self.selected_tier.get("discount_per_kg", 0.0)
        else:
            eff_rate = self.base_price
            discount = 0.0

        total = qty * eff_rate

        self.lbl_rate_info.setText(f"Base Price: NGN {self.base_price:,.2f}/kg  |  Tier Discount: NGN {discount:,.2f}/kg")
        self.lbl_effective_rate.setText(f"Effective Unit Price: NGN {eff_rate:,.2f} / kg")
        self.lbl_total_price.setText(f"TOTAL: NGN {total:,.2f}")

    def process_sale(self):
        weight_str = self.txt_weight.text().strip()
        try:
            qty = float(weight_str)
            if qty <= 0:
                raise ValueError()
        except ValueError:
            QMessageBox.warning(self, "Invalid Weight", "Please enter a valid gas weight in kg.")
            return

        if not self.selected_tier:
            QMessageBox.warning(self, "Select Tier", "Please select a pricing tier.")
            return

        eff_rate = self.selected_tier.get("effective_price_per_kg", self.base_price)
        total = qty * eff_rate

        txn_number = "TXN-" + datetime.now().strftime("%Y%m%d") + "-" + uuid.uuid4().hex[:6].upper()
        cust_name = self.txt_cust_name.text().strip() or "Walk-in Customer"
        cust_phone = self.txt_cust_phone.text().strip()
        payment_type = self.combo_payment.currentText()

        txn_data = {
            "transaction_number": txn_number,
            "cashier_id": self.cashier_id,
            "cashier_name": self.cashier_name,
            "customer_discount_id": self.selected_tier.get("id"),
            "pricing_tier_name": self.selected_tier.get("name"),
            "quantity_kg": qty,
            "price_per_kg": eff_rate,
            "total_amount": total,
            "customer_name": cust_name,
            "customer_phone": cust_phone,
            "payment_type": payment_type,
            "notes": f"Sale via Desktop POS ({self.selected_tier.get('name')})",
            "created_at": datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        }

        # Determine number of receipt copies (Default = 2, Checkbox checked = 1)
        num_copies = 1 if self.chk_single_copy.isChecked() else 2

        # 1. Save locally
        self.db.save_transaction(txn_data)

        # 2. Print Thermal Receipt
        print_res = self.printer.print_receipt(txn_data, self.company_info, num_copies=num_copies)

        # 3. Trigger immediate sync thread
        self.sync_worker.trigger_sync_now()

        # 4. Clear fields & refresh table
        self.txt_weight.clear()
        self.txt_cust_name.clear()
        self.txt_cust_phone.clear()
        self.refresh_sales_table()

        QMessageBox.information(self, "Sale Completed", f"Transaction {txn_number} recorded successfully.\nTotal: NGN {total:,.2f}\nPrinted {num_copies} copy/copies.")

    def cancel_selected_recent_sale(self):
        selected_row = self.table_sales.currentRow()
        txns = self.db.get_all_transactions(limit=50)
        cashier_txns = [t for t in txns if int(t.get('cashier_id', 0)) == self.cashier_id or t.get('cashier_name') == self.cashier_name]
        
        if selected_row < 0 or selected_row >= len(cashier_txns):
            QMessageBox.warning(self, "Select Sale", "Please click a transaction row in the table to cancel.")
            return

        txn = cashier_txns[selected_row]
        if txn.get('status') == 'cancelled':
            QMessageBox.warning(self, "Already Cancelled", f"Transaction {txn.get('transaction_number')} is already cancelled.")
            return

        from PySide6.QtWidgets import QInputDialog
        reason, ok = QInputDialog.getText(
            self, 
            "Cancel Transaction", 
            f"Enter reason for cancelling transaction {txn.get('transaction_number')}:"
        )
        
        if ok and reason.strip():
            self.db.cancel_transaction(txn.get('transaction_number'), reason.strip())
            self.sync_worker.trigger_sync_now()
            self.refresh_sales_table()
            self.refresh_stock()
            QMessageBox.information(self, "Transaction Cancelled", f"Transaction {txn.get('transaction_number')} cancelled successfully.")

    def trigger_manual_sync(self):
        res = self.sync_worker.trigger_sync_now()
        count = res.get("synced_count", 0)
        status = res.get("status")
        if status == "success":
            QMessageBox.information(self, "Sync Complete", f"Successfully synced {count} transaction(s) to Laravel Master!")
        else:
            QMessageBox.warning(self, "Sync Status", res.get("error", "No pending transactions to sync."))
        self.refresh_sales_table()

    def show_daily_summary_dialog(self):
        txns = self.db.get_all_transactions(limit=1000)
        today_str = datetime.now().strftime("%Y-%m-%d")
        
        today_txns = [t for t in txns if str(t.get('created_at', '')).startswith(today_str) and (int(t.get('cashier_id', 0)) == self.cashier_id or t.get('cashier_name') == self.cashier_name)]
        
        completed_today = [t for t in today_txns if t.get('status') != 'cancelled']
        cancelled_today = [t for t in today_txns if t.get('status') == 'cancelled']

        total_count = len(completed_today)
        total_qty = sum(float(t.get('quantity_kg', 0)) for t in completed_today)
        total_amount = sum(float(t.get('total_amount', 0)) for t in completed_today)

        cancelled_count = len(cancelled_today)
        cancelled_amount = sum(float(t.get('total_amount', 0)) for t in cancelled_today)

        cash_total = sum(float(t.get('total_amount', 0)) for t in completed_today if str(t.get('payment_type', '')).lower() == 'cash')
        card_total = sum(float(t.get('total_amount', 0)) for t in completed_today if str(t.get('payment_type', '')).lower() in ['card', 'pos'])
        transfer_total = sum(float(t.get('total_amount', 0)) for t in completed_today if str(t.get('payment_type', '')).lower() == 'transfer')

        summary_data = {
            'cashier_id': self.cashier_id,
            'cashier_name': self.cashier_name,
            'date': today_str,
            'total_sales_count': total_count,
            'total_quantity_kg': total_qty,
            'total_amount': total_amount,
            'cancelled_sales_count': cancelled_count,
            'cancelled_total_amount': cancelled_amount,
            'payment_breakdown': {
                'cash': cash_total,
                'card': card_total,
                'transfer': transfer_total
            }
        }

        dlg = QDialog(self)
        dlg.setWindowTitle(f"Ruxxen Gas - Cashier Daily Sales & Shift Report ({self.cashier_name})")
        dlg.resize(1120, 680)
        dlg.setStyleSheet("""
            QDialog { background-color: #0f172a; color: #f8fafc; font-family: 'Segoe UI', Arial, sans-serif; }
            QLabel { color: #f8fafc; }
            QFrame#card { background-color: #1e293b; border-radius: 8px; border: 1px solid #334155; padding: 12px; }
            QTableWidget { background-color: #1e293b; gridline-color: #334155; border: 1px solid #334155; border-radius: 6px; }
            QHeaderView::section { background-color: #0f172a; color: #38bdf8; padding: 8px; font-weight: bold; border: none; }
            QPushButton#prn_btn { background-color: #16a34a; color: white; font-weight: bold; padding: 10px 18px; border-radius: 6px; font-size: 13px; }
            QPushButton#prn_btn:hover { background-color: #15803d; }
            QPushButton#reprint_btn { background-color: #0284c7; color: white; font-weight: bold; padding: 10px 18px; border-radius: 6px; font-size: 13px; }
            QPushButton#reprint_btn:hover { background-color: #0369a1; }
            QPushButton#cancel_btn { background-color: #dc2626; color: white; font-weight: bold; padding: 10px 18px; border-radius: 6px; font-size: 13px; }
            QPushButton#cancel_btn:hover { background-color: #b91c1c; }
            QPushButton#cls_btn { background-color: #334155; color: white; font-weight: bold; padding: 10px 18px; border-radius: 6px; font-size: 13px; }
        """)

        main_dlg_layout = QVBoxLayout(dlg)
        main_dlg_layout.setContentsMargins(20, 20, 20, 20)
        main_dlg_layout.setSpacing(15)

        # Title & Header Bar
        hdr_layout = QHBoxLayout()
        lbl_hdr = QLabel("CASHIER DAILY SALES REPORT & SHIFT SUMMARY")
        lbl_hdr.setStyleSheet("font-size: 20px; font-weight: bold; color: #38bdf8;")
        lbl_info = QLabel(f"Cashier: <b>{self.cashier_name}</b> | Shift Date: <b>{today_str}</b>")
        lbl_info.setStyleSheet("font-size: 14px; color: #cbd5e1;")
        hdr_layout.addWidget(lbl_hdr)
        hdr_layout.addStretch()
        hdr_layout.addWidget(lbl_info)
        main_dlg_layout.addLayout(hdr_layout)

        # KPI Metric Summary Cards
        cards_layout = QHBoxLayout()

        # Card 1: Total Sales Revenue
        c1 = QFrame()
        c1.setObjectName("card")
        v1 = QVBoxLayout(c1)
        v1.addWidget(QLabel("TOTAL SHIFT REVENUE"))
        l1 = QLabel(f"NGN {total_amount:,.2f}")
        l1.setStyleSheet("font-size: 22px; font-weight: bold; color: #4ade80;")
        v1.addWidget(l1)
        cards_layout.addWidget(c1)

        # Card 2: Total Gas Sold
        c2 = QFrame()
        c2.setObjectName("card")
        v2 = QVBoxLayout(c2)
        v2.addWidget(QLabel("TOTAL GAS VOLUME SOLD"))
        l2 = QLabel(f"{total_qty:,.2f} kg")
        l2.setStyleSheet("font-size: 22px; font-weight: bold; color: #38bdf8;")
        v2.addWidget(l2)
        cards_layout.addWidget(c2)

        # Card 3: Total Transactions Count
        c3 = QFrame()
        c3.setObjectName("card")
        v3 = QVBoxLayout(c3)
        v3.addWidget(QLabel("COMPLETED SALES"))
        l3 = QLabel(f"{total_count} Sales")
        l3.setStyleSheet("font-size: 22px; font-weight: bold; color: #f59e0b;")
        v3.addWidget(l3)
        cards_layout.addWidget(c3)

        # Card 4: Cancelled Sales Summary
        c4 = QFrame()
        c4.setObjectName("card")
        v4 = QVBoxLayout(c4)
        v4.addWidget(QLabel("CANCELLED SALES"))
        l4 = QLabel(f"NGN {cancelled_amount:,.2f}")
        l4.setStyleSheet("font-size: 20px; font-weight: bold; color: #ef4444;")
        v4.addWidget(l4)
        v4.addWidget(QLabel(f"Count: <b>{cancelled_count} Cancelled</b>"))
        cards_layout.addWidget(c4)

        # Card 5: Payment Breakdown
        c5 = QFrame()
        c5.setObjectName("card")
        v5 = QVBoxLayout(c5)
        v5.setSpacing(2)
        v5.addWidget(QLabel("<b>PAYMENT BREAKDOWN</b>"))
        v5.addWidget(QLabel(f"Cash: <b>NGN {cash_total:,.2f}</b>"))
        v5.addWidget(QLabel(f"Card/POS: <b>NGN {card_total:,.2f}</b>"))
        v5.addWidget(QLabel(f"Transfer: <b>NGN {transfer_total:,.2f}</b>"))
        cards_layout.addWidget(c5)

        main_dlg_layout.addLayout(cards_layout)

        # Section Title for Detailed Transactions Table
        lbl_table_hdr = QLabel("TODAY'S DETAILED INDIVIDUAL TRANSACTIONS")
        lbl_table_hdr.setStyleSheet("font-size: 15px; font-weight: bold; color: #38bdf8; margin-top: 5px;")
        main_dlg_layout.addWidget(lbl_table_hdr)

        # Scrollable Table of Individual Transactions
        table_txns = QTableWidget()
        table_txns.setColumnCount(10)
        table_txns.setHorizontalHeaderLabels([
            "TXN #", "Time", "Customer", "Phone", "Tier", "Qty (kg)", "Rate/kg", "Total (NGN)", "Payment", "Status"
        ])
        table_txns.horizontalHeader().setSectionResizeMode(QHeaderView.Stretch)
        table_txns.setRowCount(len(today_txns))

        for row, t in enumerate(today_txns):
            created_time = str(t.get('created_at', '')).split()[-1] if ' ' in str(t.get('created_at', '')) else str(t.get('created_at', ''))
            table_txns.setItem(row, 0, QTableWidgetItem(str(t.get('transaction_number', ''))))
            table_txns.setItem(row, 1, QTableWidgetItem(created_time))
            table_txns.setItem(row, 2, QTableWidgetItem(str(t.get('customer_name', 'Walk-in Customer'))))
            table_txns.setItem(row, 3, QTableWidgetItem(str(t.get('customer_phone', '-'))))
            table_txns.setItem(row, 4, QTableWidgetItem(str(t.get('pricing_tier_name', 'Standard'))))
            table_txns.setItem(row, 5, QTableWidgetItem(f"{float(t.get('quantity_kg', 0)):.2f} kg"))
            table_txns.setItem(row, 6, QTableWidgetItem(f"NGN {float(t.get('price_per_kg', 0)):,.2f}"))
            table_txns.setItem(row, 7, QTableWidgetItem(f"NGN {float(t.get('total_amount', 0)):,.2f}"))
            table_txns.setItem(row, 8, QTableWidgetItem(str(t.get('payment_type', 'CASH')).upper()))
            
            if t.get('status') == 'cancelled':
                status_item = QTableWidgetItem("CANCELLED")
                status_item.setForeground(QColor("#ef4444"))
                status_item.setFont(QFont("Segoe UI", 9, QFont.Bold))
                # Set all cells in this row to red
                for col_idx in range(9):
                    cell = table_txns.item(row, col_idx)
                    if cell:
                        cell.setForeground(QColor("#ef4444"))
            else:
                status_item = QTableWidgetItem("COMPLETED")
                status_item.setForeground(QColor("#4ade80"))
                
            table_txns.setItem(row, 9, status_item)

        main_dlg_layout.addWidget(table_txns, 1)

        # Action Buttons Bar
        btn_bar = QHBoxLayout()

        btn_print_z = QPushButton("PRINT SHIFT SUMMARY (Z-REPORT)")
        btn_print_z.setObjectName("prn_btn")
        btn_print_z.clicked.connect(lambda: (self.printer.print_shift_summary(summary_data, self.company_info), QMessageBox.information(dlg, "Z-Report Printed", "Daily Shift Summary report printed successfully.")))
        btn_bar.addWidget(btn_print_z)

        def reprint_selected():
            selected_row = table_txns.currentRow()
            if selected_row >= 0 and selected_row < len(today_txns):
                selected_txn = today_txns[selected_row]
                self.printer.print_receipt(selected_txn, self.company_info, num_copies=1)
                QMessageBox.information(dlg, "Receipt Reprinted", f"Re-printed receipt for {selected_txn.get('transaction_number')}.")
            else:
                QMessageBox.warning(dlg, "Select Transaction", "Please click a row in the table to reprint its thermal receipt.")

        btn_reprint = QPushButton("REPRINT SELECTED RECEIPT")
        btn_reprint.setObjectName("reprint_btn")
        btn_reprint.clicked.connect(reprint_selected)
        btn_bar.addWidget(btn_reprint)

        def cancel_dialog_selected():
            selected_row = table_txns.currentRow()
            if selected_row >= 0 and selected_row < len(today_txns):
                selected_txn = today_txns[selected_row]
                if selected_txn.get('status') == 'cancelled':
                    QMessageBox.warning(dlg, "Already Cancelled", f"Transaction {selected_txn.get('transaction_number')} is already cancelled.")
                    return

                from PySide6.QtWidgets import QInputDialog
                reason, ok = QInputDialog.getText(dlg, "Cancel Transaction", f"Enter reason for cancelling transaction {selected_txn.get('transaction_number')}:")
                if ok and reason.strip():
                    self.db.cancel_transaction(selected_txn.get('transaction_number'), reason.strip())
                    self.sync_worker.trigger_sync_now()
                    self.refresh_sales_table()
                    self.refresh_stock()
                    QMessageBox.information(dlg, "Transaction Cancelled", f"Transaction {selected_txn.get('transaction_number')} cancelled successfully.")
                    dlg.accept()
                    self.show_daily_summary_dialog()
            else:
                QMessageBox.warning(dlg, "Select Transaction", "Please click a row in the table to cancel.")

        btn_cancel_dlg = QPushButton("CANCEL SELECTED SALE")
        btn_cancel_dlg.setObjectName("cancel_btn")
        btn_cancel_dlg.clicked.connect(cancel_dialog_selected)
        btn_bar.addWidget(btn_cancel_dlg)

        btn_bar.addStretch()

        btn_close = QPushButton("CLOSE REPORT")
        btn_close.setObjectName("cls_btn")
        btn_close.clicked.connect(dlg.accept)
        btn_bar.addWidget(btn_close)

        main_dlg_layout.addLayout(btn_bar)

        dlg.exec()

    def handle_logout(self):
        reply = QMessageBox.question(self, "Confirm Logout", "Are you sure you want to log out of the POS terminal?", QMessageBox.Yes | QMessageBox.No)
        if reply == QMessageBox.Yes:
            self.db.set_setting("auth_token", "")
            self.db.set_setting("cashier_name", "")
            self.db.set_setting("cashier_id", "")
            self.sync_worker.stop()
            self.close()
            if self.on_logout_callback:
                self.on_logout_callback()

    def refresh_sales_table(self):
        txns = self.db.get_all_transactions(limit=50)
        # Filter table to cashier's own transactions
        cashier_txns = [t for t in txns if int(t.get('cashier_id', 0)) == self.cashier_id or t.get('cashier_name') == self.cashier_name]
        
        self.table_sales.setRowCount(len(cashier_txns))
        for row, t in enumerate(cashier_txns):
            self.table_sales.setItem(row, 0, QTableWidgetItem(str(t.get('transaction_number', ''))))
            self.table_sales.setItem(row, 1, QTableWidgetItem(f"{t.get('quantity_kg', 0):.2f} kg"))
            self.table_sales.setItem(row, 2, QTableWidgetItem(str(t.get('pricing_tier_name', ''))))
            self.table_sales.setItem(row, 3, QTableWidgetItem(f"NGN {t.get('total_amount', 0):,.2f}"))
            
            if t.get('status') == 'cancelled':
                status_item = QTableWidgetItem("CANCELLED")
                status_item.setForeground(QColor("#ef4444"))
            elif t.get('sync_status') == 'synced':
                status_item = QTableWidgetItem("SYNCED")
                status_item.setForeground(QColor("#4ade80"))
            else:
                status_item = QTableWidgetItem("PENDING")
                status_item.setForeground(QColor("#f59e0b"))
            self.table_sales.setItem(row, 4, status_item)



class TkinterPosWindow:
    def __init__(self, api_client, db_service, printer_service, sync_worker, on_logout_callback=None):
        import tkinter as tk
        from tkinter import ttk, messagebox

        self.api = api_client
        self.db = db_service
        self.printer = printer_service
        self.sync_worker = sync_worker
        self.on_logout_callback = on_logout_callback

        self.cashier_name = self.db.get_setting("cashier_name", "Cashier")
        self.cashier_id = int(self.db.get_setting("cashier_id", 1))

        self.base_price = 0.00
        self.current_stock = 0.00
        self.pricing_tiers = []
        self.selected_tier = None
        self.company_info = {}

        self.root = tk.Tk()
        self.root.title(f"Ruxxen Gas POS Terminal - Logged in as: {self.cashier_name}")
        self.root.geometry("1100x720")
        self.root.resizable(True, True)
        self.root.configure(bg="#0f172a")

        asset_dir = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", "assets"))
        logo_png = os.path.join(asset_dir, "logo.png")
        if os.path.exists(logo_png):
            try:
                from PIL import Image, ImageTk
                img = Image.open(logo_png)
                self.icon_img = ImageTk.PhotoImage(img)
                self.root.iconphoto(False, self.icon_img)
            except Exception:
                pass

        self.init_ui()
        self.load_initial_data()
        self.sync_worker.start()

    def init_ui(self):
        import tkinter as tk
        from tkinter import ttk

        # Header
        header = tk.Frame(self.root, bg="#1e293b", padx=15, pady=10)
        header.pack(fill="x", padx=10, pady=10)

        asset_dir = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", "assets"))
        logo_png = os.path.join(asset_dir, "logo.png")
        if os.path.exists(logo_png):
            try:
                from PIL import Image, ImageTk
                img = Image.open(logo_png).resize((40, 40))
                self.hdr_logo = ImageTk.PhotoImage(img)
                lbl_h_img = tk.Label(header, image=self.hdr_logo, bg="#1e293b")
                lbl_h_img.pack(side="left", padx=(0, 10))
            except Exception:
                pass

        self.lbl_title = tk.Label(header, text="RUXXEN GAS POS TERMINAL", font=("Arial", 16, "bold"), fg="#38bdf8", bg="#1e293b")
        self.lbl_title.pack(side="left")

        btn_logout = tk.Button(header, text="LOGOUT", bg="#ef4444", fg="white", font=("Arial", 10, "bold"), command=self.handle_logout)
        btn_logout.pack(side="right", padx=5)

        btn_sync = tk.Button(header, text="SYNC NOW", bg="#0284c7", fg="white", font=("Arial", 10, "bold"), command=self.trigger_manual_sync)
        btn_sync.pack(side="right", padx=5)

        btn_summary = tk.Button(header, text="MY DAILY SALES", bg="#8b5cf6", fg="white", font=("Arial", 10, "bold"), command=self.show_daily_summary_dialog)
        btn_summary.pack(side="right", padx=5)

        self.lbl_stock = tk.Label(header, text="Stock: --- kg", font=("Arial", 12, "bold"), fg="#f59e0b", bg="#1e293b")
        self.lbl_stock.pack(side="right", padx=10)

        self.lbl_cashier_info = tk.Label(header, text=f"Cashier: {self.cashier_name}", font=("Arial", 11), fg="#cbd5e1", bg="#1e293b")
        self.lbl_cashier_info.pack(side="right", padx=10)

        # Body
        body = tk.Frame(self.root, bg="#0f172a")
        body.pack(fill="both", expand=True, padx=10, pady=5)

        # Left Sale Entry Box
        left_frame = tk.LabelFrame(body, text=" New Gas Sale Entry ", font=("Arial", 12, "bold"), fg="#38bdf8", bg="#1e293b", padx=15, pady=15)
        left_frame.pack(side="left", fill="both", expand=True, padx=(0, 5))

        tk.Label(left_frame, text="Quick Weight Presets (kg):", fg="#f8fafc", bg="#1e293b").pack(anchor="w", pady=(0, 5))
        
        btn_frame = tk.Frame(left_frame, bg="#1e293b")
        btn_frame.pack(fill="x", pady=(0, 10))
        for w in [2.0, 3.0, 6.0, 12.5, 25.0, 50.0]:
            b = tk.Button(btn_frame, text=f"{w} kg", bg="#334155", fg="#38bdf8", font=("Arial", 10, "bold"), command=lambda weight=w: self.set_weight(weight))
            b.pack(side="left", padx=3)

        tk.Label(left_frame, text="Gas Weight (kg):", fg="#f8fafc", bg="#1e293b").pack(anchor="w")
        self.ent_weight = tk.Entry(left_frame, font=("Arial", 12), width=15)
        self.ent_weight.pack(anchor="w", pady=(2, 10))
        self.ent_weight.bind("<KeyRelease>", lambda e: self.calculate_total())

        tk.Label(left_frame, text="Pricing Tier:", fg="#f8fafc", bg="#1e293b").pack(anchor="w")
        self.combo_tier_var = tk.StringVar()
        self.combo_tier = ttk.Combobox(left_frame, textvariable=self.combo_tier_var, state="readonly", width=30)
        self.combo_tier.pack(anchor="w", pady=(2, 10))
        self.combo_tier.bind("<<ComboboxSelected>>", lambda e: self.on_tier_changed())

        # Price Display Frame
        p_frame = tk.Frame(left_frame, bg="#0f172a", bd=1, relief="solid", padx=10, pady=10)
        p_frame.pack(fill="x", pady=10)

        self.lbl_rate_info = tk.Label(p_frame, text="Base Price: NGN 0.00/kg", fg="#94a3b8", bg="#0f172a", font=("Arial", 10))
        self.lbl_rate_info.pack(anchor="w")

        self.lbl_total_price = tk.Label(p_frame, text="TOTAL: NGN 0.00", fg="#4ade80", bg="#0f172a", font=("Arial", 18, "bold"))
        self.lbl_total_price.pack(anchor="w", pady=(5, 0))

        tk.Label(left_frame, text="Customer Name:", fg="#f8fafc", bg="#1e293b").pack(anchor="w")
        self.ent_cust_name = tk.Entry(left_frame, font=("Arial", 11), width=30)
        self.ent_cust_name.insert(0, "Walk-in Customer")
        self.ent_cust_name.pack(anchor="w", pady=(2, 10))

        tk.Label(left_frame, text="Payment Method:", fg="#f8fafc", bg="#1e293b").pack(anchor="w")
        self.combo_payment = ttk.Combobox(left_frame, values=["Cash", "Card", "Transfer", "POS"], state="readonly", width=15)
        self.combo_payment.current(0)
        self.combo_payment.pack(anchor="w", pady=(2, 10))

        btn_checkout = tk.Button(left_frame, text="PRINT RECEIPT & COMPLETE SALE", bg="#16a34a", fg="white", font=("Arial", 12, "bold"), command=self.process_sale, pady=8)
        btn_checkout.pack(fill="x")

        # Right History Table Box & Copy Checkbox Container
        right_frame = tk.LabelFrame(body, text=f" Recent Sales ({self.cashier_name}) ", font=("Arial", 12, "bold"), fg="#38bdf8", bg="#1e293b", padx=10, pady=10)
        right_frame.pack(side="right", fill="both", expand=True, padx=(5, 0))

        # Single Copy Checkbox (Light Border Styling)
        copy_frame = tk.Frame(right_frame, bg="#1e293b", bd=1, relief="solid", highlightbackground="#334155", highlightthickness=1, padx=10, pady=6)
        copy_frame.pack(fill="x", pady=(0, 10))

        self.var_single_copy = tk.BooleanVar(value=False)
        self.chk_single_copy = tk.Checkbutton(copy_frame, text="Print 1 Copy Only (Uncheck for 2 Copies)", variable=self.var_single_copy, font=("Arial", 11, "bold"), fg="#ffffff", bg="#0f172a", selectcolor="#0284c7", activebackground="#0f172a", activeforeground="#ffffff")
        self.chk_single_copy.pack(anchor="w")

        # Scrollable Treeview Container for Terminal Recent Sales
        tree_container = tk.Frame(right_frame, bg="#1e293b")
        tree_container.pack(fill="both", expand=True)

        tree_scroll = ttk.Scrollbar(tree_container, orient="vertical")
        cols = ("TXN", "Weight", "Tier", "Total", "Status")
        self.tree_sales = ttk.Treeview(tree_container, columns=cols, show="headings", yscrollcommand=tree_scroll.set)
        tree_scroll.config(command=self.tree_sales.yview)

        for c in cols:
            self.tree_sales.heading(c, text=c)
            self.tree_sales.column(c, width=80)

        tree_scroll.pack(side="right", fill="y")
        self.tree_sales.pack(side="left", fill="both", expand=True)

    def cancel_selected_recent_sale(self):
        from tkinter import messagebox, simpledialog
        selected = self.tree_sales.selection()
        if not selected:
            messagebox.showwarning("Select Sale", "Please click a transaction row in the table to cancel.")
            return

        item = self.tree_sales.item(selected[0])
        txn_number = item['values'][0]
        
        txns = self.db.get_all_transactions(limit=50)
        txn = next((t for t in txns if t.get('transaction_number') == txn_number), None)
        if not txn:
            return

        if txn.get('status') == 'cancelled':
            messagebox.showwarning("Already Cancelled", f"Transaction {txn_number} is already cancelled.")
            return

        reason = simpledialog.askstring("Cancel Transaction", f"Enter reason for cancelling transaction {txn_number}:")
        if reason and reason.strip():
            self.db.cancel_transaction(txn_number, reason.strip())
            self.sync_worker.trigger_sync_now()
            self.refresh_sales_table()
            messagebox.showinfo("Transaction Cancelled", f"Transaction {txn_number} cancelled successfully.")

    def set_weight(self, w):
        self.ent_weight.delete(0, tk.END)
        self.ent_weight.insert(0, str(w))
        self.calculate_total()

    def load_initial_data(self):
        token = self.db.get_setting("auth_token")
        res = self.api.get_initial_data(token)
        if res.get("success"):
            data = res.get("data", {})
            self.base_price = data.get("base_price_per_kg", 0.00)
            self.current_stock = data.get("current_stock_kg", 0.00)
            self.pricing_tiers = data.get("pricing_tiers", [])
            self.company_info = data.get("company", {})
            self.db.cache_pricing_tiers(self.pricing_tiers)
        else:
            self.pricing_tiers = self.db.get_cached_pricing_tiers()

        co_name = self.company_info.get("name", "Ruxxen Gas").upper()
        self.lbl_title.config(text=f"{co_name} POS TERMINAL")
        self.root.title(f"{co_name} POS Terminal - Cashier: {self.cashier_name}")
        self.lbl_stock.config(text=f"Stock: {self.current_stock:,.2f} kg")
        
        tier_names = [f"{t['name']} (NGN {t['effective_price_per_kg']:,.2f}/kg)" for t in self.pricing_tiers]
        self.combo_tier['values'] = tier_names
        if tier_names:
            self.combo_tier.current(0)
            self.on_tier_changed()

        self.refresh_sales_table()

    def on_tier_changed(self):
        idx = self.combo_tier.current()
        if idx >= 0 and idx < len(self.pricing_tiers):
            self.selected_tier = self.pricing_tiers[idx]
        else:
            self.selected_tier = None
        self.calculate_total()

    def calculate_total(self):
        try:
            qty = float(self.ent_weight.get().strip() or 0)
        except ValueError:
            qty = 0.0

        if self.selected_tier:
            eff_rate = self.selected_tier.get("effective_price_per_kg", self.base_price)
        else:
            eff_rate = self.base_price

        total = qty * eff_rate
        self.lbl_rate_info.config(text=f"Base Price: NGN {self.base_price:,.2f}/kg  |  Unit Rate: NGN {eff_rate:,.2f}/kg")
        self.lbl_total_price.config(text=f"TOTAL: NGN {total:,.2f}")

    def process_sale(self):
        from tkinter import messagebox
        try:
            qty = float(self.ent_weight.get().strip())
            if qty <= 0: raise ValueError()
        except ValueError:
            messagebox.showerror("Error", "Please enter a valid gas weight in kg.")
            return

        if not self.selected_tier:
            messagebox.showerror("Error", "Please select a pricing tier.")
            return

        eff_rate = self.selected_tier.get("effective_price_per_kg", self.base_price)
        total = qty * eff_rate
        txn_number = "TXN-" + datetime.now().strftime("%Y%m%d") + "-" + uuid.uuid4().hex[:6].upper()
        cust_name = self.ent_cust_name.get().strip() or "Walk-in Customer"

        txn_data = {
            "transaction_number": txn_number,
            "cashier_id": self.cashier_id,
            "cashier_name": self.cashier_name,
            "customer_discount_id": self.selected_tier.get("id"),
            "pricing_tier_name": self.selected_tier.get("name"),
            "quantity_kg": qty,
            "price_per_kg": eff_rate,
            "total_amount": total,
            "customer_name": cust_name,
            "payment_type": self.combo_payment.get(),
            "notes": f"Sale via Desktop POS ({self.selected_tier.get('name')})",
            "created_at": datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        }

        # Determine number of receipt copies (Default = 2, Checkbox checked = 1)
        num_copies = 1 if self.var_single_copy.get() else 2

        self.db.save_transaction(txn_data)
        self.printer.print_receipt(txn_data, self.company_info, num_copies=num_copies)
        self.sync_worker.trigger_sync_now()

        self.ent_weight.delete(0, 'end')
        self.refresh_sales_table()
        messagebox.showinfo("Success", f"Transaction {txn_number} recorded!\nTotal: NGN {total:,.2f}\nPrinted {num_copies} receipt copy/copies.")

    def trigger_manual_sync(self):
        from tkinter import messagebox
        res = self.sync_worker.trigger_sync_now()
        count = res.get("synced_count", 0)
        messagebox.showinfo("Sync Status", f"Synced {count} pending transaction(s).")
        self.refresh_sales_table()

    def show_daily_summary_dialog(self):
        import tkinter as tk
        from tkinter import ttk, messagebox, simpledialog
        txns = self.db.get_all_transactions(limit=1000)
        today_str = datetime.now().strftime("%Y-%m-%d")
        
        today_txns = [t for t in txns if str(t.get('created_at', '')).startswith(today_str) and (int(t.get('cashier_id', 0)) == self.cashier_id or t.get('cashier_name') == self.cashier_name)]
        
        completed_today = [t for t in today_txns if t.get('status') != 'cancelled']
        cancelled_today = [t for t in today_txns if t.get('status') == 'cancelled']

        total_count = len(completed_today)
        total_qty = sum(float(t.get('quantity_kg', 0)) for t in completed_today)
        total_amount = sum(float(t.get('total_amount', 0)) for t in completed_today)

        cancelled_count = len(cancelled_today)
        cancelled_amount = sum(float(t.get('total_amount', 0)) for t in cancelled_today)

        cash_total = sum(float(t.get('total_amount', 0)) for t in completed_today if str(t.get('payment_type', '')).lower() == 'cash')
        card_total = sum(float(t.get('total_amount', 0)) for t in completed_today if str(t.get('payment_type', '')).lower() in ['card', 'pos'])
        transfer_total = sum(float(t.get('total_amount', 0)) for t in completed_today if str(t.get('payment_type', '')).lower() == 'transfer')

        summary_data = {
            'cashier_id': self.cashier_id,
            'cashier_name': self.cashier_name,
            'date': today_str,
            'total_sales_count': total_count,
            'total_quantity_kg': total_qty,
            'total_amount': total_amount,
            'cancelled_sales_count': cancelled_count,
            'cancelled_total_amount': cancelled_amount,
            'payment_breakdown': {
                'cash': cash_total,
                'card': card_total,
                'transfer': transfer_total
            }
        }

        top = tk.Toplevel(self.root)
        top.title(f"Ruxxen Gas - Cashier Daily Sales & Shift Report ({self.cashier_name})")
        top.geometry("1080x680")
        top.configure(bg="#0f172a")

        hdr = tk.Frame(top, bg="#1e293b", padx=15, pady=10)
        hdr.pack(fill="x", padx=10, pady=10)

        tk.Label(hdr, text="CASHIER DAILY SALES REPORT & SHIFT SUMMARY", font=("Arial", 14, "bold"), fg="#38bdf8", bg="#1e293b").pack(side="left")
        tk.Label(hdr, text=f"Cashier: {self.cashier_name} | Shift Date: {today_str}", font=("Arial", 11), fg="#cbd5e1", bg="#1e293b").pack(side="right")

        # Cards frame
        cards = tk.Frame(top, bg="#0f172a")
        cards.pack(fill="x", padx=10, pady=5)

        # Revenue Card
        c1 = tk.Frame(cards, bg="#1e293b", bd=1, relief="solid", padx=10, pady=10)
        c1.pack(side="left", fill="both", expand=True, padx=3)
        tk.Label(c1, text="TOTAL REVENUE", font=("Arial", 9, "bold"), fg="#94a3b8", bg="#1e293b").pack(anchor="w")
        tk.Label(c1, text=f"NGN {total_amount:,.2f}", font=("Arial", 15, "bold"), fg="#4ade80", bg="#1e293b").pack(anchor="w")

        # Volume Card
        c2 = tk.Frame(cards, bg="#1e293b", bd=1, relief="solid", padx=10, pady=10)
        c2.pack(side="left", fill="both", expand=True, padx=3)
        tk.Label(c2, text="TOTAL GAS SOLD", font=("Arial", 9, "bold"), fg="#94a3b8", bg="#1e293b").pack(anchor="w")
        tk.Label(c2, text=f"{total_qty:,.2f} kg", font=("Arial", 15, "bold"), fg="#38bdf8", bg="#1e293b").pack(anchor="w")

        # Count Card
        c3 = tk.Frame(cards, bg="#1e293b", bd=1, relief="solid", padx=10, pady=10)
        c3.pack(side="left", fill="both", expand=True, padx=3)
        tk.Label(c3, text="COMPLETED SALES", font=("Arial", 9, "bold"), fg="#94a3b8", bg="#1e293b").pack(anchor="w")
        tk.Label(c3, text=f"{total_count} Sales", font=("Arial", 15, "bold"), fg="#f59e0b", bg="#1e293b").pack(anchor="w")

        # Cancelled Card
        c5 = tk.Frame(cards, bg="#1e293b", bd=1, relief="solid", padx=10, pady=10)
        c5.pack(side="left", fill="both", expand=True, padx=3)
        tk.Label(c5, text="CANCELLED SALES", font=("Arial", 9, "bold"), fg="#94a3b8", bg="#1e293b").pack(anchor="w")
        tk.Label(c5, text=f"NGN {cancelled_amount:,.2f}", font=("Arial", 15, "bold"), fg="#ef4444", bg="#1e293b").pack(anchor="w")

        # Breakdown Card
        c4 = tk.Frame(cards, bg="#1e293b", bd=1, relief="solid", padx=10, pady=10)
        c4.pack(side="left", fill="both", expand=True, padx=3)
        tk.Label(c4, text="PAYMENT BREAKDOWN", font=("Arial", 9, "bold"), fg="#94a3b8", bg="#1e293b").pack(anchor="w")
        tk.Label(c4, text=f"Cash: NGN {cash_total:,.2f}\nCard: NGN {card_total:,.2f}\nTrf: NGN {transfer_total:,.2f}", font=("Arial", 8), fg="#f8fafc", bg="#1e293b").pack(anchor="w")

        tk.Label(top, text="TODAY'S DETAILED INDIVIDUAL TRANSACTIONS", font=("Arial", 11, "bold"), fg="#38bdf8", bg="#0f172a").pack(anchor="w", padx=10, pady=(10, 2))

        cols = ("TXN", "Time", "Customer", "Phone", "Tier", "Qty (kg)", "Rate", "Total", "Payment", "Status")
        tree = ttk.Treeview(top, columns=cols, show="headings", height=14)
        for c in cols:
            tree.heading(c, text=c)
            tree.column(c, width=90)
        tree.pack(fill="both", expand=True, padx=10, pady=5)

        for t in today_txns:
            created_time = str(t.get('created_at', '')).split()[-1] if ' ' in str(t.get('created_at', '')) else str(t.get('created_at', ''))
            pay_str = str(t.get('payment_type', 'CASH')).upper()
            status_str = "CANCELLED" if t.get('status') == 'cancelled' else "COMPLETED"
            tree.insert("", "end", values=(
                t.get('transaction_number'),
                created_time,
                t.get('customer_name', 'Walk-in Customer'),
                t.get('customer_phone', '-'),
                t.get('pricing_tier_name', 'Standard'),
                f"{float(t.get('quantity_kg', 0)):.2f} kg",
                f"NGN {float(t.get('price_per_kg', 0)):,.2f}",
                f"NGN {float(t.get('total_amount', 0)):,.2f}",
                pay_str,
                status_str
            ))

        btn_bar = tk.Frame(top, bg="#0f172a", pady=10)
        btn_bar.pack(fill="x", padx=10)

        b_prn = tk.Button(btn_bar, text="PRINT SHIFT SUMMARY (Z-REPORT)", bg="#16a34a", fg="white", font=("Arial", 11, "bold"), command=lambda: (self.printer.print_shift_summary(summary_data, self.company_info), messagebox.showinfo("Z-Report Printed", "Report printed successfully.")))
        b_prn.pack(side="left", padx=5)

        def cancel_dlg_selected():
            selected = tree.selection()
            if not selected:
                messagebox.showwarning("Select Transaction", "Please click a row in the table to cancel.")
                return

            item = tree.item(selected[0])
            txn_number = item['values'][0]
            selected_txn = next((t for t in today_txns if t.get('transaction_number') == txn_number), None)
            if not selected_txn:
                return

            if selected_txn.get('status') == 'cancelled':
                messagebox.showwarning("Already Cancelled", f"Transaction {txn_number} is already cancelled.")
                return

            reason = simpledialog.askstring("Cancel Transaction", f"Enter reason for cancelling transaction {txn_number}:")
            if reason and reason.strip():
                self.db.cancel_transaction(txn_number, reason.strip())
                self.sync_worker.trigger_sync_now()
                self.refresh_sales_table()
                messagebox.showinfo("Transaction Cancelled", f"Transaction {txn_number} cancelled successfully.")
                top.destroy()
                self.show_daily_summary_dialog()

        b_can = tk.Button(btn_bar, text="CANCEL SELECTED SALE", bg="#dc2626", fg="white", font=("Arial", 11, "bold"), command=cancel_dlg_selected)
        b_can.pack(side="left", padx=5)

        b_cls = tk.Button(btn_bar, text="CLOSE REPORT", bg="#334155", fg="white", font=("Arial", 11, "bold"), command=top.destroy)
        b_cls.pack(side="right", padx=5)

    def handle_logout(self):
        from tkinter import messagebox
        if messagebox.askyesno("Confirm Logout", "Are you sure you want to log out of the POS terminal?"):
            self.db.set_setting("auth_token", "")
            self.db.set_setting("cashier_name", "")
            self.db.set_setting("cashier_id", "")
            self.sync_worker.stop()
            self.root.destroy()
            if self.on_logout_callback:
                self.on_logout_callback()

    def refresh_sales_table(self):
        for item in self.tree_sales.get_children():
            self.tree_sales.delete(item)
        txns = self.db.get_all_transactions(limit=50)
        cashier_txns = [t for t in txns if int(t.get('cashier_id', 0)) == self.cashier_id or t.get('cashier_name') == self.cashier_name]
        for t in cashier_txns:
            status_str = "CANCELLED" if t.get('status') == 'cancelled' else str(t.get('sync_status')).upper()
            self.tree_sales.insert("", "end", values=(
                t.get('transaction_number'),
                f"{t.get('quantity_kg', 0):.2f} kg",
                t.get('pricing_tier_name'),
                f"NGN {t.get('total_amount', 0):,.2f}",
                status_str
            ))

    def run(self):
        self.root.mainloop()


    def run(self):
        self.root.mainloop()
