import tkinter as tk
from tkinter import ttk, messagebox
import requests
import json
import os

CONFIG_FILE = "config.json"


class MasterBasePage(ttk.Frame):
    """Kelas dasar untuk halaman Master Data."""

    def __init__(self, parent, user, api_name, title, columns, headers, widths, id_key):
        super().__init__(parent)
        self.user = user
        self.api_name = api_name
        self.id_key = id_key
        self.columns = columns

        # Load Config
        config_data = self._load_config()
        self.server_ip = config_data.get("server_ip", "localhost")
        self.base_url = f"http://{self.server_ip.rstrip('/')}:1987/maju/api/{api_name}"

        # Pagination settings
        pagination_config = config_data.get("pagination_settings", {})
        self.items_per_page = pagination_config.get(
            "default_items_per_page", 30)

        self.current_page = 1

        # UI Layout
        header = ttk.Frame(self)
        header.pack(fill=tk.X, padx=2, pady=2)
        ttk.Label(header, text=title, font=(
            "Arial", 18, "bold")).pack(side=tk.LEFT)

        # Pindahkan inisialisasi UI dan pemuatan data ke dalam __init__
        self.search_var = tk.StringVar()
        self.search_var.trace_add(
            "write", lambda *a: self.refresh_view(reset_page=True))
        ttk.Entry(header, textvariable=self.search_var,
                  width=30).pack(side=tk.RIGHT, padx=5)
        ttk.Label(header, text="Cari:").pack(side=tk.RIGHT)

        # Tambahkan Scrollbar untuk Treeview
        tree_frame = ttk.Frame(self)
        tree_frame.pack(fill=tk.BOTH, expand=True, padx=2, pady=2)

        self.tree = ttk.Treeview(
            tree_frame, columns=columns, show='headings', selectmode='browse')
        for col in columns:
            self.tree.heading(col, text=headers[col])
            self.tree.column(
                col, width=widths[col], anchor=tk.W if "nama" in col or "alamat" in col else tk.CENTER)

        self.tree.pack(side=tk.LEFT, fill=tk.BOTH, expand=True)

        sb = ttk.Scrollbar(tree_frame, orient=tk.VERTICAL, command=self.tree.yview)
        sb.pack(side=tk.RIGHT, fill=tk.Y)
        self.tree.configure(yscrollcommand=sb.set)

        footer = ttk.Frame(self)
        footer.pack(fill=tk.X, padx=2, pady=2)
        ttk.Button(footer, text="Refresh", command=self.load_data).pack(
            side=tk.LEFT, padx=1)
        ttk.Button(footer, text="Tambah Baru",
                   command=self.on_add).pack(side=tk.LEFT, padx=1)
        ttk.Button(footer, text="Edit Data", command=self.on_edit).pack(
            side=tk.LEFT, padx=1)
        ttk.Button(footer, text="Hapus", command=self.on_delete).pack(
            side=tk.LEFT, padx=1)

        # Kontrol Paginasi
        self.btn_next = ttk.Button(
            footer, text="Berikutnya", command=lambda: self.change_page(1))
        self.btn_next.pack(side=tk.RIGHT, padx=5)

        self.page_info = ttk.Label(footer, text="Halaman 1")
        self.page_info.pack(side=tk.RIGHT, padx=10)

        self.btn_prev = ttk.Button(
            footer, text="Sebelumnya", command=lambda: self.change_page(-1))
        self.btn_prev.pack(side=tk.RIGHT, padx=5)

        self.all_items = []
        self.load_data()

    def load_data(self):
        """Mengambil data dari API dan memperbarui tampilan."""
        try:
            response = requests.get(self.base_url, timeout=5)
            res = response.json()
            if res.get('status') == 'success':
                self.all_items = res.get('data', [])
                self.refresh_view()
            else:
                messagebox.showerror("Error", res.get("message", "Gagal mengambil data"))
        except Exception as e:
            messagebox.showerror("Error", f"Gagal terhubung ke API: {e}")

    def _load_config(self):
        if os.path.exists(CONFIG_FILE):
            with open(CONFIG_FILE, "r") as f:
                try:
                    config = json.load(f) # Hanya panggil json.load sekali
                    return config
                except json.JSONDecodeError:
                    return {}
        return {}

    def refresh_view(self, reset_page=False):
        if reset_page:
            self.current_page = 1

        self.tree.delete(*self.tree.get_children())
        q = self.search_var.get().lower()

        # Filter data berdasarkan pencarian
        filtered_items = [
            item for item in self.all_items
            if any(q in str(v).lower() for v in item.values())
        ]

        # Hitung paginasi
        total = len(filtered_items)
        pages = max(1, (total + self.items_per_page - 1) //
                    self.items_per_page)
        self.current_page = max(1, min(self.current_page, pages))

        start = (self.current_page - 1) * self.items_per_page
        end = start + self.items_per_page
        page_data = filtered_items[start:end]

        for item in page_data:
            self.tree.insert("", tk.END, iid=item[self.id_key], values=tuple(
                item.get(c, "") for c in self.columns))

        # Update UI Status Paginasi
        self.page_info.config(
            text=f"Halaman {self.current_page} / {pages} ({total} data)")
        self.btn_prev.config(
            state=tk.NORMAL if self.current_page > 1 else tk.DISABLED)
        self.btn_next.config(
            state=tk.NORMAL if self.current_page < pages else tk.DISABLED)

    def change_page(self, delta):
        self.current_page += delta
        self.refresh_view()

    def on_delete(self):
        sel = self.tree.selection()
        if sel and messagebox.askyesno("Konfirmasi", "Yakin ingin menghapus data ini?"):
            try:
                res = requests.delete(f"{self.base_url}?id={sel[0]}").json()
                messagebox.showinfo("Info", res.get('message'))
                self.load_data()
            except:
                messagebox.showerror("Error", "Gagal menghapus")

    def on_add(self): self.show_form()

    def on_edit(self):
        sel = self.tree.selection()
        if sel:
            data = next(i for i in self.all_items if str(
                i[self.id_key]) == str(sel[0]))
            self.show_form(data)
        else:
            messagebox.showwarning("Peringatan", "Pilih data terlebih dahulu")

    def show_form(self, data=None): pass


