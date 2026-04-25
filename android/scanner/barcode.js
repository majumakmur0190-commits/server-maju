// ================================
// CONFIG
// ================================
var API_URL = 'http://192.168.1.250:1987/maju/api/barcode.php';

// ================================
// GLOBAL ALERT
// ================================
function showAlert(message, title) {
    title = title || 'Informasi';
    document.getElementById('alertTitle').textContent = title;
    document.getElementById('alertMessage').textContent = message;
    document.getElementById('globalAlert').style.display = 'flex';
}

function closeAlert() {
    document.getElementById('globalAlert').style.display = 'none';
}

// ================================
// IndexedDB Setup
// ================================
var db;

// Fungsi untuk membuka IndexedDB
function openDB() {
    var request = indexedDB.open("BarangDB", 1);

    request.onupgradeneeded = function (event) {
        var db = event.target.result;
        var objectStore = db.createObjectStore("barang", { keyPath: "barang_id" });
    };

    request.onsuccess = function (event) {
        db = event.target.result;
    };

    request.onerror = function (event) {
        console.log("Error opening IndexedDB:", event.target.error);
    };
}

// Fungsi untuk menyimpan data barang ke IndexedDB
function saveToIndexedDB(barangList) {
    var transaction = db.transaction(["barang"], "readwrite");
    var objectStore = transaction.objectStore("barang");

    barangList.forEach(function (barang) {
        objectStore.put(barang); // Menyimpan atau mengganti data barang
    });

    transaction.oncomplete = function () {
        console.log("Data berhasil disimpan ke IndexedDB.");
    };

    transaction.onerror = function (event) {
        console.log("Error menyimpan data ke IndexedDB:", event.target.error);
    };
}

// Fungsi untuk mengambil data barang dari IndexedDB
function getFromIndexedDB() {
    var transaction = db.transaction(["barang"], "readonly");
    var objectStore = transaction.objectStore("barang");
    var request = objectStore.getAll(); // Mengambil semua data barang

    request.onsuccess = function (event) {
        if (request.result.length > 0) {
            console.log("Data dari IndexedDB:", request.result);
            displayBarang(request.result);
        } else {
            console.log("Data tidak ditemukan di IndexedDB.");
            getBarangFromAPI(); // Ambil data dari API jika tidak ada di IndexedDB
        }
    };

    request.onerror = function (event) {
        console.log("Error mengambil data dari IndexedDB:", event.target.error);
        getBarangFromAPI(); // Ambil data dari API jika ada error
    };
}

// Fungsi untuk menampilkan barang di UI
function displayBarang(barangList) {
    var barangListElement = document.getElementById('barangList');
    barangListElement.innerHTML = '';

    if (!barangList || barangList.length === 0) {
        barangListElement.innerHTML = '<p>Tidak ada barang ditemukan.</p>';
        return;
    }

    barangList.forEach(function (barang) {
        var barangItem = document.createElement('div');
        barangItem.className = 'barang-item';
        barangItem.onclick = function () {
            editBarcode(barang.barang_id, barang.barcode);
        };

        barangItem.innerHTML = '<span><strong>' + barang.nama_barang +
            '</strong><br>Barcode: ' + barang.barcode + '</span>';

        barangListElement.appendChild(barangItem);
    });
}

// Fungsi untuk mengambil barang dari API
function getBarangFromAPI() {
    var xhr = new XMLHttpRequest();
    xhr.open('GET', API_URL + '?action=get-all-barang&_=' + new Date().getTime(), true);

    xhr.onreadystatechange = function () {
        if (xhr.readyState === 4) {
            var barangList = document.getElementById('barangList');
            barangList.innerHTML = '';

            if (xhr.status !== 200) {
                showAlert('Gagal mengambil data barang', 'Error');
                return;
            }

            var data;
            try {
                data = JSON.parse(xhr.responseText);
            } catch (e) {
                showAlert('Respon server tidak valid', 'Error');
                return;
            }

            if (!data || data.length === 0) {
                barangList.innerHTML = '<p>Tidak ada barang ditemukan.</p>';
                return;
            }

            // Simpan data ke IndexedDB
            saveToIndexedDB(data);
            displayBarang(data);
        }
    };

    xhr.send();
}

// ================================
// GET BARANG
// ================================
function getBarang() {
    // Coba ambil data dari IndexedDB terlebih dahulu
    if (db) {
        getFromIndexedDB();
    } else {
        // Jika IndexedDB belum siap, langsung ambil dari API
        getBarangFromAPI();
    }
}

// ================================
// EDIT BARCODE
// ================================
function editBarcode(barangId, currentBarcode) {
    var editModal = document.getElementById('editModal');
    var editBarcodeInput = document.getElementById('editBarcode');

    editBarcodeInput.value = currentBarcode;
    editModal.setAttribute('data-barang-id', barangId);
    editModal.style.display = 'flex';

    setTimeout(function () {
        editBarcodeInput.focus();
        editBarcodeInput.select();
    }, 100);
}

// ================================
// CLOSE MODAL
// ================================
function closeModal() {
    document.getElementById('editModal').style.display = 'none';
}

// ================================
// UPDATE BARCODE
// ================================
function updateBarcode() {
    var editModal = document.getElementById('editModal');
    var barangId = editModal.getAttribute('data-barang-id');
    var newBarcode = document.getElementById('editBarcode').value;

    if (!newBarcode) {
        showAlert('Barcode tidak boleh kosong', 'Peringatan');
        return;
    }

    var xhr = new XMLHttpRequest();
    xhr.open('POST', API_URL + '?action=update-barcode', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

    xhr.onreadystatechange = function () {
        if (xhr.readyState === 4) {
            if (xhr.status !== 200) {
                showAlert('Gagal memperbarui barcode', 'Error');
                return;
            }

            var response;
            try {
                response = JSON.parse(xhr.responseText);
            } catch (e) {
                showAlert('Respon server tidak valid', 'Error');
                return;
            }

            showAlert(response.message, 'Informasi');
            closeModal();
            getBarang();
        }
    };

    xhr.send(
        'barang_id=' + encodeURIComponent(barangId) +
        '&barcode=' + encodeURIComponent(newBarcode)
    );
}

// ================================
// SEARCH BARANG
// ================================
function searchBarang() {
    var searchInput = document.getElementById('searchInput').value.toLowerCase();
    var barangItems = document.getElementsByClassName('barang-item');

    for (var i = 0; i < barangItems.length; i++) {
        var text = barangItems[i].textContent.toLowerCase();
        barangItems[i].style.display =
            text.indexOf(searchInput) !== -1 ? 'flex' : 'none';
    }
}

// ================================
// INIT
// ================================
document.addEventListener('DOMContentLoaded', function () {
    openDB();  // Membuka IndexedDB
    getBarang();  // Ambil barang (dari IndexedDB atau API)
});
