import tkinter as tk
from tkinter import messagebox, ttk
import requests
import json
import os

from penjualan import TransactionPage, PenjualanPage
from master_data import BarangPage, KategoriPage, PelangganPage

CONFIG_FILE = "config.json"


class LoginApp:
    def __init__(self, root):
        self.root = root
        self.root.title("Login API System")
        self.root.attributes('-fullscreen', True)
        self.root.bind("<Escape>", lambda e: self.root.attributes(
            "-fullscreen", False))
        self.root.configure(bg="#f0f2f5")  # Background abu-abu muda modern

        self.config_data = self.load_config()
        self.server_ip = self.config_data.get("server_ip", "")
        self.pagination_settings = self.config_data.get(
            "pagination_settings", {"default_items_per_page": 30})
        self.setup_styles()
        self.create_widgets()

    # ================= CONFIG =================
    def load_config(self):
        if os.path.exists(CONFIG_FILE):
            with open(CONFIG_FILE, "r") as f:
                try:
                    config = json.load(f)
                    return config
                except json.JSONDecodeError:
                    return {}  # Return empty dict if config is malformed
        return {}  # Return empty dict if file doesn't exist

    def save_config(self, ip=None, user=None, pagination_settings=None):
        config = self.config_data  # Start with current config data

        if ip is not None:
            config["server_ip"] = ip
            self.server_ip = ip
        if user is not None:
            config["user"] = user
        if pagination_settings is not None:
            config["pagination_settings"] = pagination_settings
            self.pagination_settings = pagination_settings  # Update instance variable

        with open(CONFIG_FILE, "w") as f:
            json.dump(config, f, indent=4)
        self.config_data = config  # Update instance variable

    def setup_styles(self):
        style = ttk.Style()
        style.theme_use('clam')

        # Style untuk Card Login
        style.configure("Login.TFrame", background="white", relief="flat")
        style.configure("Sidebar.TFrame", background="#2c3e50")
        style.configure("Content.TFrame", background="#f8f9fa")

        # Style Button Sidebar
        style.configure("Menu.TButton", padding=1, font=("Segoe UI", 10))
        style.map("Menu.TButton",
                  background=[('active', '#34495e'), ('!active', '#2c3e50')],
                  foreground=[('active', 'white'), ('!active', '#ecf0f1')]
                  )

    # ================= UI =================
    def create_widgets(self):
        # Container Utama untuk memusatkan form
        self.login_container = tk.Frame(self.root, bg="#f0f2f5")
        self.login_container.place(relx=0.5, rely=0.5, anchor="center")

        # Card Login
        card = tk.Frame(self.login_container, bg="white", padx=40, pady=40,
                        highlightbackground="#d1d9e6", highlightthickness=1)
        card.pack()

        tk.Label(card, text="Aplikasi Kasir Maju", bg="white",
                 font=("Segoe UI", 18, "bold"), fg="#2c3e50").pack(pady=(0, 20))

        # Input Fields
        tk.Label(card, text="Username", bg="white", font=(
            "Segoe UI", 10), fg="#7f8c8d").pack(anchor="w")
        self.entry_username = tk.Entry(card, font=("Segoe UI", 12), bd=0,
                                       highlightbackground="#bdc3c7", highlightthickness=1)
        self.entry_username.pack(fill=tk.X, pady=(5, 15), ipady=5)

        tk.Label(card, text="Password", bg="white", font=(
            "Segoe UI", 10), fg="#7f8c8d").pack(anchor="w")
        self.entry_password = tk.Entry(card, show="*", font=("Segoe UI", 12), bd=0,
                                       highlightbackground="#bdc3c7", highlightthickness=1)
        self.entry_password.pack(fill=tk.X, pady=(5, 20), ipady=5)

        # Buttons
        btn_login = tk.Button(card, text="MASUK", bg="#3498db", fg="white",
                              font=("Segoe UI", 11, "bold"), bd=0, cursor="hand2",
                              command=self.login, activebackground="#2980b9", activeforeground="white")
        btn_login.pack(fill=tk.X, ipady=8, pady=(0, 10))

        btn_setup = tk.Button(card, text="⚙ Setting Server", bg="white", fg="#95a5a6",
                              font=("Segoe UI", 9), bd=0, cursor="hand2", command=self.open_settings)
        btn_setup.pack()

        self.label_server = tk.Label(card, text=f"Status: Terhubung ke {self.server_ip}",
                                     bg="white", fg="#27ae60", font=("Segoe UI", 8))
        self.label_server.pack(pady=(15, 0))

    # ================= LOGIN =================
    def login(self):
        # (Logika login tetap sama namun dengan perbaikan visual feedback)
        if not self.server_ip:
            messagebox.showerror("Error", "Server belum disetting!")
            return

        username = self.entry_username.get().strip()
        password = self.entry_password.get().strip()

        if not username or not password:
            messagebox.showerror("Error", "Username dan password wajib diisi!")
            return

        # Format URL otomatis
        base_url = self.server_ip.rstrip("/")
        if not base_url.startswith("http"):
            base_url = f"http://{base_url}"

        try:
            response = requests.post(
                f"{base_url}:1987/maju/api/login.php",
                json={
                    "username": username,
                    "password": password
                },
                timeout=5
            )

            data = response.json()

            if data["status"] == "success":
                user = data["data"]
                self.save_config(user=user)  # Simpan data user ke config.json
                messagebox.showinfo("Sukses", data["message"])
                self.open_dashboard(user)
            else:
                messagebox.showerror("Login Gagal", data["message"])

        except requests.exceptions.ConnectionError:
            messagebox.showerror("Error", "Tidak bisa terhubung ke server!")
        except Exception as e:
            messagebox.showerror("Error", f"Terjadi kesalahan:\n{e}")

    # ================= DASHBOARD =================
    def open_dashboard(self, user):
        self.root.withdraw()  # Sembunyikan jendela login
        main_window = tk.Toplevel(self.root)
        main_window.title(f"MAJU POS v1.0 - {user['username']}")
        main_window.attributes('-fullscreen', True)
        main_window.configure(bg="#f8f9fa")
        main_window.bind(
            "<Escape>", lambda e: main_window.attributes("-fullscreen", False))
        main_window.protocol("WM_DELETE_WINDOW", self.root.destroy)

        # --- HEADER / TOP BAR ---
        header = ttk.Frame(main_window, style="Sidebar.TFrame")
        header.pack(side=tk.TOP, fill=tk.X)

        tk.Label(header, text="MAJUMAKMUR APP", bg="#2c3e50", fg="white",
                 font=("Segoe UI", 14, "bold"), padx=5, pady=2).pack(side=tk.LEFT)

        tk.Label(header, text=f"User: {user['username']} ({user['role']})", bg="#2c3e50", fg="#ecf0f1",
                 font=("Segoe UI", 10)).pack(side=tk.LEFT, padx=5, pady=0)

        btn_logout = ttk.Button(header, text="Logout", style="Menu.TButton",
                                command=lambda: self.logout(main_window), padding=[5, 1])
        btn_logout.pack(side=tk.RIGHT, padx=2, pady=1)

        btn_pagination_settings = ttk.Button(header, text="⚙ Setting Paginasi", style="Menu.TButton",
                                             command=self.open_pagination_settings, padding=[5, 1])
        btn_pagination_settings.pack(side=tk.RIGHT, padx=2, pady=1)

        # --- MENU TABS (NOTEBOOK) ---
        notebook = ttk.Notebook(main_window)
        notebook.pack(fill=tk.BOTH, expand=True, padx=1, pady=1)

        # 1. Halaman Transaksi
        self.transaction_page = TransactionPage(notebook, user)
        notebook.add(self.transaction_page, text="  Transaksi Baru  ")

        # Callback untuk Edit dari Riwayat
        def on_edit_transaction(penjualan_id):
            self.transaction_page.load_transaction(penjualan_id)
            notebook.select(0)  # Pindah ke tab Transaksi (index 0)

        # 2. Halaman Riwayat
        self.history_page = PenjualanPage(
            notebook, user, open_transaction_callback=on_edit_transaction)
        notebook.add(self.history_page, text="  Riwayat Penjualan  ")

        # 3. Master Data
        self.barang_page = BarangPage(notebook, user)
        notebook.add(self.barang_page, text="  Data Barang  ")

        self.kategori_page = KategoriPage(notebook, user)
        notebook.add(self.kategori_page, text="  Kategori  ")

        self.pelanggan_page = PelangganPage(notebook, user)
        notebook.add(self.pelanggan_page, text="  Pelanggan  ")

        # Event refresh data saat tab berpindah
        notebook.bind("<<NotebookTabChanged>>", self.on_tab_change)

    def logout(self, window):
        if messagebox.askyesno("Konfirmasi", "Apakah Anda yakin ingin keluar?"):
            window.destroy()
            self.root.deiconify()
            self.entry_password.delete(0, tk.END)

    def on_tab_change(self, event):
        notebook = event.widget
        tab_index = notebook.index("current")

        if tab_index == 1:
            self.history_page.fetch_data()
        elif tab_index == 2:
            self.barang_page.load_data()
        elif tab_index == 3:
            self.kategori_page.load_data()
        elif tab_index == 4:
            self.pelanggan_page.load_data()

    # ================= SETTINGS SERVER =================
    def open_settings(self):
        settings = tk.Toplevel(self.root)
        settings.title("Setting Server")
        settings.geometry("400x150")
        settings.resizable(False, False)

        tk.Label(settings, text="Masukkan IP Server (misal: 127.0.0.1):").pack(
            pady=5)

        entry_ip = tk.Entry(settings, width=50)
        entry_ip.pack(pady=5)
        entry_ip.insert(0, self.server_ip)

        def save():
            ip = entry_ip.get().strip()
            if not ip:
                messagebox.showerror("Error", "IP tidak boleh kosong!")
                return
            self.save_config(ip)
            self.label_server.config(text=f"Server: {self.server_ip}")
            messagebox.showinfo("Sukses", "Server berhasil disimpan!")
            settings.destroy()

        tk.Button(settings, text="Simpan", command=save).pack(pady=10)

    # ================= SETTING APLIKASI =================
    def open_pagination_settings(self):
        settings = tk.Toplevel(self.root)
        settings.title("Setting Aplikasi")
        settings.geometry("350x150")
        settings.resizable(False, False)

        tk.Label(settings, text="Jumlah Item per Halaman (Default):").pack(pady=5)

        current_items_per_page = self.pagination_settings.get(
            "default_items_per_page", 30)
        entry_items_per_page = tk.Entry(settings, width=10)
        entry_items_per_page.pack(pady=5)
        entry_items_per_page.insert(0, str(current_items_per_page))

        def save():
            try:
                new_value = int(entry_items_per_page.get().strip())
                if new_value <= 0:
                    messagebox.showerror(
                        "Error", "Jumlah item harus lebih dari 0!")
                    return
                new_pagination_settings = {"default_items_per_page": new_value}
                self.save_config(pagination_settings=new_pagination_settings)
                messagebox.showinfo(
                    "Sukses", "Pengaturan paginasi berhasil disimpan!")
                settings.destroy()
            except ValueError:
                messagebox.showerror("Error", "Masukkan angka yang valid!")

        tk.Button(settings, text="Simpan", command=save, bg="#2ecc71", fg="white", 
                  font=("Segoe UI", 9, "bold"), width=10).pack(pady=10)


if __name__ == "__main__":
    root = tk.Tk()
    app = LoginApp(root)
    root.mainloop()
