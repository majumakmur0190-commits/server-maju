import tkinter as tk
from tkinter import ttk, messagebox
import requests
import json
import os
import webbrowser
from decimal import Decimal, getcontext

# Atur presisi untuk kalkulasi desimal
getcontext().prec = 12

CONFIG_FILE = "config.json"

class PenjualanPage(ttk.Frame):
    """
    Kelas untuk Frame yang berisi UI Halaman Penjualan (Riwayat Penjualan).
    Fungsi ini setara dengan penjualan.html.
    """
    def __init__(self, parent, user, open_transaction_callback=None):
        super().__init__(parent)
        self.user = user
        self.open_transaction_callback = open_transaction_callback

        # Memuat konfigurasi server
        config_data = self._load_config()
        self.server_ip = config_data.get("server_ip", "localhost")
        self.base_url = self.server_ip.rstrip("/")
        if not self.base_url.startswith("http"):
            self.base_url = f"http://{self.base_url}"

        self.api_url = f"{self.base_url}:1987/maju/api/transaksi.php"
        
        # Pagination settings
        pagination_config = config_data.get("pagination_settings", {})
        self.items_per_page = pagination_config.get("default_items_per_page", 30)

        # State Management
        self.all_penjualan = []
        self.filtered_penjualan = []
        self.current_page = 1
        self.setup_ui()
        self.fetch_data()

    def _load_config(self):
        if os.path.exists(CONFIG_FILE):
            with open(CONFIG_FILE, "r") as f:
                try:
                    config = json.load(f)
                    return config
                except json.JSONDecodeError:
                    return {}
        return {}

    def setup_ui(self):
        # --- Bagian Header & Pencarian ---
        header = ttk.Frame(self)
        header.pack(fill=tk.X, padx=2, pady=2)

        ttk.Label(header, text="Riwayat Penjualan", font=("Arial", 20, "bold")).pack(side=tk.LEFT)
        
        # Input Pencarian
        self.search_var = tk.StringVar()
        self.search_var.trace_add("write", lambda *a: self.apply_filter(page=1))
        ttk.Entry(header, textvariable=self.search_var, width=30).pack(side=tk.RIGHT, padx=5)
        ttk.Label(header, text="Cari (ID/User/Pelanggan):").pack(side=tk.RIGHT)

        # --- Bagian Tabel (Treeview) ---
        container = ttk.Frame(self)
        container.pack(fill=tk.BOTH, expand=True, padx=2, pady=2)

        cols = ("id", "tgl", "user", "cust", "total")
        self.tree = ttk.Treeview(container, columns=cols, show="headings", selectmode="browse")
        
        headers = {"id": "ID", "tgl": "Tanggal Transaksi", "user": "User/Kasir", "cust": "Pelanggan", "total": "Total (Rp)"}
        widths = {"id": 60, "tgl": 180, "user": 120, "cust": 200, "total": 120}

        for col, txt in headers.items():
            self.tree.heading(col, text=txt)
            self.tree.column(col, width=widths[col], anchor=tk.CENTER if col=="id" else (tk.E if col=="total" else tk.W))
        
        self.tree.pack(side=tk.LEFT, fill=tk.BOTH, expand=True)
        
        sb = ttk.Scrollbar(container, orient=tk.VERTICAL, command=self.tree.yview)
        sb.pack(side=tk.RIGHT, fill=tk.Y)
        self.tree.configure(yscrollcommand=sb.set)

        # --- Bagian Footer (Tombol Aksi & Paginasi) ---
        footer = ttk.Frame(self)
        footer.pack(fill=tk.X, padx=2, pady=2)

        # Tombol Aksi
        ttk.Button(footer, text="Refresh", command=self.fetch_data).pack(side=tk.LEFT, padx=1)
        ttk.Button(footer, text="Edit / Detail", command=self.handle_edit).pack(side=tk.LEFT, padx=1)
        ttk.Button(footer, text="Hapus Transaksi", command=self.handle_delete).pack(side=tk.LEFT, padx=1)

        # Kontrol Paginasi
        self.btn_next = ttk.Button(footer, text="Berikutnya", command=lambda: self.apply_filter(self.current_page + 1))
        self.btn_next.pack(side=tk.RIGHT, padx=1)
        
        self.page_info = ttk.Label(footer, text="Halaman 1")
        self.page_info.pack(side=tk.RIGHT, padx=10)

        self.btn_prev = ttk.Button(footer, text="Sebelumnya", command=lambda: self.apply_filter(self.current_page - 1))
        self.btn_prev.pack(side=tk.RIGHT, padx=5)

    def fetch_data(self):
        try:
            params = {"role": self.user.get('role'), "user_id": self.user.get('id')}
            res = requests.get(self.api_url, params=params, timeout=5)
            res.raise_for_status()
            ## print(f"API Response Status Code: {res.status_code}") # Debugging
            data = res.json()
            ## print(f"API Response JSON: {data}") # Debugging

            if data.get("status") == "success":
                self.all_penjualan = data.get("data", [])
                ## print(f"Jumlah data penjualan dari API: {len(self.all_penjualan)}") # Debugging
                self.apply_filter()
            else:
                messagebox.showerror("Error", data.get("message", "Gagal mengambil data"))
        except Exception as e:
            messagebox.showerror("Error", f"Koneksi gagal: {e}")

    def apply_filter(self, page=None, data=None):
        if page: self.current_page = int(page)
        if data is not None: self.all_penjualan = data

        query = self.search_var.get().lower()
        self.filtered_penjualan = [
            p for p in self.all_penjualan 
            if query in str(p.get("penjualan_id")).lower() or 
               query in str(p.get("user_nama")).lower() or 
               query in (str(p.get("nama_pelanggan") or "umum")).lower()
        ]
        
        total = len(self.filtered_penjualan)
        pages = max(1, (total + self.items_per_page - 1) // self.items_per_page)
        self.current_page = max(1, min(self.current_page, pages))
        
        idx = (self.current_page - 1) * self.items_per_page
        page_data = self.filtered_penjualan[idx : idx + self.items_per_page]
        ##print(f"Jumlah data setelah filter dan paginasi (halaman {self.current_page}): {len(page_data)}") # Debugging
        
        self.tree.delete(*self.tree.get_children())
        for p in page_data:
            self.tree.insert("", tk.END, iid=p["penjualan_id"], values=(
                p["penjualan_id"], p["tanggal"], p["user_nama"], p.get("nama_pelanggan") or "Umum", 
                f"Rp {int(Decimal(p['total'])):,}".replace(",", ".") # Menggunakan Decimal untuk robust
            ))
        
        self.page_info.config(text=f"Halaman {self.current_page} / {pages} ({total} data)")
        self.btn_prev.config(state=tk.NORMAL if self.current_page > 1 else tk.DISABLED)
        self.btn_next.config(state=tk.NORMAL if self.current_page < pages else tk.DISABLED)

    def handle_edit(self):
        sel = self.tree.selection()
        if sel and self.open_transaction_callback:
            self.open_transaction_callback(sel[0])
        elif not sel:
            messagebox.showwarning("Peringatan", "Pilih baris data terlebih dahulu.")

    def handle_delete(self):
        sel = self.tree.selection()
        if not sel:
            messagebox.showwarning("Peringatan", "Pilih data yang ingin dihapus.")
            return
            
        penjualan_id = sel[0]
        if messagebox.askyesno("Konfirmasi", "Yakin ingin menghapus transaksi ini? Stok akan dikembalikan."):
            try:
                res = requests.delete(f"{self.api_url}?id={penjualan_id}", timeout=5)
                result = res.json()
                messagebox.showinfo("Berhasil", result.get("message", "Data terhapus"))
                self.fetch_data()
            except Exception as e:
                messagebox.showerror("Error", f"Gagal menghapus data: {e}")

class TransactionPage(ttk.Frame):
    def __init__(self, parent, user):
        super().__init__(parent)
        self.user = user
        self.current_penjualan_id = None

        config_data = self._load_config()
        # Pagination settings for products
        self.product_items_per_page = config_data.get("pagination_settings", {}).get("default_items_per_page", 30)
        self.product_current_page = 1
        self.all_products = [] # To store all products fetched from API
        self.filtered_products = [] # To store products after search filter
        self.server_ip = config_data.get("server_ip", "localhost")
        self.base_url = self.server_ip.rstrip("/")
        if not self.base_url.startswith("http"):
            self.base_url = f"http://{self.base_url}"

        self.api_urls = {
            "penjualan": f"{self.base_url}:1987/maju/api/transaksi.php",
            "pelanggan": f"{self.base_url}:1987/maju/api/pelanggan.php",
            "barang": f"{self.base_url}:1987/maju/api/barang.php",
            "histori": f"{self.base_url}:1987/maju/api/histori.php"
        }
        self.selected_customer = {'id': None, 'nama': 'Umum'}
        self.cart_items = {} # {barang_id: {data}}
        
        self.create_widgets()
        self.load_initial_products()

    def _load_config(self):
        if os.path.exists(CONFIG_FILE):
            with open(CONFIG_FILE, "r") as f:
                try:
                    config = json.load(f)
                    return config
                except json.JSONDecodeError:
                    return {}
        return {}

    def create_widgets(self):
        paned_window = ttk.PanedWindow(self, orient=tk.HORIZONTAL)
        paned_window.pack(fill=tk.BOTH, expand=True, padx=1, pady=1)

        left_pane = ttk.Frame(paned_window)
        paned_window.add(left_pane, weight=6)

        right_pane = ttk.Frame(paned_window)
        paned_window.add(right_pane, weight=4)

        # --- Widgets untuk Panel Kiri ---
        customer_frame = ttk.LabelFrame(left_pane, text="Informasi Pelanggan")
        customer_frame.pack(fill=tk.X, padx=1, pady=1)

        self.customer_display_var = tk.StringVar(value="Umum")
        tk.Label(customer_frame, text="Pelanggan:").pack(side=tk.LEFT, padx=1, pady=1)
        tk.Entry(customer_frame, textvariable=self.customer_display_var, state="readonly", width=40).pack(side=tk.LEFT, padx=1, pady=1, expand=True, fill=tk.X)
        self.btn_pilih_pelanggan = ttk.Button(customer_frame, text="Pilih Pelanggan", command=self.open_customer_modal)
        self.btn_pilih_pelanggan.pack(side=tk.LEFT, padx=1, pady=1)

        cart_frame = ttk.LabelFrame(left_pane, text="Keranjang Belanja")
        cart_frame.pack(fill=tk.BOTH, expand=True, padx=1, pady=1)

        cols = ('#', 'Nama Barang', 'Jumlah', 'Harga Satuan', 'Subtotal')
        self.cart_tree = ttk.Treeview(cart_frame, columns=cols, show='headings', height=15)
        for col in cols:
            self.cart_tree.heading(col, text=col)
        self.cart_tree.column('#', width=40, anchor=tk.CENTER)
        self.cart_tree.column('Nama Barang', width=250)
        self.cart_tree.column('Jumlah', width=80, anchor=tk.E)
        self.cart_tree.column('Harga Satuan', width=120, anchor=tk.E)
        self.cart_tree.column('Subtotal', width=120, anchor=tk.E)
        self.cart_tree.pack(side=tk.LEFT, fill=tk.BOTH, expand=True)

        cart_scrollbar = ttk.Scrollbar(cart_frame, orient=tk.VERTICAL, command=self.cart_tree.yview)
        cart_scrollbar.pack(side=tk.RIGHT, fill=tk.Y)
        self.cart_tree.configure(yscrollcommand=cart_scrollbar.set)
        
        self.cart_tree.bind('<Double-1>', self.edit_cart_item)
        
        total_frame = ttk.Frame(left_pane)
        total_frame.pack(fill=tk.X, padx=1, pady=1)

        self.grand_total_var = tk.StringVar(value="Rp 0")
        tk.Label(total_frame, text="Grand Total:", font=("Arial", 14, "bold")).pack(side=tk.LEFT, padx=2)
        tk.Label(total_frame, textvariable=self.grand_total_var, font=("Arial", 14, "bold"), fg="blue").pack(side=tk.LEFT, padx=2)

        self.btn_save = ttk.Button(total_frame, text="Simpan Transaksi", command=self.save_transaction)
        self.btn_save.pack(side=tk.RIGHT, padx=1)
        self.btn_print = ttk.Button(total_frame, text="Cetak Invoice", command=self.print_invoice, state=tk.DISABLED)
        self.btn_print.pack(side=tk.RIGHT, padx=1)
        ttk.Button(total_frame, text="Reset / Baru", command=self.reset_form).pack(side=tk.RIGHT, padx=1)
        ttk.Button(total_frame, text="Hapus Baris", command=self.remove_cart_item).pack(side=tk.RIGHT, padx=1)

        # --- Widgets untuk Panel Kanan ---
        product_search_frame = ttk.LabelFrame(right_pane, text="Pencarian Produk")
        product_search_frame.pack(fill=tk.X, padx=1, pady=1)

        self.product_search_var = tk.StringVar()
        self.product_search_var.trace_add("write", self.on_product_search)
        tk.Entry(product_search_frame, textvariable=self.product_search_var, width=40).pack(fill=tk.X, expand=True, padx=1, pady=1)

        product_list_frame = ttk.LabelFrame(right_pane, text="Daftar Produk")
        product_list_frame.pack(fill=tk.BOTH, expand=True, padx=1, pady=1)

        prod_cols = ('ID', 'Barcode', 'Nama Barang', 'Harga', 'Stok')
        self.product_tree = ttk.Treeview(product_list_frame, columns=prod_cols, show='headings', height=20)
        self.product_tree.heading('ID', text='ID')
        self.product_tree.heading('Barcode', text='Barcode')
        self.product_tree.heading('Nama Barang', text='Nama Barang')
        self.product_tree.heading('Harga', text='Harga')
        self.product_tree.heading('Stok', text='Stok')
        self.product_tree.column('ID', width=0, stretch=tk.NO) # Sembunyikan ID
        self.product_tree.column('Barcode', width=100)
        self.product_tree.column('Nama Barang', width=200)
        self.product_tree.column('Harga', width=100, anchor=tk.E)
        self.product_tree.column('Stok', width=50, anchor=tk.CENTER)
        self.product_tree.pack(side=tk.LEFT, fill=tk.BOTH, expand=True)

        prod_scrollbar = ttk.Scrollbar(product_list_frame, orient=tk.VERTICAL, command=self.product_tree.yview)
        prod_scrollbar.pack(side=tk.RIGHT, fill=tk.Y)
        self.product_tree.configure(yscrollcommand=prod_scrollbar.set)

        # Product Pagination Controls
        product_footer = ttk.Frame(right_pane)
        product_footer.pack(fill=tk.X, padx=1, pady=1)

        self.btn_prev_product = ttk.Button(product_footer, text="Sebelumnya", command=lambda: self.change_product_page(-1))
        self.btn_prev_product.pack(side=tk.LEFT, padx=5)

        self.product_page_info = ttk.Label(product_footer, text="Halaman 1")
        self.product_page_info.pack(side=tk.LEFT, padx=10)

        self.btn_next_product = ttk.Button(product_footer, text="Berikutnya", command=lambda: self.change_product_page(1))
        self.btn_next_product.pack(side=tk.LEFT, padx=5)
        
        self.product_tree.bind('<Double-1>', self.add_product_to_cart_event)

    def format_rupiah(self, number):
        return f"Rp {number:,.0f}".replace(",", ".")

    def load_initial_products(self):
        self._fetch_all_products_from_api()

    def _fetch_all_products_from_api(self):
        """Fetches all products from the API and stores them."""
        try:
            url = self.api_urls['barang']
            response = requests.get(url, timeout=5)
            response.raise_for_status()
            result = response.json()

            if result.get('status') == 'success':
                self.all_products = result.get('data', [])
                self.apply_product_filter() # Apply filter and pagination after fetching all
            else:
                messagebox.showerror("Error", result.get("message", "Gagal mengambil data produk"), parent=self)
        except requests.exceptions.RequestException as e:
            messagebox.showerror("Error Jaringan", f"Gagal mengambil data produk: {e}", parent=self)

    def on_product_search(self, *args):
        keyword = self.product_search_var.get()
        if hasattr(self, '_search_job'):
            self.after_cancel(self._search_job)
        # Delay search to avoid too many updates while typing
        self._search_job = self.after(300, lambda: self.apply_product_filter(reset_page=True))

    def apply_product_filter(self, reset_page=False):
        """Filters products based on search keyword and applies pagination."""
        if reset_page:
            self.product_current_page = 1

        keyword = self.product_search_var.get().lower()
        self.filtered_products = [
            product for product in self.all_products
            if keyword in str(product.get('barcode', '')).lower() or
               keyword in str(product.get('nama_barang', '')).lower()
        ]

        total_products = len(self.filtered_products)
        total_pages = max(1, (total_products + self.product_items_per_page - 1) // self.product_items_per_page)
        self.product_current_page = max(1, min(self.product_current_page, total_pages))

        start_idx = (self.product_current_page - 1) * self.product_items_per_page
        end_idx = start_idx + self.product_items_per_page
        paginated_products = self.filtered_products[start_idx:end_idx]

        self.product_tree.delete(*self.product_tree.get_children())
        for product in paginated_products:
            # Ensure product data is consistent with what was expected by the original search_products
                    harga_jual = Decimal(product.get('harga_hna', 0))
                    self.product_tree.insert('', tk.END, iid=product['barang_id'], values=(
                        product['barang_id'],
                        product['barcode'],
                        product['nama_barang'],
                        self.format_rupiah(harga_jual),
                        product['stok']
                    ))
        
        self.product_page_info.config(text=f"Halaman {self.product_current_page} / {total_pages} ({total_products} data)")
        self.btn_prev_product.config(state=tk.NORMAL if self.product_current_page > 1 else tk.DISABLED)
        self.btn_next_product.config(state=tk.NORMAL if self.product_current_page < total_pages else tk.DISABLED)

    def change_product_page(self, delta):
        self.product_current_page += delta
        self.apply_product_filter()

    def add_product_to_cart_event(self, event):
        if not self.product_tree.selection(): return
        if self.selected_customer['id'] is None and self.selected_customer['nama'] != 'Umum':
             messagebox.showwarning("Peringatan", "Silakan pilih pelanggan terlebih dahulu.", parent=self)
             return
        self.add_product_to_cart(self.product_tree.selection()[0])

    def add_product_to_cart(self, barang_id):
        barang_id = int(barang_id)
        self.btn_pilih_pelanggan.config(state=tk.DISABLED)

        if barang_id in self.cart_items:
            self.cart_items[barang_id]['jumlah'] += 1
            if self.cart_items[barang_id]['jumlah'] > self.cart_items[barang_id]['stok']:
                self.cart_items[barang_id]['jumlah'] = self.cart_items[barang_id]['stok']
                messagebox.showinfo("Info Stok", f"Stok {self.cart_items[barang_id]['nama_barang']} tidak mencukupi.", parent=self)
        else:
            try:
                res = requests.get(f"{self.api_urls['barang']}?id={barang_id}", timeout=5)
                res.raise_for_status()
                result = res.json()
                if result.get('status') != 'success' or not result.get('data'):
                    messagebox.showerror("Error", f"Produk dengan ID {barang_id} tidak ditemukan.", parent=self)
                    return
                
                barang = result['data']
                harga_final = Decimal(barang.get('harga_hna', 0))

                if self.selected_customer['id']:
                    histori_res = requests.get(f"{self.api_urls['histori']}?aksi=getHarga&id_pelanggan={self.selected_customer['id']}&id_barang={barang_id}", timeout=5)
                    histori_data = histori_res.json()
                    if histori_data.get('status') == 'ada':
                        harga_final = Decimal(histori_data.get('harga', harga_final))

                if int(barang['stok']) <= 0:
                    messagebox.showinfo("Info Stok", f"Stok {barang['nama_barang']} habis.", parent=self)
                    return

                self.cart_items[barang_id] = {
                    'nama_barang': barang['nama_barang'], 'jumlah': 1,
                    'harga_satuan': harga_final, 'stok': int(barang['stok'])
                }
            except requests.exceptions.RequestException as e:
                messagebox.showerror("Error Jaringan", f"Gagal mengambil detail produk: {e}", parent=self)
                return
        self.update_cart_treeview()

    def update_cart_treeview(self):
        self.cart_tree.delete(*self.cart_tree.get_children())
        for urut, (barang_id, item) in enumerate(self.cart_items.items(), 1):
            subtotal = item['jumlah'] * item['harga_satuan']
            self.cart_tree.insert('', tk.END, iid=barang_id, values=(
                urut, item['nama_barang'], item['jumlah'],
                self.format_rupiah(item['harga_satuan']), self.format_rupiah(subtotal)
            ))
        self.update_grand_total()

    def update_grand_total(self):
        total = sum(item['jumlah'] * item['harga_satuan'] for item in self.cart_items.values())
        self.grand_total_var.set(self.format_rupiah(total))

    def remove_cart_item(self):
        if not self.cart_tree.selection():
            messagebox.showwarning("Peringatan", "Pilih item di keranjang yang akan dihapus.", parent=self)
            return
        del self.cart_items[int(self.cart_tree.selection()[0])]
        if not self.cart_items: self.btn_pilih_pelanggan.config(state=tk.NORMAL)
        self.update_cart_treeview()

    def save_transaction(self):
        if not self.cart_items:
            messagebox.showerror("Error", "Keranjang tidak boleh kosong.", parent=self)
            return

        items_to_send = [{
            "detail_urut": urut, "barang_id": barang_id, "jumlah": d['jumlah'],
            "harga_satuan": float(d['harga_satuan']), "subtotal": float(d['jumlah'] * d['harga_satuan'])
        } for urut, (barang_id, d) in enumerate(self.cart_items.items(), 1)]

        data_to_send = {"user_id": self.user['id'], "pelanggan_id": self.selected_customer['id'], "items": items_to_send}

        try:
            url = f"{self.api_urls['penjualan']}{f'?id={self.current_penjualan_id}' if self.current_penjualan_id else ''}"
            method = requests.put if self.current_penjualan_id else requests.post
            
            response = method(url, json=data_to_send, timeout=10)
            response.raise_for_status()
            result = response.json()

            if result.get('status') == 'success':
                self.current_penjualan_id = result.get('penjualan_id') or self.current_penjualan_id
                self.btn_print.config(state=tk.NORMAL)
                
                if self.selected_customer['id']:
                    requests.post(f"{self.api_urls['histori']}?aksi=saveHistori", json={"pelanggan_id": self.selected_customer['id'], "items": items_to_send}, timeout=5)
                
                if messagebox.askyesno("Sukses", "Transaksi disimpan! Cetak invoice?", parent=self):
                    self.print_invoice()
            else:
                messagebox.showerror("Gagal", f"Gagal menyimpan: {result.get('message', 'Error')}", parent=self)
        except requests.exceptions.RequestException as e:
            messagebox.showerror("Error Jaringan", f"Gagal menyimpan transaksi: {e}", parent=self)

    def load_transaction(self, penjualan_id):
        try:
            self.reset_form()
            self.current_penjualan_id = penjualan_id
            res = requests.get(f"{self.api_urls['penjualan']}?id={penjualan_id}", timeout=5)
            data = res.json()
            if data.get('status') == 'success':
                p = data['data']
                self.set_customer(p.get('pelanggan_id'), p.get('nama_pelanggan') or 'Umum')
                for item in p.get('details', []):
                    self.cart_items[int(item['barang_id'])] = {
                        'nama_barang': item['nama_barang'], 'jumlah': int(item['jumlah']),
                        'harga_satuan': Decimal(str(item['harga_satuan'])), 'stok': 9999 # Dummy stok saat edit
                    }
                self.update_cart_treeview()
                self.btn_print.config(state=tk.NORMAL)
        except Exception as e:
            messagebox.showerror("Error", f"Gagal memuat transaksi: {e}")

    def print_invoice(self):
        if self.current_penjualan_id:
            url = f"{self.base_url}:1987/maju/api/print.php?id={self.current_penjualan_id}"
            webbrowser.open(url)

    def reset_form(self):
        self.current_penjualan_id = None
        self.cart_items = {}
        self.selected_customer = {'id': None, 'nama': 'Umum'}
        self.customer_display_var.set("Umum")
        self.btn_pilih_pelanggan.config(state=tk.NORMAL)
        self.btn_print.config(state=tk.DISABLED)
        self.update_cart_treeview()
        self.load_initial_products()

    def open_customer_modal(self):
        CustomerSelectionWindow(self.winfo_toplevel(), self)

    def set_customer(self, customer_id, customer_name):
        self.selected_customer['id'] = customer_id
        self.selected_customer['nama'] = customer_name
        self.customer_display_var.set(f"{customer_name} (ID: {customer_id})" if customer_id else "Umum")

    def edit_cart_item(self, event):
        if not self.cart_tree.selection(): return
        item_id = int(self.cart_tree.selection()[0])
        # Menggunakan winfo_toplevel() untuk mendapatkan root window secara dinamis
        EditItemWindow(self.winfo_toplevel(), self, item_id, self.cart_items[item_id])

class CustomerSelectionWindow(tk.Toplevel):
    def __init__(self, parent, main_app):
        super().__init__(parent)
        self.main_app = main_app
        self.title("Pilih Pelanggan"); self.geometry("500x400"); self.transient(parent); self.grab_set()
        
        search_frame = ttk.Frame(self); search_frame.pack(fill=tk.X, padx=10, pady=5)
        tk.Label(search_frame, text="Cari:").pack(side=tk.LEFT)
        self.search_var = tk.StringVar(); self.search_var.trace_add("write", self.on_search)
        tk.Entry(search_frame, textvariable=self.search_var).pack(side=tk.LEFT, fill=tk.X, expand=True, padx=5)

        cols = ('ID', 'Nama Pelanggan', 'Alamat')
        self.tree = ttk.Treeview(self, columns=cols, show='headings')
        for col in cols: self.tree.heading(col, text=col)
        self.tree.column('ID', width=50); self.tree.column('Nama Pelanggan', width=200); self.tree.column('Alamat', width=250)
        self.tree.pack(fill=tk.BOTH, expand=True, padx=10, pady=5)
        self.tree.bind("<Double-1>", self.select_customer)

        btn_frame = ttk.Frame(self); btn_frame.pack(fill=tk.X, padx=10, pady=10)
        ttk.Button(btn_frame, text="Pilih", command=self.select_customer).pack(side=tk.RIGHT)
        ttk.Button(btn_frame, text="Pilih Umum", command=self.select_umum).pack(side=tk.RIGHT, padx=10)
        self.load_customers()

    def load_customers(self, keyword=""):
        try:
            url = f"{self.main_app.api_urls['pelanggan']}{f'?search={keyword}' if keyword else ''}"
            result = requests.get(url, timeout=5).json()
            self.tree.delete(*self.tree.get_children())
            if result.get('status') == 'success':
                for cust in result.get('data', []):
                    self.tree.insert('', tk.END, values=(cust['pelanggan_id'], cust['nama_pelanggan'], cust.get('alamat', '')))
        except requests.exceptions.RequestException as e:
            messagebox.showerror("Error Jaringan", f"Gagal mengambil data pelanggan: {e}", parent=self)

    def on_search(self, *args):
        if hasattr(self, '_search_job'): self.after_cancel(self._search_job)
        self._search_job = self.after(300, lambda: self.load_customers(self.search_var.get()))

    def select_customer(self, event=None):
        if not self.tree.selection():
            messagebox.showwarning("Peringatan", "Pilih salah satu pelanggan.", parent=self)
            return
        item = self.tree.item(self.tree.selection()[0])
        self.main_app.set_customer(item['values'][0], item['values'][1])
        self.destroy()

    def select_umum(self):
        self.main_app.set_customer(None, "Umum")
        self.destroy()

class EditItemWindow(tk.Toplevel):
    def __init__(self, parent, main_app, item_id, item_data):
        super().__init__(parent)
        self.main_app, self.item_id, self.item_data = main_app, item_id, item_data
        self.title(f"Edit: {item_data['nama_barang']}"); self.geometry("350x200"); self.transient(parent); self.grab_set()
        self.resizable(False, False)

        tk.Label(self, text=f"Stok Tersedia: {item_data['stok']}", font=("Arial", 10)).pack(pady=5)
        
        frame_qty = ttk.Frame(self); frame_qty.pack(pady=5, padx=20, fill=tk.X)
        tk.Label(frame_qty, text="Jumlah:", width=12, anchor='w').pack(side=tk.LEFT)
        self.qty_var = tk.StringVar(value=str(item_data['jumlah']))
        qty_entry = tk.Entry(frame_qty, textvariable=self.qty_var)
        qty_entry.pack(side=tk.LEFT, fill=tk.X, expand=True)
        qty_entry.focus_set() # Langsung fokus ke input jumlah
        qty_entry.selection_range(0, tk.END) # Pilih semua teks agar mudah ditimpa

        frame_price = ttk.Frame(self); frame_price.pack(pady=5, padx=20, fill=tk.X)
        tk.Label(frame_price, text="Harga Satuan:", width=12, anchor='w').pack(side=tk.LEFT)
        self.price_var = tk.StringVar(value=str(item_data['harga_satuan']))
        price_entry = tk.Entry(frame_price, textvariable=self.price_var)
        price_entry.pack(side=tk.LEFT, fill=tk.X, expand=True)

        # Bind tombol Enter untuk simpan cepat
        self.bind('<Return>', lambda e: self.save_changes())

        btn_frame = ttk.Frame(self); btn_frame.pack(pady=15)
        ttk.Button(btn_frame, text="Simpan", command=self.save_changes).pack(side=tk.LEFT, padx=10)
        ttk.Button(btn_frame, text="Batal", command=self.destroy).pack(side=tk.LEFT, padx=10)

    def save_changes(self):
        try:
            new_qty, new_price = int(self.qty_var.get()), Decimal(self.price_var.get())
            if new_qty <= 0: raise ValueError("Jumlah harus positif")
            if new_qty > self.item_data['stok']: raise ValueError(f"Jumlah melebihi stok ({self.item_data['stok']})")
            
            self.main_app.cart_items[self.item_id]['jumlah'] = new_qty
            self.main_app.cart_items[self.item_id]['harga_satuan'] = new_price
            self.main_app.update_cart_treeview()
            self.destroy()
        except Exception as e:
            messagebox.showerror("Error Input", str(e), parent=self)

if __name__ == '__main__':
    root = tk.Tk()
    root.attributes('-fullscreen', True)
    root.bind("<Escape>", lambda e: root.attributes("-fullscreen", False))
    root.mainloop()
