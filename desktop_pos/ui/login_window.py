import sys
import os
from config import get_server_url

try:
    from PySide6.QtWidgets import (
        QWidget, QVBoxLayout, QHBoxLayout, QLabel, QLineEdit, 
        QPushButton, QMessageBox, QFrame, QGraphicsDropShadowEffect
    )
    from PySide6.QtCore import Qt, Signal
    from PySide6.QtGui import QFont, QColor, QIcon, QPixmap
    PYSIDE_AVAILABLE = True
except ImportError:
    PYSIDE_AVAILABLE = False
    QWidget = object
    Signal = lambda *args: None
    import tkinter as tk
    from tkinter import messagebox


class PySideLoginWindow(QWidget):
    login_success_signal = Signal(dict)

    def __init__(self, api_client, db_service):
        super().__init__()
        self.api = api_client
        self.db = db_service
        self.init_ui()

    def init_ui(self):
        self.setWindowTitle("Ruxxen Gas POS - Terminal Login")
        self.setFixedSize(460, 560)
        self.setStyleSheet("""
            QWidget {
                background-color: #0f172a;
                color: #f8fafc;
                font-family: 'Segoe UI', Inter, Arial, sans-serif;
            }
            QFrame#card {
                background-color: #1e293b;
                border-radius: 16px;
                border: 1px solid #334155;
            }
            QLabel#title {
                font-size: 24px;
                font-weight: 800;
                color: #38bdf8;
                letter-spacing: 0.5px;
            }
            QLabel#subtitle {
                font-size: 13px;
                color: #94a3b8;
                margin-bottom: 5px;
            }
            QLabel#input_lbl {
                font-size: 12px;
                font-weight: 600;
                color: #cbd5e1;
                margin-top: 4px;
            }
            QLineEdit {
                background-color: #0f172a;
                border: 1.5px solid #334155;
                border-radius: 8px;
                padding: 11px;
                font-size: 14px;
                color: #ffffff;
            }
            QLineEdit:focus {
                border: 1.5px solid #38bdf8;
                background-color: #131d31;
            }
            QPushButton#login_btn {
                background-color: #0284c7;
                color: white;
                font-size: 15px;
                font-weight: bold;
                border-radius: 8px;
                padding: 13px;
                margin-top: 8px;
            }
            QPushButton#login_btn:hover {
                background-color: #0369a1;
            }
        """)

        main_layout = QVBoxLayout(self)
        main_layout.setContentsMargins(25, 25, 25, 25)

        card = QFrame()
        card.setObjectName("card")
        
        # Add soft drop shadow
        try:
            shadow = QGraphicsDropShadowEffect(self)
            shadow.setBlurRadius(25)
            shadow.setColor(QColor(0, 0, 0, 160))
            shadow.setOffset(0, 8)
            card.setGraphicsEffect(shadow)
        except Exception:
            pass

        card_layout = QVBoxLayout(card)
        card_layout.setContentsMargins(25, 25, 25, 25)
        card_layout.setSpacing(8)

        # Asset Logo loading
        asset_dir = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", "assets"))
        logo_png = os.path.join(asset_dir, "logo.png")
        logo_jpg = os.path.join(asset_dir, "logo.jpg")
        logo_path = logo_png if os.path.exists(logo_png) else logo_jpg

        if os.path.exists(logo_path):
            try:
                self.setWindowIcon(QIcon(logo_path))
            except Exception:
                pass

        if os.path.exists(logo_path):
            try:
                logo_lbl = QLabel()
                pix = QPixmap(logo_path).scaled(90, 90, Qt.KeepAspectRatio, Qt.SmoothTransformation)
                logo_lbl.setPixmap(pix)
                logo_lbl.setAlignment(Qt.AlignCenter)
                card_layout.addWidget(logo_lbl)
            except Exception:
                pass


        title = QLabel("RUXXEN GAS POS")
        title.setObjectName("title")
        title.setAlignment(Qt.AlignCenter)

        subtitle = QLabel("Cooking Gas Terminal System")
        subtitle.setObjectName("subtitle")
        subtitle.setAlignment(Qt.AlignCenter)

        card_layout.addWidget(title)
        card_layout.addWidget(subtitle)
        card_layout.addSpacing(5)

        # Email
        lbl_email = QLabel("Cashier Email:")
        lbl_email.setObjectName("input_lbl")
        self.txt_email = QLineEdit()
        self.txt_email.setPlaceholderText("cashier@ruxxen.com")

        # Password
        lbl_pwd = QLabel("Password:")
        lbl_pwd.setObjectName("input_lbl")
        self.txt_pwd = QLineEdit()
        self.txt_pwd.setEchoMode(QLineEdit.Password)
        self.txt_pwd.setPlaceholderText("••••••••")

        card_layout.addWidget(lbl_email)
        card_layout.addWidget(self.txt_email)
        card_layout.addWidget(lbl_pwd)
        card_layout.addWidget(self.txt_pwd)
        card_layout.addSpacing(10)

        btn_login = QPushButton("LOGIN TO TERMINAL")
        btn_login.setObjectName("login_btn")
        btn_login.clicked.connect(self.handle_login)
        card_layout.addWidget(btn_login)

        main_layout.addWidget(card)

    def handle_login(self):
        email = self.txt_email.text().strip()
        pwd = self.txt_pwd.text().strip()

        if not email or not pwd:
            QMessageBox.warning(self, "Input Error", "Please enter cashier email and password.")
            return

        url = get_server_url("https://app.ruxxengas.com")
        self.api.set_base_url(url)
        self.db.set_setting("server_url", url)

        res = self.api.login(email, pwd)
        if res.get("success"):
            data = res.get("data", {})
            token = data.get("token")
            user = data.get("user", {})

            self.db.set_setting("auth_token", token)
            self.db.set_setting("cashier_id", user.get("id"))
            self.db.set_setting("cashier_name", user.get("name"))
            self.db.set_setting("cashier_email", user.get("email"))

            self.login_success_signal.emit(user)
        else:
            QMessageBox.critical(self, "Login Failed", res.get("message", "Unable to log in."))


