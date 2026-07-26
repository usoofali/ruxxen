import os
import glob
import subprocess
import logging
from datetime import datetime
from PIL import Image


class ThermalPrinterService:
    def __init__(self, printer_name="POS80", host=None, port=9100, vendor_id=0x0483, product_id=0x5743):
        self.printer_name = printer_name
        self.host = host
        self.port = port
        self.vendor_id = vendor_id
        self.product_id = product_id

    def get_logo_escpos_bytes(self, logo_path="desktop_pos/assets/logo.jpg", target_width=320):
        if not os.path.exists(logo_path):
            return b""
        try:
            img = Image.open(logo_path).convert('L')
            w_percent = (target_width / float(img.size[0]))
            h_size = int((float(img.size[1]) * float(w_percent)))
            target_width = (target_width // 8) * 8
            img = img.resize((target_width, h_size), Image.Resampling.LANCZOS)
            img = img.point(lambda p: 255 if p > 128 else 0, '1')
            
            width_bytes = target_width // 8
            height_pixels = h_size
            
            # GS v 0 command: \x1d\x76\x30\x00 + xL xH yL yH
            header = bytes([0x1D, 0x76, 0x30, 0x00, width_bytes & 0xFF, (width_bytes >> 8) & 0xFF, height_pixels & 0xFF, (height_pixels >> 8) & 0xFF])
            
            raw_bytes = bytearray()
            for y in range(height_pixels):
                for x_byte in range(width_bytes):
                    b = 0
                    for bit in range(8):
                        pixel_x = x_byte * 8 + bit
                        if pixel_x < target_width:
                            if img.getpixel((pixel_x, y)) == 0:
                                b |= (1 << (7 - bit))
                    raw_bytes.append(b)
            # Center alignment command: \x1b\x61\x01 + raster bytes + \n
            return b'\x1b\x61\x01' + header + bytes(raw_bytes) + b'\x1b\x61\x00\n'
        except Exception as e:
            logging.warning(f"Error generating ESC/POS logo raster bytes: {e}")
            return b""

    def format_receipt_text(self, txn_data, company_data=None, copy_num=1, total_copies=1):
        company_data = company_data or {}
        co_name = company_data.get('name', 'Ruxxen Investment Limited').upper()
        co_address = company_data.get('address', 'Along Bye Pass Zaria Road, Lalan Gusau, Zamfara State')
        co_phone = company_data.get('phone', '+234 123 456 7890')
        co_email = company_data.get('email', 'ruxxentimessynergy@gmail.com')
        co_footer = company_data.get('receipt_footer', 'Thank you for buying from Ruxxen Gas!')

        def wrap_center(text, width=40):
            if not text:
                return []
            words = text.split()
            lines = []
            cur_line = []
            cur_len = 0
            for w in words:
                if cur_len + len(w) + (1 if cur_line else 0) <= width:
                    cur_line.append(w)
                    cur_len += len(w) + (1 if cur_line else 0)
                else:
                    lines.append(" ".join(cur_line).center(width))
                    cur_line = [w]
                    cur_len = len(w)
            if cur_line:
                lines.append(" ".join(cur_line).center(width))
            return lines

        lines = []
        lines.append("========================================")
        lines.extend(wrap_center(co_name))
        if co_address:
            lines.extend(wrap_center(co_address))
        if co_phone:
            lines.append(f"Tel: {co_phone}".center(40))
        if co_email:
            lines.append(f"Email: {co_email}".center(40))
        lines.append("========================================")
        
        if total_copies > 1:
            copy_label = "CUSTOMER COPY" if copy_num == 1 else "PLANT / MERCHANT COPY"
            lines.append(f"--- {copy_label} ---".center(40))
            lines.append("----------------------------------------")

        lines.append(f"Receipt #: {txn_data.get('transaction_number', 'N/A')}")
        lines.append(f"Date:     {txn_data.get('created_at', '')}")
        lines.append(f"Cashier:  {txn_data.get('cashier_name', 'Cashier')}")
        lines.append(f"Customer: {txn_data.get('customer_name', 'Walk-in Customer')}")
        if txn_data.get('customer_phone'):
            lines.append(f"Phone:    {txn_data.get('customer_phone')}")
        lines.append("----------------------------------------")
        
        qty = float(txn_data.get('quantity_kg', 0))
        rate = float(txn_data.get('price_per_kg', 0))
        total = float(txn_data.get('total_amount', 0))
        tier = txn_data.get('pricing_tier_name', 'Default')

        lines.append(f"Item: LPG Cooking Gas ({tier})")
        lines.append(f"Qty:  {qty:.2f} kg  x  NGN {rate:,.2f}/kg")
        lines.append("----------------------------------------")
        lines.append(f"TOTAL AMOUNT:      NGN {total:,.2f}")
        lines.append(f"Payment Type:      {txn_data.get('payment_type', 'CASH').upper()}")

        lines.append("========================================")
        lines.extend(wrap_center(co_footer))
        lines.append("========================================\n\n")

        return "\n".join(lines)

    def print_receipt(self, txn_data, company_data=None, num_copies=2):
        printed_count = 0
        receipt_texts = []
        logo_raster = self.get_logo_escpos_bytes(target_width=320)

        for copy_idx in range(1, num_copies + 1):
            receipt_text = self.format_receipt_text(txn_data, company_data, copy_num=copy_idx, total_copies=num_copies)
            receipt_texts.append(receipt_text)

            # 1. Primary: Direct /dev/usb/lp* ESC/POS raw file printing with company logo image
            lp_ports = glob.glob('/dev/usb/lp*')
            printed_direct = False
            if lp_ports:
                for lp_dev in lp_ports:
                    try:
                        with open(lp_dev, 'wb') as f:
                            esc_init = b'\x1b\x40'
                            esc_cut = b'\x1dV\x42\x00'
                            payload = esc_init + logo_raster + receipt_text.encode('utf-8', errors='ignore') + esc_cut
                            f.write(payload)
                        print(f"[Printer] Printed receipt copy #{copy_idx} with company logo to {lp_dev}")
                        printed_count += 1
                        printed_direct = True
                        break
                    except PermissionError:
                        print(f"[Printer Warning] Permission denied for {lp_dev}. Run: sudo chmod 666 {lp_dev}")
                    except Exception as e:
                        print(f"[Printer Error] Writing to {lp_dev} failed: {e}")

            if printed_direct:
                continue

            # 2. Secondary: Print via CUPS system printer queue (lpr / lp)
            try:
                res = subprocess.run(["lpstat", "-p"], capture_output=True, text=True)
                if res.returncode == 0 and res.stdout.strip():
                    proc = subprocess.Popen(["lpr"], stdin=subprocess.PIPE, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
                    stdout, stderr = proc.communicate(input=receipt_text.encode('utf-8'))
                    if proc.returncode == 0:
                        print(f"[Printer] Receipt copy #{copy_idx} sent to CUPS print spooler successfully!")
                        printed_count += 1
                        continue
            except Exception as e:
                logging.warning(f"CUPS lpr print attempt failed: {e}")

            # 3. Tertiary: PyUSB driver with logo image
            try:
                from escpos.printer import Usb
                p = Usb(self.vendor_id, self.product_id)
                if os.path.exists("desktop_pos/assets/logo.jpg"):
                    try:
                        p.image("desktop_pos/assets/logo.jpg")
                    except Exception:
                        pass
                p.text(receipt_text)
                p.cut()
                print(f"[Printer] Printed copy #{copy_idx} via PyUSB driver.")
                printed_count += 1
                continue
            except Exception:
                pass

            # 4. Fallback simulation print
            print(f"\n--- THERMAL RECEIPT SIMULATION (COPY #{copy_idx}/{num_copies}) ---")
            print("[COMPANY LOGO IMAGE: Ruxxen Gas Logo (240px)]")
            print(receipt_text)
            print("----------------------------------\n")

        if printed_count > 0:
            return {"success": True, "message": f"Printed {printed_count} copy/copies with logo successfully."}
        else:
            return {"success": True, "message": f"Receipts formatted for {num_copies} copy/copies (simulated).", "receipt_text": "\n".join(receipt_texts)}

    def print_shift_summary(self, summary_data, company_data=None):
        company_data = company_data or {}
        co_name = company_data.get('name', 'Ruxxen Investment Limited').upper()
        cashier_name = summary_data.get('cashier_name', 'Cashier')
        cashier_id = summary_data.get('cashier_id', 1)
        date_str = summary_data.get('date', datetime.now().strftime("%Y-%m-%d"))

        lines = []
        lines.append("========================================")
        lines.append(f"{co_name.center(40)}")
        lines.append("CASHIER DAILY SHIFT SUMMARY".center(40))
        lines.append("========================================")
        lines.append(f"Cashier:  {cashier_name} (ID: {cashier_id})")
        lines.append(f"Date:     {date_str}")
        lines.append(f"Printed:  {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
        lines.append("----------------------------------------")
        lines.append(f"Total Transactions:  {summary_data.get('total_sales_count', 0)} sales")
        lines.append(f"Total Gas Volume:    {summary_data.get('total_quantity_kg', 0):,.2f} kg")
        lines.append("----------------------------------------")
        lines.append(f"CANCELLED SALES:     {summary_data.get('cancelled_sales_count', 0)} transactions")
        lines.append(f"CANCELLED AMOUNT:    NGN {summary_data.get('cancelled_total_amount', 0):,.2f}")
        lines.append("----------------------------------------")
        breakdown = summary_data.get('payment_breakdown', {})
        lines.append(f"CASH SALES:          NGN {breakdown.get('cash', 0):,.2f}")
        lines.append(f"CARD / POS SALES:    NGN {breakdown.get('card', 0):,.2f}")
        lines.append(f"TRANSFER SALES:      NGN {breakdown.get('transfer', 0):,.2f}")
        lines.append("----------------------------------------")
        lines.append(f"TOTAL SHIFT REVENUE: NGN {summary_data.get('total_amount', 0):,.2f}")
        lines.append("========================================")
        lines.append("   END OF SHIFT REPORT - RUXXEN POS   ".center(40))
        lines.append("========================================\n\n")

        receipt_text = "\n".join(lines)
        logo_raster = self.get_logo_escpos_bytes(target_width=320)

        # Print via /dev/usb/lp*
        lp_ports = glob.glob('/dev/usb/lp*')
        if lp_ports:
            for lp_dev in lp_ports:
                try:
                    with open(lp_dev, 'wb') as f:
                        esc_init = b'\x1b\x40'
                        esc_cut = b'\x1dV\x42\x00'
                        payload = esc_init + logo_raster + receipt_text.encode('utf-8', errors='ignore') + esc_cut
                        f.write(payload)
                    print(f"[Printer] Printed Daily Shift Summary to {lp_dev}")
                    return {"success": True, "message": "Printed shift summary report successfully."}
                except Exception as e:
                    print(f"[Printer Error] Writing shift summary to {lp_dev} failed: {e}")

        # Fallback simulation
        print("\n--- SHIFT SUMMARY RECEIPT SIMULATION ---")
        print(receipt_text)
        print("----------------------------------------\n")
        return {"success": True, "message": "Shift summary generated (simulated).", "receipt_text": receipt_text}

