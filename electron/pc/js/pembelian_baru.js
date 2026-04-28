// Panggil fungsi dari main.js
// initializeApp();

// 🔹 Deklarasi Konstanta & Variabel
const SERVER_IP = localStorage.getItem('server_ip') || 'localhost';
const API_URL_PEMBELIAN = `http://${SERVER_IP}:1987/maju/api/pembelian.php`;
const API_URL_SUPLIER = `http://${SERVER_IP}:1987/maju/api/suplier.php`;
const API_URL_BARANG = `http://${SERVER_IP}:1987/maju/api/barang.php`;
const API_URL_HISTORI_PEMBELIAN = `http://${SERVER_IP}:1987/maju/api/histori_pembelian.php`;

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

// Elemen Modal Barang
const productModal = document.getElementById("productModal");

function openProductModal() {
    productModal.style.display = "flex";
    productSearchInput.focus();
}

function closeProductModal() {
    productModal.style.display = "none";
}

// Modal Edit Item Functions
function openEditItemModal(barangId) {
    const row = cartItemsContainer.querySelector(`[data-barang-id="${barangId}"]`);
    if (!row) return;

    const nama = row.querySelector('.cart-item-name').textContent;
    const expired = row.querySelector('input[name="pembelian_expired"]').value;
    const jumlah = row.querySelector('input[name="jumlah"]').value;
    const harga = row.querySelector('input[name="harga_satuan"]').value;
    const d1 = row.querySelector('input[name="diskon1"]').value;
    const d2 = row.querySelector('input[name="diskon2"]').value;
    const d3 = row.querySelector('input[name="diskon3"]').value;
    const d4 = row.querySelector('input[name="diskon4"]').value;

    document.getElementById('edit-barang-id').value = barangId;
    document.getElementById('edit-nama-barang').textContent = nama;
    document.getElementById('edit-expired').value = expired;
    document.getElementById('edit-jumlah').value = jumlah;
    document.getElementById('edit-harga').value = harga;
    document.getElementById('edit-diskon1').value = d1;
    document.getElementById('edit-diskon2').value = d2;
    document.getElementById('edit-diskon3').value = d3;
    document.getElementById('edit-diskon4').value = d4;

    calculateModalSubtotal();
    document.getElementById('editItemModal').style.display = 'flex';
}

function calculateModalSubtotal() {
    const jumlah = parseInt(document.getElementById('edit-jumlah').value, 10) || 0;
    const harga = parseInt(document.getElementById('edit-harga').value, 10) || 0;
    const d1 = parseFloat(document.getElementById('edit-diskon1').value) || 0;
    const d2 = parseFloat(document.getElementById('edit-diskon2').value) || 0;
    const d3 = parseFloat(document.getElementById('edit-diskon3').value) || 0;
    const d4 = parseFloat(document.getElementById('edit-diskon4').value) || 0;

    const subtotal = (jumlah * harga) * (1 - d1 / 100) * (1 - d2 / 100) * (1 - d3 / 100) * (1 - d4 / 100);
    document.getElementById('edit-subtotal-display').textContent = formatRupiah(subtotal);
}

function closeEditItemModal() {
    document.getElementById('editItemModal').style.display = 'none';
}

function applyItemEdit() {
    const barangId = document.getElementById('edit-barang-id').value;
    const row = cartItemsContainer.querySelector(`[data-barang-id="${barangId}"]`);
    if (!row) return;

    row.querySelector('input[name="pembelian_expired"]').value = document.getElementById('edit-expired').value;
    row.querySelector('input[name="jumlah"]').value = document.getElementById('edit-jumlah').value;
    row.querySelector('input[name="harga_satuan"]').value = document.getElementById('edit-harga').value;
    row.querySelector('input[name="diskon1"]').value = document.getElementById('edit-diskon1').value;
    row.querySelector('input[name="diskon2"]').value = document.getElementById('edit-diskon2').value;
    row.querySelector('input[name="diskon3"]').value = document.getElementById('edit-diskon3').value;
    row.querySelector('input[name="diskon4"]').value = document.getElementById('edit-diskon4').value;

    calculateItemSubtotal(barangId);
    closeEditItemModal();
}

