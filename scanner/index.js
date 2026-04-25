function showPage(page) {
    document.getElementById('page-search').style.display = page === 'search' ? 'block' : 'none';
    document.getElementById('page-edit').style.display = page === 'edit' ? 'block' : 'none';
}

function searchBarcode() {
    var api = 'http://192.168.1.250:1987/maju/api/barcode.php?action=search-barcode';
    var barcode = document.getElementById('barcode').value.trim();
    var result = document.getElementById('result');
    result.innerHTML = '';

    if (!barcode) {
        alert('Masukkan barcode!');
        return;
    }

    var xhr = new XMLHttpRequest();
    xhr.open('POST', api, true);
    xhr.setRequestHeader('Content-Type', 'application/json');

    xhr.onload = function () {
        var data = JSON.parse(xhr.responseText);

        if (xhr.status === 200) {
            var html = '';
            data.forEach(function (item) {
                html += `
                <div class="card">
                    <h3>${item.nama}</h3>
                    <p><strong>Barcode:</strong> ${item.barcode}</p>
                    <p><strong>Harga:</strong> ${item.harga}</p>
                    <button onclick='openEdit(${JSON.stringify(item)})'>Edit</button>
                </div>`;
            });
            result.innerHTML = html;
        } else {
            result.innerHTML = '<p style="color:red;">Data tidak ditemukan</p>';
        }
    };

    xhr.send(JSON.stringify({ barcode: barcode }));
}

function openEdit(data) {
    showPage('edit');
    document.getElementById('edit_barcode').value = data.barcode;
    document.getElementById('edit_nama').value = data.nama;
    document.getElementById('edit_harga').value = data.harga;
}

function updateBarcode() {
    var api = 'http://192.168.1.250:1987/maju/api/barcode.php?action=update-barcode';

    var payload = {
        barcode: document.getElementById('edit_barcode').value,
        nama: document.getElementById('edit_nama').value,
        harga: document.getElementById('edit_harga').value
    };

    var xhr = new XMLHttpRequest();
    xhr.open('POST', api, true);
    xhr.setRequestHeader('Content-Type', 'application/json');

    xhr.onload = function () {
        document.getElementById('edit_result').innerHTML =
            '<p style="color:green;">Data berhasil diperbarui</p>';
    };

    xhr.send(JSON.stringify(payload));
}
