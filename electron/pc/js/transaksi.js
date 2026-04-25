
// 🔹 Deklarasi Konstanta & Variabel
const SERVER_IP = localStorage.getItem('server_ip') || 'localhost';
const API_URL_PENJUALAN = `http://${SERVER_IP}:1987/maju/api/transaksi.php`;
const API_URL_PELANGGAN = `http://${SERVER_IP}:1987/maju/api/pelanggan.php`;
const API_URL_BARANG = `http://${SERVER_IP}:1987/maju/api/barang.php`;
const API_URL_HISTORI = `http://${SERVER_IP}:1987/maju/api/histori.php`;
// Elemen Form Utama
const penjualanForm = document.getElementById("penjualanForm");
const pelangganIdInput = document.getElementById("pelanggan_id");
const pelangganDisplayInput = document.getElementById("nama_pelanggan_display");
const cartItemsContainer = document.getElementById("cart-items");
const grandTotalSpan = document.getElementById("grandTotal");
const productListContainer = document.getElementById("product-list");
const productSearchInput = document.getElementById("productSearchInput");
const syncButton = document.getElementById("syncButton");
const syncStatus = document.getElementById("syncStatus");
const btnPrintInvoice = document.getElementById("btnPrintInvoice");

// Elemen Modal Pelanggan
const pelangganModal = document.getElementById("pelangganModal");
const pelangganSearchInput = document.getElementById("pelangganSearchInput");
const pelangganListContainer = document.getElementById("pelangganList");
const note = document.getElementById("note");
// fungsi menghilangkan tombol edit pelanggan saat proses transaksi dengan histori harga
function sembunyikanNote() {
    // cegah edit pelanggan saat proses
    pelangganIdInput.setAttribute('readonly', true);
    pelangganDisplayInput.setAttribute('readonly', true);
    // hilangkan tombol edit pelanggan saat proses
    const tombolEdit = document.getElementById('btnPilihPelanggan');
    tombolEdit.style.display = 'none';
    note.style.display = 'block';
}

// 🔹 Fungsi untuk update nomor urut di keranjang
function updateCartNumbers() {
    const cartItems = cartItemsContainer.querySelectorAll('[data-barang-id]');
    cartItems.forEach((item, index) => {
        const numberElement = item.querySelector('.cart-item-number');
        if (numberElement) {
            numberElement.textContent = index + 1;
            if (index + 1 == 40) {
                customAlert("Maksimum 40 item per transaksi. Silakan selesaikan transaksi ini terlebih dahulu.");
            }
        }
    });

    // Secara otomatis scroll ke item terakhir di keranjang setiap ada pembaruan.
    if (cartItems.length > 0) {
        cartItemsContainer.scrollTop = cartItemsContainer.scrollHeight;
    }
}

// =============================================
// === IndexedDB & Product Management Logic ===
// ============================================= 
// Variabel Paginasi Produk
let allProductsFromSearch = [];
let currentProductPage = 1;
const productsPerPage = 10;
const productPrevButton = document.getElementById('product-prev-page');
const productNextButton = document.getElementById('product-next-page');

let productCache = new Map(); // Cache produk di memori untuk akses cepat

// Fungsi untuk menghitung harga jual dan menambahkannya ke objek produk
function enrichProductData(product) {
    // Harga jual sekarang sama dengan harga HNA karena diskon dihilangkan
    product.harga_jual = parseFloat(product.harga_hna);
    return product;
}

// Helper: Format angka ke Rupiah
const formatRupiah = (number) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(number);
};