// 🔹 Fungsi untuk update nomor urut di keranjang
function updateCartNumbers() {
    const cartItems = cartItemsContainer.querySelectorAll('[data-barang-id]');
    cartItems.forEach((item, index) => {
        const numberElement = item.querySelector('.cart-item-number');
        if (numberElement) numberElement.textContent = index + 1;
    });

    // Otomatis scroll ke item terakhir
    if (cartItems.length > 0) {
        cartItemsContainer.scrollTop = cartItemsContainer.scrollHeight;
    }
}

// 🔹 Fungsi mengunci pilihan suplier saat transaksi dimulai
function kunciSuplier() {
    suplierIdInput.setAttribute('readonly', true);
    suplierDisplayInput.setAttribute('readonly', true);
    const btnPilih = document.getElementById('btnPilihSuplier');
    if (btnPilih) btnPilih.style.display = 'none';

    const noteSuplier = document.getElementById('noteSuplier');
    if (noteSuplier) noteSuplier.style.display = 'block';
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
    if (productsToDisplay.length === 0) {
        productListContainer.innerHTML = '<div style="padding: 20px; text-align: center; color: gray;">Produk tidak ditemukan.</div>';
        return;
    }

    productListContainer.innerHTML = `
        <table style="width: 100%; border-collapse: collapse; background: white; font-size: 11px;">
            <thead style="background: #f0f0f0; position: sticky; top: 0; z-index: 10;">
                <tr>
                    <th style="padding: 8px; border: 1px solid #ccc; text-align: left; width: 100px;">Barcode</th>
                    <th style="padding: 8px; border: 1px solid #ccc; text-align: left;">Nama Barang</th>
                </tr>
            </thead>
            <tbody>
                ${productsToDisplay.map(b => `
                    <tr onclick="addProductToCart(${b.barang_id})" onmouseover="this.style.background='#e5f4fc'" onmouseout="this.style.background='white'" style="cursor: pointer; border-bottom: 1px solid #eee;">
                        <td style="padding: 8px; border: 1px solid #eee;">${b.barcode || '-'}</td>
                        <td style="padding: 8px; border: 1px solid #eee; font-weight: bold;">${b.nama_barang}</td>
                    </tr>
                `).join('')}
            </tbody>
        </table>`;
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

        let hargaBeli = initialData ? initialData.harga_satuan : (barang.harga_beli || 0);
        if (!initialData && suplierId) {
            const hargaHistori = await getHargaHistori(suplierId, barang.barang_id);
            if (hargaHistori !== null) hargaBeli = hargaHistori;
        }

        // Siapkan data awal untuk baris baru
        const data = {
            expired: initialData?.pembelian_expired || "",
            jumlah: initialData?.jumlah || 1,
            harga: hargaBeli,
            d1: initialData?.diskon1 || 0,
            d2: initialData?.diskon2 || 0,
            d3: initialData?.diskon3 || 0,
            d4: initialData?.diskon4 || 0
        };

        const rowHtml = `
            <div data-barang-id="${barang.barang_id}" class="cart-row grid-kolom" 
                 onclick="warnabaris(this)" ondblclick="openEditItemModal(${barang.barang_id})" 
                 oncontextmenu="showContextMenu(event, ${barang.barang_id})">
                <div class="cart-item-number"></div>
                <div class="cart-item-name">${barang.nama_barang}</div>
                <div class="display-expired"></div>
                <div class="display-jumlah"></div>
                <div class="display-harga"></div>
                <div class="display-d1"></div>
                <div class="display-d2"></div>
                <div class="display-d3"></div>
                <div class="display-d4"></div>
                <div class="cart-subtotal"><span class="item-subtotal"></span></div>
                <input type="hidden" name="pembelian_expired" value="${data.expired}">
                <input type="hidden" name="jumlah" value="${data.jumlah}">
                <input type="hidden" name="harga_satuan" value="${data.harga}">
                <input type="hidden" name="diskon1" value="${data.d1}">
                <input type="hidden" name="diskon2" value="${data.d2}">
                <input type="hidden" name="diskon3" value="${data.d3}">
                <input type="hidden" name="diskon4" value="${data.d4}">
            </div>`;

        cartItemsContainer.insertAdjacentHTML('beforeend', rowHtml);
        kunciSuplier(); // Kunci suplier agar tidak diganti di tengah jalan

        calculateItemSubtotal(barang.barang_id);
    }
    updateCartNumbers();
    if (!initialData) closeProductModal();
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

    // Menggunakan pageY/X agar posisi menu akurat meski halaman di-scroll
    contextMenu.style.top = `${e.pageY}px`;
    contextMenu.style.left = `${e.pageX}px`;
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

