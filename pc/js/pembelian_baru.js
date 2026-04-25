// Panggil fungsi dari main.js
// initializeApp();
 
// 🔹 Deklarasi Konstanta & Variabel
const API_URL_PEMBELIAN = "../api/pembelian.php";
const API_URL_SUPLIER = "../api/suplier.php";
const API_URL_BARANG = "../api/barang.php";
const API_URL_HISTORI_PEMBELIAN = "../api/histori_pembelian.php";

// Elemen Form Utama
const pembelianForm = document.getElementById("pembelianForm");
const suplierIdInput = document.getElementById("suplier_id");
const suplierDisplayInput = document.getElementById("nama_suplier_display");
const cartItemsContainer = document.getElementById("cart-items");
const grandTotalSpan = document.getElementById("grandTotal");
const productListContainer = document.getElementById("product-list");
const productSearchInput = document.getElementById("productSearchInput");
const syncButton = document.getElementById("syncButton");
const syncStatus = document.getElementById("syncStatus");

// Elemen Modal Suplier
const suplierModal = document.getElementById("suplierModal");
const suplierSearchInput = document.getElementById("suplierSearchInput");
const suplierListContainer = document.getElementById("suplierList");

// 🔹 Fungsi untuk update nomor urut di keranjang
function updateCartNumbers() {
    const cartItems = cartItemsContainer.querySelectorAll('[data-barang-id]');
    cartItems.forEach((item, index) => {
        const numberElement = item.querySelector('.cart-item-number');
        if (numberElement) numberElement.textContent = index + 1;
    });
}

// =============================================
// === Product Management Logic ===
// ============================================= 
let allProductsFromSearch = [];
let currentProductPage = 1;
const productsPerPage = 10;
const productPrevButton = document.getElementById('product-prev-page');
const productNextButton = document.getElementById('product-next-page');
let productCache = new Map();

const formatRupiah = (number) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(number);
};

async function searchProductsFromAPI(keyword = "") {
    syncStatus.textContent = 'Mencari produk...';
    let url = API_URL_BARANG;
    if (keyword.trim()) url += `?search=${encodeURIComponent(keyword)}`;

    try {
        const res = await fetch(url);
        const result = await res.json();
        if (result.status === 'success') {
            allProductsFromSearch = result.data;
            allProductsFromSearch.forEach(p => productCache.set(p.barang_id, p));
            displayPaginatedProducts(1);
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
    if (productCache.has(numericId)) return productCache.get(numericId);

    try {
        const res = await fetch(`${API_URL_BARANG}?id=${numericId}`);
        const result = await res.json();
        if (result.status === 'success' && result.data) {
            productCache.set(numericId, result.data);
            return result.data;
        }
    } catch (error) {
        console.error("Gagal mengambil produk by ID:", error);
    }
    return null;
}

function renderProductList(productsToDisplay) {
    productListContainer.innerHTML = productsToDisplay.map(b => `
        <div onclick="addProductToCart(${b.barang_id})" class="flex items-center p-2 border rounded-lg hover:bg-blue-50 hover:border-primary cursor-pointer transition">
            <div class="flex-grow">
                <p class="font-medium text-xs text-gray-800">${b.barcode}</p>
                <p class="font-medium text-xs text-gray-800">${b.nama_barang}</p>
                <p class="text-xs text-gray-500">Stok Saat Ini: ${b.stok}</p>
            </div>
            <div class="text-primary font-semibold text-sm">+</div>
        </div>
    `).join('');
}

function displayPaginatedProducts(page) {
    currentProductPage = page;
    const startIndex = (currentProductPage - 1) * productsPerPage;
    const endIndex = startIndex + productsPerPage;
    const paginatedItems = allProductsFromSearch.slice(startIndex, endIndex);
    renderProductList(paginatedItems);
    renderProductPagination();
}

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

let searchTimeout;
productSearchInput.addEventListener('input', (e) => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => searchProductsFromAPI(e.target.value), 300);
});
syncButton.addEventListener('click', () => searchProductsFromAPI(productSearchInput.value));
productPrevButton.addEventListener('click', () => { if (currentProductPage > 1) displayPaginatedProducts(currentProductPage - 1); });
productNextButton.addEventListener('click', () => { const totalPages = Math.ceil(allProductsFromSearch.length / productsPerPage); if (currentProductPage < totalPages) displayPaginatedProducts(currentProductPage + 1); });

