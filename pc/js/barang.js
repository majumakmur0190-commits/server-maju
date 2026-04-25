const API_URL = "../api/barang.php";
const API_URL_KATEGORI = "../api/kategori.php";
const API_URL_HISTORI = "../api/histori.php";
const tableBody = document.getElementById("barangTable");
const modal = document.getElementById("modal");
const form = document.getElementById("barangForm");
const modalTitle = document.getElementById("modalTitle");
const btnTambah = document.getElementById("btnTambah");
const btnBatal = document.getElementById("btnBatal");
const search = document.getElementById("search");
let editMode = false;

// Ambil elemen form
const barang_id = document.getElementById('barang_id');
const barcode = document.getElementById('barcode');
const nama_barang = document.getElementById('nama_barang');
const kategori_id = document.getElementById('kategori_id');
// Sesuaikan dengan form yang baru
const harga_hna = document.getElementById('harga_hna');
const satuan = document.getElementById('satuan');
const stok = document.getElementById('stok');

// =============================================
// === Variabel & Elemen Paginasi ===
// =============================================
let allBarang = [];
let filteredBarang = [];
let currentPage = 1;
const itemsPerPage = 10;
const paginationControls = document.getElementById('pagination-controls');
const prevButton = document.getElementById('prev-page');
const nextButton = document.getElementById('next-page');
const pageInfo = document.getElementById('page-info');
// =============================================
// === Data Management Logic ===
// =============================================

// ✅ Load kategori dari API
async function loadKategoriOptions() {
    try {
        const res = await fetch(API_URL_KATEGORI);
        const result = await res.json();
        const select = document.getElementById("kategori_id");
        select.innerHTML = '<option value="">Pilih Kategori...</option>';
        if (result.status === "success") {
            result.data.forEach(k => {
                select.innerHTML += `<option value="${k.kategori_id}">${k.nama_kategori}</option>`;
            });
        }
    } catch {
        console.error("Gagal memuat kategori");
    }
}

// ✅ Fungsi baru untuk paginasi dan render
function displayPaginatedData(page = 1) {
    currentPage = page;
    const totalItems = filteredBarang.length;
    const totalPages = Math.ceil(totalItems / itemsPerPage);
    const startIndex = (currentPage - 1) * itemsPerPage;
    const endIndex = startIndex + itemsPerPage;
    const paginatedItems = filteredBarang.slice(startIndex, endIndex);

    renderTable(paginatedItems);
    renderPagination(totalItems, totalPages);
}


// ✅ Ambil data barang dari API dan filter
async function fetchAndDisplayBarang(keyword = "", page = 1) {
    tableBody.innerHTML = `<tr><td colspan="9" class="text-center py-4">Memuat data...</td></tr>`;

    let url = API_URL;
    if (keyword.trim() !== "") {
        url += `?search=${encodeURIComponent(keyword)}`;
    }

    try {
        const res = await fetch(url);
        const result = await res.json();
        if (result.status === 'success') {
            allBarang = result.data;
            // console.log(allBarang);
            filteredBarang = allBarang; // Data sudah difilter oleh API
            displayPaginatedData(page);
        }
    } catch (error) {
        console.error("Gagal mengambil data dari server:", error);
        tableBody.innerHTML = `<tr><td colspan="9" class="text-center py-4 text-red-500">Gagal mengambil data dari server.</td></tr>`;
    }
}

// ✅ Render kontrol paginasi
function renderPagination(totalItems, totalPages) {
    if (totalPages > 1) {
        paginationControls.style.display = 'flex';
        pageInfo.textContent = `Halaman ${currentPage} dari ${totalPages} (${totalItems} item)`;
        prevButton.disabled = currentPage === 1;
        nextButton.disabled = currentPage === totalPages;
    } else {
        paginationControls.style.display = 'none';
    }
}

// ✅ Tampilkan data ke tabel
function renderTable(data) {
    if (!data.length) {
        tableBody.innerHTML = `<tr><td colspan="9" class="text-center py-4">Tidak ada data.</td></tr>`;
        return;
    }
    const startIndex = (currentPage - 1) * itemsPerPage;
    tableBody.innerHTML = data.map((b, i) => `
        <tr class="hover:bg-gray-100">
          <td class="px-4 py-2">${startIndex + i + 1}</td>
          <td class="px-4 py-2">${b.barcode ? b.barcode : '-'}</td>
          <td class="px-4 py-2">${b.nama_barang}</td>
          <td class="px-4 py-2 text-right">${formatRupiah(b.harga_hna)}</td>
          <td class="px-4 py-2 text-right">${b.stok}</td>
          <td class="px-4 py-2">${b.satuan}</td>
          <td class="px-4 py-2 text-center whitespace-nowrap space-x-1">
            <button onclick="editBarang(${b.barang_id})" class="bg-blue-500 hover:bg-blue-600 text-white px-2 py-1 rounded text-xs">Edit</button>
            <button onclick="hapusBarang(${b.barang_id})" class="bg-red-500 hover:bg-red-600 text-white px-2 py-1 rounded text-xs">Hapus</button>
          </td>
        </tr>
      `).join("");
}