function removeCartItem(barangId) {
    cartItemsContainer.querySelector(`[data-barang-id="${barangId}"]`).remove();
    calculateGrandTotal();
    updateCartNumbers();
}

function calculateItemSubtotal(barangId) {
    const row = cartItemsContainer.querySelector(`[data-barang-id="${barangId}"]`);
    if (!row) return;

    const inputExpired = row.querySelector('input[name="pembelian_expired"]');
    const inputJumlah = row.querySelector('input[name="jumlah"]');
    const inputHarga = row.querySelector('input[name="harga_satuan"]');
    const d1Val = parseFloat(row.querySelector('input[name="diskon1"]').value) || 0;
    const d2Val = parseFloat(row.querySelector('input[name="diskon2"]').value) || 0;
    const d3Val = parseFloat(row.querySelector('input[name="diskon3"]').value) || 0;
    const d4Val = parseFloat(row.querySelector('input[name="diskon4"]').value) || 0;

    const jumlah = parseInt(inputJumlah.value, 10) || 0;
    const harga = parseInt(inputHarga.value, 10) || 0;

    // Kalkulasi diskon bertingkat: Harga * (1-d1) * (1-d2) * (1-d3) * (1-d4)
    const subtotal = (jumlah * harga) * (1 - d1Val / 100) * (1 - d2Val / 100) * (1 - d3Val / 100) * (1 - d4Val / 100);

    // Update display text
    row.querySelector('.display-expired').textContent = inputExpired.value || '-';
    row.querySelector('.display-jumlah').textContent = jumlah;
    row.querySelector('.display-harga').textContent = formatRupiah(harga);
    row.querySelector('.display-d1').textContent = d1Val;
    row.querySelector('.display-d2').textContent = d2Val;
    row.querySelector('.display-d3').textContent = d3Val;
    row.querySelector('.display-d4').textContent = d4Val;

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

    const globalDiscPersen = parseFloat(document.getElementById('global-diskon-persen').value) || 0;
    const globalDiscNominal = parseFloat(document.getElementById('global-diskon-nominal').value) || 0;

    let finalTotal = totalItems * (1 - globalDiscPersen / 100);
    finalTotal -= globalDiscNominal;
    if (finalTotal < 0) finalTotal = 0;

    grandTotalSpan.textContent = formatRupiah(finalTotal);
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
    const global_diskon_persen = parseFloat(document.getElementById("global-diskon-persen").value) || 0;
    const global_diskon_nominal = parseFloat(document.getElementById("global-diskon-nominal").value) || 0;

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
        const diskon1 = parseFloat(row.querySelector('input[name="diskon1"]').value) || 0;
        const diskon2 = parseFloat(row.querySelector('input[name="diskon2"]').value) || 0;
        const diskon3 = parseFloat(row.querySelector('input[name="diskon3"]').value) || 0;
        const diskon4 = parseFloat(row.querySelector('input[name="diskon4"]').value) || 0;
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
            items.push({ barang_id, jumlah, harga_satuan, diskon1, diskon2, diskon3, diskon4, subtotal, pembelian_expired });
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

    // simpan variable diskon global ke dalam data yang akan dikirim
    const globalDiskon = {
        global_diskon_persen,
        global_diskon_nominal
    };
    const dataToSend = {
        user_id,
        suplier_id: suplier_id,
        tanggal,
        keterangan,
        items,
        globalDiskon
    };

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
    if (list.length === 0) {
        suplierListContainer.innerHTML = '<div style="padding: 20px; text-align: center; color: gray;">Tidak ada data suplier ditemukan.</div>';
        return;
    }

    suplierListContainer.innerHTML = `
        <table style="width: 100%; border-collapse: collapse; background: white; font-size: 12px;">
            <thead style="background: #f0f0f0; position: sticky; top: 0; z-index: 10;">
                <tr>
                    <th style="padding: 8px; border: 1px solid #ccc; text-align: left; width: 60px;">ID</th>
                    <th style="padding: 8px; border: 1px solid #ccc; text-align: left;">Nama Suplier</th>
                    <th style="padding: 8px; border: 1px solid #ccc; text-align: left;">Alamat</th>
                </tr>
            </thead>
            <tbody>
                ${list.map(s => `
                    <tr onclick="selectSuplier(${s.suplier_id}, '${s.nama_suplier}')" 
                        onmouseover="this.style.backgroundColor='#e5f4fc'" 
                        onmouseout="this.style.backgroundColor='transparent'"
                        style="cursor: pointer; border-bottom: 1px solid #eee;">
                        <td style="padding: 8px; border: 1px solid #eee; text-align: center;">${s.suplier_id}</td>
                        <td style="padding: 8px; border: 1px solid #eee; font-weight: bold; color: #003399;">${s.nama_suplier}</td>
                        <td style="padding: 8px; border: 1px solid #eee;">${s.alamat || '-'}</td>
                    </tr>
                `).join('')}
            </tbody>
        </table>`;
}