async function searchProductsFromAPI(keyword = "") {
    syncStatus.textContent = 'Mencari produk...';
    let url = API_URL_BARANG;
    if (keyword.trim()) {
        url += `?search=${encodeURIComponent(keyword)}`;
    }

    try {
        const res = await fetch(url);
        const result = await res.json();
        if (result.status === 'success') {
            // Simpan hasil ke cache
            allProductsFromSearch = result.data;
            allProductsFromSearch.forEach(p => productCache.set(p.barang_id, p));
            displayPaginatedProducts(1); // Tampilkan halaman pertama
            syncStatus.textContent = `Ditemukan ${allProductsFromSearch.length} produk.`;
        } else {
            syncStatus.textContent = 'Gagal mencari produk.';
        }
    } catch (error) {
        syncStatus.textContent = 'Error koneksi ke API barang.';
    }
}

async function getProductById(id) {
    const numericId = parseInt(id, 10);
    if (productCache.has(numericId)) {
        return enrichProductData(productCache.get(numericId));
    }

    // Jika tidak ada di cache, ambil dari API
    try {
        const res = await fetch(`${API_URL_BARANG}?id=${numericId}`);
        const result = await res.json();
        if (result.status === 'success' && result.data) {
            productCache.set(numericId, result.data);
            return enrichProductData(result.data);
        }
    } catch (error) {
        console.error("Gagal mengambil produk by ID:", error);
    }
    return null;
}
// 🔹 Render daftar produk di kolom kanan
// Fungsi ini sekarang mengambil data dari IndexedDB, membuatnya jauh lebih cepat.
function renderProductList(productsToDisplay) {
    productListContainer.innerHTML = productsToDisplay.map(b => {
        // console.log(b.nama_kategori);
        const isOutOfStock = b.stok <= 0;
        const stockColor = isOutOfStock ? 'red' : 'gray';
        const cursorStyle = isOutOfStock ? 'not-allowed' : 'pointer';
        const opacityStyle = isOutOfStock ? '0.5' : '1';
        const bgHover = isOutOfStock ? '' : 'onmouseover="this.style.background=\'#e5f4fc\'" onmouseout="this.style.background=\'#fff\'"';

        enrichProductData(b); // Selalu pastikan harga jual dihitung

        return `
                <div onclick="${isOutOfStock ? '' : `addProductToCart(${b.barang_id})`}" ${bgHover} style="display: flex; align-items: center; padding: 6px; border: 1px solid #8e8f8f; margin-bottom: 4px; background: #fff; cursor: ${cursorStyle}; opacity: ${opacityStyle};">
                    <div style="flex-grow: 1;">
                        <p style="margin: 0; font-weight: bold; font-size: 12px;">${b.barcode}</p>
                        <p style="margin: 0; font-size: 12px;">${b.nama_barang}</p>
                        <p style="margin: 0; font-size: 11px; color: ${stockColor};">${formatRupiah(b.harga_jual)} | Stok: ${b.stok}</p>
                    </div>
                    <div style="font-weight: bold; font-size: 14px; ${isOutOfStock ? 'display: none;' : ''}">+</div>
                </div>
            `}).join('');
}

// 🔹 Fungsi baru untuk menampilkan produk dengan paginasi
function displayPaginatedProducts(page) {
    currentProductPage = page;
    const startIndex = (currentProductPage - 1) * productsPerPage;
    const endIndex = startIndex + productsPerPage;
    const paginatedItems = allProductsFromSearch.slice(startIndex, endIndex);

    renderProductList(paginatedItems);
    renderProductPagination();
}

// 🔹 Fungsi baru untuk merender kontrol paginasi produk
function renderProductPagination() {
    const totalPages = Math.ceil(allProductsFromSearch.length / productsPerPage);
    const controls = document.getElementById('product-pagination-controls');
    const pageInfo = document.getElementById('product-page-info');

    if (totalPages > 1) {
        controls.style.display = 'flex';
        pageInfo.textContent = `Hal ${currentProductPage} dari ${totalPages}`;
        productPrevButton.disabled = currentProductPage === 1;
        productNextButton.disabled = currentProductPage === totalPages;
    } else {
        controls.style.display = 'none';
    }
}