class TkinterLoginWindow:
    def __init__(self, api_client, db_service, on_success_callback):
        self.api = api_client
        self.db = db_service
        self.on_success = on_success_callback
        
        self.root = tk.Tk()
        self.root.title("Ruxxen Gas POS - Terminal Login")
        self.root.geometry("450x500")
        self.root.configure(bg="#0f172a")

        asset_dir = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", "assets"))
        logo_png = os.path.join(asset_dir, "logo.png")
        if os.path.exists(logo_png):
            try:
                from PIL import Image, ImageTk
                img = Image.open(logo_png)
                self.icon_img = ImageTk.PhotoImage(img)
                self.root.iconphoto(False, self.icon_img)

                # Show header logo image
                logo_resized = img.resize((110, 110))
                self.header_logo = ImageTk.PhotoImage(logo_resized)
                lbl_img = tk.Label(self.root, image=self.header_logo, bg="#0f172a")
                lbl_img.pack(pady=(20, 0))
            except Exception:
                pass

        lbl_title = tk.Label(self.root, text="RUXXEN GAS POS", font=("Arial", 18, "bold"), fg="#38bdf8", bg="#0f172a")
        lbl_title.pack(pady=(10, 2))

        lbl_sub = tk.Label(self.root, text="Cooking Gas Terminal System", font=("Arial", 10), fg="#94a3b8", bg="#0f172a")
        lbl_sub.pack(pady=(0, 15))

        frame = tk.Frame(self.root, bg="#1e293b", bd=1, relief="solid")
        frame.pack(padx=25, pady=5, fill="both", expand=True)

        tk.Label(frame, text="Cashier Email:", font=("Arial", 10, "bold"), fg="#cbd5e1", bg="#1e293b").pack(anchor="w", padx=20, pady=(15, 2))
        self.ent_email = tk.Entry(frame, font=("Arial", 11), width=30)
        self.ent_email.pack(padx=20, pady=(0, 10))

        tk.Label(frame, text="Password:", font=("Arial", 10, "bold"), fg="#cbd5e1", bg="#1e293b").pack(anchor="w", padx=20, pady=(5, 2))
        self.ent_pwd = tk.Entry(frame, font=("Arial", 11), width=30, show="*")
        self.ent_pwd.pack(padx=20, pady=(0, 15))

        btn = tk.Button(frame, text="LOGIN TO TERMINAL", bg="#0284c7", fg="white", font=("Arial", 11, "bold"), command=self.handle_login, pady=8)
        btn.pack(padx=20, pady=15, fill="x")

    def handle_login(self):
        email = self.ent_email.get().strip()
        pwd = self.ent_pwd.get().strip()

        if not email or not pwd:
            messagebox.showerror("Error", "Please enter email and password.")
            return

        url = get_server_url("https://app.ruxxengas.com")
        self.api.set_base_url(url)
        self.db.set_setting("server_url", url)

        res = self.api.login(email, pwd)
        if res.get("success"):
            data = res.get("data", {})
            token = data.get("token")
            user = data.get("user", {})

            self.db.set_setting("auth_token", token)
            self.db.set_setting("cashier_id", user.get("id"))
            self.db.set_setting("cashier_name", user.get("name"))
            self.db.set_setting("cashier_email", user.get("email"))

            self.root.destroy()
            if self.on_success:
                self.on_success(user)
        else:
            messagebox.showerror("Login Failed", res.get("message", "Unable to log in."))

    def run(self):
        self.root.mainloop()
