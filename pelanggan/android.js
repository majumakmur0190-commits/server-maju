
const API_URL = "api_pelanggan.php";
let semuaData = [];   // data yang sekarang ditampilkan (bisa hasil filter)
let dataAsli = [];    // salinan data asli dari server

// utility: aman memproses response JSON (jika server salah kirim, fallback ke array)
function safeJson(res) {
    return res.json().catch(() => ([]));
}

function loadData() {
    fetch(API_URL)
        .then(res => safeJson(res))
        .then(data => {
            if (!Array.isArray(data)) data = [];
            dataAsli = data;
            semuaData = data.slice(); // salin
            tampilkan();
        })
        .catch(err => {
            console.error("Gagal load data:", err);
            dataAsli = [];
            semuaData = [];
            tampilkan();
        });
}
loadData();

function tampilkan() {
    let html = "";
    if (semuaData.length === 0) {
        html = `<div class="bg-gray-50 p-4 rounded-lg text-center text-gray-500">Tidak ada data pelanggan.</div>`;
    } else {
        semuaData.forEach(row => {
            // pastikan nilai ada
            const id = row.pelanggan_id ?? "";
            const nama = row.nama_pelanggan ?? "";
            const alamat = row.alamat ?? "";
            const telp = row.no_telepon ?? "";

            html += `
                <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-4 flex flex-col">
                    <div class="flex-grow">
                        <p class="font-bold text-lg mb-1">${escapeHtml(nama)}</p>
                        ${telp ? `<p class="text-sm text-gray-600 mb-1">${escapeHtml(telp)}</p>` : ''}
                        ${alamat ? `<p class="text-sm text-gray-500">${escapeHtml(alamat)}</p>` : ''}
                    </div>
                    <div class="border-t mt-3 pt-3 flex justify-end gap-2">
                        <button class='bg-yellow-500 text-white px-3 py-1 rounded-md text-xs font-medium' onclick='editData(${id})'>
                            Edit
                        </button>

                    </div>
                </div>
            `;
        });
    }
    document.getElementById("dataPelanggan").innerHTML = html;
}

function searchData() {
    let q = document.getElementById("search").value.trim().toLowerCase();

    if (q === "") {
        semuaData = dataAsli.slice();
    } else {
        semuaData = dataAsli.filter(row => {
            const nama = (row.nama_pelanggan || "").toLowerCase();
            const alamat = (row.alamat || "").toLowerCase();
            const tel = (row.no_telepon || "").toLowerCase();
            return nama.includes(q) || alamat.includes(q) || tel.includes(q);
        });
    }

    tampilkan();
}

function openForm() {
    document.getElementById("modalForm").classList.remove("hidden");
    document.getElementById("formPelanggan").reset();

    // PENTING: kosongkan id supaya selalu POST untuk "Tambah"
    document.getElementById("pelanggan_id").value = "";

    document.getElementById("err_nama").classList.add("hidden");
    document.getElementById("titleForm").innerText = "Tambah Pelanggan";
}

function closeForm() {
    document.getElementById("modalForm").classList.add("hidden");
}

function simpan() {
    let nama = document.getElementById("nama").value.trim();
    if (!nama) {
        document.getElementById("err_nama").classList.remove("hidden");
        return;
    }

    let id = document.getElementById("pelanggan_id").value;
    let data = {
        pelanggan_id: id,
        nama_pelanggan: nama,
        alamat: document.getElementById("alamat").value || "",
        no_telepon: document.getElementById("telepon").value || "",
        aktif: document.getElementById("aktif").value || 0
    };

    let method = id ? "PUT" : "POST";

    fetch(API_URL, {
        method: method,
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(data)
    })
        .then(res => res.json().catch(() => ({})))
        .then(response => {
            if (response && response.status === "error") {
                showAlert("Error: " + (response.message || "Terjadi kesalahan"));
            } else {
                showAlert(response.message || "Sukses");
                closeForm();
                loadData();
            }
        })
        .catch(err => {
            console.error("Fetch error:", err);
            showAlert("Gagal menyimpan data. Cek console.");
        });
}

function editData(id) {
    // cari data dari dataAsli
    const row = dataAsli.find(r => String(r.pelanggan_id) === String(id));
    if (!row) {
        showAlert("Data tidak ditemukan");
        return;
    }

    openForm();
    document.getElementById("titleForm").innerText = "Edit Pelanggan";
    document.getElementById("pelanggan_id").value = row.pelanggan_id ?? "";
    document.getElementById("nama").value = row.nama_pelanggan ?? "";
    document.getElementById("alamat").value = row.alamat ?? "";
    document.getElementById("telepon").value = row.no_telepon ?? "";
    document.getElementById("aktif").value = row.aktif ?? 1;
}

function hapus(id) {
    showConfirm("Yakin hapus data ini?", () => {
        fetch(API_URL + "?pelanggan_id=" + encodeURIComponent(id), { method: "DELETE" })
            .then(res => res.json().catch(() => ({})))
            .then(response => {
                showAlert(response.message || "Selesai");
                loadData();
            })
            .catch(err => {
                console.error("Hapus error:", err);
                showAlert("Gagal menghapus data. Cek console.");
            });
    });

}

// small helper to escape text when injecting into table
function escapeHtml(text) {
    if (text === null || text === undefined) return "";
    return String(text)
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;");
}