// 🔹 Pencarian produk
let searchTimeout;
productSearchInput.addEventListener('input', (e) => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        searchProductsFromAPI(e.target.value);
    }, 300); // Debounce
});
 
syncButton.addEventListener('click', () => searchProductsFromAPI(productSearchInput.value));

// 🔹 Event listener untuk paginasi produk
productPrevButton.addEventListener('click', () => {
    if (currentProductPage > 1) {
        displayPaginatedProducts(currentProductPage - 1);
    }
});
productNextButton.addEventListener('click', () => {
    const totalPages = Math.ceil(allProductsFromSearch.length / productsPerPage);
    if (currentProductPage < totalPages) {
        displayPaginatedProducts(currentProductPage + 1);
    }
});

// 🔹 Tambah produk ke keranjang
async function addProductToCart(barangId, itemData = null) {
    const existingCartItem = cartItemsContainer.querySelector(`[data-barang-id="${barangId}"]`);
    const barang = await getProductById(barangId);

    const pelangganId = pelangganIdInput.value;
    let hargaFinal = barang.harga_jual;
    if (pelangganId == "") {
        customAlert("Silakan pilih pelanggan terlebih dahulu sebelum menambahkan barang ke keranjang.");
        return;
    }

    // console.log("Menambahkan barang ID ke keranjang:", barangId);
    sembunyikanNote(); // Panggil fungsi untuk menyembunyikan tombol edit pelanggan



    // 🔍 cek harga histori pelanggan
    if (pelangganId) {
        const histori = await getHargaHistori(pelangganId, barangId);
        if (histori && histori.status === "ada") {
            hargaFinal = histori.harga;   // gunakan harga histori!
        }
    }


    if (existingCartItem && !itemData) { // Jika item sudah ada di keranjang (dan bukan mode edit)
        const barang = productCache.get(barangId); // Ambil dari cache, harusnya sudah ada
        const maxStok = barang ? parseInt(barang.stok) : 0;

        // Jika barang sudah ada di keranjang, tambah jumlahnya
        const jumlahInput = existingCartItem.querySelector('input[name="jumlah"]');
        const newJumlah = parseInt(jumlahInput.value) + 1;

        if (newJumlah > maxStok) {
            customAlert(`Stok untuk ${barang.nama_barang} tidak mencukupi. Stok tersedia: ${maxStok}`);
            jumlahInput.value = maxStok;
        } else {
            jumlahInput.value = newJumlah;
        }

        calculateItemSubtotal(barangId);
    } else {
        // Jika barang belum ada, tambahkan baris baru
        const barang = await getProductById(barangId); // Selalu ambil data produk lengkap
        if (!barang) return;

        // Gabungkan data dari transaksi yang disimpan (itemData) dengan data produk lengkap (barang)
        const item = {
            barang_id: barang.barang_id,
            nama_barang: barang.nama_barang,
            nama_kategori: barang.nama_kategori,
            jumlah: itemData ? itemData.jumlah : 1,
            harga_satuan: itemData ? itemData.harga_satuan : hargaFinal,
            subtotal: itemData ? itemData.subtotal : hargaFinal
        };



        const rowHtml = `
                        <div data-barang-id="${item.barang_id}" 
                            data-original-jumlah="${itemData ? item.jumlah : 0}" 
                            class="cart-row grid-kolom"
                            onclick="warnabaris(this)" 
                            oncontextmenu="showContextMenu(event, ${item.barang_id})">

                            <div class="cart-item-number"></div>

                            <div class="cart-item-name">
                                ${item.nama_barang}
                            </div>

                            <div>
                                <input type="number" name="jumlah" value="${item.jumlah}" min="1" step="1"
                                    oninput="this.value = Math.round(this.value);"
                                    class="cart-input">
                            </div>

                            <div>
                                <input type="number" name="harga_satuan" value="${item.harga_satuan}" step="1"
                                    oninput="this.value = Math.round(this.value);" 
                                    class="cart-input">
                            </div>

                            <div class="cart-subtotal">
                                <span class="item-subtotal">${formatRupiah(item.subtotal)}</span>
                            </div>
                        </div>
                        `;



        cartItemsContainer.insertAdjacentHTML('beforeend', rowHtml);

        // ✅ Panggil fungsi untuk menambahkan event listener ke baris baru
        const newRow = cartItemsContainer.querySelector(`[data-barang-id="${item.barang_id}"]`);
        addEventListenersToCartItem(newRow);

        // ✅ Panggil kalkulasi awal untuk item baru
        calculateItemSubtotal(item.barang_id);
    }
    updateCartNumbers();
}