// ✅ Format ke Rupiah
function formatRupiah(number) {
    return new Intl.NumberFormat("id-ID", { style: "currency", currency: "IDR", minimumFractionDigits: 0 }).format(number);
}

// 🔹 Tambah barang
btnTambah.onclick = () => {
    editMode = false;
    form.reset();
    modalTitle.textContent = "Tambah Barang";
    // Saat menambah, stok bisa diisi
    stok.parentElement.classList.remove('hidden');
    stok.required = true;
    stok.value = 0;
    modal.classList.remove("hidden");
};

// 🔹 Batal modal
btnBatal.onclick = () => modal.classList.add("hidden");
// 🔹 Variabel untuk menyimpan harga awal saat edit
function cekNamaBarang() {
    const namaBarangError = document.getElementById("namaBarangError");
    const namaBarang = document.getElementById("nama_barang");
    if (namaBarang.value.length > 50) {
        namaBarangError.style.display = "block";
        return false;
    } else {
        namaBarangError.style.display = "none";
        return true;
    }
}
let hargaPertama = 0;
// 🔹 Simpan barang
form.onsubmit = async (e) => {
    e.preventDefault();
    // Validasi nama barang
    if (!cekNamaBarang()) {
        return;
    }
    const data = {
        barcode: barcode.value.trim(),
        nama_barang: nama_barang.value.trim(),
        kategori_id: kategori_id.value,
        harga_hna: harga_hna.value,
        stok: stok.value,
        satuan: satuan.value,
    };
    if (hargaPertama !== harga_hna.value) {
        try {
            await fetch(`${API_URL_HISTORI}?aksi=hapus_barang&barang_id=${barang_id.value}`);
        } catch (e) {
            console.error("Gagal menghapus histori harga lama:", e);
        }
    };
    const method = editMode ? "PUT" : "POST";
    const url = editMode ? `${API_URL}?id=${barang_id.value}` : API_URL;

    try {
        const res = await fetch(url, {
            method,
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(data)
        });
        const result = await res.json();
        modal.classList.add("hidden");
        // Gunakan customAlert dengan callback untuk refresh data setelah ditutup
        customAlert(result.message, result.status === 'success' ? 'Sukses' : 'Gagal', () => {
            fetchAndDisplayBarang(search.value);
        });
    } catch {
        customAlert("Gagal menyimpan data ke server.", "Error");
    }
};

// 🔹 Edit barang
window.editBarang = async (id) => {
    try {
        const res = await fetch(`${API_URL}?id=${id}`);
        const result = await res.json();
        if (result.status === "success" && result.data) {
            const b = result.data;
            barang_id.value = b.barang_id;
            barcode.value = b.barcode;
            nama_barang.value = b.nama_barang;
            kategori_id.value = b.kategori_id;
            harga_hna.value = b.harga_hna;
            hargaPertama = b.harga_hna;
            stok.value = b.stok;
            satuan.value = b.satuan;
            editMode = true;
            modalTitle.textContent = "Edit Barang";
            // Saat edit, sembunyikan input stok karena stok dikelola oleh transaksi
            // stok.parentElement.classList.add('hidden');
            // stok.required = false;
            modal.classList.remove("hidden");
        }
    } catch {
        customAlert("Gagal memuat data barang untuk diedit.", "Error");
    }
};

// 🔹 Hapus barang
window.hapusBarang = async (id) => {
    customConfirm(
        "Apakah Anda yakin ingin menghapus barang ini? Stok akan dikembalikan dan tindakan ini tidak dapat dibatalkan.",
        async () => { // onConfirm
            try {
                const res = await fetch(`${API_URL}?id=${id}`, { method: "DELETE" });
                const result = await res.json();
                customAlert(result.message, result.status === 'success' ? 'Sukses' : 'Gagal', () => {
                    fetchAndDisplayBarang(search.value);
                });
            } catch {
                customAlert("Gagal menghapus barang dari server.", "Error");
            }
        }
    );
};

// Panggil fungsi dari main.js
initializeApp();

// 🔹 Pencarian
let searchTimeout;
search.oninput = (e) => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        fetchAndDisplayBarang(e.target.value, 1);
    }, 300); // Debounce untuk mengurangi request saat mengetik
};

// 🔹 Event Listener untuk tombol paginasi
prevButton.addEventListener('click', () => {
    if (currentPage > 1) {
        displayPaginatedData(currentPage - 1);
    }
});
nextButton.addEventListener('click', () => {
    const totalPages = Math.ceil(filteredBarang.length / itemsPerPage);
    if (currentPage < totalPages) {
        displayPaginatedData(currentPage + 1);
    }
});

// 🔹 Inisialisasi awal
(async () => {
    await loadKategoriOptions();
    // Langsung ambil data dari API saat halaman dimuat
    fetchAndDisplayBarang();
})();