// 🔹 Tambah produk ke keranjang
async function addProductToCart(barangId, initialData = null) {
    // ✅ Cek apakah suplier sudah dipilih (kecuali saat mode edit)
    const suplierId = suplierIdInput.value;
    if (!suplierId && !initialData) {
        customAlert("Mohon pilih suplier terlebih dahulu.");
        return;
    }

    const existingCartItem = cartItemsContainer.querySelector(`[data-barang-id="${barangId}"]`);

    if (existingCartItem && !initialData) {
        const jumlahInput = existingCartItem.querySelector('input[name="jumlah"]');
        jumlahInput.value = parseInt(jumlahInput.value) + 1;
        calculateItemSubtotal(barangId);
    } else {
        const barang = initialData ? initialData : await getProductById(barangId);
        if (!barang) return;

        // ✅ Ambil harga terakhir dari histori atau gunakan harga default
        let hargaBeli = initialData ? initialData.harga_satuan : (barang.harga_beli || 0);
        if (!initialData && suplierId) { // Hanya cari histori jika bukan mode edit & suplier dipilih
            const hargaHistori = await getHargaHistori(suplierId, barang.barang_id);
            if (hargaHistori !== null) {
                hargaBeli = hargaHistori;
            }
        }
        const jumlahAwal = initialData ? initialData.jumlah : 1;
        const expiredAwal = initialData && initialData.pembelian_expired ? initialData.pembelian_expired : "";

        const rowHtml = `
            <div data-barang-id="${barang.barang_id}" class="cart-row grid-kolom">
                <div class="cart-item-number"></div>
                <div class="cart-item-name">${barang.nama_barang}</div>
                <div>
                    <input type="date" name="pembelian_expired" value="${expiredAwal}" class="cart-input text-xs">
                </div>
                <div>
                    <input type="number" name="jumlah" value="${jumlahAwal}" min="1" class="cart-input">
                </div>
                <div>
                    <input type="number" name="harga_satuan" value="${hargaBeli}" class="cart-input">
                </div>
                <div class="cart-subtotal">
                    <span class="item-subtotal">${formatRupiah(hargaBeli * jumlahAwal)}</span>
                </div>
                <div class="text-right">
                    <button type="button" onclick="removeCartItem(${barang.barang_id})" class="cart-remove-btn">&times;</button>
                </div>
            </div>`;

        cartItemsContainer.insertAdjacentHTML('beforeend', rowHtml);
        const newRow = cartItemsContainer.querySelector(`[data-barang-id="${barang.barang_id}"]`);

        newRow.querySelectorAll('input').forEach(input => {
            input.addEventListener('input', () => calculateItemSubtotal(barang.barang_id));
        });
        calculateItemSubtotal(barang.barang_id);
    }
    updateCartNumbers();
}

function removeCartItem(barangId) {
    cartItemsContainer.querySelector(`[data-barang-id="${barangId}"]`).remove();
    calculateGrandTotal();
    updateCartNumbers();
}

function calculateItemSubtotal(barangId) {
    const row = cartItemsContainer.querySelector(`[data-barang-id="${barangId}"]`);
    if (!row) return;

    const jumlah = parseInt(row.querySelector('input[name="jumlah"]').value, 10) || 0;
    const harga = parseInt(row.querySelector('input[name="harga_satuan"]').value, 10) || 0;
    const subtotal = jumlah * harga;

    row.querySelector('.item-subtotal').textContent = formatRupiah(subtotal);
    calculateGrandTotal();
}

function calculateGrandTotal() {
    let totalItems = 0;
    cartItemsContainer.querySelectorAll('[data-barang-id]').forEach(row => {
        const subtotalText = row.querySelector('.item-subtotal').textContent;
        const subtotalValue = parseFloat(subtotalText.replace(/Rp|\./g, '').replace(',', '.')) || 0;
        totalItems += subtotalValue;
    });

    grandTotalSpan.textContent = formatRupiah(totalItems);
}

// 🔹 Ambil harga terakhir dari histori pembelian
async function getHargaHistori(suplierId, barangId) {
    if (!suplierId || !barangId) return null;
    try {
        const res = await fetch(`${API_URL_HISTORI_PEMBELIAN}?aksi=getHarga&id_suplier=${suplierId}&id_barang=${barangId}`);
        const result = await res.json();
        if (result.status === 'ada') {
            return result.harga;
        }
    } catch (error) {
        console.error("Gagal mengambil harga histori pembelian:", error);
    }
    return null;
}