// 🔹 Highlight baris saat diklik
function warnabaris(row) {
    document.querySelectorAll('.cart-row').forEach(r => r.classList.remove('selected-row'));
    row.classList.add('selected-row');
}

// 🔹 Context Menu untuk hapus item
function showContextMenu(e, id) {
    e.preventDefault();
    const contextMenu = document.getElementById("contextMenu");
    if (!contextMenu) return;

    // Highlight baris yang diklik kanan agar user tidak bingung
    warnabaris(e.currentTarget);

    // Posisikan menu menggunakan koordinat client agar stabil terhadap viewport
    contextMenu.style.top = `${e.clientY}px`;
    contextMenu.style.left = `${e.clientX}px`;
    contextMenu.style.display = "block";

    const menuDelete = document.getElementById("menuDelete");
    if (menuDelete) {
        menuDelete.onclick = (event) => {
            event.stopPropagation();
            removeCartItem(id);
            contextMenu.style.display = "none";
        };
    }
}

// Sembunyikan context menu saat klik di mana saja
document.addEventListener("click", () => {
    const contextMenu = document.getElementById("contextMenu");
    if (contextMenu) contextMenu.style.display = "none";
});

// 🔹 Fungsi baru untuk menambahkan event listener ke item keranjang
function addEventListenersToCartItem(row) {
    const barangId = row.dataset.barangId;
    row.querySelectorAll('input[name="jumlah"], input[name="harga_satuan"]').forEach(input => {
        input.addEventListener('input', () => calculateItemSubtotal(barangId));
    });
}

// 🔹 Hapus item dari keranjang
function removeCartItem(barangId) {
    cartItemsContainer.querySelector(`[data-barang-id="${barangId}"]`).remove();
    calculateGrandTotal();
    updateCartNumbers();
}

// 🔹 Hitung Subtotal per Item
async function calculateItemSubtotal(barangId) {
    const row = cartItemsContainer.querySelector(`[data-barang-id="${barangId}"]`);
    if (!row) return;

    const jumlahInput = row.querySelector('input[name="jumlah"]');
    let jumlah = parseInt(jumlahInput.value, 10) || 0;

    // Ambil data produk dari cache
    const barang = await getProductById(barangId);

    // ✅ Perbaikan: Jika barang tidak ditemukan (misalnya sudah dihapus), hentikan eksekusi.
    if (!barang) {
        customAlert(`Barang dengan ID ${barangId} tidak ditemukan. Mungkin sudah dihapus.`, 'error');
        return;
    }

    let maxStok = barang ? parseInt(barang.stok) : 0;

    // ✅ Logika baru: Jika dalam mode edit, tambahkan stok asli barang ini kembali untuk validasi
    const penjualanId = document.getElementById("penjualan_id").value;
    if (penjualanId) {
        const originalJumlah = parseInt(row.dataset.originalJumlah) || 0;
        maxStok += originalJumlah;
    }

    // ✅ Validasi stok saat jumlah diubah manual
    if (jumlah > maxStok) {
        customAlert(`Stok untuk ${barang.nama_barang} tidak mencukupi. Stok tersedia: ${maxStok}`);
        jumlahInput.value = maxStok; // Reset ke stok maksimal
        jumlah = maxStok; // Update variabel untuk kalkulasi
    }

    const harga_satuan = parseInt(row.querySelector('input[name="harga_satuan"]').value, 10) || 0;

    let subtotal = jumlah * harga_satuan;

    row.querySelector('.item-subtotal').textContent = formatRupiah(subtotal);
    calculateGrandTotal();
}

