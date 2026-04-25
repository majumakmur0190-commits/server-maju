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
// GET BARANG
// ================================
function getBarang() {
    var xhr = new XMLHttpRequest();
    xhr.open('GET', API_URL + '?action=get-all-barang', true);

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

            for (var i = 0; i < data.length; i++) {
                (function (barang) {
                    var barangItem = document.createElement('div');
                    barangItem.className = 'barang-item';

                    barangItem.onclick = function () {
                        editBarcode(barang.barang_id, barang.barcode);
                    };

                    barangItem.innerHTML =
                        '<span><strong>' + barang.nama_barang +
                        '</strong><br>Barcode: ' + barang.barcode + '</span>';

                    barangList.appendChild(barangItem);
                })(data[i]);
            }
        }
    };

    xhr.send();
}

// ================================
// EDIT BARCODE
// ================================
function editBarcode(barangId, currentBarcode) {
    var editModal = document.getElementById('editModal');
    var editBarcodeInput = document.getElementById('editBarcode');

    editBarcodeInput.value = currentBarcode;
    editModal.setAttribute('data-barang-id', barangId);
    editModal.style.display = 'block';

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
    xhr.setRequestHeader(
        'Content-Type',
        'application/x-www-form-urlencoded'
    );

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
    getBarang();
});