function selectSuplier(id, nama) {
    suplierIdInput.value = id;
    suplierDisplayInput.value = nama;
    suplierModal.style.display = "none";
}

document.getElementById("btnPilihSuplier").addEventListener("click", () => {
    // Perbesar ukuran modal secara dinamis agar lebih lega
    const modalWindow = suplierModal.querySelector('.window');
    if (modalWindow) {
        modalWindow.style.width = '80%';
        modalWindow.style.maxWidth = '800px';
    }
    suplierModal.style.display = "flex";
    renderSuplierList(allSuplier);
});
document.getElementById("btnBatalSuplier").addEventListener("click", () => suplierModal.style.display = "none");
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
            document.getElementById('global-diskon-persen').value = data.global_diskon_persen || 0;
            document.getElementById('global-diskon-nominal').value = data.global_diskon_nominal || 0;

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

// Event listeners untuk perhitungan subtotal otomatis di Modal Edit
document.getElementById('edit-jumlah').addEventListener('input', calculateModalSubtotal);
document.getElementById('edit-harga').addEventListener('input', calculateModalSubtotal);
document.getElementById('edit-diskon1').addEventListener('input', calculateModalSubtotal);
document.getElementById('edit-diskon2').addEventListener('input', calculateModalSubtotal);
document.getElementById('edit-diskon3').addEventListener('input', calculateModalSubtotal);
document.getElementById('edit-diskon4').addEventListener('input', calculateModalSubtotal);
document.getElementById('global-diskon-persen').addEventListener('input', calculateGrandTotal);
document.getElementById('global-diskon-nominal').addEventListener('input', calculateGrandTotal);

// Set User ID
const userData = sessionStorage.getItem('loggedInUser');
if (userData) document.getElementById('user_id').value = JSON.parse(userData).id;