// 🔹 Hitung Total Penjualan Keseluruhan
function calculateGrandTotal() {
    let totalSubtotalItems = 0;
    cartItemsContainer.querySelectorAll('[data-barang-id]').forEach(row => {
        const subtotalText = row.querySelector('.item-subtotal').textContent;
        const subtotalValue = parseFloat(subtotalText.replace(/Rp|\./g, '').replace(',', '.')) || 0;
        totalSubtotalItems += subtotalValue;
    });

    let grandTotal = totalSubtotalItems;

    grandTotalSpan.textContent = formatRupiah(grandTotal);
}

// 🔹 Simpan Data Penjualan
async function saveTransaction() {
    let penjualan_id = document.getElementById("penjualan_id").value;
    const user_id = document.getElementById("user_id").value;
    const pelanggan_id = pelangganIdInput.value;

    const items = [];
    cartItemsContainer.querySelectorAll('[data-barang-id]').forEach((row, index) => {
        const barang_id = row.dataset.barangId;
        const jumlah = parseInt(row.querySelector('input[name="jumlah"]').value, 10) || 0;
        const harga_satuan = parseInt(row.querySelector('input[name="harga_satuan"]').value, 10) || 0;
        const subtotalText = row.querySelector('.item-subtotal').textContent;
        const subtotal = parseFloat(subtotalText.replace(/Rp|\./g, '').replace(',', '.')) || 0;

        if (barang_id && jumlah > 0 && harga_satuan >= 0) {
            items.push({
                detail_urut: index + 1,  // 🔹 nomor urut mulai dari 1
                barang_id,
                jumlah,
                harga_satuan,
                subtotal
            });
        }
    });


    if (items.length === 0) {
        customAlert("Keranjang tidak boleh kosong untuk menyimpan.");
        return { success: false, id: null };
    }

    const dataToSend = {
        user_id,
        pelanggan_id: pelanggan_id === "" ? null : pelanggan_id,
        items
    };

    const url = penjualan_id ? `${API_URL_PENJUALAN}?id=${penjualan_id}` : API_URL_PENJUALAN;
    const method = penjualan_id ? "PUT" : "POST";
    // console.log("Data to send:", dataToSend);
    try {
        const res = await fetch(url, {
            method: method,
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(dataToSend)
        });
        const result = await res.json();

        if (result.status === 'success') {

            // ======================================================
            // 🔥 Tambahan: Simpan Histori Harga Pelanggan
            // ======================================================
            if (pelanggan_id) {
                try {
                    await fetch(API_URL_HISTORI + "?aksi=saveHistori", {
                        method: "POST",
                        headers: { "Content-Type": "application/json" },
                        body: JSON.stringify({
                            pelanggan_id,
                            items
                        })
                    });
                } catch (e) {
                    console.error("Gagal menyimpan histori:", e);
                }
            }
            // ======================================================

            return { success: true, id: result.id || penjualan_id };

        } else {
            customAlert(result.message || "Terjadi kesalahan saat menyimpan.");
            return { success: false, id: null };
        }
    } catch (error) {
        customAlert("Gagal terhubung ke server.");
        return { success: false, id: null };
    }
}

// simpan transaksi saat form disubmit
penjualanForm.addEventListener("submit", async e => {
    e.preventDefault();
    const saveResult = await saveTransaction();
    if (saveResult.success) {
        customAlert("Transaksi berhasil disimpan!");
        window.location.href = 'penjualan.html';
    }
});

