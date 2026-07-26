import sys
import os

# Add desktop_pos root to path
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

# Configure QT_PLUGIN_PATH to point to PySide6 package plugins (resolves Conda Qt plugin conflicts)
try:
    import PySide6
    qt_plugins_dir = os.path.join(os.path.dirname(PySide6.__file__), "Qt", "plugins")
    if os.path.exists(qt_plugins_dir):
        os.environ["QT_PLUGIN_PATH"] = qt_plugins_dir
except Exception:
    pass

from services.db_service import LocalDatabase
from services.api_client import ApiClient
from services.printer_service import ThermalPrinterService
from services.sync_worker import SyncWorker

from ui.login_window import PySideLoginWindow, TkinterLoginWindow, PYSIDE_AVAILABLE
from ui.pos_window import PySidePosWindow, TkinterPosWindow


def main():
    db = LocalDatabase()
    
    # Check server URL setting
    server_url = db.get_setting("server_url", "http://localhost:8000")
    api = ApiClient(base_url=server_url)
    printer = ThermalPrinterService()
    sync_worker = SyncWorker(db, api)

    pyside_initialized = False

    if PYSIDE_AVAILABLE:
        try:
            from PySide6.QtWidgets import QApplication
            from PySide6.QtGui import QIcon
            app = QApplication(sys.argv)
            app.setApplicationName("Ruxxen Gas POS")
            pyside_initialized = True

            # Set taskbar app icon for Linux Mint / X11 window managers
            icon_png = os.path.abspath(os.path.join(os.path.dirname(__file__), "assets", "logo.png"))
            if os.path.exists(icon_png):
                app.setWindowIcon(QIcon(icon_png))

            pos_window = None
            login_win = None

            def show_login():
                nonlocal login_win
                login_win = PySideLoginWindow(api, db)
                login_win.login_success_signal.connect(lambda user: (login_win.close(), show_pos(user)))
                login_win.show()

            def show_pos(user_data=None):
                nonlocal pos_window
                pos_window = PySidePosWindow(api, db, printer, sync_worker, on_logout_callback=show_login)
                pos_window.show()

            token = db.get_setting("auth_token")
            if token:
                show_pos()
            else:
                show_login()

            sys.exit(app.exec())
        except Exception as e:
            print(f"[Warning] PySide6 Qt GUI initialization failed ({e}). Switching to Tkinter POS GUI...")
            pyside_initialized = False

    if not pyside_initialized:
        # Fallback to Tkinter POS GUI
        def launch_pos_gui():
            pos_gui = TkinterPosWindow(api, db, printer, sync_worker, on_logout_callback=launch_login_gui)
            pos_gui.run()

        def launch_login_gui():
            tk_login = TkinterLoginWindow(api, db, on_success_callback=lambda user: launch_pos_gui())
            tk_login.run()

        token = db.get_setting("auth_token")
        if token:
            launch_pos_gui()
        else:
            launch_login_gui()

if __name__ == "__main__":
    main()
