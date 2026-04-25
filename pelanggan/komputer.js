
const API_URL = "api_pelanggan.php";
const API_URL_OFFLINE = "http://localhost:1987/maju/api/sycn.php";
let semuaData = [];   // data yang sekarang ditampilkan (bisa hasil filter)
let dataAsli = [];    // salinan data asli dari server
let halaman = 1;
let limit = 5;

async function safeJson(res) {
    try {
        const data = await res.json();
        return data ?? []; // jika null → jadikan array
    } catch (e) {
        console.error("JSON tidak valid:", e);
        return []; // fallback aman
    }
}

function loadData() {
    fetch(API_URL)
        .then(res => safeJson(res))
.then(data => {
    dataAsli = data.pelanggan || [];
    semuaData = [...dataAsli];
    halaman = 1;
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
    let start = (halaman - 1) * limit;
    let pageData = semuaData.slice(start, start + limit);

    let html = "";
    if (pageData.length === 0) {
        html = `<tr><td class="border p-4 text-center" colspan="6">Tidak ada data</td></tr>`;
    } else {
        pageData.forEach(row => {
            // pastikan nilai ada
            const id = row.pelanggan_id ?? "";
            const nama = row.nama_pelanggan ?? "";
            const alamat = row.alamat ?? "";
            const tel = row.no_telepon ?? "";
            const aktif = row.aktif ?? "";

            html += `
                <tr>
                    <td class="border p-2">${id}</td>
                    <td class="border p-2">${escapeHtml(nama)}</td>
                    <td class="border p-2 hidden md:table-cell">${escapeHtml(alamat)}</td>
                    <td class="border p-2">${escapeHtml(tel)}</td>
                    <td class="border p-2">${escapeHtml(aktif)}</td>
                    <td class="border p-2 text-center">
                        <button class='bg-yellow-500 text-white px-2 py-1 rounded text-xs' onclick='editData(${id})'>Edit</button>
                        <button class='bg-red-600 text-white px-2 py-1 rounded text-xs' onclick='hapus(${id})'>Hapus</button>
                    </td>
                </tr>
            `;
        });
    }
    document.getElementById("dataPelanggan").innerHTML = html;
    buatPagination();
}

function buatPagination() {
    let totalHalaman = Math.max(1, Math.ceil(semuaData.length / limit));
    let html = "";

    for (let i = 1; i <= totalHalaman; i++) {
        html += `<button onclick="keHalaman(${i})" class="px-3 py-1 border rounded ${halaman === i ? 'bg-blue-600 text-white' : ''}">${i}</button>`;
    }

    document.getElementById("pagination").innerHTML = html;
}

function keHalaman(no) {
    halaman = no;
    tampilkan();
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

    halaman = 1;
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
        _method: id ? "PUT" : "POST",   // override method aman
        pelanggan_id: id || null,
        nama_pelanggan: nama,
        alamat: document.getElementById("alamat").value || "",
        no_telepon: document.getElementById("telepon").value || "",
        aktif: document.getElementById("aktif").value || 0
    };

    console.log("Kirim:", data);

    fetch(API_URL, {
        method: "POST", // selalu POST agar tidak diblok server
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(data)
    })
        .then(res => res.json().catch(() => ({})))
        .then(response => {
            if (!response || response.status === "error") {
                showAlert("Error: " + (response.message || "Terjadi kesalahan"));
                return;
            }

            showAlert(response.message || "Sukses");
            closeForm();
            loadData();
        })
        .catch(err => {
            console.error("Fetch error:", err);
            showAlert("Gagal menyimpan data. Lihat console.");
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


async function sync() {
    try {

        let loop = 0;
        let inserted = 0;
        let updated = 0;

        do {
            loop++;

            console.log(`Sync loop ke-${loop}`);

            // 1. Ambil data dari server online
            const res = await fetch(API_URL);
            const pelangganOnline = await safeJson(res);

            // 2. Susun payload
            const payload = {
                pelanggan: Array.isArray(pelangganOnline)
                    ? pelangganOnline
                    : pelangganOnline.pelanggan || []
            };

            // 3. Kirim ke offline
            const resOffline = await fetch(API_URL_OFFLINE, {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(payload)
            });

            const offline = await safeJson(resOffline);

            inserted = offline.inserted;
            updated  = offline.updated;

            console.log("Loop:", loop);
            console.log("Insert:", inserted);
            console.log("Update:", updated);
            console.log("Nochange:", offline.nochange);

            // Jika ada perubahan, load ulang UI
            loadData();

            // Jeda kecil agar server offline tidak overload
            await new Promise(r => setTimeout(r, 200));

        } while (inserted > 0 || updated > 0);

        showAlert("Sinkronisasi selesai total!");

    } catch (err) {
        console.error("Gagal sync:", err);
    }
}
