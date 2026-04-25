import requests
import json
import tkinter as tk
from tkinter import ttk

BASE_URL = "http://localhost/maju/api"

def search_barang(query):
    """Mencari barang melalui API barang.php"""
    try:
        response = requests.get(f"{BASE_URL}/barang.php", params={'search': query})
        response.raise_for_status()
        result = response.json()
        if result.get('status') == 'success':
            return result.get('data', [])
        return []
    except Exception as e:
        print(f"Error mencari barang: {e}")
        return []

def search_pelanggan(query):
    """
    Mencari pelanggan. 
    Catatan: Mengasumsikan adanya pelanggan.php yang mengikuti pola suplier.php
    """
    try:
        # Jika pelanggan.php belum ada, Anda mungkin perlu membuatnya 
        # dengan logika serupa suplier.php
        response = requests.get(f"{BASE_URL}/pelanggan.php", params={'search': query})
        response.raise_for_status()
        result = response.json()
        if result.get('status') == 'success':
            return result.get('data', [])
        return []
    except Exception:
        # Fallback jika API belum siap
        print("API pelanggan.php tidak merespon atau belum dibuat.")
        return []

def post_transaksi(user_id, pelanggan_id, items):
    """Mengirim transaksi baru ke transaksi.php"""
    payload = {
        "user_id": user_id,
        "pelanggan_id": pelanggan_id,
        "items": items
    }
    try:
        response = requests.post(
            f"{BASE_URL}/transaksi.php", 
            json=payload,
            headers={"Content-Type": "application/json"}
        )
        response.raise_for_status()
        return response.json()
    except Exception as e:
        return {"status": "error", "message": str(e)}

class TransaksiPage(ttk.Frame):
    """
    Kelas untuk Frame yang berisi UI Halaman Pembuatan Transaksi.
    """
    def __init__(self, parent):
        super().__init__(parent)

        # Konten untuk halaman transaksi
        label = ttk.Label(self, text="Halaman Buat Transaksi Baru", font=("Arial", 20, "bold"))
        label.pack(padx=20, pady=50)

        # Di sini nanti bisa ditambahkan form (Label, Entry, Button)
        # untuk menginput data transaksi baru.

def main():
    print("=== Sistem Transaksi Maju (CLI) ===")
    
    # 1. Pilih Pelanggan
    pelanggan_id = None
    p_search = input("Cari Nama Pelanggan (Kosongkan untuk 'Umum'): ")
    if p_search:
        pelanggans = search_pelanggan(p_search)
        if pelanggans:
            for idx, p in enumerate(pelanggans):
                print(f"{idx+1}. {p['nama_pelanggan']} ({p['alamat']})")
            p_choice = int(input("Pilih nomor pelanggan: ")) - 1
            pelanggan_id = pelanggans[p_choice]['pelanggan_id']
    
    # 2. Pilih Barang
    items_to_buy = []
    while True:
        b_search = input("\nCari Nama Barang / Barcode (ketik 'done' untuk selesai): ")
        if b_search.lower() == 'done':
            break
            
        barangs = search_barang(b_search)
        if not barangs:
            print("Barang tidak ditemukan.")
            continue
            
        for idx, b in enumerate(barangs):
            print(f"{idx+1}. {b['nama_barang']} | Stok: {b['stok']} | Harga: {b['harga_hna']}")
        
        b_choice = int(input("Pilih nomor barang: ")) - 1
        selected_barang = barangs[b_choice]
        
        qty = int(input(f"Jumlah untuk {selected_barang['nama_barang']}: "))
        
        items_to_buy.append({
            "barang_id": selected_barang['barang_id'],
            "jumlah": qty,
            "harga_satuan": selected_barang['harga_hna'],
            "detail_urut": len(items_to_buy) + 1
        })

    # 3. Kirim Transaksi
    if items_to_buy:
        print("\nMengirim transaksi...")
        # Ganti user_id 1 dengan ID user yang sedang login jika perlu
        result = post_transaksi(user_id=1, pelanggan_id=pelanggan_id, items=items_to_buy)
        if result.get('status') == 'success':
            print(f"Sukses! ID Penjualan: {result.get('penjualan_id')}")
        else:
            print(f"Gagal: {result.get('message')}")

if __name__ == "__main__":
    main()