class PelangganPage(MasterBasePage):
    def __init__(self, parent, user):
        cols = ('pelanggan_id', 'nama_pelanggan', 'alamat', 'no_telepon')
        heads = {'pelanggan_id': 'ID', 'nama_pelanggan': 'Nama Pelanggan',
                 'alamat': 'Alamat', 'no_telepon': 'No. Telepon'}
        widths = {'pelanggan_id': 10, 'nama_pelanggan': 200,
                  'alamat': 440, 'no_telepon': 120}
        super().__init__(parent, user, "pelanggan.php",
                         "Master Pelanggan", cols, heads, widths, "pelanggan_id")

    def show_form(self, data=None):
        win = tk.Toplevel(self)
        win.title("Form Pelanggan")
        win.geometry("400x350")
        win.grab_set()
        entries = {}
        for label, key in [("Nama", "nama_pelanggan"), ("Alamat", "alamat"), ("Telepon", "no_telepon")]:
            tk.Label(win, text=label).pack(pady=1)
            e = tk.Entry(win, width=40)
            e.pack(pady=1)
            if data:
                e.insert(0, data.get(key, ""))
            entries[key] = e

        def save():
            payload = {k: v.get() for k, v in entries.items()}
            try:
                if data:
                    requests.put(
                        f"{self.base_url}?id={data['pelanggan_id']}", json=payload)
                else:
                    requests.post(self.base_url, json=payload)
                self.load_data()
                win.destroy()
            except:
                messagebox.showerror("Error", "Simpan gagal")
        ttk.Button(win, text="Simpan Data", command=save).pack(pady=20)