// 🔹 Simpan Pembelian
pembelianForm.addEventListener("submit", async e => {
    e.preventDefault();
    const pembelian_id = document.getElementById("pembelian_id").value;
    const user_id = document.getElementById("user_id").value;
    const suplier_id = suplierIdInput.value;
    const tanggal = document.getElementById("tanggal").value;
    const keterangan = document.getElementById("keterangan").value;

    // ✅ Validasi Suplier
    if (!suplier_id) {
        customAlert("Mohon pilih suplier terlebih dahulu.");
        return;
    }

    const items = [];
    let isExpiredValid = true;

    cartItemsContainer.querySelectorAll('[data-barang-id]').forEach((row) => {
        const barang_id = row.dataset.barangId;
        const jumlah = parseInt(row.querySelector('input[name="jumlah"]').value, 10) || 0;
        const harga_satuan = parseInt(row.querySelector('input[name="harga_satuan"]').value, 10) || 0;
        const subtotalText = row.querySelector('.item-subtotal').textContent;
        const subtotal = parseFloat(subtotalText.replace(/Rp|\./g, '').replace(',', '.')) || 0;
        const pembelian_expiredInput = row.querySelector('input[name="pembelian_expired"]');
        const pembelian_expired = pembelian_expiredInput.value;

        // ✅ Validasi Expired
        if (!pembelian_expired) {
            isExpiredValid = false;
            pembelian_expiredInput.classList.add('border-red-500', 'bg-red-50');
        } else {
            pembelian_expiredInput.classList.remove('border-red-500', 'bg-red-50');
        }

        if (barang_id && jumlah > 0) {
            items.push({ barang_id, jumlah, harga_satuan, subtotal, pembelian_expired });
        }
    });

    if (items.length === 0) {
        customAlert("Keranjang tidak boleh kosong.");
        return;
    }

    if (!isExpiredValid) {
        customAlert("Tanggal expired wajib diisi untuk semua barang.");
        return;
    }

    const dataToSend = { user_id, suplier_id: suplier_id, tanggal, keterangan, items };

    const url = pembelian_id ? `${API_URL_PEMBELIAN}?id=${pembelian_id}` : API_URL_PEMBELIAN;
    const method = pembelian_id ? "PUT" : "POST";

    try {
        const res = await fetch(url, {
            method: method,
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(dataToSend)
        });
        const result = await res.json();

        if (result.status === 'success') {
            // 🔥 Tambahan: Simpan Histori Harga Pembelian
            try {
                await fetch(`${API_URL_HISTORI_PEMBELIAN}?aksi=saveHistori`, {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({ suplier_id: suplier_id, items: items })
                });
            } catch (historiError) {
                console.error("Gagal menyimpan histori pembelian:", historiError);
                // Tidak perlu blokir user, cukup log error
            }

            customAlert("Pembelian berhasil disimpan!", "Sukses", () => {
                window.location.href = 'pembelian.html';
            });
        } else {
            customAlert(result.message || "Gagal menyimpan.");
        }
    } catch (error) {
        customAlert("Gagal terhubung ke server.");
    }
});

// =============================================
// === Logika Modal Suplier ==================
// =============================================
let allSuplier = [];

async function loadAllSuplier() {
    try {
        const res = await fetch(API_URL_SUPLIER);
        const result = await res.json();
        if (result.status === "success") allSuplier = result.data;
    } catch (error) { console.error("Gagal memuat suplier:", error); }
}

function renderSuplierList(list) {
    suplierListContainer.innerHTML = list.map(s => `
        <div onclick="selectSuplier(${s.suplier_id}, '${s.nama_suplier}')" class="p-3 border-b rounded-lg hover:bg-blue-50 cursor-pointer">
            <p class="font-semibold">${s.nama_suplier}</p>
            <p class="text-xs text-gray-500">${s.alamat || '-'}</p>
        </div>
    `).join('');
}

function selectSuplier(id, nama) {
    suplierIdInput.value = id;
    suplierDisplayInput.value = nama;
    suplierModal.classList.add("hidden");
}

document.getElementById("btnPilihSuplier").addEventListener("click", () => { suplierModal.classList.remove("hidden"); renderSuplierList(allSuplier); });
document.getElementById("btnBatalSuplier").addEventListener("click", () => suplierModal.classList.add("hidden"));
suplierSearchInput.addEventListener("input", (e) => renderSuplierList(allSuplier.filter(s => s.nama_suplier.toLowerCase().includes(e.target.value.toLowerCase()))));

// 🔹 Inisialisasi Mode Edit
async function initEditMode() {
    const urlParams = new URLSearchParams(window.location.search);
    const id = urlParams.get('id');
    if (!id) return;

    document.getElementById('pembelian_id').value = id;
    
    try {
        const res = await fetch(`${API_URL_PEMBELIAN}?id=${id}`);
        const json = await res.json();
        if (json.status === 'success') {
            const data = json.data;
            if (data.suplier_id) selectSuplier(data.suplier_id, data.nama_suplier || 'Suplier');
            document.getElementById('tanggal').value = data.tanggal;
            document.getElementById('keterangan').value = data.keterangan;

            for (const item of data.details) {
                // Pastikan struktur item sesuai dengan yang diharapkan addProductToCart
                await addProductToCart(item.barang_id, item);
            }
        }
    } catch (e) { console.error("Gagal memuat data edit", e); }
}

// Init
loadAllSuplier();
searchProductsFromAPI('');
initEditMode();

// Set User ID
const userData = sessionStorage.getItem('loggedInUser');
if (userData) document.getElementById('user_id').value = JSON.parse(userData).id;