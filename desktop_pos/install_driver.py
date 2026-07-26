#!/usr/bin/env python3
import sys
import os
import platform
import subprocess

def install_printer_driver():
    os_name = platform.system()
    base_dir = os.path.dirname(os.path.abspath(__file__))
    drivers_dir = os.path.join(base_dir, "Drivers")

    print(f"==================================================")
    print(f"   Ruxxen Gas POS - Thermal Printer Driver Setup  ")
    print(f"   Target Operating System: {os_name}             ")
    print(f"==================================================\n")

    if os_name == "Linux":
        deb_path = os.path.join(drivers_dir, "Linux", "printer-driver-xprinter_3.13.37_all.deb")
        if not os.path.exists(deb_path):
            print(f"[Error] Linux driver package not found at: {deb_path}")
            sys.exit(1)

        print(f"[*] Installing Xprinter Linux Driver (.deb)...")
        try:
            # Install .deb driver package
            cmd = f"sudo dpkg -i '{deb_path}' || sudo apt-get install -f -y"
            res = subprocess.run(cmd, shell=True)
            if res.returncode == 0:
                print("[✔] Xprinter Linux driver installed successfully!")
            else:
                print("[!] Driver installation returned non-zero exit code. Please verify CUPS service.")

            # Create persistent USB printer permissions udev rule
            udev_rule_content = 'SUBSYSTEM=="usb", ATTRS{idVendor}=="0483", ATTRS{idProduct}=="5743", MODE="0666", GROUP="plugdev"\n'
            rule_path = "/etc/udev/rules.d/99-thermal-printer.rules"
            
            print("[*] Setting up persistent USB device permissions (/etc/udev/rules.d/)...")
            udev_cmd = f"echo '{udev_rule_content.strip()}' | sudo tee {rule_path} > /dev/null && sudo chmod 666 /dev/usb/lp* 2>/dev/null || true"
            subprocess.run(udev_cmd, shell=True)
            subprocess.run("sudo udevadm control --reload-rules && sudo udevadm trigger", shell=True)
            print("[✔] Persistent USB printer permissions configured! (No sudo chmod required for /dev/usb/lp0)")

        except Exception as e:
            print(f"[Error] Failed to install Linux printer driver: {e}")

    elif os_name == "Windows":
        exe_path = os.path.join(drivers_dir, "Windows", "Driver", "Xprinter.driver.2025.06.23.1.exe")
        if not os.path.exists(exe_path):
            print(f"[Error] Windows driver executable not found at: {exe_path}")
            sys.exit(1)

        print(f"[*] Launching Xprinter Windows Driver Setup...")
        try:
            # Run installer with silent flags /S or /VERYSILENT
            cmd = f'"{exe_path}" /S'
            print(f"Executing: {cmd}")
            subprocess.run(cmd, shell=True, check=False)
            print("[✔] Windows Printer Driver installer triggered successfully!")
        except Exception as e:
            print(f"[Error] Failed to launch Windows printer driver: {e}")

    else:
        print(f"[Warning] Unsupported Operating System: {os_name}")

if __name__ == "__main__":
    install_printer_driver()