class KategoriPage(MasterBasePage):
    def __init__(self, parent, user):
        cols = ('kategori_id', 'nama_kategori')
        heads = {'kategori_id': 'ID', 'nama_kategori': 'Nama Kategori'}
        widths = {'kategori_id': 80, 'nama_kategori': 500}
        super().__init__(parent, user, "kategori.php",
                         "Master Kategori", cols, heads, widths, "kategori_id")

    def show_form(self, data=None):
        win = tk.Toplevel(self)
        win.title("Form Kategori")
        win.geometry("350x180")
        win.grab_set()
        tk.Label(win, text="Nama Kategori:").pack(pady=1)
        e = tk.Entry(win, width=35)
        e.pack(pady=1)
        if data:
            e.insert(0, data['nama_kategori'])

        def save():
            try:
                payload = {"nama_kategori": e.get()}
                if data:
                    requests.put(
                        f"{self.base_url}?id={data['kategori_id']}", json=payload)
                else:
                    requests.post(self.base_url, json=payload)
                self.load_data()
                win.destroy()
            except:
                messagebox.showerror("Error", "Gagal simpan")
        ttk.Button(win, text="Simpan", command=save).pack(pady=15)


class BarangPage(MasterBasePage):
    def __init__(self, parent, user):
        cols = ('barang_id', 'barcode', 'nama_barang',
                'nama_kategori', 'stok', 'harga_hna')
        heads = {'barang_id': 'ID', 'barcode': 'Barcode', 'nama_barang': 'Nama Barang',
                 'nama_kategori': 'Kategori', 'stok': 'Stok', 'harga_hna': 'Harga'}
        widths = {'barang_id': 20, 'barcode': 120, 'nama_barang': 250,
                  'nama_kategori': 120, 'stok': 70, 'harga_hna': 100}
        super().__init__(parent, user, "barang.php",
                         "Master Barang", cols, heads, widths, "barang_id")

    def show_form(self, data=None):
        win = tk.Toplevel(self)
        win.title("Form Barang")
        win.geometry("450x450")
        win.grab_set()

        # Ambil data kategori untuk dropdown
        res_kat = requests.get(self.base_url.replace(
            "barang.php", "kategori.php")).json()
        kategori_list = res_kat.get('data', [])
        kat_names = [k['nama_kategori'] for k in kategori_list]
        kat_map = {k['nama_kategori']: k['kategori_id'] for k in kategori_list}

        entries = {}
        for label, key in [("Barcode", "barcode"), ("Nama Barang", "nama_barang"), ("Harga HNA", "harga_hna"), ("Stok", "stok")]:
            tk.Label(win, text=label).pack(pady=1)
            e = tk.Entry(win, width=45)
            e.pack(pady=1)
            if data:
                e.insert(0, data.get(key, ""))
            entries[key] = e

        tk.Label(win, text="Kategori").pack(pady=1)
        cb_kat = ttk.Combobox(win, values=kat_names, width=42)
        cb_kat.pack(pady=1)
        if data:
            cb_kat.set(data.get('nama_kategori', ''))

        tk.Label(win, text="Satuan").pack(pady=1)
        cb_sat = ttk.Combobox(
            win, values=["Pcs", "Box", "Botol", "Tablet"], width=42)
        cb_sat.pack(pady=1)
        if data:
            cb_sat.set(data.get('satuan', 'Pcs'))

        def save():
            payload = {k: v.get() for k, v in entries.items()}
            payload['kategori_id'] = kat_map.get(cb_kat.get())
            payload['satuan'] = cb_sat.get()
            try:
                if data:
                    requests.put(
                        f"{self.base_url}?id={data['barang_id']}", json=payload)
                else:
                    requests.post(self.base_url, json=payload)
                self.load_data()
                win.destroy()
            except:
                messagebox.showerror("Error", "Gagal simpan")

        ttk.Button(win, text="Simpan Barang", command=save).pack(pady=20)