// 🔹 Cek apakah mode edit atau tambah baru
async function initializeForm() {
    // Ambil data user dari session dan set user_id
    const userDataString = sessionStorage.getItem('loggedInUser');
    if (userDataString) {
        const user = JSON.parse(userDataString);
        // Gunakan 'id' sesuai dengan respons dari login.php
        document.getElementById('user_id').value = user.id;
    } else {
        // Jika tidak ada data login, kembali ke halaman login
        window.location.href = 'login.html';
    }

    // Langsung cari produk dari API saat halaman dimuat
    searchProductsFromAPI('');

    const urlParams = new URLSearchParams(window.location.search);
    const penjualanId = urlParams.get('id');

    if (penjualanId) {
        // Mode Edit
        document.getElementById("penjualan_id").value = penjualanId;
        btnPrintInvoice.disabled = false; // Aktifkan tombol cetak

        const res = await fetch(`${API_URL_PENJUALAN}?id=${penjualanId}`);
        const result = await res.json();

        if (result.status === "success" && result.data) {
            const p = result.data; // Data penjualan
            selectPelanggan(p.pelanggan_id, p.nama_pelanggan || 'Umum');

            cartItemsContainer.innerHTML = ''; // Clear existing rows

            // 🔹 Urutkan detail berdasarkan detail_urut
            p.details.sort((a, b) => a.detail_urut - b.detail_urut);

            // 🔹 Tambahkan produk ke keranjang satu per satu agar nomor urut benar
            for (const item of p.details) {
                const itemFullData = {
                    ...item,
                    nama_barang: item.nama_barang || 'Barang Dihapus',
                    barang_id: item.barang_id
                };
                await addProductToCart(item.barang_id, itemFullData);
            }

            // Pastikan nomor urut terakhir benar
            updateCartNumbers();
            calculateGrandTotal();
        } else {
            customAlert(result.message || "Gagal memuat data penjualan.");
        }
    } else {
        btnPrintInvoice.disabled = true; // Pastikan nonaktif untuk transaksi baru
    }

}

// =============================================
// === Logika Modal Pelanggan ==================
// =============================================
let allPelanggan = [];
let filteredPelanggan = [];
let currentPelangganPage = 1;
const pelangganPerPage = 5; // Tampilkan 5 pelanggan per halaman di modal

const pelangganPrevButton = document.getElementById('pelanggan-prev-page');
const pelangganNextButton = document.getElementById('pelanggan-next-page');
const pelangganPageInfo = document.getElementById('pelanggan-page-info');

async function loadAllPelanggan() {
    try {
        const res = await fetch(API_URL_PELANGGAN);
        const result = await res.json();
        if (result.status === "success" && result.data) {
            allPelanggan = result.data;
        }
        // sembunyikanNote(); // Panggil fungsi untuk menyembunyikan tombol edit pelanggan
    } catch (error) {
        console.error("Gagal memuat semua pelanggan:", error);
    }
}

function renderPelangganList(pelangganToDisplay) {
    pelangganListContainer.innerHTML = ''; // Kosongkan dulu
    // Tambahkan opsi "Umum" di paling atas
    pelangganListContainer.innerHTML += `
                <div onclick="selectPelanggan('', 'Umum')" style="padding: 8px; border-bottom: 1px solid #ccc; cursor: pointer;" onmouseover="this.style.background='#e5f4fc'" onmouseout="this.style.background='#fff'">
                    <p style="margin: 0; font-weight: bold;">Umum (Tanpa Pelanggan)</p>
                </div>
            `;
    pelangganListContainer.innerHTML += pelangganToDisplay.map(p => `
                <div onclick="selectPelanggan(${p.pelanggan_id}, '${p.nama_pelanggan}')" style="padding: 8px; border-bottom: 1px solid #ccc; cursor: pointer;" onmouseover="this.style.background='#e5f4fc'" onmouseout="this.style.background='#fff'">
                    <p style="margin: 0; font-weight: bold;">${p.nama_pelanggan}</p>
                    <p style="margin: 0; font-size: 11px; color: gray;">${p.alamat || 'Alamat tidak tersedia'}</p>
                </div>
            `).join('');
}

function searchAndFilterPelanggan(keyword = "", page = 1) {
    currentPelangganPage = page;
    const lowerKeyword = keyword.toLowerCase();

    filteredPelanggan = allPelanggan.filter(p =>
        p.nama_pelanggan.toLowerCase().includes(lowerKeyword) ||
        p.pelanggan_id.toString().includes(lowerKeyword)
    );

    displayPaginatedPelanggan(currentPelangganPage);
}

function displayPaginatedPelanggan(page) {
    currentPelangganPage = page;
    const startIndex = (currentPelangganPage - 1) * pelangganPerPage;
    const endIndex = startIndex + pelangganPerPage;
    const paginatedItems = filteredPelanggan.slice(startIndex, endIndex);

    renderPelangganList(paginatedItems);
    renderPelangganPagination();
}

function renderPelangganPagination() {
    const totalPages = Math.ceil(filteredPelanggan.length / pelangganPerPage);
    const controls = document.getElementById('pelanggan-pagination-controls');

    if (controls) { // Tambahkan pengecekan ini
        if (totalPages > 1) {
            controls.style.display = 'flex';
            pelangganPageInfo.textContent = `Hal ${currentPelangganPage} dari ${totalPages}`;
            pelangganPrevButton.disabled = currentPelangganPage === 1;
            pelangganNextButton.disabled = currentPelangganPage === totalPages;
        } else {
            controls.style.display = 'none';
        }
    }
}

function selectPelanggan(id, nama) {
    pelangganIdInput.value = id;
    pelangganDisplayInput.value = nama;
    pelangganModal.style.display = "none";
    pelangganSearchInput.value = ""; // Reset pencarian saat modal ditutup
}

function openPelangganModal() {
    pelangganModal.style.display = "flex";
    pelangganSearchInput.value = "";
    searchAndFilterPelanggan("", 1);
}

function closePelangganModal() {
    pelangganModal.style.display = "none";
}
// Event Listeners untuk Modal Pelanggan
document.getElementById("btnPilihPelanggan").addEventListener("click", () => {
    openPelangganModal();
});
document.getElementById("btnBatalPelanggan").addEventListener("click", () => {
    pelangganModal.style.display = "none";
});
pelangganSearchInput.addEventListener("input", (e) => {
    searchAndFilterPelanggan(e.target.value, 1);
});
 
// Event listener untuk paginasi pelanggan
pelangganPrevButton.addEventListener('click', () => {
    if (currentPelangganPage > 1) {
        // Panggil displayPaginatedPelanggan, bukan searchAndFilterPelanggan, agar filter tetap terjaga
        displayPaginatedPelanggan(currentPelangganPage - 1);
    }
});
pelangganNextButton.addEventListener('click', () => {
    const totalPages = Math.ceil(filteredPelanggan.length / pelangganPerPage);
    if (currentPelangganPage < totalPages) {
        displayPaginatedPelanggan(currentPelangganPage + 1);
    }
});

// Event Listener untuk Tombol Cetak
btnPrintInvoice.addEventListener("click", () => {
    customConfirm("Simpan perubahan sebelum mencetak?", async () => {
        const saveResult = await saveTransaction();
        if (saveResult.success && saveResult.id) {
            window.open(`http://localhost:1987/maju/api/print.php?id=${saveResult.id}`, '_blank');
        }
    });
});

// pencarian histori transaksi
async function getHargaHistori(pelangganId, barangId) {
    const url = API_URL_HISTORI + `?aksi=getHarga&id_pelanggan=${pelangganId}&id_barang=${barangId}`;
    try {
        const res = await fetch(url);
        return await res.json();
    } catch (err) {
        console.error("Gagal ambil harga histori", err);
        return null;
    }
}

// 🔹 Load data awal
loadAllPelanggan();
initializeForm();
